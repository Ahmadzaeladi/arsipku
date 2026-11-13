/**
 * ArsipKu - Document version models.
 * No `document_versions` table exists yet (project.md roadmap item 3), so these
 * types define a contract that the backend must satisfy before these calls work.
 */

import type { PaginationParams, WorkspaceScopedParams } from './api';
import type { Document, SharedPermission } from './document';

export interface DocumentVersion {
  id: string;
  document_id: string;
  /** 1-based, monotonic per document. */
  version_number: number;
  label?: string | null;
  size_bytes: number;
  mime_type: string;
  extension: string;
  checksum_sha256?: string | null;
  created_by: string;
  created_by_name: string;
  created_at: string;
  /** Exactly one version per document is current. */
  is_current: boolean;
  download_url?: string | null;
}

export interface ListVersionsParams extends WorkspaceScopedParams, PaginationParams {
  document_id: string;
}

export interface UploadVersionRequest extends WorkspaceScopedParams {
  document_id: string;
  file: File;
  label?: string;
  on_name_conflict?: 'new_version';
  onProgress?: (percent: number) => void;
}

export interface RestoreVersionRequest extends WorkspaceScopedParams {
  document_id: string;
  version_id: string;
}

/** POST .../versions/{id}/restore returns the freshly created current version. */
export interface RestoreVersionResult {
  restored_version: DocumentVersion;
  document: Document;
}

export interface DeleteVersionRequest extends WorkspaceScopedParams {
  document_id: string;
  version_id: string;
}

export interface CompareVersionsRequest extends WorkspaceScopedParams {
  document_id: string;
  from_version_number: number;
  to_version_number: number;
}

export interface VersionDiff {
  from: Pick<DocumentVersion, 'id' | 'version_number' | 'size_bytes' | 'created_at'>;
  to: Pick<DocumentVersion, 'id' | 'version_number' | 'size_bytes' | 'created_at'>;
  size_delta_bytes: number;
  summary: string;
}

export interface DocumentLockState {
  document_id: string;
  is_locked: boolean;
  locked_by?: string | null;
  locked_by_name?: string | null;
  expires_at?: string | null;
  /** Whether the caller may take over the lock. */
  can_override: boolean;
  shared_permission?: SharedPermission;
}
