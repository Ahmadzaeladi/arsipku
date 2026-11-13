/**
 * ArsipKu - trash hook.
 *
 * Retention is server policy: the client shows `purge_after` and never computes its
 * own deadline beyond the display hint the service already derives.
 */

import type { Paginated, VoidResult } from '../types/api';
import type { TrashItem, TrashListParams, TrashOperationResult, TrashStats } from '../types/trash';
import { emit } from '../lib/events';
import { toApiError } from '../lib/errors';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { TrashService } from '../services/trash';

export interface TrashHook {
  page: Resource<Paginated<TrashItem>>;
  stats: Resource<TrashStats>;
  filters: Store<TrashListParams>;
  selection: Store<Set<string>>;
  busy: Store<boolean>;
  setQuery(q: string): Promise<void>;
  setPage(page: number): Promise<void>;
  restore(id: string, folderId?: string | null): Promise<VoidResult | null>;
  purge(id: string): Promise<VoidResult | null>;
  batchRestore(): Promise<TrashOperationResult | null>;
  batchPurge(): Promise<TrashOperationResult | null>;
  empty(confirmName?: string): Promise<TrashOperationResult | null>;
  toggle(id: string, selected: boolean): void;
  clearSelection(): void;
  destroy(): void;
}

export function useTrash(trash: TrashService, initial: TrashListParams = {}): TrashHook {
  const filters = createStore<TrashListParams>({ per_page: 20, ...initial });
  const selection = createStore<Set<string>>(new Set());
  const busy = createStore(false);

  const page = createResource(() => trash.list(filters.get()), { keys: ['trash', 'documents'] });
  const stats = createResource(() => trash.stats(), { keys: ['trash', 'workspace-stats'] });

  async function mutate<T>(run: () => Promise<T>): Promise<T | null> {
    busy.set(true);
    try {
      return await run();
    } catch (err) {
      emit('toast', { message: toApiError(err).message, type: 'error' });
      return null;
    } finally {
      busy.set(false);
    }
  }

  async function refetch(next: TrashListParams): Promise<void> {
    filters.set(next);
    selection.set(new Set());
    await page.refocus(() => trash.list(next));
  }

  /** A completed bulk action consumes the selection, then reports how many rows moved. */
  function finishBatch(result: TrashOperationResult, label: string): void {
    selection.set(new Set());
    emit('toast', {
      message: `${result.affected_count} item ${label}.`,
      type: result.failed_ids.length ? 'warning' : 'success',
    });
  }

  return {
    page,
    stats,
    filters,
    selection,
    busy,

    setQuery: (q) => refetch({ ...filters.get(), q, page: 1 }),
    setPage: (page2) => refetch({ ...filters.get(), page: page2 }),

    async restore(id, folderId) {
      const result = await mutate(() => trash.restore({ id, folder_id: folderId }));
      if (result) emit('toast', { message: result.message, type: 'success' });
      return result;
    },

    async purge(id) {
      const result = await mutate(() => trash.purge({ id }));
      if (result) emit('toast', { message: result.message, type: 'success' });
      return result;
    },

    async batchRestore() {
      const result = await mutate(() => trash.batch({ action: 'restore', ids: [...selection.get()] }));
      if (result) finishBatch(result, 'dikembalikan dari Sampah');
      return result;
    },

    async batchPurge() {
      const result = await mutate(() => trash.batch({ action: 'permanent_delete', ids: [...selection.get()] }));
      if (result) finishBatch(result, 'dihapus permanen');
      return result;
    },

    /** Destructive: the caller must confirm, and the server re-checks the workspace name. */
    empty(confirmName) {
      return mutate(() => trash.empty({ confirm_name: confirmName }));
    },

    toggle(id, selected) {
      selection.update((current) => {
        const next = new Set(current);
        if (selected) next.add(id);
        else next.delete(id);
        return next;
      });
    },

    clearSelection: () => selection.set(new Set()),

    destroy() {
      page.destroy();
      stats.destroy();
    },
  };
}
