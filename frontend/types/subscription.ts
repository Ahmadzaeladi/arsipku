/**
 * ArsipKu - Subscription and storage usage models.
 * Contract note: api/workspaces.php?action=change_plan currently accepts
 * `limit_bytes` from the browser. That must be removed - the server derives quota
 * from the plan, so ChangePlanRequest below only carries `plan_key`.
 */

import type { WorkspaceScopedParams } from './api';
import type { FileKind } from './document';

export type PlanKey = 'personal_pro' | 'enterprise_business' | 'edu_institution';

export type BillingCycle = 'monthly' | 'yearly';

export type SubscriptionStatus =
  | 'active' | 'trialing' | 'past_due' | 'cancelled' | 'expired' | 'unpaid';

export interface Plan {
  key: PlanKey;
  name: string;
  description: string;
  storage_limit_bytes: number;
  max_members: number | null;
  max_upload_bytes: number;
  price_monthly_idr: number;
  price_yearly_idr: number;
  currency: 'IDR';
  features: string[];
  /** Set by the server for the workspace that currently holds this plan. */
  is_current: boolean;
  available_for_workspace_type: string[];
}

export interface Subscription {
  workspace_id: string;
  plan_key: PlanKey;
  plan_name: string;
  status: SubscriptionStatus;
  billing_cycle: BillingCycle;
  current_period_start: string;
  current_period_end: string;
  cancel_at_period_end: boolean;
  seat_count: number;
  seat_limit: number | null;
  payment_method_label?: string | null;
  /** IDR minor units are plain rupiah integers in this app. */
  amount_due_idr?: number;
}

export interface StorageBreakdownItem {
  kind: FileKind;
  label: string;
  bytes: number;
  document_count: number;
  /** 0-1 share of used_bytes, computed by the server. */
  share: number;
}

/** Superset of WorkspaceStats limited to capacity: GET /api/billing.php?action=usage */
export interface StorageUsage {
  used_bytes: number;
  limit_bytes: number;
  remaining_bytes: number;
  usage_percentage: number;
  is_warning: boolean;
  is_over_quota: boolean;
  /** Trash rows may or may not count against quota; the server states which. */
  trash_included: boolean;
  trash_bytes: number;
  breakdown: StorageBreakdownItem[];
  /** Documents larger than the threshold, newest first. */
  largest_documents?: { id: string; name: string; size_bytes: number }[];
}

export interface ChangePlanRequest extends WorkspaceScopedParams {
  plan_key: PlanKey;
  billing_cycle?: BillingCycle;
  /** Client-supplied quota is rejected with VALIDATION_FAILED if present. */
  idempotency_key?: string;
}

export interface ChangePlanResult {
  subscription: Subscription;
  usage: StorageUsage;
  /** Present when the workspace already exceeds the new, smaller quota. */
  over_quota_warning?: string | null;
}

export interface Invoice {
  id: string;
  number: string;
  period_start: string;
  period_end: string;
  amount_idr: number;
  status: 'paid' | 'open' | 'void' | 'refunded';
  issued_at: string;
  download_url?: string | null;
}

export interface SubscriptionOverview {
  subscription: Subscription;
  usage: StorageUsage;
  plans: Plan[];
  invoices: Invoice[];
}
