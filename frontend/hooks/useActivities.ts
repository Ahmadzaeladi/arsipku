/**
 * ArsipKu - activity feed hook.
 *
 * There is no JSON activity endpoint yet (activities are server-rendered inside
 * views), so this reads the planned api/activities.php and fails until it lands.
 * Nothing here re-derives a description: `description` is the server's sentence.
 */

import type { Activity } from '../types/activity';
import type { ActivityListParams } from '../types/activity';
import type { Paginated } from '../types/api';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { ActivityService } from '../services/activities';

export interface ActivitiesHook {
  page: Resource<Paginated<Activity>>;
  recent: Resource<Activity[]>;
  filters: Store<ActivityListParams>;
  setFilters(patch: Partial<ActivityListParams>): Promise<void>;
  setPage(page: number): Promise<void>;
  destroy(): void;
}

export function useActivities(activities: ActivityService, initial: ActivityListParams = {}): ActivitiesHook {
  const filters = createStore<ActivityListParams>({ per_page: 20, ...initial });
  const recent = createResource(() => activities.recent(8), { keys: ['activities'] });

  const page = createResource(() => activities.list(filters.get()), {
    keys: ['activities', 'documents'],
    immediate: false,
  });

  async function refetch(next: ActivityListParams): Promise<void> {
    filters.set(next);
    await page.refocus(() => activities.list(next));
  }

  return {
    page,
    recent,
    filters,

    setFilters: (patch) => refetch({ ...filters.get(), ...patch, page: 1 }),
    setPage: (page2) => refetch({ ...filters.get(), page: page2 }),

    destroy() {
      page.destroy();
      recent.destroy();
    },
  };
}
