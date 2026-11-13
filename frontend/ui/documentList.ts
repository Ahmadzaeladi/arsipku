/**
 * ArsipKu - document list and grid adapters.
 *
 * Rows are server-rendered today. These templates reproduce
 * views/components/document-table.php and views/components/document-grid.php from the
 * typed model, keeping every class and `data-doc-*` attribute, so the row markup - and
 * therefore the CSS, the delegated handlers and the docs - stays one contract.
 *
 * Mounting is opt-in (`data-arsipku-ui="ts"`): legacy assets/js/documents.js binds the
 * same buttons directly, and a page must run one controller or the other, not both.
 */

import type { Document } from '../types/document';
import type { TrashItem } from '../types/trash';
import type { Paginated } from '../types/api';
import type { AsyncState, Resource, Store } from '../hooks/reactive';
import type { AppHooks } from '../hooks';
import {
  daysUntil, escapeHtml, extensionOf, formatBytes, formatIndoDate,
  kindBackground, kindIcon, timeAgoIndo,
} from '../lib/format';
import { getDocumentView, setDocumentView } from '../lib/prefs';
import { icon } from './icons';
import { openDeleteModal, openMoveModal, openPermanentDeleteModal, openRenameModal, openShareModal } from './forms';

declare global {
  interface Window {
    /** Legacy renderer in assets/js/preview.js; used when the page still ships it. */
    openDocumentPreview?: (docId: string) => void | Promise<void>;
  }
}

/** One row for either template: flattens Document and TrashItem into display values. */
interface ListRow {
  id: string;
  name: string;
  extension: string;
  size_bytes: number;
  owner_name: string;
  folder_name: string | null;
  folder_id: string | null;
  /** Modified (documents) or deleted (trash) timestamp shown in the date column. */
  date: string;
  is_favorite: boolean;
  download_url: string | null;
  /** Trash only: when the item is gone for good. */
  purge_after?: string | null;
}

export interface DocumentListOptions {
  /** Render the trash action set and read from hooks.trash. */
  trash?: boolean;
  listContainerId?: string;
  gridContainerId?: string;
}

function fromDocument(doc: Document): ListRow {
  return {
    id: doc.id,
    name: doc.name,
    extension: doc.extension || extensionOf(doc.name),
    size_bytes: doc.size_bytes,
    owner_name: doc.owner_name,
    folder_name: doc.folder_name ?? null,
    folder_id: doc.folder_id,
    date: doc.updated_at,
    is_favorite: doc.is_favorite,
    download_url: doc.download_url ?? null,
  };
}

function fromTrashItem(item: TrashItem): ListRow {
  const doc = item.document;
  return {
    id: item.id,
    name: item.name,
    extension: item.extension ?? doc?.extension ?? extensionOf(item.name),
    size_bytes: item.size_bytes ?? doc?.size_bytes ?? 0,
    owner_name: item.deleted_by_name ?? doc?.owner_name ?? '',
    folder_name: item.original_folder_name ?? null,
    folder_id: item.original_folder_id ?? null,
    date: item.deleted_at,
    is_favorite: false,
    download_url: null,
    purge_after: item.purge_after ?? null,
  };
}

/** `kind` is on TrashItem only, so it separates the two row shapes. */
type AnyRow = Document | TrashItem;
type AnyPage = Paginated<AnyRow>;

function toRows(page: AnyPage | null): ListRow[] {
  if (!page) return [];
  return page.items.map((item) => ('kind' in item ? fromTrashItem(item) : fromDocument(item)));
}

function folderCell(row: ListRow): string {
  if (!row.folder_name) return '<span class="text-slate-400 text-[11px] italic">Arsip Utama</span>';
  return `
    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
      ${icon('folder', 'w-3 h-3 text-slate-400')}
      ${escapeHtml(row.folder_name)}
    </span>`;
}

function dateCell(row: ListRow, isTrash: boolean): string {
  const ago = escapeHtml(timeAgoIndo(row.date));
  const remaining = isTrash ? daysUntil(row.purge_after ?? null) : null;
  // Fixture and server clocks drift; an item past its purge date must not read "sisa -195 hari".
  const suffix = remaining === null
    ? ''
    : ` <span class="text-slate-400">${remaining > 0 ? `• sisa ${remaining} hari` : '• segera dihapus'}</span>`;
  return `
    <span title="${escapeHtml(formatIndoDate(row.date))}">${isTrash ? `Dihapus ${ago}` : ago}${suffix}</span>`;
}

/** `data-dropdown-toggle` keeps the existing dropdown controller in app.js working. */
function actionsMenu(row: ListRow, isTrash: boolean): string {
  const items = isTrash
    ? `<button type="button" class="btn-action-restore w-full flex items-center gap-2 px-3 py-1.5 text-xs text-indigo-600 hover:bg-indigo-50 text-left" data-doc-id="${escapeHtml(row.id)}">
         ${icon('refresh', 'w-3.5 h-3.5')} Pulihkan Dokumen
       </button>
       <button type="button" class="btn-action-permanent-delete w-full flex items-center gap-2 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50 text-left" data-doc-id="${escapeHtml(row.id)}" data-doc-name="${escapeHtml(row.name)}">
         ${icon('trash', 'w-3.5 h-3.5')} Hapus Permanen
       </button>`
    : `<button type="button" class="btn-action-preview w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 text-left" data-doc-id="${escapeHtml(row.id)}">
         ${icon('eye', 'w-3.5 h-3.5 text-slate-400')} Pratinjau
       </button>
       ${row.download_url
         ? `<a href="${escapeHtml(row.download_url)}" download="${escapeHtml(row.name)}" class="flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
              ${icon('download', 'w-3.5 h-3.5 text-slate-400')} Unduh Dokumen
            </a>`
         : `<span class="flex items-center gap-2 px-3 py-1.5 text-xs text-slate-400">
              ${icon('download', 'w-3.5 h-3.5 text-slate-400')} Tautan unduh belum dikirim server
            </span>`}
       <button type="button" class="btn-action-rename w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 text-left" data-doc-id="${escapeHtml(row.id)}" data-doc-name="${escapeHtml(row.name)}" data-doc-ext="${escapeHtml(row.extension)}">
         ${icon('pencil', 'w-3.5 h-3.5 text-slate-400')} Ubah Nama
       </button>
       <button type="button" class="btn-action-move w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 text-left" data-doc-id="${escapeHtml(row.id)}" data-doc-folder-id="${escapeHtml(row.folder_id ?? '')}">
         ${icon('move', 'w-3.5 h-3.5 text-slate-400')} Pindahkan
       </button>
       <button type="button" class="btn-action-share w-full flex items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 text-left" data-doc-id="${escapeHtml(row.id)}" data-doc-name="${escapeHtml(row.name)}">
         ${icon('share', 'w-3.5 h-3.5 text-slate-400')} Bagikan Tautan
       </button>
       <div class="my-1 border-t border-slate-100"></div>
       <button type="button" class="btn-action-delete w-full flex items-center gap-2 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50 text-left" data-doc-id="${escapeHtml(row.id)}" data-doc-name="${escapeHtml(row.name)}">
         ${icon('trash', 'w-3.5 h-3.5 text-rose-500')} Pindahkan ke Sampah
       </button>`;

  return `
    <div class="relative inline-block text-left">
      <button type="button" data-dropdown-toggle="doc-menu-${escapeHtml(row.id)}" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors">
        ${icon('more-horizontal', 'w-4 h-4')}
      </button>
      <div id="doc-menu-${escapeHtml(row.id)}" class="dropdown-menu hidden absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-40">
        ${items}
      </div>
    </div>`;
}

function favoriteButton(row: ListRow): string {
  return `<button type="button" class="btn-toggle-favorite text-slate-300 hover:text-amber-500 transition-colors shrink-0 ${row.is_favorite ? 'text-amber-500' : ''}" data-doc-id="${escapeHtml(row.id)}" title="Tandai Favorit">
            ${icon(row.is_favorite ? 'star-solid' : 'star', 'w-4 h-4')}
          </button>`;
}

function rowMarkup(row: ListRow, isTrash: boolean): string {
  const attributes = `data-doc-id="${escapeHtml(row.id)}"`;
  return `
    <tr class="document-row hover:bg-slate-50/80 transition-colors group" ${attributes}>
      <td class="py-3.5 px-4 text-center">
        <input type="checkbox" class="doc-checkbox rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 border-slate-300" ${attributes}>
      </td>
      <td class="py-3.5 px-4">
        <div class="flex items-center gap-3 min-w-0">
          ${isTrash ? '' : favoriteButton(row)}
          <div class="w-8 h-8 rounded-lg ${kindBackground(row.extension)} flex items-center justify-center shrink-0 border">
            ${icon(kindIcon(row.extension), 'w-4 h-4')}
          </div>
          <div class="min-w-0">
            <button type="button" class="btn-action-preview text-slate-800 font-medium hover:text-indigo-600 truncate block text-left max-w-xs sm:max-w-md transition-colors" ${attributes}>
              ${escapeHtml(row.name)}
            </button>
            <span class="text-[10px] text-slate-400 block lg:hidden">
              ${escapeHtml(row.owner_name)} • ${escapeHtml(timeAgoIndo(row.date))}
            </span>
          </div>
        </div>
      </td>
      <td class="py-3.5 px-4 hidden md:table-cell text-slate-600">${folderCell(row)}</td>
      <td class="py-3.5 px-4 hidden sm:table-cell text-slate-600">${escapeHtml(row.owner_name)}</td>
      <td class="py-3.5 px-4 hidden lg:table-cell text-slate-500">${dateCell(row, isTrash)}</td>
      <td class="py-3.5 px-4 text-slate-500 font-mono text-[11px]">${escapeHtml(formatBytes(row.size_bytes))}</td>
      <td class="py-3.5 px-4 text-right">${actionsMenu(row, isTrash)}</td>
    </tr>`;
}

function listMarkup(rows: ListRow[], isTrash: boolean): string {
  const body = rows.length
    ? rows.map((row) => rowMarkup(row, isTrash)).join('')
    : '<tr><td colspan="7" class="py-12 text-center text-slate-400">Tidak ada dokumen yang ditemukan.</td></tr>';
  return `
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
              <th class="py-3 px-4 w-10 text-center">
                <input type="checkbox" id="select-all-docs" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 border-slate-300">
              </th>
              <th class="py-3 px-4">Nama Dokumen</th>
              <th class="py-3 px-4 hidden md:table-cell">Folder</th>
              <th class="py-3 px-4 hidden sm:table-cell">Pemilik</th>
              <th class="py-3 px-4 hidden lg:table-cell">${isTrash ? 'Dihapus' : 'Terakhir Diubah'}</th>
              <th class="py-3 px-4">Ukuran</th>
              <th class="py-3 px-4 w-16 text-right">Tindakan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">${body}</tbody>
        </table>
      </div>
    </div>`;
}

function cardMarkup(row: ListRow, isTrash: boolean): string {
  const attributes = `data-doc-id="${escapeHtml(row.id)}"`;
  return `
    <div class="document-card group bg-white rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-subtle transition-all flex flex-col justify-between overflow-hidden p-4 relative" ${attributes}>
      <div class="flex items-start justify-between gap-2 mb-3">
        <div class="w-12 h-12 rounded-xl ${kindBackground(row.extension)} flex items-center justify-center shrink-0 border">
          ${icon(kindIcon(row.extension), 'w-6 h-6')}
        </div>
        <div class="flex items-center gap-1">
          ${isTrash ? '' : `<button type="button" class="btn-toggle-favorite text-slate-300 hover:text-amber-500 p-1 rounded-lg transition-colors ${row.is_favorite ? 'text-amber-500' : ''}" ${attributes} title="Tandai Favorit">${icon(row.is_favorite ? 'star-solid' : 'star', 'w-4 h-4')}</button>`}
          <div class="relative">
            <button type="button" data-dropdown-toggle="card-menu-${escapeHtml(row.id)}" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-50">
              ${icon('more-horizontal', 'w-4 h-4')}
            </button>
            <div id="card-menu-${escapeHtml(row.id)}" class="dropdown-menu hidden absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-30">
              ${actionsMenu(row, isTrash)}
            </div>
          </div>
        </div>
      </div>
      <div class="min-w-0 mb-3">
        <button type="button" class="btn-action-preview text-sm font-semibold text-slate-800 hover:text-indigo-600 truncate text-left w-full transition-colors" ${attributes} title="${escapeHtml(row.name)}">
          ${escapeHtml(row.name)}
        </button>
        ${row.folder_name
          ? `<p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5 truncate">
               ${icon('folder', 'w-3 h-3 text-slate-400 shrink-0')}
               ${escapeHtml(row.folder_name)}
             </p>`
          : ''}
      </div>
      <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
        <span class="font-mono font-medium">${escapeHtml(formatBytes(row.size_bytes))}</span>
        <span>${escapeHtml(timeAgoIndo(row.date))}</span>
      </div>
    </div>`;
}

function gridMarkup(rows: ListRow[], isTrash: boolean): string {
  if (!rows.length) {
    return '<div class="col-span-full py-12 text-center text-slate-400">Tidak ada dokumen yang ditemukan.</div>';
  }
  return `<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">${rows.map((row) => cardMarkup(row, isTrash)).join('')}</div>`;
}

function docIdOf(target: EventTarget | null): string {
  const node = (target as HTMLElement | null)?.closest?.('[data-doc-id]');
  return node ? node.getAttribute('data-doc-id') ?? '' : '';
}

function dataOf(target: EventTarget | null, key: string): string {
  const node = (target as HTMLElement | null)?.closest?.(`[data-doc-${key}]`);
  return node ? node.getAttribute(`data-doc-${key}`) ?? '' : '';
}

/**
 * Replace the server-rendered rows with the live resource, then keep both templates in
 * step. Until the first answer arrives the page keeps showing what PHP rendered, so a
 * slow or missing endpoint never blanks the table.
 */
export function mountDocumentList(hooks: AppHooks, options: DocumentListOptions = {}): () => void {
  const isTrash = options.trash === true;
  const list = document.getElementById(options.listContainerId ?? 'view-list-container');
  const grid = document.getElementById(options.gridContainerId ?? 'view-grid-container');
  if (!list && !grid) return () => {};

  // Both pages answer ListRow-shaped items; the union is only ever read through toRows().
  const page = (isTrash ? hooks.trash.page : hooks.documents.page) as unknown as Resource<AnyPage>;
  const selection: Store<Set<string>> = isTrash ? hooks.trash.selection : hooks.documents.selection;

  const render = (state: AsyncState<AnyPage>): void => {
    if (!state.loaded) return;
    const rows = toRows(state.data);
    if (list) list.innerHTML = listMarkup(rows, isTrash);
    if (grid) grid.innerHTML = gridMarkup(rows, isTrash);
    syncSelection(selection.get());
  };

  const unsubscribe = page.subscribe(render);
  const unsubscribeSelection = selection.subscribe(syncSelection);
  render(page.get());

  function syncSelection(selected: Set<string>): void {
    const bar = document.getElementById('multi-select-bar');
    const count = document.getElementById('multi-selected-count');
    if (count) count.textContent = String(selected.size);
    bar?.classList.toggle('show', selected.size > 0);

    const boxes = Array.from(document.querySelectorAll<HTMLInputElement>('.doc-checkbox'));
    for (const box of boxes) {
      box.checked = selected.has(box.dataset.docId ?? '');
      box.closest('.document-row')?.classList.toggle('bg-indigo-50/50', box.checked);
    }
    const selectAll = document.getElementById('select-all-docs') as HTMLInputElement | null;
    if (selectAll) {
      selectAll.checked = boxes.length > 0 && boxes.every((box) => selected.has(box.dataset.docId ?? ''));
      selectAll.indeterminate = selected.size > 0 && !selectAll.checked;
    }
  }

  function onClick(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    const id = docIdOf(target);
    const button = target?.closest?.('button[class]') as HTMLElement | null;
    const classes = button?.className ?? '';
    if (!classes) return;
    const row = id ? toRows(page.get().data).find((item) => item.id === id) : undefined;

    if (classes.includes('btn-toggle-favorite')) {
      event.preventDefault();
      if (id) void hooks.documents.toggleFavorite(id);
      return;
    }
    if (classes.includes('btn-action-preview')) {
      event.preventDefault();
      if (!id) return;
      void hooks.documents.openDetail(id).then(() => {
        if (window.openDocumentPreview) window.openDocumentPreview(id);
      });
      return;
    }
    if (classes.includes('btn-action-rename')) {
      event.preventDefault();
      openRenameModal(id, dataOf(target, 'name'), dataOf(target, 'ext'));
      return;
    }
    if (classes.includes('btn-action-move')) {
      event.preventDefault();
      openMoveModal(id || null, row?.folder_id ?? null);
      return;
    }
    if (classes.includes('btn-action-share')) {
      event.preventDefault();
      openShareModal(id, dataOf(target, 'name'));
      return;
    }
    if (classes.includes('btn-action-delete')) {
      event.preventDefault();
      openDeleteModal(id, dataOf(target, 'name'));
      return;
    }
    if (classes.includes('btn-action-restore')) {
      event.preventDefault();
      // No folder override: the server puts the document back where it came from.
      if (id) void hooks.trash.restore(id);
      return;
    }
    if (classes.includes('btn-action-permanent-delete')) {
      event.preventDefault();
      openPermanentDeleteModal(id, dataOf(target, 'name'));
    }
  }

  function onChange(event: Event): void {
    const box = event.target as HTMLInputElement | null;
    if (!(box instanceof HTMLInputElement)) return;
    if (box.id === 'select-all-docs') {
      if (box.checked) {
        const ids = toRows(page.get().data).map((row) => row.id);
        selection.set(new Set(ids));
      } else {
        selection.set(new Set());
      }
      syncSelection(selection.get());
      return;
    }
    if (!box.classList.contains('doc-checkbox')) return;
    const id = box.dataset.docId ?? '';
    if (id) selection.update((current) => {
      const next = new Set(current);
      if (box.checked) next.add(id);
      else next.delete(id);
      return next;
    });
    syncSelection(selection.get());
  }

  for (const container of [list, grid]) {
    container?.addEventListener('click', onClick);
    container?.addEventListener('change', onChange);
  }

  const cleanups: (() => void)[] = [
    () => {
      for (const container of [list, grid]) {
        container?.removeEventListener('click', onClick);
        container?.removeEventListener('change', onChange);
      }
    },
    unsubscribe,
    unsubscribeSelection,
  ];

  if (!isTrash) cleanups.push(mountSelectionBar(hooks.documents.selection, hooks));
  else cleanups.push(mountTrashBar(hooks.trash.selection, hooks));

  return () => {
    for (const cleanup of cleanups) cleanup();
  };
}

/**
 * Bind a bulk-bar button. Assignment to `onclick` (not addEventListener) is deliberate:
 * `#btn-multi-move` ships with an inline handler that reads the legacy `selectedDocIds`
 * global, and in TS mode that global must not exist.
 */
function bindBarButtons(ids: Record<string, () => void>): () => void {
  const nodes: HTMLElement[] = [];
  for (const [id, handler] of Object.entries(ids)) {
    const node = document.getElementById(id);
    if (!node) continue;
    node.onclick = handler;
    nodes.push(node);
  }
  return () => {
    for (const node of nodes) node.onclick = null;
  };
}

/** The floating bulk bar: move/rename/trash act on the whole selection at once. */
function mountSelectionBar(selection: Store<Set<string>>, hooks: AppHooks): () => void {
  return bindBarButtons({
    'btn-multi-cancel': () => selection.set(new Set()),
    'btn-multi-delete': () => {
      const size = selection.get().size;
      if (!size || !window.confirm(`Pindahkan ${size} dokumen terpilih ke Sampah?`)) return;
      void hooks.documents.batchTrash();
    },
    'btn-multi-move': () => {
      if (!selection.get().size) return;
      openMoveModal(null, null);
    },
  });
}

function mountTrashBar(selection: Store<Set<string>>, hooks: AppHooks): () => void {
  return bindBarButtons({
    'btn-multi-cancel': () => selection.set(new Set()),
    'btn-multi-restore': () => {
      if (!selection.get().size) return;
      void hooks.trash.batchRestore();
    },
    'btn-multi-perm-delete': () => {
      const size = selection.get().size;
      if (!size || !window.confirm(`Hapus PERMANEN ${size} item terpilih? Tindakan ini tidak dapat dibatalkan.`)) return;
      void hooks.trash.batchPurge();
    },
  });
}

/**
 * List/grid switch, same element ids and the same localStorage key as the legacy
 * toggle, so nobody loses their preferred view when the TS layer takes over.
 */
export function mountViewToggle(): () => void {
  const buttons = [document.getElementById('btn-view-list'), document.getElementById('btn-view-grid')];
  const views = [document.getElementById('view-list-container'), document.getElementById('view-grid-container')];
  if (buttons.some((node) => !node) || views.some((node) => !node)) return () => {};
  const [btnList, btnGrid] = buttons as HTMLElement[];
  const [listEl, gridEl] = views as HTMLElement[];

  function apply(view: 'list' | 'grid'): void {
    const active = ['bg-white', 'text-indigo-600', 'shadow-xs'];
    const idle = ['text-slate-500'];
    listEl.classList.toggle('hidden', view !== 'list');
    gridEl.classList.toggle('hidden', view !== 'grid');
    for (const [button, isOn] of [[btnList, view === 'list'], [btnGrid, view === 'grid']] as const) {
      for (const cls of active) button.classList.toggle(cls, isOn);
      for (const cls of idle) button.classList.toggle(cls, !isOn);
    }
  }

  const showList = (): void => {
    apply('list');
    setDocumentView('list');
  };
  const showGrid = (): void => {
    apply('grid');
    setDocumentView('grid');
  };
  btnList.addEventListener('click', showList);
  btnGrid.addEventListener('click', showGrid);
  apply(getDocumentView());

  return () => {
    btnList.removeEventListener('click', showList);
    btnGrid.removeEventListener('click', showGrid);
  };
}
