/**
 * ArsipKu - Search models.
 * Today search is a LIKE over name/original_name/owner_name inside
 * api/documents.php. The contract adds scoped results, facets and snippets;
 * a PostgreSQL backend can satisfy it with ILIKE + pg_trgm (or tsvector) without
 * changing these types.
 */

import type { PaginationMeta, PaginationParams, WorkspaceScopedParams } from './api';
import type { DateRangeFilter, Document, DocumentSort, DocumentTypeFilter } from './document';
import type { Folder } from './folder';
import type { Member } from './member';

export type SearchScope = 'documents' | 'folders' | 'members' | 'all';

export type SearchArea = 'this_workspace' | 'current_folder' | 'shared_with_me' | 'trash' | 'favorites';

export interface SearchParams extends WorkspaceScopedParams, PaginationParams {
  /** Free text. Empty string means "browse with filters". */
  q?: string;
  scope?: SearchScope;
  area?: SearchArea;
  type?: DocumentTypeFilter;
  date_range?: DateRangeFilter;
  folder_id?: string | null;
  owner?: string;
  favorite_only?: boolean;
  sort?: DocumentSort;
  /** Ask the server for <mark> snippets instead of plain strings. */
  highlight?: boolean;
}

export type SearchResultKind = 'document' | 'folder' | 'member';

export interface SearchHit {
  kind: SearchResultKind;
  id: string;
  name: string;
  /** Parent folder label, resolved server-side so breadcrumbs stay cheap. */
  container_name?: string | null;
  container_id?: string | null;
  score?: number;
  /** Snippet with <mark> around matches. Already escaped by the server. */
  highlight?: string | null;
  matched_fields?: string[];
  document?: Document;
  folder?: Folder;
  member?: Member;
}

export interface SearchFacetBucket {
  value: string;
  label: string;
  count: number;
}

export interface SearchFacets {
  by_type: SearchFacetBucket[];
  by_owner: SearchFacetBucket[];
  by_folder: SearchFacetBucket[];
  by_date_range: SearchFacetBucket[];
}

export interface SearchResult {
  hits: SearchHit[];
  meta: PaginationMeta;
  facets?: SearchFacets;
  /** Echo of the normalised query so the UI can show "menampilkan untuk". */
  normalized_query: string;
  took_ms?: number;
  /** True when the server could not answer and the client fell back to filters. */
  degraded?: boolean;
}

export interface SearchSuggestion {
  text: string;
  kind: SearchResultKind | 'term';
  id?: string;
}

/** Server-persisted query (contract); recent terms are stored client-side. */
export interface SavedSearch {
  id: string;
  workspace_id: string;
  name: string;
  params: SearchParams;
  created_at: string;
}

export interface CreateSavedSearchRequest extends WorkspaceScopedParams {
  name: string;
  params: SearchParams;
}

/** localStorage key holding the caller's recent search terms. */
export const RECENT_SEARCHES_KEY = 'arsipku_recent_searches';

export const MAX_RECENT_SEARCHES = 8;
