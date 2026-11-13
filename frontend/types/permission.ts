/**
 * ArsipKu - Permissions models.
 * Today authorization lives in SQL `WHERE workspace_id = ?` clauses plus a flat
 * `is_shared`/`shared_permission` pair on the document row. These types describe
 * the effective-permission contract: the server computes them, the UI only reads them.
 */

import type { WorkspaceScopedParams } from './api';
import type { MemberRoleName } from './auth';
import type { SharedPermission } from './document';

export type ResourceType = 'workspace' | 'folder' | 'document';

export type PermissionAction =
  | 'view' | 'download' | 'rename' | 'move' | 'delete'
  | 'restore' | 'permanent_delete' | 'share' | 'upload'
  | 'create_folder' | 'manage_members' | 'manage_workspace'
  | 'change_plan' | 'override_lock';

/** Coarse capability map the UI consumes for enable/disable + hidden affordances. */
export type CapabilityMap = Partial<Record<PermissionAction, boolean>>;

/**
 * Why the caller holds the access they hold. `inherited` means it came from the
 * workspace role or an ancestor folder rather than an explicit grant.
 */
export interface EffectivePermissions {
  resource_type: ResourceType;
  resource_id: string;
  role: MemberRoleName | null;
  permission: SharedPermission | null;
  source: 'owner' | 'workspace_role' | 'inherited' | 'explicit' | 'link' | 'none';
  capabilities: CapabilityMap;
  /** Deny wins over allow and is always reported by the server. */
  denied_actions: PermissionAction[];
}

export type GranteeType = 'user' | 'member' | 'role' | 'public_link';

export interface AccessGrant {
  id: string;
  workspace_id: string;
  resource_type: ResourceType;
  resource_id: string;
  grantee_type: GranteeType;
  grantee_id: string | null;
  /** Display copy resolved server-side, e.g. "Siti Rahmawati" or "Anyone with the link". */
  grantee_label: string;
  permission: SharedPermission;
  granted_by: string;
  granted_by_name: string;
  granted_at: string;
  expires_at: string | null;
  inherited_from?: string | null;
}

export interface ListGrantsRequest extends WorkspaceScopedParams {
  resource_type: ResourceType;
  resource_id: string;
}

export interface GrantAccessRequest extends WorkspaceScopedParams {
  resource_type: ResourceType;
  resource_id: string;
  grantee_type: GranteeType;
  grantee_id?: string | null;
  permission: SharedPermission;
  expires_at?: string | null;
  /** Folder grants cascade to descendants unless the server says otherwise. */
  cascade?: boolean;
}

export interface RevokeAccessRequest extends WorkspaceScopedParams {
  grant_id: string;
}

export interface ShareLink {
  token: string;
  url: string;
  resource_type: ResourceType;
  resource_id: string;
  permission: SharedPermission;
  is_public: boolean;
  password_protected: boolean;
  expires_at: string | null;
  access_count: number;
  created_at: string;
}

export interface CreateShareLinkRequest extends WorkspaceScopedParams {
  resource_type: ResourceType;
  resource_id: string;
  permission?: SharedPermission;
  expires_in_days?: number | null;
  password?: string;
}

export interface UpdateShareLinkRequest extends WorkspaceScopedParams {
  token: string;
  permission?: SharedPermission;
  expires_at?: string | null;
  revoke?: boolean;
}

/** GET /api/permissions.php?action=matrix - role to capability reference data. */
export interface PermissionMatrix {
  roles: MemberRoleName[];
  actions: PermissionAction[];
  /** role -> action -> allowed */
  grants: Record<string, Partial<Record<PermissionAction, boolean>>>;
}
