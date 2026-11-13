/**
 * ArsipKu - workspace hook.
 *
 * The active workspace is the app's scope: every other hook follows it. Switching
 * here only changes the `?ws=` the client sends - the server still decides whether
 * the caller belongs to that workspace, so a forged slug fails as 403/404 upstream.
 */

import type { StorageUsage } from '../types/subscription';
import type { Workspace, WorkspaceStats } from '../types/workspace';
import { getWorkspaceContext, getWorkspaceSlug, subscribeWorkspace } from '../lib/workspaceContext';
import { createResource, createStore, type AsyncState, type Resource, type Store } from './reactive';
import { usageFromStats } from '../services/subscription';
import type { WorkspaceCurrent, WorkspaceService } from '../services/workspaces';
import type { CreateWorkspaceRequest, DeleteWorkspaceRequest, SwitchWorkspaceRequest, UpdateWorkspaceRequest } from '../types/workspace';

export interface WorkspaceHook {
  /** `undefined` until workspaces.php answers; drives the boot skeleton. */
  active: Store<Workspace | undefined>;
  role: Store<string | null>;
  current: Resource<WorkspaceCurrent>;
  list: Resource<Workspace[]>;
  usage: Store<StorageUsage>;
  switchTo(request: SwitchWorkspaceRequest): Promise<Workspace | null>;
  create(request: CreateWorkspaceRequest): Promise<Workspace | null>;
  update(request: UpdateWorkspaceRequest): Promise<boolean>;
  remove(request: DeleteWorkspaceRequest): Promise<boolean>;
  destroy(): void;
}

export function useWorkspace(workspaces: WorkspaceService): WorkspaceHook {
  const active = createStore<Workspace | undefined>(undefined);
  const role = createStore<string | null>(getWorkspaceContext().role);
  const usage = createStore<StorageUsage>(usageFromStats(emptyStats()));

  // These two must not follow workspace:switched: switching is what they report on.
  const current = createResource(() => workspaces.current(), {
    keys: ['workspace-stats', 'workspaces'],
    scoped: false,
  });
  const list = createResource(() => workspaces.list(), { keys: ['workspaces'], scoped: false });

  let seenSlug = getWorkspaceSlug();

  function publish(state: AsyncState<WorkspaceCurrent>): void {
    const value = state.data;
    if (!value?.workspace?.id) return;
    seenSlug = value.workspace.slug;
    active.set(value.workspace);
    role.set(value.workspace.user_role ?? getWorkspaceContext().role);
    usage.set(usageFromStats(value.stats));
  }

  // current() itself re-applies the context, so compare slugs before refetching or
  // this listener would loop. It exists for switches made outside the hook - the
  // legacy sidebar, a deep link, or another tab.
  const unsubscribeContext = subscribeWorkspace((context) => {
    if (context.slug === seenSlug) return;
    seenSlug = context.slug;
    if (current.get().loaded) void current.reload();
  });

  const unsubscribeCurrent = current.subscribe(publish);

  return {
    active,
    role,
    current,
    list,
    usage,

    async switchTo(request) {
      try {
        const result = await workspaces.switchTo(request);
        active.set(result.workspace);
        role.set(result.workspace.user_role ?? null);
        void list.reload();
        void current.reload();
        return result.workspace;
      } catch {
        return null;
      }
    },

    async create(request) {
      try {
        const created = await workspaces.create(request);
        void list.reload();
        void current.reload();
        return created;
      } catch {
        return null;
      }
    },

    async update(request) {
      try {
        await workspaces.update(request);
        void current.reload();
        void list.reload();
        return true;
      } catch {
        return false;
      }
    },

    async remove(request) {
      try {
        await workspaces.remove(request);
        void list.reload();
        void current.reload();
        return true;
      } catch {
        return false;
      }
    },

    destroy() {
      unsubscribeContext();
      unsubscribeCurrent();
      current.destroy();
      list.destroy();
    },
  };
}

/** Placeholder so the meter renders a bar, not NaN, before the first stats answer. */
function emptyStats(): WorkspaceStats {
  return {
    total_documents: 0,
    total_folders: 0,
    total_size_bytes: 0,
    storage_limit_bytes: 0,
    usage_percentage: 0,
    is_warning: false,
    plan_name: '',
  };
}
