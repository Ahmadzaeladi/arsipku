/**
 * ArsipKu - workspace service.
 *
 * Multi-tenancy rule: the client sends the workspace slug, the server decides
 * whether the caller belongs to it. A forged `?ws=` must come back as 403/404, never
 * as another tenant's rows - so switching here is a UX affordance, not a privilege.
 *
 * Target endpoint: api/workspaces.php (live, action-style).
 */

import type { VoidResult } from '../types/api';
import type {
  CreateWorkspaceRequest,
  DeleteWorkspaceRequest,
  SwitchWorkspaceRequest,
  SwitchWorkspaceResult,
  UpdateWorkspaceRequest,
  Workspace,
  WorkspaceContext,
  WorkspaceStats,
} from '../types/workspace';
import { emit, reportAsyncError } from '../lib/events';
import * as http from '../lib/http';
import {
  asStr,
  normalizeArray,
  normalizeStats,
  normalizeWorkspace,
} from '../lib/normalize';
import {
  applyWorkspace,
  getWorkspaceSlug,
  mirrorSlugInUrl,
  setWorkspaceContext,
} from '../lib/workspaceContext';

export interface WorkspaceCurrent {
  workspace: Workspace;
  stats: WorkspaceStats;
}

export interface WorkspaceService {
  /** Workspaces the caller can enter - filtered by the server. */
  list(): Promise<Workspace[]>;
  /** Resolves (and pins) the active workspace for this session. */
  current(): Promise<WorkspaceCurrent>;
  stats(): Promise<WorkspaceStats>;
  switchTo(request: SwitchWorkspaceRequest): Promise<SwitchWorkspaceResult>;
  create(request: CreateWorkspaceRequest): Promise<Workspace>;
  update(request: UpdateWorkspaceRequest): Promise<VoidResult>;
  remove(request: DeleteWorkspaceRequest): Promise<VoidResult>;
}

export function createHttpWorkspaceService(): WorkspaceService {
  return {
    async list(): Promise<Workspace[]> {
      const rows = await http.get<unknown>('workspaces', 'list');
      return normalizeArray(rows, normalizeWorkspace);
    },

    async current(): Promise<WorkspaceCurrent> {
      const { body } = await http.call('workspaces', { action: 'current', method: 'GET' });
      const workspace = normalizeWorkspace(readObject(body.workspace ?? body.data));
      const stats = normalizeStats(readObject(body.stats));
      activate(workspace);
      return { workspace, stats };
    },

    async stats(): Promise<WorkspaceStats> {
      const { body } = await http.call('workspaces', { action: 'current', method: 'GET' });
      return normalizeStats(readObject(body.stats ?? body.data));
    },

    async switchTo(request): Promise<SwitchWorkspaceResult> {
      const { body } = await http.call('workspaces', {
        action: 'switch',
        method: 'POST',
        body: { slug: request.slug },
      });
      const workspace = normalizeWorkspace(readObject(body.workspace ?? body.data));
      const pinned = activate(workspace);
      return { workspace, context: pinned };
    },

    async create(request): Promise<Workspace> {
      const { body } = await http.call('workspaces', {
        action: 'create',
        method: 'POST',
        body: { name: request.name.trim(), type: request.type },
      });
      // The PHP endpoint auto-switches the session to the new workspace.
      const workspace = normalizeWorkspace(readObject(body.workspace ?? body.data));
      if (workspace.slug) activate(workspace);
      return workspace;
    },

    async update(request): Promise<VoidResult> {
      const current = await this.current();
      const { body } = await http.call('workspaces', {
        action: 'update',
        method: 'POST',
        body: {
          name: request.name ?? current.workspace.name,
          type: request.type ?? current.workspace.type,
        },
      });
      emit('data:invalidated', { reason: 'workspace-updated', keys: ['workspaces', 'workspace-current'] });
      return { message: asStr(body.message, 'Pengaturan workspace berhasil disimpan.') };
    },

    async remove(request): Promise<VoidResult> {
      const { body } = await http.call('workspaces', {
        action: 'delete',
        method: 'POST',
        body: { confirm_name: request.confirm_name },
      });

      // Re-pin the context to a workspace the server says still exists.
      let survivors: Workspace[] = [];
      try {
        survivors = normalizeArray(await http.get<unknown>('workspaces', 'list'), normalizeWorkspace);
      } catch (err) {
        reportAsyncError(err, 'workspace delete follow-up list');
      }

      const next = survivors[0];
      if (next) activate(next);
      else setWorkspaceContext({ slug: '', workspaceId: null, type: null, role: null });

      emit('data:invalidated', { reason: 'workspace-deleted', keys: ['workspaces', 'documents', 'folders'] });
      return { message: asStr(body.message, 'Workspace berhasil dihapus.') };
    },
  };
}

/**
 * Pin the server-resolved workspace as the active context and tell the app.
 * Returns the context every scoped request will now carry.
 */
function activate(workspace: Workspace): WorkspaceContext {
  const previous = getWorkspaceSlug() || null;
  const context = applyWorkspace(workspace);
  if (previous !== workspace.slug) {
    mirrorSlugInUrl(workspace.slug);
    emit('workspace:switched', { slug: workspace.slug, previous });
  }
  return context;
}

export { activate };

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
