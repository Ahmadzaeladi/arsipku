/**
 * ArsipKu - favorites hook.
 *
 * Contract caveat the UI keeps visible: today `documents.is_favorite` is one column
 * shared by the workspace, so a star here can light up for a colleague. The service
 * reports `favorited_at`/`added_by` as empty strings when the backend cannot tell.
 */

import type { FavoriteItem, FavoriteListParams, FavoritesSummary } from '../types/favorite';
import type { Paginated } from '../types/api';
import { emit } from '../lib/events';
import { toApiError } from '../lib/errors';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { FavoriteService } from '../services/favorites';

export interface FavoritesHook {
  page: Resource<Paginated<FavoriteItem>>;
  summary: Resource<FavoritesSummary>;
  filters: Store<FavoriteListParams>;
  isStarred: (documentId: string) => boolean;
  setQuery(q: string): Promise<void>;
  setPage(page: number): Promise<void>;
  toggle(documentId: string, next?: boolean): Promise<boolean | null>;
  setMany(documentIds: string[], favorite: boolean): Promise<number>;
  destroy(): void;
}

export function useFavorites(favorites: FavoriteService, initial: FavoriteListParams = {}): FavoritesHook {
  const filters = createStore<FavoriteListParams>({ per_page: 24, ...initial });
  const page = createResource(() => favorites.list(filters.get()), { keys: ['favorites', 'documents'] });
  const summary = createResource(() => favorites.summary(), { keys: ['favorites'] });

  async function refetch(next: FavoriteListParams): Promise<void> {
    filters.set(next);
    await page.refocus(() => favorites.list(next));
  }

  return {
    page,
    summary,
    filters,

    isStarred: (documentId) => page.get().data?.items.some((item) => item.document.id === documentId) ?? false,

    setQuery: (q) => refetch({ ...filters.get(), q, page: 1 }),
    setPage: (page2) => refetch({ ...filters.get(), page: page2 }),

    async toggle(documentId, next) {
      try {
        const result = await favorites.toggle({ document_id: documentId, favorite: next });
        return result.is_favorite;
      } catch (err) {
        emit('toast', { message: toApiError(err).message, type: 'error' });
        return null;
      }
    },

    async setMany(documentIds, favorite) {
      try {
        const result = await favorites.setMany({ document_ids: documentIds, favorite });
        if (result.failed_ids.length) {
          emit('toast', { message: `${result.failed_ids.length} bintang gagal disimpan.`, type: 'warning' });
        }
        return result.affected_count;
      } catch (err) {
        emit('toast', { message: toApiError(err).message, type: 'error' });
        return 0;
      }
    },

    destroy() {
      page.destroy();
      summary.destroy();
    },
  };
}
