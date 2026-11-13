/**
 * ArsipKu - search service.
 *
 * Live behaviour: search is a `LIKE` over document name/original name/owner,
 * including the containing folder name, inside api/documents.php. There are no
 * facets, no snippets, and no relevance score.
 * Those fields stay undefined and `degraded: true` tells the UI to hide the parts of
 * the panel the backend cannot feed yet (docs/api-contract.md #10).
 *
 * A PostgreSQL backend can implement api/search.php with ILIKE + pg_trgm without any
 * change to these types.
 */

import type { PaginationMeta } from '../types/api';
import type { SearchHit, SearchParams, SearchResult, SearchSuggestion } from '../types/search';
import * as http from '../lib/http';
import { asStr, normalizeArray, normalizeDocument, normalizeFolder, normalizeMember } from '../lib/normalize';

const SUGGESTION_LIMIT = 5;

export interface SearchService {
  search(params?: SearchParams): Promise<SearchResult>;
  suggestions(q: string): Promise<SearchSuggestion[]>;
}

export function createHttpSearchService(): SearchService {
  return {
    async search(params = {}): Promise<SearchResult> {
      const scope = params.scope ?? 'documents';
      const query = asStr(params.q).trim();
      const meta = metaFor(params);

      if (scope === 'documents' || scope === 'all') {
        const { body } = await http.call('documents', {
          action: 'list',
          method: 'GET',
          params: {
            q: query || undefined,
            type: params.type && params.type !== 'all' ? params.type : undefined,
            date_range: params.date_range && params.date_range !== 'all' ? params.date_range : undefined,
            folder_id: scopeFolderId(params),
            sort: params.sort ?? 'updated_desc',
            favorite: params.favorite_only,
            trash: params.area === 'trash' ? true : undefined,
            owner: params.owner,
            limit: meta.per_page,
          },
        });
        const documents = normalizeArray(body.data, normalizeDocument);
        const hits: SearchHit[] = documents.map((document) => ({
          kind: 'document',
          id: document.id,
          name: document.name,
          container_name: document.folder_name ?? null,
          container_id: document.folder_id,
          document,
        }));

        if (scope === 'all') {
          hits.push(...await folderHits(query), ...await memberHits(query));
        }

        return {
          hits,
          meta: metaFor(params, body.count),
          normalized_query: query,
          degraded: true,
        };
      }

      if (scope === 'folders') {
        const rows = await http.get<unknown>('folders', 'all_flat');
        const needle = query.toLowerCase();
        const matched = normalizeArray(rows, normalizeFolder).filter(
          (folder) => !needle || folder.name.toLowerCase().includes(needle),
        );
        return {
          hits: matched.map((folder) => ({
            kind: 'folder', id: folder.id, name: folder.name, container_id: folder.parent_id, folder,
          })),
          meta: metaFor(params, matched.length),
          normalized_query: query,
          degraded: true,
        };
      }

      const members = normalizeArray(await http.get<unknown>('members', 'list'), normalizeMember);
      const needle = query.toLowerCase();
      const matched = members.filter(
        (member) => !needle
          || member.name.toLowerCase().includes(needle)
          || member.email.toLowerCase().includes(needle),
      );
      return {
        hits: matched.map((member) => ({ kind: 'member', id: member.id, name: member.name, member })),
        meta: metaFor(params, matched.length),
        normalized_query: query,
        degraded: true,
      };
    },

    async suggestions(q: string): Promise<SearchSuggestion[]> {
      const trimmed = asStr(q).trim();
      if (!trimmed) return [];
      const { hits } = await this.search({ q: trimmed, scope: 'documents', per_page: SUGGESTION_LIMIT });
      return hits.slice(0, SUGGESTION_LIMIT).map((hit) => ({
        text: hit.name,
        kind: hit.kind,
        id: hit.id,
      }));
    },
  };
}

/** Total unknown => meta.total echoes what the server reported (`count` key). */
function metaFor(params: SearchParams, total?: unknown): PaginationMeta {
  const per_page = params.per_page ?? 25;
  const resolved = typeof total === 'number' ? total : 0;
  return {
    page: params.page ?? 1,
    per_page,
    total: resolved,
    total_pages: per_page > 0 ? Math.max(1, Math.ceil(resolved / per_page)) : 1,
    has_more: false,
  };
}

/** 'current_folder' narrows to the folder the user is browsing; 'this_workspace' does not. */
function scopeFolderId(params: SearchParams): string | undefined {
  if (params.area === 'current_folder' || params.folder_id) {
    return params.folder_id ?? undefined;
  }
  return undefined;
}

async function folderHits(query: string): Promise<SearchHit[]> {
  try {
    const rows = await http.get<unknown>('folders', 'all_flat');
    const needle = query.toLowerCase();
    return normalizeArray(rows, normalizeFolder)
      .filter((folder) => !needle || folder.name.toLowerCase().includes(needle))
      .slice(0, 10)
      .map((folder) => ({
        kind: 'folder' as const,
        id: folder.id,
        name: folder.name,
        container_id: folder.parent_id,
        folder,
      }));
  } catch {
    // Secondary scopes never break the primary document result.
    return [];
  }
}

async function memberHits(query: string): Promise<SearchHit[]> {
  try {
    const rows = await http.get<unknown>('members', 'list');
    const needle = query.toLowerCase();
    return normalizeArray(rows, normalizeMember)
      .filter((member) => !needle || member.name.toLowerCase().includes(needle) || member.email.toLowerCase().includes(needle))
      .slice(0, 10)
      .map((member) => ({ kind: 'member' as const, id: member.id, name: member.name, member }));
  } catch {
    return [];
  }
}
