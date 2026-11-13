/**
 * ArsipKu - Favorites models.
 * IMPORTANT contract note: `documents.is_favorite` is a single column, so one star
 * is currently shared by every member of the workspace. The contract moves this to a
 * per-user `document_favorites` table (workspace_id, user_id, document_id); the types
 * below describe the future per-user API contract; the current endpoint uses the
 * shared database column until that schema is implemented.
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';
import type { Document, DocumentSort } from './document';

export interface FavoriteItem {
  document: Document;
  /** When the caller starred it, not when the document was created. */
  favorited_at: string;
  added_by: string;
}

export interface FavoriteListParams extends WorkspaceScopedParams, PaginationParams {
  q?: string;
  folder_id?: string | null;
  sort?: DocumentSort;
}

export interface ToggleFavoriteRequest extends WorkspaceScopedParams {
  document_id: string;
  /** Absolute value wins over `toggle` when both are sent. */
  favorite?: boolean;
}

export interface FavoriteToggleResult {
  document_id: string;
  is_favorite: boolean;
}

export interface BulkFavoriteRequest extends WorkspaceScopedParams {
  document_ids: string[];
  favorite: boolean;
}

export interface BulkFavoriteResult {
  requested_count: number;
  affected_count: number;
  failed_ids: string[];
}

export interface FavoritesSummary {
  count: number;
  total_size_bytes: number;
}
