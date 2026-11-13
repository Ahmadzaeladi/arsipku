/**
 * ArsipKu - normalisation of raw PHP rows into the typed models.
 *
 * Why this layer exists: the current endpoints return PDO/MySQL values, which
 * means booleans arrive as `0`/`1`, integers may arrive as strings, dates are
 * `Y-m-d H:i:s` (no timezone), and a few endpoints put payload keys beside
 * `data` instead of inside it. Nothing below that line should leak into the UI.
 */

import type {
  ApiEnvelope,
  Paginated,
  PaginationMeta,
  RawEnvelope,
} from '../types/api';
import type { Activity } from '../types/activity';
import type { User } from '../types/auth';
import type { Document, DocumentRow } from '../types/document';
import type { Folder, FolderFlat, FolderRow } from '../types/folder';
import type { Member, MemberRoleName, MemberStatus } from '../types/member';
import type { UploadFailure, UploadResult } from '../types/upload';
import type { DocumentVersion } from '../types/version';
import type { Workspace, WorkspaceStats } from '../types/workspace';

/* ------------------------------------------------------------------ primitives */

export function asBool(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') {
    const v = value.trim().toLowerCase();
    return v === '1' || v === 'true' || v === 'yes';
  }
  return false;
}

export function asInt(value: unknown, fallback = 0): number {
  if (typeof value === 'number' && Number.isFinite(value)) return Math.trunc(value);
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : fallback;
  }
  return fallback;
}

export function asFloat(value: unknown, fallback = 0): number {
  const n = typeof value === 'string' ? Number(value) : value;
  return typeof n === 'number' && Number.isFinite(n) ? n : fallback;
}

export function asStr(value: unknown, fallback = ''): string {
  if (typeof value === 'string') return value;
  if (value === null || value === undefined) return fallback;
  return String(value);
}

/** Empty string and the literal 'null' are treated as "no value". */
export function asNullStr(value: unknown): string | null {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && (value === '' || value === 'null')) return null;
  return String(value);
}

/** Coerce a JSON array of ids, dropping blanks. Missing/!array => []. */
export function asStrIds(value: unknown): string[] {
  if (!Array.isArray(value)) return [];
  return value.map((item) => asStr(item)).filter((item) => item !== '');
}

/** MySQL DATETIME ("2026-03-24 14:30:00") -> ISO-8601 in local time. */
export function asDate(value: unknown): string {
  if (value instanceof Date) return value.toISOString();
  const raw = asStr(value).trim();
  if (!raw) return '';
  if (raw.includes('T')) return raw;
  const normalised = raw.replace(' ', 'T');
  const parsed = new Date(normalised);
  return Number.isNaN(parsed.getTime()) ? raw : parsed.toISOString();
}

/** Normalise + sort-free: callers rely on server ordering. */
export function normalizeArray<T>(
  value: unknown,
  itemNormalizer: (row: Record<string, unknown>) => T,
): T[] {
  if (!Array.isArray(value)) return [];
  return value
    .filter((row): row is Record<string, unknown> => !!row && typeof row === 'object')
    .map((row) => itemNormalizer(row));
}

/* ------------------------------------------------------------------- envelopes */

/**
 * Read the payload of a response, tolerating the three shapes the PHP endpoints
 * use today: `{data}`, `{workspace, stats}`, `{uploaded, errors}` or a bare array.
 */
export function extractData<T = unknown>(body: unknown, keys: string[] = ['data']): T {
  if (Array.isArray(body)) return body as T;
  if (!body || typeof body !== 'object') return body as T;
  const record = body as Record<string, unknown>;
  for (const key of keys) {
    if (record[key] !== undefined) return record[key] as T;
  }
  return body as T;
}

export function readSuccess(body: RawEnvelope): boolean {
  if (typeof body?.success === 'boolean') return body.success;
  if (body?.success === undefined) return true;
  return asBool(body.success);
}

export function readMessage(body: RawEnvelope, fallback = ''): string {
  const message = asStr(body?.message).trim();
  return message || fallback;
}

/** Synthesise pagination for endpoints that return a plain array. */
export function paginate<T>(
  items: T[],
  meta?: Partial<PaginationMeta> | null,
  requestedPage = 1,
  requestedPerPage = items.length || 50,
): Paginated<T> {
  const total = asInt(meta?.total, items.length);
  const perPage = Math.max(1, asInt(meta?.per_page, requestedPerPage));
  const page = Math.max(1, asInt(meta?.page, requestedPage));
  const totalPages = meta?.total_pages !== undefined
    ? asInt(meta.total_pages)
    : Math.max(1, Math.ceil(total / perPage));
  return {
    items,
    meta: {
      page,
      per_page: perPage,
      total,
      total_pages: totalPages,
      has_more: meta?.has_more !== undefined ? asBool(meta.has_more) : page < totalPages,
    },
  };
}

/** Narrow a typed envelope without repeating casts in every service. */
export function asEnvelope<T>(body: unknown): ApiEnvelope<T> {
  return (body ?? {}) as ApiEnvelope<T>;
}

/* --------------------------------------------------------------------- models */

export function normalizeDocument(row: DocumentRow | Record<string, unknown>): Document {
  const r = row as Record<string, unknown>;
  return {
    id: asStr(r.id),
    workspace_id: asStr(r.workspace_id),
    folder_id: asNullStr(r.folder_id),
    name: asStr(r.name),
    original_name: asStr(r.original_name, asStr(r.name)),
    mime_type: asStr(r.mime_type, 'application/octet-stream'),
    extension: asStr(r.extension).toLowerCase().replace(/^\./, ''),
    size_bytes: Math.max(0, asInt(r.size_bytes)),
    uploaded_by: asStr(r.uploaded_by),
    owner_name: asStr(r.owner_name),
    is_favorite: asBool(r.is_favorite),
    is_shared: asBool(r.is_shared),
    shared_permission: r.shared_permission === 'edit' ? 'edit' : 'view',
    created_at: asDate(r.created_at),
    updated_at: asDate(r.updated_at),
    deleted_at: r.deleted_at === undefined ? null : asNullStr(r.deleted_at),
    download_url: normalizeDownloadUrl(r.download_url),
    preview_url: asNullStr(r.preview_url),
    recipient_permission: r.recipient_permission === 'edit' || r.recipient_permission === 'view'
      ? r.recipient_permission
      : null,
    can_edit: typeof r.can_edit === 'boolean' ? r.can_edit : undefined,
    can_delete: typeof r.can_delete === 'boolean' ? r.can_delete : undefined,
    can_share: typeof r.can_share === 'boolean' ? r.can_share : undefined,
    folder_name: asNullStr(r.folder_name),
    folder_color: normalizeFolderColor(r.folder_color),
  };
}

/**
 * The server returns a session-scoped api/download.php URL; never construct a
 * public uploads/ URL from a storage filename.
 */
export function normalizeDownloadUrl(value: unknown): string | null {
  const raw = asNullStr(value);
  if (!raw) return null;
  if (/^https?:\/\//i.test(raw) || raw.startsWith('/') || raw.includes('?')) return raw;
  return null;
}

export function normalizeFolderColor(value: unknown): Folder['color'] | null {
  const colors = ['indigo', 'emerald', 'blue', 'amber', 'purple'] as const;
  const raw = asStr(value).toLowerCase() as (typeof colors)[number];
  return colors.includes(raw) ? raw : null;
}

export function normalizeFolder(row: FolderRow): Folder {
  return {
    id: asStr(row.id),
    workspace_id: asStr(row.workspace_id),
    parent_id: asNullStr(row.parent_id),
    name: asStr(row.name),
    color: normalizeFolderColor(row.color) ?? 'indigo',
    icon: asStr(row.icon, 'folder'),
    doc_count: asInt(row.doc_count),
    subfolder_count: row.subfolder_count === undefined ? undefined : asInt(row.subfolder_count),
    created_at: asDate(row.created_at),
    updated_at: asDate(row.updated_at),
    deleted_at: row.deleted_at === undefined ? null : asNullStr(row.deleted_at),
  };
}

export function normalizeFolderFlat(row: Record<string, unknown>): FolderFlat {
  return { id: asStr(row.id), name: asStr(row.name), parent_id: asNullStr(row.parent_id) };
}

const ROLES: MemberRoleName[] = ['Owner', 'Admin', 'Editor', 'Viewer'];

export function normalizeRole(value: unknown, fallback: MemberRoleName = 'Viewer'): MemberRoleName {
  const raw = asStr(value).trim().toLowerCase();
  return ROLES.find((role) => role.toLowerCase() === raw) ?? fallback;
}

export function normalizeMember(row: Record<string, unknown>): Member {
  const status = asStr(row.status).toLowerCase();
  return {
    id: asStr(row.id),
    workspace_id: asStr(row.workspace_id),
    user_id: asNullStr(row.user_id),
    name: asStr(row.name),
    email: asStr(row.email),
    avatar_url: asStr(row.avatar_url || row.avatar),
    role: normalizeRole(row.role),
    status: (status === 'invited' || status === 'suspended' ? status : 'active') as MemberStatus,
    joined_at: asDate(row.joined_at ?? row.created_at),
  };
}

export function normalizeWorkspace(row: Record<string, unknown>): Workspace {
  return {
    id: asStr(row.id),
    name: asStr(row.name),
    slug: asStr(row.slug),
    type: normalizeWorkspaceType(row.type),
    storage_limit_bytes: Math.max(0, asInt(row.storage_limit_bytes)),
    plan_name: asStr(row.plan_name, 'Pro Plan'),
    user_role: row.user_role ? normalizeRole(row.user_role) : undefined,
    member_count: row.member_count === undefined ? undefined : asInt(row.member_count),
    created_at: asDate(row.created_at),
    updated_at: row.updated_at === undefined ? undefined : asDate(row.updated_at),
  };
}

export function normalizeWorkspaceType(value: unknown): Workspace['type'] {
  const raw = asStr(value).toLowerCase();
  return raw === 'personal' || raw === 'school' ? raw : 'company';
}

export function normalizeStats(row: Record<string, unknown>): WorkspaceStats {
  const percentage = asFloat(row.usage_percentage);
  return {
    total_documents: asInt(row.total_documents ?? row.total_docs),
    total_folders: asInt(row.total_folders),
    total_size_bytes: asInt(row.total_size_bytes ?? row.total_bytes),
    storage_limit_bytes: asInt(row.storage_limit_bytes),
    usage_percentage: percentage,
    is_warning: row.is_warning === undefined ? percentage >= 80 : asBool(row.is_warning),
    plan_name: asStr(row.plan_name, ''),
  };
}

export function normalizeUser(row: Record<string, unknown>): User {
  return {
    id: asStr(row.id),
    name: asStr(row.name),
    email: asStr(row.email),
    title: row.role ? asStr(row.role) : undefined,
    avatar_url: asStr(row.avatar_url || row.avatar),
    email_verified_at: asNullStr(row.email_verified_at),
    created_at: asDate(row.created_at),
    updated_at: asDate(row.updated_at),
  };
}

export function normalizeActivity(row: Record<string, unknown>): Activity {
  return {
    id: asStr(row.id),
    workspace_id: asStr(row.workspace_id),
    user_id: asNullStr(row.user_id),
    user_name: asStr(row.user_name),
    user_avatar_url: asStr(row.user_avatar_url || row.user_avatar),
    action_type: asStr(row.action_type) as Activity['action_type'],
    description: asStr(row.description),
    created_at: asDate(row.created_at),
    resource_type: row.resource_type ? (asStr(row.resource_type) as Activity['resource_type']) : null,
    resource_id: asNullStr(row.resource_id),
  };
}

export function normalizeVersion(row: Record<string, unknown>): DocumentVersion {
  return {
    id: asStr(row.id),
    document_id: asStr(row.document_id),
    version_number: asInt(row.version_number, 1),
    label: asNullStr(row.label),
    size_bytes: asInt(row.size_bytes),
    mime_type: asStr(row.mime_type),
    extension: asStr(row.extension).toLowerCase(),
    checksum_sha256: asNullStr(row.checksum_sha256),
    created_by: asStr(row.created_by),
    created_by_name: asStr(row.created_by_name),
    created_at: asDate(row.created_at),
    is_current: asBool(row.is_current),
    download_url: normalizeDownloadUrl(row.download_url),
  };
}

/** api/upload.php answers with `uploaded: []` + `errors: string[]`. */
export function normalizeUploadResult(body: RawEnvelope): UploadResult {
  const uploaded = normalizeArray(extractData<unknown>(body, ['uploaded']), normalizeDocument);
  const rawErrors = Array.isArray(body?.errors) ? body.errors : [];
  const failed: UploadFailure[] = rawErrors
    .map((entry) => (typeof entry === 'string' ? { file_name: '', reason: entry } : {
      file_name: asStr((entry as Record<string, unknown>).file_name),
      reason: asStr((entry as Record<string, unknown>).reason),
      code: asNullStr((entry as Record<string, unknown>).code) ?? undefined,
    }))
    .filter((entry) => entry.reason !== '');
  return { uploaded, failed, messages: rawErrors.filter((e): e is string => typeof e === 'string') };
}
