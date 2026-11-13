/**
 * ArsipKu - API envelopes, pagination, and error contracts.
 * Backend: PHP native endpoints under /api (action-based) backed by MySQL/MariaDB.
 * Nothing here assumes a framework.
 */

/** Normalised transport envelope every ArsipKu endpoint is expected to return. */
export interface ApiEnvelope<T = unknown> {
  success: boolean;
  message: string;
  data?: T;
  meta?: PaginationMeta;
  links?: PaginationLinks;
  code?: ApiErrorCode;
  errors?: FieldErrors;
}

/** What the PHP endpoints actually return today: extra keys sit beside `data`. */
export type RawEnvelope = Record<string, unknown> & {
  success?: unknown;
  message?: unknown;
  data?: unknown;
  meta?: unknown;
  links?: unknown;
  code?: unknown;
  errors?: unknown;
};

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  /** True when the server confirmed there is another page. */
  has_more: boolean;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

/** Successful list payload after normalisation. */
export interface Paginated<T> {
  items: T[];
  meta: PaginationMeta;
  links?: PaginationLinks;
}

export interface PaginationParams {
  page?: number;
  per_page?: number;
}

/** Sort directions accepted by the documented contract. */
export type SortOrder = 'asc' | 'desc';

/** Stable machine-readable error codes. `code` is optional on legacy responses. */
export type ApiErrorCode =
  | 'UNAUTHENTICATED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'VALIDATION_FAILED'
  | 'CONFLICT'
  | 'WORKSPACE_NOT_FOUND'
  | 'WORKSPACE_CONTEXT_MISSING'
  | 'STORAGE_QUOTA_EXCEEDED'
  | 'FILE_TOO_LARGE'
  | 'UNSUPPORTED_FILE_TYPE'
  | 'PARTIAL_UPLOAD'
  | 'LAST_WORKSPACE'
  | 'RATE_LIMITED'
  | 'SERVER_ERROR'
  | 'NETWORK_ERROR'
  | 'TIMEOUT'
  | 'ABORTED'
  | 'MALFORMED_RESPONSE';

export type FieldErrors = Record<string, string[]>;

/** Error thrown by the API client and re-thrown by every service. */
export interface ApiError {
  status: number;
  code: ApiErrorCode;
  message: string;
  fieldErrors: FieldErrors;
  /** Endpoint the request was sent to, for logging and toasts. */
  endpoint?: string;
  /** Raw parsed body, kept for debugging only. Never render blindly. */
  raw?: unknown;
}

/** Workspace context every scoped request must carry. */
export interface WorkspaceScopedParams {
  /** Workspace slug, sent as `ws`. Server resolves it to a workspace id. */
  ws?: string;
}

export type BatchAction = 'delete' | 'restore' | 'permanent_delete' | 'move';

/** Success copy authored by the server, returned by write actions. */
export interface VoidResult {
  message: string;
}

export interface BatchActionRequest extends WorkspaceScopedParams {
  ids: string[];
  action: BatchAction;
  /** Required for `move`. `'root'` or null moves to Arsip Utama. */
  target_folder_id?: string | null;
}

export interface BatchActionResult {
  requested_count: number;
  affected_count: number;
  failed_ids: string[];
}

/** Discriminated result used by hooks that want to avoid try/catch. */
export type Result<T, E = ApiError> =
  | { ok: true; value: T }
  | { ok: false; error: E };
