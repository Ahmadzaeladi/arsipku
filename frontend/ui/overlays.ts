/**
 * ArsipKu - toast + modal adapters.
 *
 * These are the two globals the server-rendered pages already call from inline
 * handlers (onclick="showToast(...)"), so installing them is what lets the TS layer
 * take over without editing every view: the events bus is the source of truth, the
 * window functions are a thin facade for the legacy markup.
 */

import type { ToastType } from '../lib/events';
import { escapeHtml } from '../lib/format';
import { icon } from './icons';
import type { NotificationsHook } from '../hooks/notifications';

const TONE: Record<ToastType, string> = {
  success: 'bg-white border-emerald-200 text-slate-800',
  error: 'bg-white border-rose-200 text-slate-800',
  warning: 'bg-white border-amber-200 text-slate-800',
  info: 'bg-white border-indigo-200 text-slate-800',
};

const ICON: Record<ToastType, { name: string; chip: string }> = {
  success: { name: 'check', chip: 'bg-emerald-100 text-emerald-600' },
  error: { name: 'x', chip: 'bg-rose-100 text-rose-600' },
  warning: { name: 'alert', chip: 'bg-amber-100 text-amber-600' },
  info: { name: 'info', chip: 'bg-indigo-100 text-indigo-600' },
};

export function mountToasts(notifications: NotificationsHook, containerId = 'toast-container'): () => void {
  const container = document.getElementById(containerId);
  if (!container) return () => {};

  const render = (): void => {
    container.innerHTML = notifications.toasts.get()
      .map((toast) => toastMarkup(toast.message, toast.type, toast.id))
      .join('');
  };

  const unsubscribe = notifications.toasts.subscribe(render);
  render();

  container.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest('[data-toast-dismiss]');
    if (target) notifications.dismiss(Number(target.getAttribute('data-toast-dismiss')));
  });

  return () => {
    unsubscribe();
    container.innerHTML = '';
  };
}

function toastMarkup(message: string, type: ToastType, id: number): string {
  const tone = TONE[type];
  const mark = ICON[type];
  return `
    <div class="toast-item flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium transition-all ${tone}" data-toast-id="${id}">
      <span class="w-6 h-6 rounded-full ${mark.chip} flex items-center justify-center shrink-0">${icon(mark.name, 'w-4 h-4')}</span>
      <span class="flex-1">${escapeHtml(message)}</span>
      <button type="button" class="text-slate-400 hover:text-slate-600 ml-2" data-toast-dismiss="${id}">${icon('x', 'w-4 h-4')}</button>
    </div>`;
}

/**
 * Open/close a `.modal-backdrop` by id. Exported so the adapters below call it
 * directly; `installModalFacade()` only republishes it for the legacy inline handlers.
 */
export function openModal(modalId: string): void {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  modal.classList.add('active');
  document.body.classList.add('overflow-hidden');
  const firstInput = modal.querySelector<HTMLInputElement>('input:not([type=hidden]), textarea');
  if (firstInput) setTimeout(() => firstInput.focus(), 100);
}

export function closeModal(modalId: string): void {
  document.getElementById(modalId)?.classList.remove('active');
  if (!document.querySelector('.modal-backdrop.active')) document.body.classList.remove('overflow-hidden');
}

/** Same contract as the legacy window.openModal/closeModal pair. */
export function installModalFacade(): void {
  const win = window as unknown as { openModal?: (id: string) => void; closeModal?: (id: string) => void };
  win.openModal = openModal;
  win.closeModal = closeModal;
}

/**
 * window.showToast is what inline handlers and the legacy scripts call. Delegating it
 * to the notifications hook means one queue, one style, and one place to change copy.
 */
export function installToastFacade(notifications: NotificationsHook): void {
  const win = window as unknown as { showToast?: (message: string, type?: string, duration?: number) => void };
  win.showToast = (message: string, type = 'success', duration?: number) => {
    notifications.push(message, (type in TONE ? type : 'info') as ToastType, duration);
  };
}
