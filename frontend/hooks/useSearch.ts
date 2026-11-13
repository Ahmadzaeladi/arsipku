/**
 * ArsipKu - search hook.
 *
 * Replaces the legacy behaviour where typing in the search box reloaded the whole
 * page: the query is debounced, results arrive as JSON, and the URL mirror stays the
 * only navigation. `limit` for suggestions is deliberately small - api/search.php is
 * planned, so today's LIKE search still runs through documents.php.
 */

import type { SearchParams, SearchResult, SearchSuggestion } from '../types/search';
import { getRecentSearches, pushRecentSearch } from '../lib/prefs';
import { createResource, debounce, createStore, type Resource, type Store } from './reactive';
import type { SearchService } from '../services/search';

export interface SearchHook {
  /** What the input box holds, before the debounce fires. */
  input: Store<string>;
  params: Store<SearchParams>;
  results: Resource<SearchResult | null>;
  suggestions: Resource<SearchSuggestion[]>;
  recent: Store<string[]>;
  type(value: string): void;
  submit(): Promise<void>;
  setParams(patch: Partial<SearchParams>): Promise<void>;
  clear(): Promise<void>;
  destroy(): void;
}

export function useSearch(search: SearchService, initial: SearchParams = {}): SearchHook {
  const params = createStore<SearchParams>({ per_page: 20, scope: 'documents', ...initial });
  const input = createStore<string>(initial.q ?? '');
  const recent = createStore<string[]>(getRecentSearches());

  const results = createResource<SearchResult | null>(
    () => (params.get().q?.trim() ? search.search(params.get()) : Promise.resolve(null)),
    { keys: ['documents', 'search'], immediate: false },
  );
  const suggestions = createResource<SearchSuggestion[]>(
    () => search.suggestions(input.get()),
    { immediate: false, keys: ['documents'] },
  );

  const commit = debounce(() => {
    void submitQuery(input.get());
  }, 260);

  async function submitQuery(q: string): Promise<void> {
    const next: SearchParams = { ...params.get(), q, page: 1 };
    params.set(next);
    await results.refocus(() => (q.trim() ? search.search(next) : Promise.resolve(null)));
    if (q.trim()) recent.set(pushRecentSearch(q));
  }

  return {
    input,
    params,
    results,
    suggestions,
    recent,

    type(value) {
      input.set(value);
      commit();
      // Suggestions are cheap and the dropdown is open while typing: no debounce tier.
      if (value.trim().length >= 2) void suggestions.reload();
    },

    submit() {
      commit.cancel();
      return submitQuery(input.get());
    },

    async setParams(patch) {
      const next = { ...params.get(), ...patch };
      params.set(next);
      await results.refocus(() => (next.q?.trim() ? search.search(next) : Promise.resolve(null)));
    },

    async clear() {
      commit.cancel();
      input.set('');
      await submitQuery('');
    },

    destroy() {
      commit.cancel();
      results.destroy();
      suggestions.destroy();
    },
  };
}
