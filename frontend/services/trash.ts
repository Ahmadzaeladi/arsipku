/**
 * ArsipKu - trash and restore service.
 *
 * Every trash operation is answered by api/documents.php today (the router exposes
 * delete/restore/permanent_delete/empty_trash/multi_action). The domain stays its own
 * service so the UI can treat Sampah as a first-class view; api/trash.php is the
 * documented target once the router is split (docs/api-contract.md #11).
 */

import type { BatchAction, Paginated, VoidResult } from '../types/api';
import type {
  EmptyTrashRequest,
  RestoreRequest,
  TrashItem,
  TrashListParams,
  TrashOperationResult,
  TrashStats,
} from '../types/trash';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import {
  asInt,
  asNullStr,
  asStr,
  asStrIds,
  normalizeArray,
  normalizeDocument,
  paginate,
} from '../lib/normalize';
import { TRASH_RETENTION_DAYS } from '../types/trash';

export interface TrashService {
  list(params?: TrashListParams): Promise<Paginated<TrashItem>>;
  moveToTrash(input: { id: string; kind?: 'document' | 'folder' }): Promise<VoidResult>;
  restore(request: RestoreRequest): Promise<VoidResult>;
  purge(input: { id: string; kind?: 'document' | 'folder' }): Promise<VoidResult>;
  batch(input: { action: Extract<BatchAction, 'delete' | 'restore' | 'permanent_delete'>; ids: string[] }): Promise<TrashOperationResult>;
  empty(request?: EmptyTrashRequest): Promise<TrashOperationResult>;
  stats(): Promise<TrashStats>;
}

export function createHttpTrashService(): TrashService {
  return {
    async list(params = {}): Promise<Paginated<TrashItem>> {
      const { body } = await http.call('documents', {
        action: 'list',
        method: 'GET',
        params: { trash: true, q: params.q, sort: params.sort, page: params.page, per_page: params.per_page },
      });
      const items = normalizeArray(body.data, (row) => toTrashItem(row));
      return paginate(items, null, params.page ?? 1, params.per_page ?? items.length);
    },

    async moveToTrash({ id, kind = 'document' }): Promise<VoidResult> {
      const { body } = await http.call('documents', {
        action: 'delete',
        method: 'POST',
        body: { id, kind },
      });
      invalidate();
      return { message: asStr(body.message, 'Dokumen dipindahkan ke Sampah.') };
    },

    async restore(request): Promise<VoidResult> {
      const { body } = await http.call('documents', {
        action: 'restore',
        method: 'POST',
        body: {
          id: request.id,
          kind: request.kind ?? 'document',
          folder_id: request.folder_id ?? undefined,
        },
      });
      invalidate();
      return { message: asStr(body.message, 'Dokumen berhasil dipulihkan.') };
    },

    async purge({ id, kind = 'document' }): Promise<VoidResult> {
      const { body } = await http.call('documents', {
        action: 'permanent_delete',
        method: 'POST',
        body: { id, kind },
      });
      invalidate();
      return { message: asStr(body.message, 'Dokumen telah dihapus permanen.') };
    },

    async batch({ action, ids }): Promise<TrashOperationResult> {
      if (ids.length === 0) {
        return { requested_count: 0, affected_count: 0, failed_ids: [] };
      }
      const { body } = await http.call('documents', {
        action: 'multi_action',
        method: 'POST',
        body: { sub_action: action, ids },
      });
      invalidate();
      const data = readObject(body.data);
      return {
        requested_count: ids.length,
        affected_count: asInt(data.affected_count),
        failed_ids: asStrIds(data.failed_ids),
      };
    },

    async empty(request = {}): Promise<TrashOperationResult> {
      const { body } = await http.call('documents', {
        action: 'empty_trash',
        method: 'POST',
        body: {
          kind: request.kind ?? 'all',
          older_than_days: request.older_than_days,
          confirm_name: request.confirm_name,
        },
      });
      invalidate();
      const count = asInt(body.count ?? readObject(body.data).affected_count);
      return { requested_count: count, affected_count: count, failed_ids: [] };
    },

    /**
     * There is no trash stats endpoint, so this aggregates the trash list.
     * One request today, but api/trash.php?action=stats should replace it before
     * workspaces grow large - see the contract gap list.
     */
    async stats(): Promise<TrashStats> {
      const { items } = await this.list({ per_page: 1000 });
      const documents = items.filter((item) => item.kind === 'document');
      const folders = items.filter((item) => item.kind === 'folder');
      const deletedDates = items
        .map((item) => Date.parse(item.deleted_at))
        .filter((ms) => Number.isFinite(ms));
      return {
        document_count: documents.length,
        folder_count: folders.length,
        total_size_bytes: documents.reduce((sum, item) => sum + (item.size_bytes ?? 0), 0),
        retention_days: TRASH_RETENTION_DAYS,
        oldest_deleted_at: deletedDates.length
          ? new Date(Math.min(...deletedDates)).toISOString()
          : null,
      };
    },
  };
}

/** Legacy rows only carry the document itself, so recovery hints stay null. */
function toTrashItem(row: Record<string, unknown>): TrashItem {
  const document = normalizeDocument(row);
  return {
    kind: 'document',
    id: document.id,
    name: document.name,
    deleted_at: document.deleted_at ?? '',
    deleted_by: null,
    deleted_by_name: null,
    original_folder_id: document.folder_id,
    original_folder_name: document.folder_name ?? null,
    restore_target_available: true,
    size_bytes: document.size_bytes,
    extension: document.extension,
    purge_after: purgeAfter(document.deleted_at),
    document,
  };
}

/** deleted_at + retention window used by the trash UI. */
export function purgeAfter(deletedAt: string | null): string | null {
  const raw = asNullStr(deletedAt);
  if (!raw) return null;
  const ms = Date.parse(raw.includes('T') ? raw : raw.replace(' ', 'T'));
  if (!Number.isFinite(ms)) return null;
  return new Date(ms + TRASH_RETENTION_DAYS * 86_400_000).toISOString();
}

function invalidate(): void {
  emit('data:invalidated', { reason: 'trash-changed', keys: ['documents', 'trash', 'workspace-stats'] });
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
