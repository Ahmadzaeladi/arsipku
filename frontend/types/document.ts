/**
 * ArsipKu - Document models.
 * Mirrors the `documents` table (src/Database.php) and the rows returned by
 * DocumentService::getDocuments()/getById(). Raw DB values are normalised in
 * lib/normalize.ts, so services always hand the UI real booleans and numbers.
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';
import type { MemberRoleName } from './auth';
import type { FolderColor } from './folder';

/** Extension buckets accepted by api/upload.php (Config::ALLOWED_EXTENSIONS). */
export type DocumentExtension =
  | 'pdf' | 'doc' | 'docx'
  | 'xls' | 'xlsx' | 'csv'
  | 'ppt' | 'pptx'
  | 'png' | 'jpg' | 'jpeg' | 'webp' | 'svg'
  | 'txt' | 'zip';

/** UI-level file family, derived from extension by lib/format.ts. */
export type FileKind =
  | 'pdf' | 'word' | 'spreadsheet' | 'presentation'
  | 'image' | 'archive' | 'text' | 'other';

export type SharedPermission = 'view' | 'edit';

export interface Document {
  id: string;
  workspace_id: string;
  /** null = Arsip Utama (root). */
  folder_id: string | null;
  name: string;
  original_name: string;
  mime_type: string;
  extension: string;
  size_bytes: number;
  uploaded_by: string;
  owner_name: string;
  is_favorite: boolean;
  is_shared: boolean;
  shared_permission: SharedPermission;
  created_at: string;
  updated_at: string;
  /** Set when the row lives in Trash. */
  deleted_at: string | null;
  /** Session- and workspace-scoped URL served by api/download.php. */
  download_url?: string | null;
  preview_url?: string | null;
  recipient_permission?: SharedPermission | null;
  can_edit?: boolean;
  can_delete?: boolean;
  can_share?: boolean;
  folder_name?: string | null;
  folder_color?: FolderColor | null;
}

/** Everything the current PHP endpoints return before normalisation. */
export interface DocumentRow {
  id?: unknown;
  workspace_id?: unknown;
  folder_id?: unknown;
  name?: unknown;
  original_name?: unknown;
  mime_type?: unknown;
  extension?: unknown;
  size_bytes?: unknown;
  storage_path?: unknown;
  uploaded_by?: unknown;
  owner_name?: unknown;
  is_favorite?: unknown;
  is_shared?: unknown;
  shared_permission?: unknown;
  created_at?: unknown;
  updated_at?: unknown;
  deleted_at?: unknown;
  download_url?: unknown;
  preview_url?: unknown;
  recipient_permission?: unknown;
  shared_by_name?: unknown;
  can_edit?: unknown;
  can_delete?: unknown;
  can_share?: unknown;
  folder_name?: unknown;
  folder_color?: unknown;
  /** Legacy server-computed presentation fields. Ignored by the UI layer. */
  formatted_size?: unknown;
  formatted_date?: unknown;
  formatted_created?: unknown;
  time_ago?: unknown;
  meta?: unknown;
}

/** Sort tokens accepted by DocumentService today. `created_desc` is documented
 *  but currently falls through to `updated_desc` server-side. */
export type DocumentSort =
  | 'updated_desc' | 'updated_asc'
  | 'name_asc' | 'name_desc'
  | 'size_asc' | 'size_desc'
  | 'created_desc';

/** Type buckets from the DocumentService switch statement. */
export type DocumentTypeFilter =
  | 'all' | 'pdf' | 'word' | 'spreadsheet' | 'excel'
  | 'image' | 'archive' | 'zip' | 'txt';

export type DateRangeFilter = 'all' | 'today' | '7days' | '30days' | 'this_year';

export interface DocumentFilters extends WorkspaceScopedParams, PaginationParams {
  q?: string;
  type?: DocumentTypeFilter;
  date_range?: DateRangeFilter;
  /** 'root' selects documents without a folder. */
  folder_id?: string | null;
  sort?: DocumentSort;
  favorite?: boolean;
  shared?: boolean;
  trash?: boolean;
  owner?: string;
  limit?: number;
}

export interface DocumentListRequest extends DocumentFilters {
  /** Free-text alias kept for the global search box. */
  search?: string;
}

export interface RenameDocumentRequest extends WorkspaceScopedParams {
  id: string;
  /** Extension is re-appended by the server when the user omits it. */
  name: string;
}

export interface MoveDocumentRequest extends WorkspaceScopedParams {
  id: string;
  folder_id: string | null;
}

export interface SetDocumentPermissionRequest extends WorkspaceScopedParams {
  id: string;
  is_shared: boolean;
  permission?: SharedPermission;
  /** Fine-grained sharing added by the permissions contract. */
  member_ids?: string[];
  role?: Extract<MemberRoleName, 'Admin' | 'Editor' | 'Viewer'>;
}

export interface DocumentDetail extends Document {
  /** Effective capabilities for the caller, computed by the server. */
  capabilities?: DocumentCapabilities;
  versions_count?: number;
  latest_version_number?: number;
  breadcrumbs?: { id: string; name: string }[];
}

/**
 * Server-computed flags. The UI may hide or dim actions based on these, but the
 * server re-checks every request: these values are a convenience, not a gate.
 */
export interface DocumentCapabilities {
  can_view: boolean;
  can_download: boolean;
  can_rename: boolean;
  can_move: boolean;
  can_delete: boolean;
  can_share: boolean;
  can_restore: boolean;
  can_permanent_delete: boolean;
  can_upload_version: boolean;
}

export interface VersionSummary {
  id: string;
  version_number: number;
  size_bytes: number;
  created_at: string;
  created_by_name: string;
}

export interface SortOption {
  value: DocumentSort;
  label: string;
}

export const DOCUMENT_SORTS: SortOption[] = [
  { value: 'updated_desc', label: 'Terbaru diperbarui' },
  { value: 'updated_asc', label: 'Terlama diperbarui' },
  { value: 'name_asc', label: 'Nama A-Z' },
  { value: 'name_desc', label: 'Nama Z-A' },
  { value: 'size_desc', label: 'Ukuran terbesar' },
  { value: 'size_asc', label: 'Ukuran terkecil' },
];
