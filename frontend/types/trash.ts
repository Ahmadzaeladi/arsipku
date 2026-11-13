/**
 * ArsipKu - Trash and restore models.
 * Soft delete uses `deleted_at`. The contract adds who deleted an item, where it
 * came from (for restore-in-place), and the auto-purge date.
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';
import type { Document, DocumentSort } from './document';
import type { Folder } from './folder';

export type TrashedKind = 'document' | 'folder';

/** A trashed document plus the recovery metadata the UI needs. */
export interface TrashItem {
  kind: TrashedKind;
  id: string;
  name: string;
  deleted_at: string;
  deleted_by?: string | null;
  deleted_by_name?: string | null;
  /** Original folder; null means Arsip Utama. Restore returns it here when it still exists. */
  original_folder_id?: string | null;
  original_folder_name?: string | null;
  /** False when the original folder was itself deleted. */
  restore_target_available?: boolean;
  size_bytes?: number;
  extension?: string;
  /** Null when the backend has no retention policy yet. */
  purge_after?: string | null;
  document?: Document;
  folder?: Folder;
}

export interface TrashListParams extends WorkspaceScopedParams, PaginationParams {
  kind?: TrashedKind | 'all';
  q?: string;
  sort?: DocumentSort;
}

export interface RestoreRequest extends WorkspaceScopedParams {
  kind?: TrashedKind;
  id: string;
  /** Override the original folder on restore. 'root' clears it. */
  folder_id?: string | null;
}

export interface PurgeRequest extends WorkspaceScopedParams {
  kind?: TrashedKind;
  id: string;
}

export interface EmptyTrashRequest extends WorkspaceScopedParams {
  kind?: TrashedKind | 'all';
  /** Purge only items older than N days. Omitted = everything in trash. */
  older_than_days?: number;
  /** Mirrors the workspace-name confirmation the PHP endpoint expects. */
  confirm_name?: string;
}

export interface TrashOperationResult {
  requested_count: number;
  affected_count: number;
  failed_ids: string[];
  freed_bytes?: number;
}

export interface TrashStats {
  document_count: number;
  folder_count: number;
  total_size_bytes: number;
  retention_days: number | null;
  oldest_deleted_at: string | null;
}

/** Sampah keeps deleted_at; UI derives "sisa X hari" from purge_after. */
export const TRASH_RETENTION_DAYS = 30;
