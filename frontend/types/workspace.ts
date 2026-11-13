/**
 * ArsipKu - Workspace models and the active-workspace context object.
 * Mirrors the `workspaces` table in src/Database.php and
 * WorkspaceService::getStats() in src/Services/WorkspaceService.php.
 */

import type { MemberRoleName } from './auth';
import type { PlanKey } from './subscription';

export type WorkspaceType = 'personal' | 'company' | 'school';

/** Human-readable plan names used by the current PHP seed. */
export type LegacyPlanName = 'Personal Pro' | 'Enterprise Business' | 'Edu & Institution' | 'Edu Institution' | 'Pro Plan';

export interface Workspace {
  /** MySQL VARCHAR primary key, e.g. `ws_abc`. */
  id: string;
  name: string;
  /** URL/session discriminator, sent as `?ws=` on every scoped request. */
  slug: string;
  type: WorkspaceType;
  storage_limit_bytes: number;
  plan_name: string;
  plan_key?: PlanKey;
  /** Server-resolved role of the current user in this workspace. */
  user_role?: MemberRoleName;
  member_count?: number;
  created_at: string;
  updated_at?: string;
}

export interface CreateWorkspaceRequest {
  name: string;
  type: WorkspaceType;
}

export interface UpdateWorkspaceRequest {
  name?: string;
  type?: WorkspaceType;
}

export interface DeleteWorkspaceRequest {
  /** Server compares against the workspace name; mismatch => VALIDATION_FAILED. */
  confirm_name: string;
}

export interface SwitchWorkspaceRequest {
  slug: string;
}

export interface SwitchWorkspaceResult {
  workspace: Workspace;
  /** Echo of the context the client should pin for subsequent requests. */
  context: WorkspaceContext;
}

/**
 * Resolved workspace context for the running app. The slug is transport;
 * the id is what services key their caches on. Authorization never reads
 * `role` as a permission decision - it only decorates the UI.
 */
export interface WorkspaceContext {
  slug: string;
  workspaceId: string | null;
  type: WorkspaceType | null;
  role: MemberRoleName | null;
  loadedAt: number;
}

/** GET /api/workspaces.php?action=current -> stats block. */
export interface WorkspaceStats {
  total_documents: number;
  total_folders: number;
  total_size_bytes: number;
  storage_limit_bytes: number;
  usage_percentage: number;
  is_warning: boolean;
  plan_name: string;
}

/** Server-provided presentation strings (legacy PHP enriches these). */
export interface WorkspaceStatsPresented extends WorkspaceStats {
  formatted_size: string;
  formatted_limit: string;
}
