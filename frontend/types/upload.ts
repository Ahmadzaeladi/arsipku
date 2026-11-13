/**
 * ArsipKu - Upload and download models.
 * Single-shot uploads hit api/upload.php today (multipart, `file` or `files[]`).
 * The chunked types below describe the resumable contract the backend still needs
 * for files larger than MAX_UPLOAD_SIZE (50 MB).
 */

import type { WorkspaceScopedParams } from './api';
import type { Document, DocumentRow } from './document';

/** GET /api/upload.php?action=limits */
export interface UploadLimits {
  max_bytes: number;
  max_files_per_request: number;
  allowed_extensions: string[];
  allowed_mime_types: string[];
  /** Remaining quota for the active workspace. */
  remaining_bytes: number | null;
  chunking_supported: boolean;
  chunk_size_bytes?: number;
}

export interface UploadTarget extends WorkspaceScopedParams {
  /** null / 'root' stores the document in Arsip Utama. */
  folder_id?: string | null;
  /** Server-side duplicate handling for same-name uploads. */
  on_name_conflict?: 'rename' | 'new_version' | 'reject';
  idempotency_key?: string;
}

export interface UploadRequest extends UploadTarget {
  files: File[];
  signal?: AbortSignal;
  onProgress?: (progress: UploadProgress) => void;
}

export interface UploadProgress {
  /** Stable client-side key, not a server id. */
  local_id: string;
  file_name: string;
  total_bytes: number;
  loaded_bytes: number;
  /** 0-100. */
  percent: number;
  state: UploadItemState;
  speed_bytes_per_second?: number;
  message?: string;
}

export type UploadItemState =
  | 'queued' | 'checking' | 'uploading' | 'verifying'
  | 'done' | 'failed' | 'canceled';

export interface UploadQueueItem {
  local_id: string;
  file: File;
  state: UploadItemState;
  loaded_bytes: number;
  total_bytes: number;
  percent: number;
  /** Set once the server confirms the document row. */
  document?: Document;
  error?: string;
  upload_id?: string;
}

export interface UploadFailure {
  file_name: string;
  reason: string;
  code?: string;
}

/** Normalised result of one upload request. */
export interface UploadResult {
  uploaded: Document[];
  failed: UploadFailure[];
  /** Raw `errors: string[]` from api/upload.php, kept for toast copy. */
  messages: string[];
}

export type UploadRow = DocumentRow;

export interface DownloadRequest extends WorkspaceScopedParams {
  id: string;
  /** Download a specific version instead of the latest. */
  version_id?: string;
  disposition?: 'attachment' | 'inline';
}

/** Server answers with a short-lived signed URL; the client never guesses paths. */
export interface DownloadTicket {
  url: string;
  expires_at: string;
  filename: string;
  size_bytes: number;
  mime_type: string;
}

export interface UploadInitRequest extends UploadTarget {
  file_name: string;
  size_bytes: number;
  mime_type: string;
  checksum_sha256?: string;
}

export interface UploadInitResponse {
  upload_id: string;
  chunk_size_bytes: number;
  /** Zero-based part numbers still missing on the server. */
  required_parts: number[];
  expires_at: string;
}

export interface UploadPartRequest extends WorkspaceScopedParams {
  upload_id: string;
  part_number: number;
  data: Blob;
}

export interface UploadCompleteRequest extends WorkspaceScopedParams {
  upload_id: string;
}
