/**
 * ArsipKu - Workspace member models.
 * Mirrors the `members` table and MemberService (role ordering, invite flow).
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';
import type { MemberRoleName } from './auth';

export type { MemberRoleName };

/** Roles a caller may assign. `Owner` is never settable through the API. */
export type AssignableRole = Exclude<MemberRoleName, 'Owner'>;

export type MemberStatus = 'active' | 'invited' | 'suspended';

export interface Member {
  id: string;
  workspace_id: string;
  user_id?: string | null;
  name: string;
  email: string;
  avatar_url: string;
  role: MemberRoleName;
  status: MemberStatus;
  joined_at: string;
  invited_at?: string | null;
  last_active_at?: string | null;
  capabilities?: MemberCapabilities;
}

export interface MemberCapabilities {
  can_change_role: boolean;
  can_remove: boolean;
  /** False for the Owner row; the server enforces the same rule. */
  is_owner: boolean;
}

export type MemberRow = Record<string, unknown>;

export interface MemberListParams extends WorkspaceScopedParams, PaginationParams {
  q?: string;
  role?: MemberRoleName;
  status?: MemberStatus;
}

export interface InviteMemberRequest extends WorkspaceScopedParams {
  email: string;
  role: AssignableRole;
  message?: string;
}

export interface UpdateMemberRoleRequest extends WorkspaceScopedParams {
  id: string;
  role: AssignableRole;
}

export interface RemoveMemberRequest extends WorkspaceScopedParams {
  id: string;
}

/** Result of creating an email invitation; access is granted only after acceptance. */
export interface InviteMemberResult {
  invitation: MemberInvitation;
}

export interface MemberInvitation {
  id: string;
  workspace_id: string;
  invitee_user_id: string;
  name: string;
  email: string;
  role: AssignableRole;
  source: 'email' | 'link';
  status: 'pending' | 'accepted' | 'declined' | 'rejected' | 'cancelled';
  created_at?: string;
}
