/**
 * ArsipKu - document version service.
 *
 * Nothing exists server-side yet: there is no `document_versions` table and no
 * api/versions.php. This implementation is written against the documented contract
 * (docs/api-contract.md #7); requests fail until the MySQL table and PHP endpoint exist.
 */

import type { Paginated } from '../types/api';
import type {
  CompareVersionsRequest,
  DocumentVersion,
  ListVersionsParams,
  RestoreVersionRequest,
  RestoreVersionResult,
  UploadVersionRequest,
  VersionDiff,
} from '../types/version';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import { asInt, asStr, normalizeArray, normalizeDocument, normalizeVersion, paginate } from '../lib/normalize';

export interface VersionService {
  list(params: ListVersionsParams): Promise<Paginated<DocumentVersion>>;
  /** Same-name re-upload becomes version N+1 instead of a duplicate document. */
  upload(request: UploadVersionRequest): Promise<RestoreVersionResult>;
  restore(request: RestoreVersionRequest): Promise<RestoreVersionResult>;
  remove(request: { document_id: string; version_id: string }): Promise<{ message: string }>;
  compare(request: CompareVersionsRequest): Promise<VersionDiff>;
}

export function createHttpVersionService(): VersionService {
  return {
    async list(params): Promise<Paginated<DocumentVersion>> {
      const rows = await http.get<unknown>('versions', 'list', {
        document_id: params.document_id,
        page: params.page,
        per_page: params.per_page,
      });
      const items = normalizeArray(rows, normalizeVersion);
      return paginate(items, null, params.page ?? 1, params.per_page ?? items.length);
    },

    async upload(request): Promise<RestoreVersionResult> {
      const form = new FormData();
      form.append('file', request.file, request.file.name);
      form.append('document_id', request.document_id);
      if (request.label) form.append('label', request.label);

      const body = await http.upload('versions', 'create', form, {});
      const version = normalizeVersion(readObject(body.data ?? body.version));
      return {
        restored_version: version,
        document: normalizeDocument(readObject(body.document)),
      };
    },

    async restore(request): Promise<RestoreVersionResult> {
      const { body } = await http.call('versions', {
        action: 'restore',
        method: 'POST',
        body: { document_id: request.document_id, version_id: request.version_id },
      });
      invalidate(request.document_id);
      return {
        restored_version: normalizeVersion(readObject(body.data ?? body.version)),
        document: normalizeDocument(readObject(body.document)),
      };
    },

    async remove(request): Promise<{ message: string }> {
      const { body } = await http.call('versions', {
        action: 'delete',
        method: 'POST',
        body: { document_id: request.document_id, version_id: request.version_id },
      });
      invalidate(request.document_id);
      return { message: typeof body.message === 'string' ? body.message : 'Versi dihapus.' };
    },

    async compare(request): Promise<VersionDiff> {
      const payload = await http.get<unknown>('versions', 'compare', {
        document_id: request.document_id,
        from_version_number: request.from_version_number,
        to_version_number: request.to_version_number,
      });
      const row = readObject(payload);
      return {
        from: readVersionStub(row.from),
        to: readVersionStub(row.to),
        size_delta_bytes: typeof row.size_delta_bytes === 'number' ? row.size_delta_bytes : 0,
        summary: typeof row.summary === 'string' ? row.summary : '',
      };
    },
  };
}

function readVersionStub(value: unknown): VersionDiff['from'] {
  const row = readObject(value);
  return {
    id: asStr(row.id),
    version_number: asInt(row.version_number),
    size_bytes: asInt(row.size_bytes),
    created_at: asStr(row.created_at),
  };
}

function invalidate(documentId: string): void {
  emit('data:invalidated', { reason: 'version-changed', keys: [`versions:${documentId}`, 'documents'] });
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
