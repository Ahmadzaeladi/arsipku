/**
 * ArsipKu - page boot.
 *
 * One entry point: build the hooks against the service registry, mount the adapters
 * onto the DOM ids the PHP views already ship, and publish `window.ArsipKu` for the
 * dev menu. Nothing here decides access - `hooks.workspace.role` and the row
 * capabilities come from the server, and every request still carries `?ws=` for the
 * backend to re-check.
 *
 * TS mode is opt-in (`data-arsipku-ui="ts"`, `?ui=ts`, or window.__ARSIPKU__.ui)
 * because assets/js/documents.js and uploader.js bind the very same buttons: a page
 * runs one controller, not both.
 */

import { getServices } from '../services/registry';
import { getWorkspaceSlug } from '../lib/workspaceContext';
import type { DocumentFilters, DocumentSort, DocumentTypeFilter, DateRangeFilter } from '../types/document';
import { createHooks, type AppHooks } from '../hooks';
import { installModalFacade, installToastFacade, mountToasts } from './overlays';
import { mountDocumentList, mountViewToggle } from './documentList';
import { mountUploadQueue } from './uploadQueue';
import { mountStorageMeter } from './meters';
import { mountFolderSelects, wireDocumentModals } from './forms';

const SORTS: DocumentSort[] = [
  'updated_desc', 'updated_asc', 'name_asc', 'name_desc', 'size_asc', 'size_desc', 'created_desc',
];
const TYPES: DocumentTypeFilter[] = ['all', 'pdf', 'word', 'spreadsheet', 'excel', 'image', 'archive', 'zip', 'txt'];
const RANGES: DateRangeFilter[] = ['all', 'today', '7days', '30days', 'this_year'];

export interface ArsipKuApp {
  hooks: AppHooks;
  /** The list adapters are live; legacy scripts must not be loaded as well. */
  takeover: boolean;
  destroy(): void;
}

declare global {
  interface Window {
    ArsipKu?: ArsipKuApp;
  }
}

function bodyFlag(name: string): string | undefined {
  return typeof document === 'undefined' ? undefined : document.body?.dataset[name];
}

/** `?ui=ts` also works for the optional TypeScript UI adapters. */
export function isTakeoverRequested(): boolean {
  if (bodyFlag('arsipkuUi') === 'ts') return true;
  if (typeof window === 'undefined') return false;
  if (window.__ARSIPKU__?.ui === 'ts') return true;
  return new URLSearchParams(window.location.search).get('ui') === 'ts';
}

function isTrashView(): boolean {
  if (bodyFlag('arsipkuView') === 'trash') return true;
  return typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('page') === 'trash';
}

/** The pages drive filters through the URL, so the first paint matches the query. */
export function filtersFromUrl(): DocumentFilters {
  if (typeof window === 'undefined') return {};
  const params = new URLSearchParams(window.location.search);
  const filters: DocumentFilters = {};
  const q = params.get('q');
  const type = params.get('type');
  const range = params.get('date_range');
  const sort = params.get('sort');
  const folderId = params.get('folder_id');
  const page = Number(params.get('page'));
  if (q) filters.q = q;
  if (type && TYPES.includes(type as DocumentTypeFilter)) filters.type = type as DocumentTypeFilter;
  if (range && RANGES.includes(range as DateRangeFilter)) filters.date_range = range as DateRangeFilter;
  if (sort && SORTS.includes(sort as DocumentSort)) filters.sort = sort as DocumentSort;
  if (folderId) filters.folder_id = folderId;
  if (Number.isInteger(page) && page > 0) filters.page = page;
  return filters;
}

export function boot(): ArsipKuApp | null {
  if (typeof document === 'undefined') return null;
  const takeover = isTakeoverRequested();
  // lib/workspaceContext already resolved the slug from ?ws=, the body attribute,
  // window.__ARSIPKU__ and localStorage - nothing to re-read here.
  const hooks = createHooks(getServices(), {
    workspaceSlug: getWorkspaceSlug() || null,
    documentFilters: filtersFromUrl(),
  });

  const cleanups: (() => void)[] = [
    mountToasts(hooks.notifications),
    hooks.notifications.expired.subscribe((expired) => {
      if (!expired) return;
      hooks.notifications.push('Sesi berakhir. Muat ulang halaman untuk masuk kembali.', 'error', 10_000);
      hooks.notifications.acknowledgeExpired();
    }),
  ];
  installToastFacade(hooks.notifications);
  installModalFacade();

  if (takeover) {
    const trash = isTrashView();
    cleanups.push(
      mountViewToggle(),
      mountDocumentList(hooks, { trash }),
      mountFolderSelects(hooks),
      wireDocumentModals(hooks, trash),
      mountUploadQueue(hooks),
      mountStorageMeter(hooks),
    );
  }

  const app: ArsipKuApp = {
    hooks,
    takeover,
    destroy() {
      for (const cleanup of cleanups.splice(0).reverse()) cleanup();
      hooks.destroy();
    },
  };
  window.ArsipKu = app;
  return app;
}

/** Kept out of the module side effects so a page can call boot() itself. */
export function autoBoot(): void {
  if (window.ArsipKu) return;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { void boot(); }, { once: true });
    return;
  }
  void boot();
}

