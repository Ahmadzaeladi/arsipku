/**
 * ArsipKu - upload and download service.
 *
 * Uploads stay one-file-per-request so progress, cancellation, and per-file errors
 * map onto the queue rows the UI already renders. api/upload.php accepts `file` or
 * `files[]`, so a batch is a loop rather than a single multipart request.
 *
 * Downloads must be answered by the server. The legacy rows carry an
 * `uploads/<file>` path that is world-readable; api/download.php is the contract
 * replacement that returns a scoped, expiring ticket (docs/api-contract.md #6).
 */

import type { Document } from '../types/document';
import type {
  DownloadTicket,
  UploadFailure,
  UploadLimits,
  UploadRequest,
  UploadResult,
  UploadTarget,
} from '../types/upload';
import { ApiClientError } from '../lib/errors';
import { emit } from '../lib/events';
import { extensionOf } from '../lib/format';
import * as http from '../lib/http';
import { asInt, asStr, normalizeArray, normalizeUploadResult } from '../lib/normalize';

/** Mirrors src/Config.php: MAX_UPLOAD_SIZE and ALLOWED_EXTENSIONS. */
const FALLBACK_LIMITS: UploadLimits = {
  max_bytes: 50 * 1024 * 1024,
  max_files_per_request: 10,
  allowed_extensions: [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'png', 'jpg', 'jpeg', 'webp', 'svg', 'txt', 'csv', 'zip',
  ],
  allowed_mime_types: [],
  remaining_bytes: null,
  chunking_supported: false,
};

export interface UploadService {
  limits(): Promise<UploadLimits>;
  /** Reject the file before it leaves the browser, using server-declared limits. */
  validate(file: File, limits?: UploadLimits): { ok: boolean; reason?: string };
  upload(request: UploadRequest): Promise<UploadResult>;
  /** Ticket for a real download. Never build the URL on the client. */
  downloadTicket(documentId: string, versionId?: string): Promise<DownloadTicket>;
}

export function createHttpUploadService(): UploadService {
  return {
    async limits(): Promise<UploadLimits> {
      try {
        const { body } = await http.call('upload', {
          action: 'limits',
          method: 'POST',
          body: {},
          retries: 0,
        });
        const payload = readObject(body.data ?? body);
        return {
          max_bytes: asInt(payload.max_bytes, FALLBACK_LIMITS.max_bytes),
          max_files_per_request: asInt(payload.max_files_per_request, FALLBACK_LIMITS.max_files_per_request),
          allowed_extensions: Array.isArray(payload.allowed_extensions)
            ? payload.allowed_extensions.map((value) => asStr(value).toLowerCase())
            : FALLBACK_LIMITS.allowed_extensions,
          allowed_mime_types: normalizeArray(payload.allowed_mime_types, (row) => asStr(row.mime ?? row.value)),
          remaining_bytes: payload.remaining_bytes === undefined
            ? null
            : asInt(payload.remaining_bytes),
          chunking_supported: payload.chunking_supported === true,
          chunk_size_bytes: payload.chunk_size_bytes === undefined ? undefined : asInt(payload.chunk_size_bytes),
        };
      } catch {
        // api/upload.php has no `limits` action yet: fall back to the shipped defaults.
        return FALLBACK_LIMITS;
      }
    },

    validate(file, limits = FALLBACK_LIMITS) {
      if (file.size > limits.max_bytes) {
        return {
          ok: false,
          reason: `Ukuran ${file.name} melebihi batas ${(limits.max_bytes / 1024 / 1024).toFixed(0)} MB.`,
        };
      }
      const extension = extensionOf(file.name);
      if (!limits.allowed_extensions.includes(extension)) {
        return { ok: false, reason: `Format .${extension || 'tanpa ekstensi'} tidak didukung.` };
      }
      return { ok: true };
    },

    async upload(request): Promise<UploadResult> {
      const limits = await this.limits();
      const uploaded: Document[] = [];
      const failed: UploadFailure[] = [];

      for (const file of request.files) {
        const check = this.validate(file, limits);
        if (!check.ok) {
          failed.push({ file_name: file.name, reason: check.reason ?? 'Berkas ditolak.' });
          request.onProgress?.({
            local_id: localIdOf(file),
            file_name: file.name,
            total_bytes: file.size,
            loaded_bytes: 0,
            percent: 0,
            state: 'failed',
            message: check.reason,
          });
          continue;
        }

        try {
          const document = await uploadSingle({ file, target: request, signal: request.signal, onProgress: request.onProgress });
          uploaded.push(document);
        } catch (err) {
          const reason = err instanceof ApiClientError ? err.message : 'Upload gagal.';
          failed.push({
            file_name: file.name,
            reason,
            code: err instanceof ApiClientError ? err.code : undefined,
          });
          request.onProgress?.({
            local_id: localIdOf(file),
            file_name: file.name,
            total_bytes: file.size,
            loaded_bytes: 0,
            percent: 0,
            state: 'failed',
            message: reason,
          });
        }
      }

      emit('upload:finished', { uploadedCount: uploaded.length, failedCount: failed.length });
      emit('data:invalidated', { reason: 'upload', keys: ['documents', 'folders', 'workspace-stats'] });
      return { uploaded, failed, messages: failed.map((item) => item.reason) };
    },

    async downloadTicket(documentId, versionId): Promise<DownloadTicket> {
      const { body } = await http.call('download', {
        action: 'ticket',
        method: 'POST',
        body: { id: documentId, version_id: versionId },
      });
      const payload = readObject(body.data ?? body);
      const url = asStr(payload.url ?? payload.download_url);
      if (!url) {
        throw new ApiClientError({
          message: 'Server tidak mengembalikan tautan unduhan.',
          status: 502,
          code: 'MALFORMED_RESPONSE',
        });
      }
      return {
        url,
        expires_at: asStr(payload.expires_at),
        filename: asStr(payload.filename, `${documentId}`),
        size_bytes: asInt(payload.size_bytes),
        mime_type: asStr(payload.mime_type, 'application/octet-stream'),
      };
    },
  };
}

async function uploadSingle(input: {
  file: File;
  target: UploadTarget;
  signal?: AbortSignal;
  onProgress?: UploadRequest['onProgress'];
}): Promise<Document> {
  const { file } = input;
  const localId = localIdOf(file);
  const form = new FormData();
  form.append('file', file, file.name);
  const folderId = input.target.folder_id;
  if (folderId && folderId !== 'root') form.append('folder_id', folderId);
  if (input.target.on_name_conflict) form.append('on_name_conflict', input.target.on_name_conflict);

  const total = file.size;
  const body = await http.uploadWithProgress({
    endpoint: 'upload',
    action: '',
    form,
    signal: input.signal,
    onProgress: (loaded) => {
      input.onProgress?.({
        local_id: localId,
        file_name: file.name,
        total_bytes: total,
        loaded_bytes: loaded,
        percent: total > 0 ? Math.min(99, Math.round((loaded / total) * 100)) : 0,
        state: 'uploading',
      });
    },
  });

  const result = normalizeUploadResult(body);
  const document = result.uploaded[0];
  if (!document) {
    throw new ApiClientError({
      message: result.failed[0]?.reason ?? 'Server tidak mengonfirmasi berkas yang diunggah.',
      status: 502,
      code: 'PARTIAL_UPLOAD',
    });
  }

  input.onProgress?.({
    local_id: localId,
    file_name: file.name,
    total_bytes: total,
    loaded_bytes: total,
    percent: 100,
    state: 'done',
  });

  return document;
}

/**
 * Stable per-file key for the queue. Names repeat, so the size and last-modified
 * time join in; two identical files in one queue is a user error, not a collision
 * we need to solve here.
 */
export function localIdOf(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`;
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
