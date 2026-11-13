/**
 * ArsipKu - subscription and storage usage service.
 * Target endpoint: api/billing.php (planned; docs/api-contract.md #12).
 *
 * Today the only live path is api/workspaces.php?action=stats (capacity numbers)
 * and ?action=change_plan, and the latter lets the browser post its own
 * `limit_bytes`. That is the exact behaviour this service refuses to reproduce:
 * ChangePlanRequest carries a plan key only, and quota is whatever the server
 * derives from that plan. Until billing.php exists requests for this domain fail;
 * the UI must not depend on browser-supplied quota values or synthetic responses.
 */

import type { Paginated } from '../types/api';
import type {
  ChangePlanRequest,
  ChangePlanResult,
  Invoice,
  Plan,
  PlanKey,
  StorageBreakdownItem,
  StorageUsage,
  Subscription,
  SubscriptionOverview,
} from '../types/subscription';
import type { WorkspaceStats } from '../types/workspace';
import { ApiClientError } from '../lib/errors';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import { asBool, asFloat, asInt, asNullStr, asStr, normalizeArray, paginate } from '../lib/normalize';
import { getWorkspaceSlug } from '../lib/workspaceContext';

export interface SubscriptionService {
  overview(): Promise<SubscriptionOverview>;
  subscription(): Promise<Subscription>;
  usage(): Promise<StorageUsage>;
  plans(): Promise<Plan[]>;
  changePlan(request: ChangePlanRequest): Promise<ChangePlanResult>;
  invoices(params?: { page?: number; per_page?: number }): Promise<Paginated<Invoice>>;
  /** Live adapter: the numbers WorkspaceService::getStats() already returns. */
  fromStats(stats: WorkspaceStats): StorageUsage;
}

/** Catalogue the three seeded plans map onto. Used only for the live adapter. */
const PLAN_BY_NAME: Record<string, PlanKey> = {
  'Personal Pro': 'personal_pro',
  'Enterprise Business': 'enterprise_business',
  'Edu Institution': 'edu_institution',
};

export function createHttpSubscriptionService(): SubscriptionService {
  return {
    async overview(): Promise<SubscriptionOverview> {
      const payload = readObject(await http.get<unknown>('billing', 'overview'));
      return {
        subscription: normalizeSubscription(payload.subscription),
        usage: normalizeUsage(payload.usage),
        plans: normalizeArray(payload.plans, normalizePlan),
        invoices: normalizeArray(payload.invoices, normalizeInvoice),
      };
    },

    async subscription(): Promise<Subscription> {
      return normalizeSubscription(readObject(await http.get<unknown>('billing', 'subscription')));
    },

    async usage(): Promise<StorageUsage> {
      return normalizeUsage(readObject(await http.get<unknown>('billing', 'usage')));
    },

    async plans(): Promise<Plan[]> {
      const rows = await http.get<unknown>('billing', 'plans', undefined, { ws: null });
      return normalizeArray(rows, normalizePlan);
    },

    async changePlan(request): Promise<ChangePlanResult> {
      if (!request.plan_key) {
        throw new ApiClientError({
          message: 'Pilih paket lebih dulu sebelum mengirim perubahan.',
          code: 'VALIDATION_FAILED',
          status: 422,
          endpoint: 'billing.php?action=change_plan',
        });
      }
      const { body } = await http.call('billing', {
        action: 'change_plan',
        method: 'POST',
        ws: request.ws ?? getWorkspaceSlug() ?? null,
        body: {
          plan_key: request.plan_key,
          billing_cycle: request.billing_cycle ?? null,
          idempotency_key: request.idempotency_key ?? null,
        },
      });
      emit('data:invalidated', { reason: 'plan-changed', keys: ['subscription', 'usage'] });
      const payload = readObject(body.data ?? body);
      return {
        subscription: normalizeSubscription(payload.subscription),
        usage: normalizeUsage(payload.usage),
        over_quota_warning: asNullStr(payload.over_quota_warning),
      };
    },

    async invoices(params): Promise<Paginated<Invoice>> {
      const rows = await http.get<unknown>('billing', 'invoices', {
        page: params?.page,
        per_page: params?.per_page,
      });
      const items = normalizeArray(rows, normalizeInvoice);
      return paginate(items, null, params?.page ?? 1, params?.per_page ?? (items.length || 12));
    },

    fromStats(stats): StorageUsage {
      return usageFromStats(stats);
    },
  };
}

/* ------------------------------------------------------------------ normalisers */

export function normalizeSubscription(value: unknown): Subscription {
  const row = readObject(value);
  const planName = asStr(row.plan_name);
  return {
    workspace_id: asStr(row.workspace_id),
    plan_key: normalizePlanKey(row.plan_key ?? PLAN_BY_NAME[planName] ?? row.plan_key),
    plan_name: planName,
    status: normalizeStatus(row.status),
    billing_cycle: row.billing_cycle === 'yearly' ? 'yearly' : 'monthly',
    current_period_start: asStr(row.current_period_start),
    current_period_end: asStr(row.current_period_end),
    cancel_at_period_end: asBool(row.cancel_at_period_end),
    seat_count: asInt(row.seat_count),
    seat_limit: row.seat_limit === null || row.seat_limit === undefined ? null : asInt(row.seat_limit),
    payment_method_label: asNullStr(row.payment_method_label),
    amount_due_idr: row.amount_due_idr === undefined ? undefined : asInt(row.amount_due_idr),
  };
}

export function normalizePlan(value: unknown): Plan {
  const row = readObject(value);
  return {
    key: normalizePlanKey(row.key ?? row.plan_key),
    name: asStr(row.name),
    description: asStr(row.description),
    storage_limit_bytes: Math.max(0, asInt(row.storage_limit_bytes)),
    max_members: row.max_members === null || row.max_members === undefined ? null : asInt(row.max_members),
    max_upload_bytes: Math.max(0, asInt(row.max_upload_bytes)),
    price_monthly_idr: asInt(row.price_monthly_idr),
    price_yearly_idr: asInt(row.price_yearly_idr),
    currency: 'IDR',
    features: normalizeArray(row.features, (entry) => asStr(entry.value ?? entry)),
    is_current: asBool(row.is_current),
    available_for_workspace_type: normalizeArray(
      row.available_for_workspace_type,
      (entry) => asStr(entry.value ?? entry),
    ),
  };
}

export function normalizeInvoice(value: unknown): Invoice {
  const row = readObject(value);
  const status = asStr(row.status).toLowerCase();
  return {
    id: asStr(row.id),
    number: asStr(row.number),
    period_start: asStr(row.period_start),
    period_end: asStr(row.period_end),
    amount_idr: asInt(row.amount_idr),
    status: (status === 'open' || status === 'void' || status === 'refunded' ? status : 'paid') as Invoice['status'],
    issued_at: asStr(row.issued_at),
    download_url: asNullStr(row.download_url),
  };
}

export function normalizeUsage(value: unknown): StorageUsage {
  const row = readObject(value);
  const used = Math.max(0, asInt(row.used_bytes ?? row.total_size_bytes));
  const limit = Math.max(0, asInt(row.limit_bytes ?? row.storage_limit_bytes));
  const percentage = row.usage_percentage === undefined
    ? (limit > 0 ? (used / limit) * 100 : 0)
    : asFloat(row.usage_percentage);
  return {
    used_bytes: used,
    limit_bytes: limit,
    remaining_bytes: row.remaining_bytes === undefined ? Math.max(0, limit - used) : asInt(row.remaining_bytes),
    usage_percentage: percentage,
    is_warning: row.is_warning === undefined ? percentage >= 80 : asBool(row.is_warning),
    is_over_quota: row.is_over_quota === undefined ? percentage > 100 : asBool(row.is_over_quota),
    trash_included: asBool(row.trash_included),
    trash_bytes: asInt(row.trash_bytes),
    breakdown: normalizeArray(row.breakdown, normalizeBreakdown),
    largest_documents: normalizeArray(row.largest_documents, (entry) => ({
      id: asStr(entry.id),
      name: asStr(entry.name),
      size_bytes: asInt(entry.size_bytes),
    })),
  };
}

function normalizeBreakdown(value: unknown): StorageBreakdownItem {
  const row = readObject(value);
  return {
    kind: asStr(row.kind) as StorageBreakdownItem['kind'],
    label: asStr(row.label),
    bytes: Math.max(0, asInt(row.bytes)),
    document_count: asInt(row.document_count),
    share: asFloat(row.share),
  };
}

function normalizePlanKey(value: unknown): PlanKey {
  const raw = asStr(value).toLowerCase().replace(/[\s-]+/g, '_');
  return raw === 'personal_pro' || raw === 'edu_institution' ? raw as PlanKey : 'enterprise_business';
}

function normalizeStatus(value: unknown): Subscription['status'] {
  const raw = asStr(value).toLowerCase();
  const known: Subscription['status'][] = ['active', 'trialing', 'past_due', 'cancelled', 'expired', 'unpaid'];
  return known.includes(raw as Subscription['status']) ? raw as Subscription['status'] : 'active';
}

/**
 * Adapter for the live stats envelope. It can only report totals: no trash
 * split, no per-kind breakdown, so those stay empty rather than faked.
 */
export function usageFromStats(stats: WorkspaceStats): StorageUsage {
  return {
    used_bytes: stats.total_size_bytes,
    limit_bytes: stats.storage_limit_bytes,
    remaining_bytes: Math.max(0, stats.storage_limit_bytes - stats.total_size_bytes),
    usage_percentage: stats.usage_percentage,
    is_warning: stats.is_warning,
    is_over_quota: stats.usage_percentage > 100,
    trash_included: false,
    trash_bytes: 0,
    breakdown: [],
  };
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
