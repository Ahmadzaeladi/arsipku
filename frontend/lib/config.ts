/**
 * ArsipKu - runtime configuration for the frontend layer.
 *
 * Values resolve in this order (later wins):
 *   1. defaults below
 *   2. window.__ARSIPKU__ injected by views/layouts/app.php
 *   3. <body data-arsipku-*> attributes
 *   4. URL query params (?ws=)
 */

import type { WorkspaceType } from '../types/workspace';

export interface ArsipKuConfig {
  /** Folder that holds the PHP endpoints, relative to the app root. */
  apiBase: string;
  /** Directory with the physical files. Only used to render legacy links. */
  uploadsBase: string;
  /** Slug pinned by the shell; empty means "read from URL/session". */
  workspaceSlug: string;
  requestTimeoutMs: number;
  uploadTimeoutMs: number;
  /** Retries for idempotent GET requests only. */
  maxRetries: number;
  retryBackoffMs: number;
  csrfCookieName: string;
  csrfHeaderName: string;
  /** Sends X-Requested-With so api/*.php can tell AJAX from a page load. */
  identifyAjax: boolean;
  /** Emit a console warning when an endpoint response omits a known payload key. */
  warnOnFallback: boolean;
}

/** Global injected by the PHP layout: `<script>window.__ARSIPKU__ = {...}</script>`. */
declare global {
  interface Window {
    __ARSIPKU__?: Partial<ArsipKuConfig> & {
      workspace_type?: WorkspaceType;
      page?: string;
      user_id?: string;
      /** 'ts' mounts the UI adapters in place of the legacy vanilla scripts. */
      ui?: 'ts' | 'legacy';
    };
  }
}

export const defaultConfig: ArsipKuConfig = {
  apiBase: 'api',
  uploadsBase: 'uploads',
  workspaceSlug: '',
  requestTimeoutMs: 15_000,
  uploadTimeoutMs: 120_000,
  maxRetries: 2,
  retryBackoffMs: 400,
  csrfCookieName: 'XSRF-TOKEN',
  csrfHeaderName: 'X-CSRF-TOKEN',
  identifyAjax: true,
  warnOnFallback: true,
};

let config: ArsipKuConfig = resolveConfig();

function readBodyAttributes(): Partial<ArsipKuConfig> {
  if (typeof document === 'undefined') return {};
  const body = document.body;
  if (!body) return {};
  const attrs: Partial<ArsipKuConfig> = {};
  const apiBase = body.dataset.arsipkuApiBase;
  const uploads = body.dataset.arsipkuUploadsBase;
  const ws = body.dataset.arsipkuWorkspace;
  if (apiBase) attrs.apiBase = apiBase;
  if (uploads) attrs.uploadsBase = uploads;
  if (ws) attrs.workspaceSlug = ws;
  return attrs;
}

function readQuery(): { ws: string | null } {
  if (typeof window === 'undefined' || !window.location) {
    return { ws: null };
  }
  const params = new URLSearchParams(window.location.search);
  return { ws: params.get('ws') };
}

function resolveConfig(): ArsipKuConfig {
  const injected = typeof window !== 'undefined' && window.__ARSIPKU__ ? window.__ARSIPKU__ : {};
  const fromBody = readBodyAttributes();
  const query = readQuery();
  const merged: ArsipKuConfig = {
    ...defaultConfig,
    ...stripUnknown(injected),
    ...fromBody,
  };

  if (query.ws) merged.workspaceSlug = query.ws;

  return merged;
}

function stripUnknown(partial: object): Partial<ArsipKuConfig> {
  const source = partial as Record<string, unknown>;
  const known = Object.keys(defaultConfig) as (keyof ArsipKuConfig)[];
  const out: Partial<ArsipKuConfig> = {};
  for (const key of known) {
    if (source[key] !== undefined) {
      // The injected object is ours, so a direct assignment is safe here.
      out[key] = source[key] as never;
    }
  }
  return out;
}

export function getConfig(): ArsipKuConfig {
  return config;
}
