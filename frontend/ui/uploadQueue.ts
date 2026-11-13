/**
 * ArsipKu - upload queue adapter.
 *
 * Drives the existing modal-upload shell (views/layouts/modals.php): dropzone, file
 * input, queue rows, start/clear buttons. State lives in hooks.uploads; this file only
 * paints it and forwards gestures. `window.removeFromQueue` keeps working because the
 * queue markup is copied with inline handlers by older pages.
 */

import type { UploadItemState, UploadQueueItem } from '../types/upload';
import type { AppHooks } from '../hooks';
import { escapeHtml, extensionOf, formatBytes } from '../lib/format';
import { icon } from './icons';
import { closeModal } from './overlays';

const STATE_BADGE: Record<UploadItemState, { label: string; className: string }> = {
  queued: { label: 'Menunggu', className: 'bg-slate-100 text-slate-600' },
  checking: { label: 'Memeriksa', className: 'bg-slate-100 text-slate-600' },
  uploading: { label: 'Mengunggah', className: 'bg-indigo-100 text-indigo-700' },
  verifying: { label: 'Memverifikasi', className: 'bg-indigo-100 text-indigo-700' },
  done: { label: 'Berhasil', className: 'bg-emerald-100 text-emerald-700' },
  failed: { label: 'Gagal', className: 'bg-rose-100 text-rose-700' },
  canceled: { label: 'Dibatalkan', className: 'bg-slate-100 text-slate-500' },
};

/** Progress is only meaningful for the two in-flight states. */
function isActive(state: UploadItemState): boolean {
  return state === 'uploading' || state === 'verifying';
}

function isPending(state: UploadItemState): boolean {
  return state === 'queued' || state === 'checking';
}

function rowMarkup(item: UploadQueueItem, removable: boolean): string {
  const badge = STATE_BADGE[item.state];
  const extension = extensionOf(item.file.name).toUpperCase().slice(0, 4);
  const percent = Math.round(item.percent);
  const label = isActive(item.state) && percent > 0 ? `${badge.label} ${percent}%` : badge.label;
  const check = isActive(item.state) ? icon('check', 'w-3.5 h-3.5') : '';
  return `
    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex flex-col gap-2" data-upload-local-id="${escapeHtml(item.local_id)}">
      <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 uppercase text-xs font-bold border border-indigo-100">
            ${escapeHtml(extension || 'FILE')}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800 truncate">${escapeHtml(item.file.name)}</p>
            <p class="text-xs text-slate-500">${escapeHtml(formatBytes(item.total_bytes))}${item.loaded_bytes ? ` • ${escapeHtml(formatBytes(item.loaded_bytes))} terkirim` : ''}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-xs px-2 py-0.5 rounded-full font-medium ${badge.className} flex items-center gap-1" ${item.error ? `title="${escapeHtml(item.error)}"` : ''}>${check} ${escapeHtml(label)}</span>
          ${removable && isPending(item.state)
            ? `<button type="button" class="btn-remove-upload text-slate-400 hover:text-rose-600 p-1" data-local-id="${escapeHtml(item.local_id)}">${icon('x', 'w-4 h-4')}</button>`
            : ''}
        </div>
      </div>
      ${isActive(item.state)
        ? `<div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
             <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-200" style="width: ${percent}%"></div>
           </div>`
        : ''}
      ${item.state === 'failed' && item.error
        ? `<p class="text-[11px] text-rose-600">${escapeHtml(item.error)}</p>`
        : ''}
    </div>`;
}

export interface UploadQueueOptions {
  /** Folder the confirmed files land in; `null` = Arsip Utama. */
  destinationSelectId?: string;
  onClose?(): void;
}

/** Read the picker the same way uploader.js did: `root` means "no folder". */
function destinationId(selectId: string): string | null {
  const select = document.getElementById(selectId) as HTMLSelectElement | null;
  const value = select?.value ?? '';
  return !value || value === 'root' ? null : value;
}

export function mountUploadQueue(hooks: AppHooks, options: UploadQueueOptions = {}): () => void {
  const queue = hooks.uploads;
  const container = document.getElementById('upload-queue-container');
  const listEl = document.getElementById('upload-queue-list');
  const dropzone = document.getElementById('upload-dropzone');
  const fileInput = document.getElementById('upload-file-input') as HTMLInputElement | null;
  const btnSelect = document.getElementById('btn-select-file');
  const btnStart = document.getElementById('btn-start-upload') as HTMLButtonElement | null;
  const btnClear = document.getElementById('btn-clear-queue') as HTMLButtonElement | null;
  const countBadge = document.getElementById('upload-queue-count');
  const selectId = options.destinationSelectId ?? 'upload-destination-folder';
  if (!listEl) return () => {};

  const cleanups: (() => void)[] = [];

  function render(): void {
    const items = queue.items.get();
    const uploading = queue.uploading.get();
    container?.classList.toggle('hidden', items.length === 0);
    if (countBadge) countBadge.textContent = String(items.length);
    if (btnStart) btnStart.disabled = uploading || !items.some((item) => isPending(item.state));
    if (btnClear) btnClear.disabled = uploading;
    listEl!.innerHTML = items.map((item) => rowMarkup(item, !uploading)).join('');
  }

  cleanups.push(queue.items.subscribe(render));
  cleanups.push(queue.uploading.subscribe(render));

  function addFiles(files: FileList | File[]): void {
    const selected = Array.from(files);
    if (!selected.length) return;
    const before = new Set(queue.items.get().map((item) => item.local_id));
    queue.add(selected);
    // add() validates against the upload limits and marks rejects as failed; surface those
    // once here instead of letting them sit silently in the queue.
    for (const item of queue.items.get()) {
      if (!before.has(item.local_id) && item.state === 'failed') {
        hooks.notifications.push(item.error ?? `${item.file.name} ditolak.`, 'warning');
      }
    }
  }

  if (fileInput) {
    const onChange = (): void => {
      if (fileInput.files) addFiles(fileInput.files);
      fileInput.value = '';
    };
    fileInput.addEventListener('change', onChange);
    cleanups.push(() => fileInput.removeEventListener('change', onChange));
  }

  if (btnSelect) {
    const onClick = (): void => fileInput?.click();
    btnSelect.addEventListener('click', onClick);
    cleanups.push(() => btnSelect.removeEventListener('click', onClick));
  }

  if (dropzone) {
    const highlight = (event: DragEvent, on: boolean): void => {
      event.preventDefault();
      event.stopPropagation();
      dropzone.classList.toggle('dragover', on);
      dropzone.classList.toggle('border-indigo-600', on);
      dropzone.classList.toggle('bg-indigo-50/50', on);
    };
    const onEnter = (event: DragEvent): void => highlight(event, true);
    const onLeave = (event: DragEvent): void => highlight(event, false);
    const onDrop = (event: DragEvent): void => {
      highlight(event, false);
      if (event.dataTransfer?.files.length) addFiles(event.dataTransfer.files);
    };
    for (const name of ['dragenter', 'dragover'] as const) {
      dropzone.addEventListener(name, onEnter);
      cleanups.push(() => dropzone.removeEventListener(name, onEnter));
    }
    for (const name of ['dragleave'] as const) {
      dropzone.addEventListener(name, onLeave);
      cleanups.push(() => dropzone.removeEventListener(name, onLeave));
    }
    dropzone.addEventListener('drop', onDrop);
    cleanups.push(() => dropzone.removeEventListener('drop', onDrop));
  }

  if (btnStart) {
    const onClick = (): void => {
      void queue.start(destinationId(selectId)).then((result) => {
        if (!result?.uploaded.length) return;
        closeModal('modal-upload');
        options.onClose?.();
      });
    };
    btnStart.addEventListener('click', onClick);
    cleanups.push(() => btnStart.removeEventListener('click', onClick));
  }

  if (btnClear) {
    const onClick = (): void => queue.clear();
    btnClear.addEventListener('click', onClick);
    cleanups.push(() => btnClear.removeEventListener('click', onClick));
  }

  const onListClick = (event: MouseEvent): void => {
    const button = (event.target as HTMLElement).closest<HTMLElement>('.btn-remove-upload');
    if (!button) return;
    const localId = button.dataset.localId ?? '';
    if (localId) queue.remove(localId);
  };
  listEl.addEventListener('click', onListClick);
  cleanups.push(() => listEl.removeEventListener('click', onListClick));

  // The legacy markup calls this global from generated rows; keep the name, point it here.
  const win = window as unknown as { removeFromQueue?: (localId: string) => void };
  const previousRemove = win.removeFromQueue;
  win.removeFromQueue = (localId: string): void => queue.remove(localId);
  cleanups.push(() => {
    win.removeFromQueue = previousRemove;
  });

  render();
  void queue.limits.reload();

  return () => {
    for (const cleanup of cleanups) cleanup();
  };
}
