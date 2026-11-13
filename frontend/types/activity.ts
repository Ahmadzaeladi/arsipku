/**
 * ArsipKu - Activity feed models.
 * Mirrors the `activities` table written by DocumentService/FolderService::recordActivity().
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';

/** Values already produced by the PHP services. */
export type ActivityAction =
  | 'upload' | 'rename' | 'folder_create' | 'delete'
  | 'favorite' | 'share' | 'restore' | 'move'
  /** Required by the new contract domains. */
  | 'version_upload' | 'version_restore' | 'permission_change'
  | 'member_invite' | 'member_role_change' | 'member_remove'
  | 'plan_change' | 'workspace_create' | 'workspace_delete' | 'permanent_delete';

export interface Activity {
  id: string;
  workspace_id: string;
  user_id?: string | null;
  user_name: string;
  user_avatar_url: string;
  action_type: ActivityAction;
  description: string;
  created_at: string;
  /** Optional deep link target for the feed row. */
  resource_type?: 'document' | 'folder' | 'member' | 'workspace' | null;
  resource_id?: string | null;
}

export interface ActivityListParams extends WorkspaceScopedParams, PaginationParams {
  actions?: ActivityAction[];
  user_id?: string;
  /** ISO datetime, inclusive. */
  since?: string;
  limit?: number;
}
