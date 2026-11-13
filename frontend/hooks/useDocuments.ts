/**
 * ArsipKu - documents hook.
 *
 * Owns the filter/pagination state the document pages carry, the multi-select set
 * behind the bulk bar, and the mutations. Views change filters; the resource refetches.
 * Nothing here decides permissions - the row's `capabilities` come from the server.
 */

import type { Document, DocumentDetail, DocumentFilters, DocumentSort } from '../types/document';
import type { BatchActionResult, Paginated } from '../types/api';
import type { TrashOperationResult } from '../types/trash';
import type { ApiClientError } from '../lib/errors';
import { toApiError } from '../lib/errors';
import { emit } from '../lib/events';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { DocumentService } from '../services/documents';
import type { FavoriteService } from '../services/favorites';
import type { TrashService } from '../services/trash';

export interface DetailState {
  loading: boolean;
  data: DocumentDetail | null;
  error: ApiClientError | null;
}

export interface DocumentsHook {
  page: Resource<Paginated<Document>>;
  filters: Store<DocumentFilters>;
  /** Set by the list view for the bulk bar; cleared whenever the page changes. */
  selection: Store<Set<string>>;
  busy: Store<boolean>;
  error: Store<ApiClientError | null>;
  detail: Store<DetailState>;
  setFilters(patch: Partial<DocumentFilters>): Promise<void>;
  setPage(page: number): Promise<void>;
  setSort(sort: DocumentSort): Promise<void>;
  reload(): Promise<void>;
  openDetail(id: string): Promise<void>;
  closeDetail(): void;
  rename(id: string, name: string): Promise<boolean>;
  move(id: string, folderId: string | null): Promise<boolean>;
  /** Resolves to the new star state, or null when the write failed. */
  toggleFavorite(id: string): Promise<boolean | null>;
  moveToTrash(id: string): Promise<boolean>;
  batchMove(targetFolderId: string | null): Promise<BatchActionResult | null>;
  batchTrash(): Promise<TrashOperationResult | null>;
  toggle(id: string, selected: boolean): void;
  selectAll(): void;
  clearSelection(): void;
  destroy(): void;
}

export function useDocuments(
  documents: DocumentService,
  favorites: FavoriteService,
  trash: TrashService,
  initial: DocumentFilters = {},
): DocumentsHook {
  const filters = createStore<DocumentFilters>({ per_page: 20, sort: 'updated_desc', ...initial });
  const selection = createStore<Set<string>>(new Set());
  const busy = createStore(false);
  const error = createStore<ApiClientError | null>(null);
  const detail = createStore<DetailState>({ loading: false, data: null, error: null });
  /**
   * Star writes are queued, one at a time. `toggle` takes an absolute value, so two
   * clicks in flight would otherwise both read the same pre-toggle row and the second
   * would resend it as "no change".
   */
  let favoriteChain: Promise<unknown> = Promise.resolve();

  const page = createResource(() => documents.list(filters.get()), { keys: ['documents', 'favorites'] });

  async function refetch(next: DocumentFilters): Promise<void> {
    filters.set(next);
    selection.set(new Set());
    await page.refocus(() => documents.list(next));
  }

  async function mutate<T>(run: () => Promise<T>): Promise<T | null> {
    busy.set(true);
    error.set(null);
    try {
      return await run();
    } catch (err) {
      const apiError = toApiError(err);
      error.set(apiError);
      emit('toast', { message: apiError.message, type: 'error' });
      return null;
    } finally {
      busy.set(false);
    }
  }

  return {
    page,
    filters,
    selection,
    busy,
    error,
    detail,

    setFilters: (patch) => refetch({ ...filters.get(), ...patch, page: 1 }),
    setPage: (next) => refetch({ ...filters.get(), page: next }),
    setSort: (sort) => refetch({ ...filters.get(), sort, page: 1 }),
    reload: () => page.reload().then(() => undefined),

    async openDetail(id) {
      detail.update((current) => ({ ...current, loading: true, error: null }));
      try {
        detail.set({ loading: false, data: await documents.get(id), error: null });
      } catch (err) {
        detail.set({ loading: false, data: null, error: toApiError(err) });
      }
    },

    closeDetail: () => detail.set({ loading: false, data: null, error: null }),

    async rename(id, name) {
      const renamed = await mutate(() => documents.rename({ id, name }));
      if (!renamed) return false;
      await page.reload();
      emit('toast', { message: 'Nama dokumen berhasil diubah.', type: 'success' });
      return true;
    },

    async move(id, folderId) {
      const moved = await mutate(() => documents.move({ id, folder_id: folderId }));
      if (!moved) return false;
      await page.reload();
      emit('toast', { message: moved.message, type: 'success' });
      return true;
    },

    async toggleFavorite(id) {
      const queued = favoriteChain.then(async () => {
        const row = page.get().data?.items.find((document) => document.id === id);
        const result = await mutate(() => favorites.toggle({
          document_id: id,
          // Send the absolute value the UI showed, so a lost race cannot double-toggle.
          favorite: row ? !row.is_favorite : undefined,
        }));
        if (!result) return null;
        // Resolve only once the list carries the new star, so the next click reads it.
        await page.reload();
        return result.is_favorite;
      });
      favoriteChain = queued.catch(() => undefined);
      return queued;
    },

    async moveToTrash(id) {
      const moved = await mutate(() => trash.moveToTrash({ id }));
      if (!moved) return false;
      await page.reload();
      emit('toast', { message: moved.message, type: 'success' });
      return true;
    },

    async batchMove(targetFolderId) {
      const ids = [...selection.get()];
      if (!ids.length) return null;
      const result = await mutate(() => documents.batchMove({ ids, targetFolderId }));
      if (result) {
        selection.set(new Set());
        await page.reload();
        emit('toast', {
          message: `${result.affected_count} dokumen dipindahkan.`,
          type: result.failed_ids.length ? 'warning' : 'success',
        });
      }
      return result;
    },

    async batchTrash() {
      const ids = [...selection.get()];
      if (!ids.length) return null;
      const result = await mutate(() => trash.batch({ action: 'delete', ids }));
      if (result) {
        selection.set(new Set());
        await page.reload();
        emit('toast', {
          message: `${result.affected_count} dokumen dipindahkan ke Sampah.`,
          type: result.failed_ids.length ? 'warning' : 'success',
        });
      }
      return result;
    },

    toggle(id, selected) {
      selection.update((current) => {
        const next = new Set(current);
        if (selected) next.add(id);
        else next.delete(id);
        return next;
      });
    },

    selectAll() {
      selection.set(new Set((page.get().data?.items ?? []).map((document) => document.id)));
    },

    clearSelection: () => selection.set(new Set()),

    destroy: () => page.destroy(),
  };
}
