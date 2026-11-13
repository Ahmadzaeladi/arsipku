/**
 * ArsipKu - HTTP client.
 *
 * Responsibilities:
 *   - build action-style URLs for the PHP endpoints (lib/endpoints.ts)
 *   - attach the active workspace slug to every scoped request
 *   - send session credentials and the CSRF header
 *   - enforce timeouts, aborts, and bounded retries for idempotent reads
 *   - translate `{success:false}` / non-2xx into ApiClientError
 *
 * It deliberately knows nothing about documents, folders, or permissions: those live
 * in the service layer. It also never decides access - a 403 from the server is the
 * only source of truth, and no client-side flag can substitute for it.
 */

import type { RawEnvelope } from '../types/api';
import { getConfig } from './config';
import { buildPostUrl, buildUrl, endpointPath, type EndpointName } from './endpoints';
import { emit } from './events';
import { ApiClientError, errorFromBody, isAbortError, toApiError } from './errors';
import { readSuccess } from './normalize';
import { getWorkspaceSlug } from './workspaceContext';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestOptions {
  /** Action token, e.g. `list`. */
  action?: string;
  /** Query params, merged into the URL. */
  params?: Record<string, unknown>;
  /** JSON body for write requests. `action` is injected automatically. */
  body?: Record<string, unknown>;
  method?: HttpMethod;
  /** Slug override. `null` opts out of workspace injection (auth, plan catalogue). */
  ws?: string | null;
  signal?: AbortSignal;
  timeoutMs?: number;
  /** Retry budget for idempotent reads; 0 disables retries. */
  retries?: number;
  headers?: Record<string, string>;
}

export interface ApiCall {
  endpoint: EndpointName;
  url: string;
  method: HttpMethod;
  status: number;
  body: RawEnvelope;
}

/**
 * Low-level call. Returns the parsed envelope; throws ApiClientError on transport,
 * HTTP, or `success:false` failures.
 */
export async function call(endpoint: EndpointName, options: RequestOptions = {}): Promise<ApiCall> {
  const config = getConfig();
  const method = options.method ?? 'GET';
  const url = buildRequestUrl(endpoint, method, options);
  const init: RequestInit = {
    method,
    credentials: 'same-origin',
    headers: buildHeaders(method, options),
  };

  if (method !== 'GET' && options.body !== undefined) {
    init.body = JSON.stringify(withAction(options));
  }

  const { signal, cleanup } = createTimeoutSignal(options.signal, options.timeoutMs ?? timeoutFor(method));
  init.signal = signal;

  const attempts = method === 'GET' ? retryBudget(options) : 0;
  let lastError: ApiClientError | null = null;

  try {
    for (let attempt = 0; attempt <= attempts; attempt += 1) {
      if (attempt > 0) await sleep(config.retryBackoffMs * 2 ** (attempt - 1), signal);
      try {
        const response = await fetch(url, init);
        const body = await parseBody(response, url);
        const status = response.status;

        if (!response.ok || !readSuccess(body)) {
          const error = errorFromBody(body, status, url);
          if (status === 401) emit('auth:expired', { endpoint: url });
          // 4xx are contract failures: retrying cannot change the outcome.
          if (status >= 400 && status < 500 && status !== 429) throw error;
          lastError = error;
          continue;
        }

        return { endpoint, url, method, status, body };
      } catch (err) {
        if (err instanceof ApiClientError) {
          if (!err.isRetryable || attempt === attempts) throw err;
          lastError = err;
          continue;
        }
        if (isAbortError(err)) {
          throw new ApiClientError({
            message: options.signal?.aborted ? 'Permintaan dibatalkan.' : 'Waktu permintaan habis.',
            code: options.signal?.aborted ? 'ABORTED' : 'TIMEOUT',
            status: 0,
            endpoint: url,
          });
        }
        lastError = toApiError(err, url);
      }
    }
  } finally {
    cleanup();
  }

  throw lastError
    ?? new ApiClientError({ message: 'Permintaan gagal tanpa detail.', status: 0, endpoint: url });
}

/** GET helper returning the payload (`data`, or the legacy sibling keys). */
export async function get<T = unknown>(
  endpoint: EndpointName,
  action: string,
  params?: Record<string, unknown>,
  options: Omit<RequestOptions, 'action' | 'params' | 'method' | 'body'> = {},
): Promise<T> {
  const { body } = await call(endpoint, { ...options, action, params, method: 'GET' });
  return extractPayload<T>(body, endpoint, action);
}

/** Write helper returning the whole envelope so callers can read `message`. */
export async function post(
  endpoint: EndpointName,
  action: string,
  body?: Record<string, unknown>,
  options: Omit<RequestOptions, 'action' | 'body' | 'params'> = {},
): Promise<RawEnvelope> {
  const response = await call(endpoint, {
    ...options,
    action,
    method: options.method ?? 'POST',
    body: withWorkspace(body ?? {}, options.ws),
  });
  return response.body;
}

/**
 * Multipart upload without progress. `fetch` cannot report upload progress, so use
 * uploadWithProgress() whenever the UI shows a bar.
 */
export async function upload(
  endpoint: EndpointName,
  action: string,
  form: FormData,
  options: { ws?: string | null; signal?: AbortSignal } = {},
): Promise<RawEnvelope> {
  const config = getConfig();
  const slug = resolveSlug(options.ws);
  const url = buildPostUrl(endpoint, action, slug);
  const { signal, cleanup } = createTimeoutSignal(options.signal, config.uploadTimeoutMs);

  try {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: buildMultipartHeaders(),
      body: form,
      signal,
    });
    const body = await parseBody(response, url);
    if (!response.ok || !readSuccess(body)) {
      const error = errorFromBody(body, response.status, url);
      if (response.status === 401) emit('auth:expired', { endpoint: url });
      throw error;
    }
    return body;
  } finally {
    cleanup();
  }
}

/** XMLHttpRequest upload with byte progress, used by the uploader UI. */
export function uploadWithProgress(input: {
  endpoint: EndpointName;
  action?: string;
  form: FormData;
  ws?: string | null;
  signal?: AbortSignal;
  onProgress: (loadedBytes: number, totalBytes: number) => void;
}): Promise<RawEnvelope> {
  const config = getConfig();
  const url = buildPostUrl(input.endpoint, input.action ?? '', resolveSlug(input.ws));

  return new Promise<RawEnvelope>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const detach = () => input.signal?.removeEventListener('abort', abort);
    const abort = () => xhr.abort();

    xhr.open('POST', url, true);
    xhr.withCredentials = true;
    xhr.timeout = config.uploadTimeoutMs;
    for (const [key, value] of Object.entries(buildMultipartHeaders())) {
      xhr.setRequestHeader(key, value);
    }
    input.signal?.addEventListener('abort', abort);
    if (input.signal?.aborted) abort();

    xhr.upload.addEventListener('progress', (event) => {
      input.onProgress(event.loaded, event.total);
    });

    xhr.addEventListener('load', () => {
      detach();
      const text = xhr.responseText ?? '';
      let parsed: RawEnvelope;
      try {
        parsed = text ? (JSON.parse(text) as RawEnvelope) : {};
      } catch {
        reject(new ApiClientError({
          message: `Respons upload bukan JSON (HTTP ${xhr.status}).`,
          status: xhr.status,
          code: 'MALFORMED_RESPONSE',
          endpoint: url,
          raw: text.slice(0, 500),
        }));
        return;
      }
      if (xhr.status < 200 || xhr.status >= 300 || !readSuccess(parsed)) {
        const error = errorFromBody(parsed, xhr.status, url);
        if (xhr.status === 401) emit('auth:expired', { endpoint: url });
        reject(error);
        return;
      }
      resolve(parsed);
    });

    xhr.addEventListener('error', () => {
      detach();
      reject(toApiError(new TypeError('network'), url));
    });
    xhr.addEventListener('timeout', () => {
      detach();
      reject(new ApiClientError({
        message: 'Waktu upload habis.',
        code: 'TIMEOUT',
        status: 408,
        endpoint: url,
      }));
    });
    xhr.addEventListener('abort', () => {
      detach();
      reject(new ApiClientError({
        message: 'Upload dibatalkan.',
        code: 'ABORTED',
        status: 0,
        endpoint: url,
      }));
    });

    xhr.send(input.form);
  });
}

/* ------------------------------------------------------------------- internals */

function buildRequestUrl(endpoint: EndpointName, method: HttpMethod, options: RequestOptions): string {
  const slug = resolveSlug(options.ws);
  if (method === 'GET') {
    return buildUrl(endpoint, options.action ?? 'list', { ...options.params, ws: slug ?? undefined });
  }
  return buildPostUrl(endpoint, options.action ?? '', slug);
}

function withAction(options: RequestOptions): Record<string, unknown> {
  const body = { ...(options.body ?? {}) };
  if (options.action && body.action === undefined) body.action = options.action;
  return body;
}

function withWorkspace(body: Record<string, unknown>, ws: string | null | undefined): Record<string, unknown> {
  if (body.ws !== undefined || ws === null) return body;
  const slug = resolveSlug(ws);
  return slug ? { ...body, ws: slug } : body;
}

/** `undefined` = use the active context, `null` = explicitly unscoped. */
function resolveSlug(ws: string | null | undefined): string | null {
  if (ws === null) return null;
  if (typeof ws === 'string') return ws || null;
  return getWorkspaceSlug() || null;
}

function buildHeaders(method: HttpMethod, options: RequestOptions): Record<string, string> {
  const config = getConfig();
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (config.identifyAjax) headers['X-Requested-With'] = 'XMLHttpRequest';
  if (method !== 'GET') {
    const token = readCsrfToken();
    if (token) headers[config.csrfHeaderName] = token;
  }
  if (options.body !== undefined) headers['Content-Type'] = 'application/json; charset=utf-8';
  return { ...headers, ...options.headers };
}

/** Content-Type stays unset for FormData so the browser can add the boundary. */
function buildMultipartHeaders(): Record<string, string> {
  const config = getConfig();
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (config.identifyAjax) headers['X-Requested-With'] = 'XMLHttpRequest';
  const token = readCsrfToken();
  if (token) headers[config.csrfHeaderName] = token;
  return headers;
}

/**
 * CSRF comes from a readable, non-httpOnly cookie the backend mirrors
 * (`XSRF-TOKEN` by default). Never logged, never sent on GET.
 */
export function readCsrfToken(): string | null {
  const name = getConfig().csrfCookieName;
  if (typeof document === 'undefined' || !document.cookie) return null;
  for (const pair of document.cookie.split(';')) {
    const [key, ...rest] = pair.trim().split('=');
    if (key === name) {
      const raw = rest.join('=');
      try {
        return decodeURIComponent(raw);
      } catch {
        return raw;
      }
    }
  }
  return null;
}

async function parseBody(response: Response, url: string): Promise<RawEnvelope> {
  const text = await response.text();
  if (!text.trim()) return {};
  try {
    const parsed: unknown = JSON.parse(text);
    if (parsed && typeof parsed === 'object') return parsed as RawEnvelope;
    return {
      success: false,
      message: `Respons server bukan objek JSON (HTTP ${response.status}).`,
      data: parsed,
    };
  } catch {
    // A PHP fatal error prints HTML: keep a short excerpt for debugging only.
    throw new ApiClientError({
      message: `Respons server tidak dapat dibaca (HTTP ${response.status}).`,
      status: response.status,
      code: 'MALFORMED_RESPONSE',
      endpoint: url,
      raw: text.slice(0, 500),
    });
  }
}

/** Keys the legacy endpoints use beside or instead of `data`. */
const PAYLOAD_KEYS = [
  'data', 'workspace', 'stats', 'uploaded', 'workspaces',
  'documents', 'folders', 'members', 'is_favorite', 'count',
];

function extractPayload<T>(body: RawEnvelope, endpoint: EndpointName, action: string): T {
  for (const key of PAYLOAD_KEYS) {
    if (body[key] !== undefined) return body[key] as T;
  }
  if (getConfig().warnOnFallback) {
    console.warn(`[ArsipKu] ${endpointPath(endpoint)} action=${action} tidak mengirim kunci data yang dikenal.`);
  }
  return body as T;
}

function timeoutFor(method: HttpMethod): number {
  const config = getConfig();
  return method === 'GET' ? config.requestTimeoutMs : config.requestTimeoutMs * 2;
}

function retryBudget(options: RequestOptions): number {
  const requested = options.retries ?? getConfig().maxRetries;
  return Math.max(0, Math.min(3, requested));
}

function createTimeoutSignal(
  external: AbortSignal | undefined,
  timeoutMs: number,
): { signal: AbortSignal; cleanup: () => void } {
  const controller = new AbortController();
  const timer = timeoutMs > 0 ? setTimeout(() => controller.abort(), timeoutMs) : null;
  const onExternalAbort = () => controller.abort();
  if (external) {
    if (external.aborted) onExternalAbort();
    else external.addEventListener('abort', onExternalAbort, { once: true });
  }
  return {
    signal: controller.signal,
    cleanup: () => {
      if (timer !== null) clearTimeout(timer);
      external?.removeEventListener('abort', onExternalAbort);
    },
  };
}

function sleep(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    if (signal.aborted) {
      reject(new ApiClientError({ message: 'Permintaan dibatalkan.', code: 'ABORTED', status: 0 }));
      return;
    }
    const timer = setTimeout(resolve, ms);
    signal.addEventListener('abort', () => {
      clearTimeout(timer);
      reject(new ApiClientError({ message: 'Permintaan dibatalkan.', code: 'ABORTED', status: 0 }));
    }, { once: true });
  });
}
