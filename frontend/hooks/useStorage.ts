/**
 * ArsipKu - storage and subscription hook.
 *
 * `usage` stays null while api/billing.php does not exist; useWorkspace publishes the
 * workspaces.php stats-derived meter, so the UI reads `storage.usage ?? workspace.usage`
 * rather than this hook guessing. A client-sent quota is never assembled here:
 * changePlan() sends the plan key only, and the server owns the limit.
 */

import type {
  ChangePlanRequest,
  Invoice,
  Plan,
  StorageUsage,
  Subscription,
  SubscriptionOverview,
} from '../types/subscription';
import type { Paginated } from '../types/api';
import { emit } from '../lib/events';
import { toApiError } from '../lib/errors';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { SubscriptionService } from '../services/subscription';

export interface StorageHook {
  overview: Resource<SubscriptionOverview | null>;
  plans: Resource<Plan[]>;
  invoices: Resource<Paginated<Invoice>>;
  usage: Store<StorageUsage | null>;
  subscription: Store<Subscription | null>;
  changePlan(request: ChangePlanRequest): Promise<boolean>;
  destroy(): void;
}

export function useStorage(subscription: SubscriptionService): StorageHook {
  const usage = createStore<StorageUsage | null>(null);
  const subscriptionState = createStore<Subscription | null>(null);

  const overview = createResource<SubscriptionOverview | null>(() => subscription.overview(), {
    keys: ['subscription', 'usage'],
  });
  const plans = createResource(() => subscription.plans(), { keys: ['subscription'] });
  const invoices = createResource(() => subscription.invoices({ per_page: 12 }), { keys: ['subscription'] });

  const unsubscribeOverview = overview.subscribe((state) => {
    if (!state.data) return;
    usage.set(state.data.usage);
    subscriptionState.set(state.data.subscription);
  });

  return {
    overview,
    plans,
    invoices,
    usage,
    subscription: subscriptionState,

    async changePlan(request) {
      try {
        const result = await subscription.changePlan(request);
        usage.set(result.usage);
        subscriptionState.set(result.subscription);
        emit('toast', {
          message: result.over_quota_warning
            ? `Paket diganti, tetapi penyimpanan melebihi kuota: ${result.over_quota_warning}`
            : 'Paket berhasil diganti.',
          type: result.over_quota_warning ? 'warning' : 'success',
        });
        void plans.reload();
        return true;
      } catch (err) {
        emit('toast', { message: toApiError(err).message, type: 'error' });
        return false;
      }
    },

    destroy() {
      unsubscribeOverview();
      overview.destroy();
      plans.destroy();
      invoices.destroy();
    },
  };
}
