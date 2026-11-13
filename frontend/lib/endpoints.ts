/**
 * ArsipKu - endpoint inventory and URL builders.
 *
 * `live`   = the PHP script exists today under /api.
 * `planned`= documented in docs/api-contract.md but not implemented yet.
 *            Calls to these endpoints fail explicitly; no mock data is substituted.
 *
 * Every scoped request carries the active workspace slug as `?ws=`.
 * WorkspaceService::getCurrentWorkspace() already honours `$_GET['ws']`, so the
 * param works against the current backend - but the server, never the client,
 * decides whether the caller may touch that workspace.
 */

import { getConfig } from './config';

export type EndpointStatus = 'live' | 'planned';

export interface EndpointDef {
  /** File name inside config.apiBase. */
  file: string;
  status: EndpointStatus;
  /** Short note about the gap for planned endpoints. */
  note?: string;
}

export const endpoints = {
  auth: { file: 'auth.php', status: 'live', note: 'MySQL-backed username/email and password-hash session login.' },
  workspaces: { file: 'workspaces.php', status: 'live' },
  members: { file: 'members.php', status: 'live' },
  documents: { file: 'documents.php', status: 'live' },
  folders: { file: 'folders.php', status: 'live' },
  upload: { file: 'upload.php', status: 'live' },
  download: { file: 'download.php', status: 'live', note: 'Authenticated downloads use the active workspace and document grants.' },
  versions: { file: 'versions.php', status: 'planned', note: 'document_versions table does not exist yet.' },
  permissions: { file: 'permissions.php', status: 'planned', note: 'Capabilities are inferred from is_shared/shared_permission only.' },
  favorites: { file: 'favorites.php', status: 'planned', note: 'Stars live on documents.is_favorite, shared across all members.' },
  search: { file: 'search.php', status: 'planned', note: 'Search currently piggybacks on documents.php?q= with LIKE.' },
  trash: { file: 'trash.php', status: 'planned', note: 'Trash operations go through documents.php actions today.' },
  billing: { file: 'billing.php', status: 'planned', note: 'Plan changes go through workspaces.php?action=change_plan and accept limit_bytes.' },
  activities: { file: 'activities.php', status: 'planned', note: 'Activity feed is server-rendered only, never exposed as JSON.' },
} satisfies Record<string, EndpointDef>;

export type EndpointName = keyof typeof endpoints;

/** Actions the current PHP scripts recognise. Keep in sync with api/*.php. */
export const legacyActions = {
  workspaces: ['list', 'current', 'switch', 'update', 'create', 'change_plan', 'delete'],
  members: ['list', 'invite', 'update_role', 'remove'],
  documents_get: ['list', 'detail', 'share_info'],
  documents_post: [
    'rename', 'move', 'toggle_favorite', 'delete', 'restore',
    'permanent_delete', 'empty_trash', 'multi_action', 'share',
  ],
  folders_get: ['list', 'breadcrumbs', 'all_flat'],
  folders_post: ['create', 'rename', 'delete'],
} as const;

export function endpointPath(name: EndpointName): string {
  const base = getConfig().apiBase.replace(/\/+$/, '');
  return `${base}/${endpoints[name].file}`;
}

export function endpointStatus(name: EndpointName): EndpointStatus {
  return endpoints[name].status;
}

/**
 * Serialises params. Undefined/null values are dropped, booleans become the
 * `1`/omitted form the PHP endpoints expect (`!empty($_GET['favorite'])`).
 */
export function toQuery(params?: Record<string, unknown> | undefined): string {
  if (!params) return '';
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue;
    if (typeof value === 'boolean') {
      if (value) search.set(key, '1');
      continue;
    }
    if (Array.isArray(value)) {
      for (const item of value) search.append(key, String(item));
      continue;
    }
    if (value instanceof Date) {
      search.set(key, value.toISOString());
      continue;
    }
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : '';
}

/** Absolute-ish URL for a GET request. `ws` is always appended when known. */
export function buildUrl(
  name: EndpointName,
  action: string,
  params?: Record<string, unknown>,
): string {
  return `${endpointPath(name)}${toQuery({ action, ...params })}`;
}

/** POST targets keep the action in the query too, so routers can read either. */
export function buildPostUrl(name: EndpointName, action: string, ws?: string | null): string {
  return `${endpointPath(name)}${toQuery({ action, ws })}`;
}
