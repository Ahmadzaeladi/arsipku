/**
 * ArsipKu - Folder models.
 * Mirrors the `folders` table and FolderService (hierarchy, breadcrumbs, counts).
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';

export type FolderColor = 'indigo' | 'emerald' | 'blue' | 'amber' | 'purple';

export interface Folder {
  id: string;
  workspace_id: string;
  /** null = top-level folder under Arsip Utama. */
  parent_id: string | null;
  name: string;
  color: FolderColor;
  icon: string;
  /** Non-trashed documents directly inside this folder. */
  doc_count: number;
  /** Direct subfolders. Present only on list responses. */
  subfolder_count?: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  capabilities?: FolderCapabilities;
}

export interface FolderCapabilities {
  can_view: boolean;
  can_rename: boolean;
  can_delete: boolean;
  can_upload_into: boolean;
  can_create_child: boolean;
}

/** Row shape before normalisation (api/folders.php returns raw PDO values). */
export type FolderRow = Record<string, unknown>;

/** Slim entry used by the "move to folder" picker. */
export interface FolderFlat {
  id: string;
  name: string;
  parent_id: string | null;
}

/** FolderService::getBreadcrumbs() item. Root is prepended by the UI. */
export interface Breadcrumb {
  id: string;
  name: string;
  parent_id: string | null;
}

/** Client-side navigation tree. Built from FolderFlat, so no counts. */
export interface FolderTreeNode extends FolderFlat {
  children: FolderTreeNode[];
  depth: number;
}

export interface FolderListParams extends WorkspaceScopedParams, PaginationParams {
  /** Omit or 'root' for top level. */
  parent_id?: string | null;
  /** Include soft-deleted folders (Trash view). */
  with_trashed?: boolean;
}

export interface CreateFolderRequest extends WorkspaceScopedParams {
  name: string;
  parent_id?: string | null;
  color?: FolderColor;
}

export interface RenameFolderRequest extends WorkspaceScopedParams {
  id: string;
  name: string;
}

export interface MoveFolderRequest extends WorkspaceScopedParams {
  id: string;
  parent_id: string | null;
}

export interface DeleteFolderRequest extends WorkspaceScopedParams {
  id: string;
  /** When true the server also trashes the folders and documents inside it. */
  cascade?: boolean;
}
