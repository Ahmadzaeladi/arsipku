/**
 * ArsipKu - typed API errors.
 * The PHP endpoints return `{success:false, message}` with a status code and no
 * machine-readable `code`, so Indonesian message heuristics fill in the gap.
 * Anything that leaves a service is an ApiClientError.
 */

import type { ApiError, ApiErrorCode, FieldErrors, RawEnvelope } from '../types/api';

export class ApiClientError extends Error implements ApiError {
  readonly status: number;
  readonly code: ApiErrorCode;
  readonly fieldErrors: FieldErrors;
  readonly endpoint?: string;
  readonly raw?: unknown;

  constructor(input: {
    message: string;
    status?: number;
    code?: ApiErrorCode;
    fieldErrors?: FieldErrors;
    endpoint?: string;
    raw?: unknown;
  }) {
    super(input.message);
    this.name = 'ApiClientError';
    this.status = input.status ?? 0;
    this.code = input.code ?? inferCode(input.status ?? 0, input.message);
    this.fieldErrors = input.fieldErrors ?? {};
    this.endpoint = input.endpoint;
    this.raw = input.raw;
  }

  get isAuthError(): boolean {
    return this.code === 'UNAUTHENTICATED' || this.status === 401;
  }

  get isPermissionError(): boolean {
    return this.code === 'FORBIDDEN' || this.status === 403;
  }

  get isRetryable(): boolean {
    return this.status === 0 || this.status === 429 || this.status >= 500;
  }

  /** First field error, for compact toasts. */
  get fieldMessage(): string | null {
    const first = Object.values(this.fieldErrors).flat()[0];
    return first ?? null;
  }
}

/** Status -> code, used when the server omits `code`. */
export function codeFromStatus(status: number): ApiErrorCode {
  switch (status) {
    case 0: return 'NETWORK_ERROR';
    case 400: return 'VALIDATION_FAILED';
    case 401: return 'UNAUTHENTICATED';
    case 403: return 'FORBIDDEN';
    case 404: return 'NOT_FOUND';
    case 409: return 'CONFLICT';
    case 413: return 'FILE_TOO_LARGE';
    case 415: return 'UNSUPPORTED_FILE_TYPE';
    case 422: return 'VALIDATION_FAILED';
    case 429: return 'RATE_LIMITED';
    case 408: return 'TIMEOUT';
    default: return status >= 500 ? 'SERVER_ERROR' : 'VALIDATION_FAILED';
  }
}

const MESSAGE_HINTS: [RegExp, ApiErrorCode][] = [
  [/belum (masuk|login|terautentikasi)|s(?:esi )?kedaluwarsa|unauthor/i, 'UNAUTHENTICATED'],
  [/tidak (?:memiliki )?hak|ditolak|forbidden|tidak diizinkan/i, 'FORBIDDEN'],
  [/tidak ditemukan|not found/i, 'NOT_FOUND'],
  [/sudah menjadi anggota|telah diundang|sudah ada|duplikat|conflict/i, 'CONFLICT'],
  [/satu-satunya workspace/i, 'LAST_WORKSPACE'],
  [/kuota|kapasitas (?:penuh|habis)|melampaui kuota|storage/i, 'STORAGE_QUOTA_EXCEEDED'],
  [/melampaui batas|max_upload|terlalu besar|too large/i, 'FILE_TOO_LARGE'],
  [/format file|tidak didukung|ekstensi/i, 'UNSUPPORTED_FILE_TYPE'],
  [/workspace .*(?:tidak valid|tidak dikenali)|aktif/i, 'WORKSPACE_NOT_FOUND'],
  [/tidak boleh kosong|tidak valid|minimal|maksimal .*karakter/i, 'VALIDATION_FAILED'],
];

function inferCode(status: number, message: string): ApiErrorCode {
  for (const [pattern, code] of MESSAGE_HINTS) {
    if (pattern.test(message)) return code;
  }
  return codeFromStatus(status);
}

/** Turn a parsed failure body into an error. */
export function errorFromBody(
  body: RawEnvelope,
  status: number,
  endpoint?: string,
): ApiClientError {
  const message = typeof body.message === 'string' && body.message.trim()
    ? body.message
    : fallbackMessage(status);
  return new ApiClientError({
    message,
    status,
    code: typeof body.code === 'string' ? (body.code as ApiErrorCode) : undefined,
    fieldErrors: readFieldErrors(body.errors),
    endpoint,
    raw: body,
  });
}

export function fallbackMessage(status: number): string {
  if (status === 0) return 'Koneksi ke server ArsipKu gagal.';
  if (status === 401) return 'Sesi berakhir. Silakan masuk kembali.';
  if (status === 403) return 'Anda tidak memiliki hak untuk aksi ini.';
  if (status === 404) return 'Data tidak ditemukan.';
  if (status === 413) return 'Ukuran berkas melebihi batas upload.';
  if (status === 422 || status === 400) return 'Data yang dikirim tidak valid.';
  if (status === 429) return 'Terlalu banyak percobaan. Tunggu sebentar.';
  if (status >= 500) return 'Terjadi kesalahan pada server.';
  return `Permintaan gagal (HTTP ${status}).`;
}

/** PHP/PDO responses can carry errors as `{field: "msg"}` instead of arrays. */
export function readFieldErrors(value: unknown): FieldErrors {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
  const out: FieldErrors = {};
  for (const [key, raw] of Object.entries(value as Record<string, unknown>)) {
    if (Array.isArray(raw)) out[key] = raw.map(String);
    else if (typeof raw === 'string') out[key] = [raw];
    else out[key] = [String(raw)];
  }
  return out;
}

export function isAbortError(err: unknown): boolean {
  return err instanceof DOMException && err.name === 'AbortError';
}

/** Normalise anything thrown (fetch TypeError, string, unknown) into ApiClientError. */
export function toApiError(err: unknown, endpoint?: string): ApiClientError {
  if (err instanceof ApiClientError) return err;
  if (isAbortError(err)) {
    return new ApiClientError({ message: 'Permintaan dibatalkan.', code: 'ABORTED', status: 0, endpoint });
  }
  if (err instanceof TypeError) {
    return new ApiClientError({ message: fallbackMessage(0), code: 'NETWORK_ERROR', status: 0, endpoint });
  }
  const message = err instanceof Error ? err.message : String(err);
  return new ApiClientError({ message, status: 0, endpoint, raw: err });
}

export type { ApiError };
