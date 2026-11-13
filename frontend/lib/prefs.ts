/**
 * ArsipKu - persisted UI preferences.
 * Keys match the ones the legacy vanilla scripts already write, so adopting the
 * new layer does not reset a user's sidebar or view mode.
 */

const KEYS = {
  sidebarCollapsed: 'arsipku_sidebar_collapsed',
  documentView: 'arsipku_doc_view',
  recentSearches: 'arsipku_recent_searches',
} as const;

export type DocumentViewMode = 'list' | 'grid';

function readRaw(key: string): string | null {
  try {
    return globalThis.localStorage?.getItem(key) ?? null;
  } catch {
    return null;
  }
}

function writeRaw(key: string, value: string | null): void {
  try {
    if (value === null) globalThis.localStorage?.removeItem(key);
    else globalThis.localStorage?.setItem(key, value);
  } catch {
    /* quota or private mode: preferences are best-effort */
  }
}

export function getBoolean(key: keyof typeof KEYS, fallback = false): boolean {
  const raw = readRaw(KEYS[key]);
  if (raw === null) return fallback;
  return raw === 'true' || raw === '1';
}

export function setBoolean(key: keyof typeof KEYS, value: boolean): void {
  writeRaw(KEYS[key], String(value));
}

export function getString(key: keyof typeof KEYS, fallback = ''): string {
  return readRaw(KEYS[key]) ?? fallback;
}

export function setString(key: keyof typeof KEYS, value: string | null): void {
  writeRaw(KEYS[key], value);
}

export function getDocumentView(fallback: DocumentViewMode = 'list'): DocumentViewMode {
  return readRaw(KEYS.documentView) === 'grid' ? 'grid' : fallback;
}

export function setDocumentView(mode: DocumentViewMode): void {
  writeRaw(KEYS.documentView, mode);
}

export function getRecentSearches(limit = 8): string[] {
  const raw = readRaw(KEYS.recentSearches);
  if (!raw) return [];
  try {
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed
      .filter((term): term is string => typeof term === 'string' && term.trim() !== '')
      .slice(0, limit);
  } catch {
    return [];
  }
}

export function pushRecentSearch(term: string, limit = 8): string[] {
  const trimmed = term.trim();
  if (!trimmed) return getRecentSearches(limit);
  const next = [trimmed, ...getRecentSearches(limit).filter((t) => t !== trimmed)].slice(0, limit);
  writeRaw(KEYS.recentSearches, JSON.stringify(next));
  return next;
}

export function clearRecentSearches(): void {
  writeRaw(KEYS.recentSearches, null);
}

export const PREF_KEYS = KEYS;
