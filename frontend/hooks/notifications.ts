/**
 * ArsipKu - notification hook.
 *
 * Services and the HTTP client never touch the DOM: they publish `toast`, `api:error`
 * and `auth:expired` on the event bus, and this is the single place that turns those
 * into UI state. Auto-dismiss timings live here so the copy, not the caller, decides.
 */

import type { ToastType } from '../lib/events';
import { on } from '../lib/events';
import { createStore, type Store } from './reactive';

export interface Toast {
  id: number;
  message: string;
  type: ToastType;
  durationMs: number;
}

export interface NotificationsHook {
  toasts: Store<Toast[]>;
  /** Session is gone: the UI opens one dialog, never a toast pile. */
  expired: Store<boolean>;
  push(message: string, type?: ToastType, durationMs?: number): Toast;
  dismiss(id: number): void;
  acknowledgeExpired(): void;
  destroy(): void;
}

const DEFAULT_TTL: Record<ToastType, number> = {
  success: 3500,
  info: 3500,
  warning: 5000,
  error: 7000,
};

export function useNotifications(): NotificationsHook {
  const toasts = createStore<Toast[]>([]);
  const expired = createStore(false);
  const timers = new Map<number, ReturnType<typeof setTimeout>>();
  let sequence = 0;

  function dismiss(id: number): void {
    const timer = timers.get(id);
    if (timer) {
      clearTimeout(timer);
      timers.delete(id);
    }
    toasts.update((current) => current.filter((toast) => toast.id !== id));
  }

  function push(message: string, type: ToastType = 'info', durationMs?: number): Toast {
    const toast: Toast = { id: ++sequence, message, type, durationMs: durationMs ?? DEFAULT_TTL[type] };
    toasts.update((current) => [...current.slice(-4), toast]);
    if (toast.durationMs > 0) timers.set(toast.id, setTimeout(() => dismiss(toast.id), toast.durationMs));
    return toast;
  }

  const unsubscribers = [
    on('toast', ({ message, type, durationMs }) => { push(message, type, durationMs); }),
    on('api:error', ({ error }) => { push(error.message, 'error'); }),
    on('auth:expired', () => { expired.set(true); }),
  ];

  return {
    toasts,
    expired,
    push,
    dismiss,
    acknowledgeExpired: () => expired.set(false),
    destroy() {
      for (const timer of timers.values()) clearTimeout(timer);
      timers.clear();
      for (const unsubscribe of unsubscribers) unsubscribe();
    },
  };
}
