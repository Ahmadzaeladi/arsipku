/**
 * ArsipKu - favorites service.
 *
 * Contract warning the UI must not paper over: `documents.is_favorite` is one column
 * per document, so a star today is shared by the whole workspace. Per-user favorites
 * need the `document_favorites` table and api/favorites.php (docs/api-contract.md #9).
 * Until then this service reads and writes through api/documents.php and reports
 * `favorited_at`/`added_by` as empty strings, meaning "unknown", never a guess.
 */

import type { Paginated } from '../types/api';
import type {
  BulkFavoriteRequest,
  BulkFavoriteResult,
  FavoriteItem,
  FavoriteListParams,
  FavoritesSummary,
  FavoriteToggleResult,
  ToggleFavoriteRequest,
} from '../types/favorite';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import { normalizeArray, normalizeDocument, paginate } from '../lib/normalize';

export interface FavoriteService {
  list(params?: FavoriteListParams): Promise<Paginated<FavoriteItem>>;
  toggle(request: ToggleFavoriteRequest): Promise<FavoriteToggleResult>;
  setMany(request: BulkFavoriteRequest): Promise<BulkFavoriteResult>;
  summary(): Promise<FavoritesSummary>;
}

export function createHttpFavoriteService(): FavoriteService {
  return {
    async list(params = {}): Promise<Paginated<FavoriteItem>> {
      const { body } = await http.call('documents', {
        action: 'list',
        method: 'GET',
        params: {
          favorite: true,
          q: params.q,
          folder_id: params.folder_id ?? undefined,
          sort: params.sort ?? 'updated_desc',
          page: params.page,
          per_page: params.per_page,
        },
      });
      const documents = normalizeArray(body.data, normalizeDocument);
      const items: FavoriteItem[] = documents.map((document) => ({
        document,
        // Empty string means "the backend cannot tell me": no per-user star history exists yet.
        favorited_at: '',
        added_by: '',
      }));
      return paginate(items, null, params.page ?? 1, params.per_page ?? items.length);
    },

    async toggle(request): Promise<FavoriteToggleResult> {
      const { body } = await http.call('documents', {
        action: 'toggle_favorite',
        method: 'POST',
        body: { id: request.document_id },
      });
      invalidate();
      // The legacy router answers with `is_favorite` beside `data`, not inside it.
      const isFavorite = typeof body.is_favorite === 'boolean'
        ? body.is_favorite
        : body.is_favorite === 1 || body.is_favorite === '1';
      return { document_id: request.document_id, is_favorite: isFavorite };
    },

    /**
     * No batch action exists for stars, so this reconciles by toggling the mismatched
     * ids. api/favorites.php?action=set_many is the endpoint that replaces the loop.
     */
    async setMany(request): Promise<BulkFavoriteResult> {
      const wanted = request.favorite;
      const { items } = await this.list({ per_page: 1000 });
      const starred = new Set(items.map((item) => item.document.id));
      const targets = request.document_ids.filter((id) => starred.has(id) !== wanted);

      const settled = await Promise.allSettled(
        targets.map((document_id) => this.toggle({ document_id, favorite: wanted })),
      );

      const failed = settled
        .map((outcome, index) => ({ outcome, id: targets[index] }))
        .filter(({ outcome }) => outcome.status === 'rejected')
        .map(({ id }) => id);

      return {
        requested_count: request.document_ids.length,
        affected_count: targets.length - failed.length,
        failed_ids: failed,
      };
    },

    async summary(): Promise<FavoritesSummary> {
      const { items } = await this.list({ per_page: 1000 });
      return {
        count: items.length,
        total_size_bytes: items.reduce((sum, item) => sum + item.document.size_bytes, 0),
      };
    },
  };
}

function invalidate(): void {
  emit('data:invalidated', { reason: 'favorites-changed', keys: ['favorites', 'documents'] });
}
