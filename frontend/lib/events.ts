/**
 * ArsipKu - cross-layer event bus.
 * Services and the HTTP client never touch the DOM; they publish here and the UI
 * layer subscribes (toasts, auth-expired dialog, upload completion refresh).
 */

import type { ApiClientError } from './errors';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ArsipKuEvents {
  'auth:expired': { endpoint?: string };
  'workspace:switched': { slug: string; previous: string | null };
  'toast': { message: string; type: ToastType; durationMs?: number };
  'api:error': { error: ApiClientError };
  'upload:finished': { uploadedCount: number; failedCount: number };
  'data:invalidated': { reason: string; keys: string[] };
}

type Handler<P> = (payload: P) => void;

const handlers = new Map<keyof ArsipKuEvents, Set<Handler<never>>>();

export function on<K extends keyof ArsipKuEvents>(
  event: K,
  handler: Handler<ArsipKuEvents[K]>,
): () => void {
  const set = handlers.get(event) ?? new Set();
  set.add(handler as Handler<never>);
  handlers.set(event, set);
  return () => off(event, handler);
}

export function off<K extends keyof ArsipKuEvents>(
  event: K,
  handler: Handler<ArsipKuEvents[K]>,
): void {
  handlers.get(event)?.delete(handler as Handler<never>);
}

export function emit<K extends keyof ArsipKuEvents>(event: K, payload: ArsipKuEvents[K]): void {
  const set = handlers.get(event);
  if (!set) return;
  for (const handler of set) {
    try {
      (handler as Handler<ArsipKuEvents[K]>)(payload);
    } catch (err) {
      reportAsyncError(err, `listener for ${event}`);
    }
  }
}

export function clearAllHandlers(): void {
  handlers.clear();
}

/** console.error with a tag; swap for a reporter when one exists. */
export function reportAsyncError(err: unknown, context: string): void {
  // Never let a listener failure break the emit loop or the caller's promise chain.
  console.error(`[ArsipKu] unhandled error in ${context}`, err);
}
