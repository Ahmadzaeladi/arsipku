/**
 * ArsipKu - active workspace context.
 *
 * One place decides which workspace slug travels with every request. Services read
 * it through `requireWorkspaceSlug()`; the HTTP client injects it automatically, so
 * no call site can silently forget it.
 *
 * This is transport context, not an authorization mechanism. `role` is display
 * metadata copied from the server response; every endpoint re-checks access with its
 * own `WHERE workspace_id = ?` and membership query.
 */

import type { Workspace, WorkspaceContext } from '../types/workspace';
import { getConfig } from './config';

const LS_ACTIVE_WORKSPACE = 'arsipku_active_workspace';

type Listener = (context: WorkspaceContext) => void;

const listeners = new Set<Listener>();

let current: WorkspaceContext = {
  slug: '',
  workspaceId: null,
  type: null,
  role: null,
  loadedAt: 0,
};

current.slug = initialSlug();

function initialSlug(): string {
  const fromConfig = getConfig().workspaceSlug;
  if (fromConfig) return fromConfig;
  try {
    return globalThis.localStorage?.getItem(LS_ACTIVE_WORKSPACE) ?? '';
  } catch {
    return '';
  }
}

export function getWorkspaceContext(): WorkspaceContext {
  return { ...current };
}

/** Slug to send. Empty string means the server falls back to its session default. */
export function getWorkspaceSlug(): string {
  return current.slug;
}

/** Same as getWorkspaceSlug but signals "this call is invalid without one". */
export function requireWorkspaceSlug(): string {
  if (!current.slug) {
    throw new WorkspaceContextError('Konteks workspace aktif belum tersedia.');
  }
  return current.slug;
}

export class WorkspaceContextError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'WorkspaceContextError';
  }
}

export function setWorkspaceContext(context: Partial<WorkspaceContext>): WorkspaceContext {
  const next: WorkspaceContext = { ...current, ...context, loadedAt: Date.now() };
  current = next;
  if (next.slug) {
    try {
      globalThis.localStorage?.setItem(LS_ACTIVE_WORKSPACE, next.slug);
    } catch {
      /* ignore private-mode storage failures */
    }
  }
  notify();
  return getWorkspaceContext();
}

/** Apply a server-returned workspace: pins slug + id + role in one step. */
export function applyWorkspace(workspace: Workspace): WorkspaceContext {
  return setWorkspaceContext({
    slug: workspace.slug,
    workspaceId: workspace.id,
    type: workspace.type,
    role: workspace.user_role ?? current.role,
  });
}

export function clearWorkspaceContext(): void {
  current = { slug: '', workspaceId: null, type: null, role: null, loadedAt: 0 };
  try {
    globalThis.localStorage?.removeItem(LS_ACTIVE_WORKSPACE);
  } catch {
    /* ignore */
  }
  notify();
}

export function subscribeWorkspace(listener: Listener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function notify(): void {
  const snapshot = getWorkspaceContext();
  for (const listener of listeners) listener(snapshot);
}

/**
 * Keep `?ws=` in the address bar so a refresh, a shared URL, or a server-rendered
 * page lands on the same workspace. Uses replaceState: no history spam, no reload.
 */
export function mirrorSlugInUrl(slug: string): void {
  if (typeof window === 'undefined' || !slug) return;
  try {
    const url = new URL(window.location.href);
    if (url.searchParams.get('ws') === slug) return;
    url.searchParams.set('ws', slug);
    window.history.replaceState(window.history.state, '', url.toString());
  } catch {
    /* non-browser host (tests): nothing to mirror */
  }
}

export const ACTIVE_WORKSPACE_STORAGE_KEY = LS_ACTIVE_WORKSPACE;
