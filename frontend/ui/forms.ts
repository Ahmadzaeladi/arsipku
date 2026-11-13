/**
 * ArsipKu - form and select adapters.
 *
 * The dialogs are server-rendered (views/layouts/modals.php) and keep their element
 * ids, so this module binds those ids to the hooks instead of rebuilding the modals.
 * Mount it only in TS mode: legacy assets/js/documents.js submits the same forms and
 * two listeners would post twice.
 */

import type { AppHooks } from '../hooks';
import type { FolderFlat } from '../types/folder';
import { escapeHtml } from '../lib/format';
import { emit } from '../lib/events';
import { closeModal, openModal } from './overlays';

/** The pickers spell "no folder" as `root`; the API spells it `null`. */
const ROOT_VALUE = 'root';

export function element(id: string): HTMLElement | null {
  return document.getElementById(id);
}

function inputValue(id: string): string {
  const node = element(id);
  return node && 'value' in node ? String((node as HTMLInputElement).value) : '';
}

function setInputValue(id: string, value: string): void {
  const node = element(id) as HTMLInputElement | null;
  if (node) node.value = value;
}

function setText(id: string, value: string): void {
  const node = element(id);
  if (node) node.textContent = value;
}

/** Rebuild a folder picker from the flat list, keeping the current choice when possible. */
export function syncFolderSelect(selectId: string, folders: readonly FolderFlat[]): void {
  const select = element(selectId);
  if (!(select instanceof HTMLSelectElement)) return;
  const previous = select.value;
  const options = [`<option value="${ROOT_VALUE}">📁 Arsip Utama (Tanpa Folder)</option>`];
  for (const folder of folders) {
    options.push(`<option value="${escapeHtml(folder.id)}">📁 ${escapeHtml(folder.name)}</option>`);
  }
  select.innerHTML = options.join('');
  if (folders.some((folder) => folder.id === previous)) select.value = previous;
}

/**
 * Keep the two pickers in step with folders.allFlat(). A select the page already
 * filled server-side is left alone until the resource answers, so an HTTP-mode page
 * never loses its options to an empty response.
 */
export function mountFolderSelects(hooks: AppHooks, selectIds: string[] = ['upload-destination-folder', 'move-target-folder']): () => void {
  const unsubscribe = hooks.folders.flat.subscribe((state) => {
    if (!state.loaded || !state.data) return;
    for (const id of selectIds) syncFolderSelect(id, state.data);
  });
  return () => {
    unsubscribe();
  };
}

/** Value of the move picker as an id, or null for Arsip Utama. */
function moveTarget(): string | null {
  const value = inputValue('move-target-folder');
  return !value || value === ROOT_VALUE ? null : value;
}

/**
 * Bind the document dialogs to the hooks. `isTrash` decides which destructive confirm
 * is live, because the trash page and the documents page share the modal shell.
 */
export function wireDocumentModals(hooks: AppHooks, isTrash = false): () => void {
  const cleanups: (() => void)[] = [];

  const on = <K extends keyof HTMLElementEventMap>(
    id: string,
    event: K,
    handler: (e: HTMLElementEventMap[K]) => void,
  ): void => {
    const node = element(id);
    if (!node) return;
    node.addEventListener(event, handler as EventListener);
    cleanups.push(() => node.removeEventListener(event, handler as EventListener));
  };

  on('form-rename-document', 'submit', (event) => {
    event.preventDefault();
    const id = inputValue('rename-doc-id');
    const base = inputValue('rename-doc-name').trim();
    const extension = inputValue('rename-doc-ext-pill').trim();
    if (!id) return;
    if (!base) {
      emit('toast', { message: 'Nama dokumen tidak boleh kosong.', type: 'warning' });
      return;
    }
    void hooks.documents.rename(id, base + extension).then((ok) => {
      if (ok) closeModal('modal-rename');
    });
  });

  on('form-move-document', 'submit', (event) => {
    event.preventDefault();
    const id = inputValue('move-doc-id');
    const target = moveTarget();
    const done = (ok: boolean): void => {
      if (ok) closeModal('modal-move');
    };
    if (id) void hooks.documents.move(id, target).then(done);
    else if (hooks.documents.selection.get().size) void hooks.documents.batchMove(target).then((r) => done(r !== null));
  });

  if (isTrash) {
    on('btn-confirm-perm-delete-doc', 'click', () => {
      const id = inputValue('perm-delete-doc-id');
      if (!id) return;
      void hooks.trash.purge(id).then((result) => {
        if (result) closeModal('modal-confirm-permanent-delete');
      });
    });
  } else {
    on('btn-confirm-delete-doc', 'click', () => {
      const id = inputValue('delete-doc-id');
      if (!id) return;
      void hooks.documents.moveToTrash(id).then((ok) => {
        if (ok) closeModal('modal-confirm-delete');
      });
    });
  }

  return () => {
    for (const cleanup of cleanups) cleanup();
  };
}

/** Prefill + open the rename dialog, matching the attributes the row already carries. */
export function openRenameModal(id: string, name: string, extension: string): void {
  setInputValue('rename-doc-id', id);
  const pill = extension.startsWith('.') ? extension.slice(1) : extension;
  const base = pill && name.endsWith(`.${pill}`) ? name.slice(0, -(pill.length + 1)) : name;
  setInputValue('rename-doc-name', base);
  setText('rename-doc-ext-pill', pill ? `.${pill}` : '');
  openModal('modal-rename');
}

export function openMoveModal(id: string | null, currentFolderId: string | null): void {
  setInputValue('move-doc-id', id ?? '');
  const select = element('move-target-folder');
  if (select instanceof HTMLSelectElement) select.value = currentFolderId ?? ROOT_VALUE;
  openModal('modal-move');
}

export function openDeleteModal(id: string, name: string): void {
  setInputValue('delete-doc-id', id);
  setText('delete-doc-name-target', `"${name}"`);
  openModal('modal-confirm-delete');
}

export function openPermanentDeleteModal(id: string, name: string): void {
  setInputValue('perm-delete-doc-id', id);
  setText('perm-delete-doc-name', `"${name}"`);
  openModal('modal-confirm-permanent-delete');
}

/**
 * Share stays a locator, not a permission: the URL is the page deep-link the legacy UI
 * already uses, and api/permissions.php (real share records) is a planned endpoint.
 */
export function openShareModal(id: string, name: string): void {
  setText('share-doc-name', name);
  const url = new URL(window.location.href);
  url.search = '';
  url.searchParams.set('page', 'preview');
  url.searchParams.set('id', id);
  setInputValue('share-link-input', url.toString());
  openModal('modal-share');
}
