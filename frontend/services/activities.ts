/**
 * ArsipKu - activity feed service.
 * Target endpoint: api/activities.php (planned; docs/api-contract.md #13).
 *
 * There is no JSON feed today: DocumentService::getRecentActivities() is read by
 * the PHP templates and never reaches the browser as data. Writing against the
 * request fails explicitly until the API exists - the UI must not silently invent
 * a feed from page HTML.
 */

import type { Paginated, PaginationMeta } from '../types/api';
import type { Activity, ActivityAction, ActivityListParams } from '../types/activity';
import * as http from '../lib/http';
import { normalizeActivity, normalizeArray, paginate } from '../lib/normalize';
import { getWorkspaceSlug } from '../lib/workspaceContext';

export interface ActivityService {
  list(params?: ActivityListParams): Promise<Paginated<Activity>>;
  recent(limit?: number): Promise<Activity[]>;
}

/** Actions the seeded PHP services can emit today; anything else is contract-only. */
const KNOWN_ACTIONS: readonly string[] = [
  'upload', 'rename', 'folder_create', 'delete', 'favorite', 'share', 'restore', 'move',
  'version_upload', 'version_restore', 'permission_change',
  'member_invite', 'member_role_change', 'member_remove',
  'plan_change', 'workspace_create', 'workspace_delete', 'permanent_delete',
];

export function createHttpActivityService(): ActivityService {
  async function list(params: ActivityListParams = {}): Promise<Paginated<Activity>> {
    const perPage = params.per_page ?? params.limit ?? 20;
    const { body } = await http.call('activities', {
      action: 'list',
      method: 'GET',
      ws: params.ws ?? getWorkspaceSlug() ?? null,
      params: {
        actions: params.actions?.length ? params.actions.join(',') : undefined,
        user_id: params.user_id,
        since: params.since,
        page: params.page,
        per_page: perPage,
      },
    });
    const items = normalizeArray(body.data, normalizeActivity);
    // Without a real feed the total is unknown, so report what was returned.
    const meta: Partial<PaginationMeta> = { page: params.page ?? 1, per_page: perPage, total: items.length };
    return paginate(items, meta);
  }

  return {
    list,
    async recent(limit = 8): Promise<Activity[]> {
      return (await list({ limit })).items;
    },
  };
}

export function isKnownActivityAction(value: string): value is ActivityAction {
  return KNOWN_ACTIONS.includes(value);
}
