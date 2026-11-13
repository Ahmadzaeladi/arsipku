/**
 * ArsipKu - document service.
 *
 * Scope: listing, detail, rename, move, and batch move. Deleting to Trash,
 * restoring, starring, and searching are separate domains and live in their own
 * services, even though the current PHP router handles them from
 * api/documents.php. Keeping that mapping inside the implementations means the UI
 * never has to know which script answers a request.
 *
 * Target endpoint: api/documents.php (live, action-style).
 */

import type { BatchActionResult, Paginated, VoidResult } from '../types/api';
import type {
  Document,
  DocumentDetail,
  DocumentFilters,
  DocumentRow,
  MoveDocumentRequest,
  RenameDocumentRequest,
} from '../types/document';
import * as http from '../lib/http';
import { emit } from '../lib/events';
import { asInt, asStr, asStrIds, normalizeArray, normalizeDocument, paginate } from '../lib/normalize';

export interface DocumentService {
  list(filters?: DocumentFilters): Promise<Paginated<Document>>;
  get(documentId: string): Promise<DocumentDetail>;
  rename(request: RenameDocumentRequest): Promise<Document>;
  move(request: MoveDocumentRequest): Promise<VoidResult>;
  batchMove(input: {
    ids: string[];
    targetFolderId: string | null;
  }): Promise<BatchActionResult>;
  /** Newest-first slice for the dashboard and the Terbaru page. */
  recent(limit?: number): Promise<Document[]>;
}

/** Params as the PHP router expects them: booleans become `1` or are dropped. */
function toListParams(filters: DocumentFilters = {}): Record<string, unknown> {
  return {
    q: filters.q,
    type: filters.type && filters.type !== 'all' ? filters.type : undefined,
    date_range: filters.date_range && filters.date_range !== 'all' ? filters.date_range : undefined,
    folder_id: filters.folder_id ?? undefined,
    sort: filters.sort ?? 'updated_desc',
    favorite: filters.favorite,
    shared: filters.shared,
    trash: filters.trash,
    owner: filters.owner,
    limit: filters.limit,
    page: filters.page,
    per_page: filters.per_page,
  };
}

export function createHttpDocumentService(): DocumentService {
  return {
    async list(filters = {}): Promise<Paginated<Document>> {
      const { body } = await http.call('documents', {
        action: 'list',
        method: 'GET',
        params: toListParams(filters),
      });
      const items = normalizeArray(body.data, normalizeDocument);
      return paginate(items, null, filters.page ?? 1, filters.per_page ?? items.length);
    },

    async get(documentId: string): Promise<DocumentDetail> {
      const row = await http.get<DocumentRow>('documents', 'detail', { id: documentId });
      return toDetail(row);
    },

    async rename(request): Promise<Document> {
      const { body } = await http.call('documents', {
        action: 'rename',
        method: 'POST',
        body: { id: request.id, name: request.name },
      });
      return normalizeDocument(readObject(body.data));
    },

    async move(request): Promise<VoidResult> {
      const { body } = await http.call('documents', {
        action: 'move',
        method: 'POST',
        body: { id: request.id, folder_id: request.folder_id ?? 'root' },
      });
      emit('data:invalidated', { reason: 'document-moved', keys: ['documents'] });
      return { message: asStr(body.message, 'Dokumen berhasil dipindahkan.') };
    },

    async batchMove({ ids, targetFolderId }): Promise<BatchActionResult> {
      const { body } = await http.call('documents', {
        action: 'multi_action',
        method: 'POST',
        body: {
          sub_action: 'move',
          ids,
          target_folder_id: targetFolderId ?? 'root',
        },
      });
      const data = readObject(body.data);
      const affected = asInt(data.affected_count);
      // multi_action only reports a count today; the contract asks for per-id failures.
      return {
        requested_count: ids.length,
        affected_count: affected,
        failed_ids: asStrIds(data.failed_ids),
      };
    },

    async recent(limit = 6): Promise<Document[]> {
      const rows = await http.get<unknown>('documents', 'list', { sort: 'updated_desc', limit });
      return normalizeArray(rows, normalizeDocument);
    },
  };
}

/**
 * Detail rows carry folder labels and a download locator from the server.
 * `capabilities` stays undefined here: permissions are the permission service's job,
 * and the server - not this adapter - must decide them.
 */
function toDetail(row: DocumentRow): DocumentDetail {
  const base = normalizeDocument(row);
  const raw = row as Record<string, unknown>;
  return {
    ...base,
    versions_count: raw.versions_count === undefined ? undefined : asInt(raw.versions_count),
    latest_version_number: raw.latest_version_number === undefined
      ? undefined
      : asInt(raw.latest_version_number),
  };
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
