/**
 * ArsipKu - reactive primitives for the hooks layer.
 *
 * No framework: a store is a value plus a listener set, and a resource is an async
 * store that knows when to refetch. The UI adapters subscribe to these, and the
 * hooks below compose them around the service interfaces.
 */

import type { ApiClientError } from '../lib/errors';
import { toApiError } from '../lib/errors';
import { on } from '../lib/events';

export type Listener<T> = (value: T) => void;

export interface Store<T> {
  get(): T;
  set(next: T): void;
  update(fn: (current: T) => T): void;
  subscribe(listener: Listener<T>): () => void;
}

export function createStore<T>(initial: T): Store<T> {
  let value = initial;
  const listeners = new Set<Listener<T>>();
  return {
    get: () => value,
    set(next: T) {
      if (next === value) return;
      value = next;
      for (const listener of listeners) listener(value);
    },
    update(fn) {
      // Deliberately not identity-short-circuited: object states are rebuilt each set.
      value = fn(value);
      for (const listener of listeners) listener(value);
    },
    subscribe(listener) {
      listeners.add(listener);
      return () => { listeners.delete(listener); };
    },
  };
}

export interface AsyncState<T> {
  data: T | null;
  error: ApiClientError | null;
  loading: boolean;
  /** False until the first attempt finishes, success or not. */
  loaded: boolean;
}

export interface ResourceOptions {
  /**
   * Cache keys from `data:invalidated`. The resource reloads when an event names
   * one of them, so a star toggle refreshes the list without the caller wiring it.
   */
  keys?: string[];
  /** Follow the active workspace: reload on every workspace switch. Default true. */
  scoped?: boolean;
  /** Kick off the first load at creation. Default true. */
  immediate?: boolean;
}

export interface Resource<T> {
  get(): AsyncState<T>;
  subscribe(listener: Listener<AsyncState<T>>): () => void;
  /** Always refetches; concurrent calls share one in-flight request. */
  reload(): Promise<T | null>;
  /** Reload only when nothing has been loaded yet - the view-enter cheap path. */
  ensure(): Promise<T | null>;
  /** Swap the loader (filters changed) without losing the current value. */
  refocus(loader: () => Promise<T>): Promise<T | null>;
  destroy(): void;
}

export function createResource<T>(
  initialLoad: () => Promise<T>,
  options: ResourceOptions = {},
): Resource<T> {
  const state = createStore<AsyncState<T>>({ data: null, error: null, loading: false, loaded: false });
  let load = initialLoad;
  let attempt = 0;
  let inflight: Promise<T | null> | null = null;
  let destroyed = false;

  async function run(): Promise<T | null> {
    if (destroyed) return null;
    const current = ++attempt;
    state.update((previous) => ({ ...previous, loading: true, error: null }));
    try {
      const data = await load();
      // A newer reload already started: this answer is stale, drop it.
      if (destroyed || current !== attempt) return data;
      state.set({ data, error: null, loading: false, loaded: true });
      return data;
    } catch (err) {
      const error = toApiError(err);
      if (!destroyed && current === attempt) {
        state.set({ data: state.get().data, error, loading: false, loaded: true });
      }
      return null;
    }
  }

  function reload(): Promise<T | null> {
    if (inflight) return inflight;
    inflight = run().finally(() => { inflight = null; });
    return inflight;
  }

  const unsubscribers: (() => void)[] = [
    on('data:invalidated', ({ keys }) => {
      if (!options.keys?.some((watched) => keys.some((emitted) => keyMatches(watched, emitted)))) return;
      void reload();
    }),
  ];
  if (options.scoped !== false) {
    unsubscribers.push(on('workspace:switched', () => { void reload(); }));
  }

  const resource: Resource<T> = {
    get: state.get,
    subscribe: state.subscribe,
    reload,
    ensure: () => (state.get().loaded ? Promise.resolve(state.get().data) : reload()),
    refocus(nextLoad) {
      load = nextLoad;
      return reload();
    },
    destroy() {
      destroyed = true;
      for (const unsubscribe of unsubscribers) unsubscribe();
    },
  };

  if (options.immediate !== false) void reload();
  return resource;
}

/** `versions` also answers to `versions:doc_1`, and vice versa when one side is prefixed. */
function keyMatches(watched: string, emitted: string): boolean {
  return watched === emitted
    || emitted.startsWith(`${watched}:`)
    || watched.startsWith(`${emitted}:`);
}

export function debounce<A extends unknown[]>(fn: (...args: A) => void, ms: number): ((...args: A) => void) & { cancel(): void } {
  let timer: ReturnType<typeof setTimeout> | null = null;
  const wrapped = (...args: A): void => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => { timer = null; fn(...args); }, ms);
  };
  wrapped.cancel = () => {
    if (timer) clearTimeout(timer);
    timer = null;
  };
  return wrapped;
}
