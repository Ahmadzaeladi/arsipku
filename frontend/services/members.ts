/**
 * ArsipKu - workspace member service.
 * Target endpoint: api/members.php (live, action-style).
 *
 * Roles are the vocabulary the UI shows, never the rule it enforces: member
 * administration and invite-link writes are rejected unless the caller is an
 * active Owner/Admin; the Owner row is protected in SQL.
 */

import type { Paginated, VoidResult } from '../types/api';
import type {
  InviteMemberRequest,
  InviteMemberResult,
  Member,
  MemberListParams,
  RemoveMemberRequest,
  UpdateMemberRoleRequest,
} from '../types/member';
import { ApiClientError } from '../lib/errors';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import { asStr, normalizeArray, normalizeMember } from '../lib/normalize';

export interface MemberService {
  list(params?: MemberListParams): Promise<Paginated<Member>>;
  invite(request: InviteMemberRequest): Promise<InviteMemberResult>;
  updateRole(request: UpdateMemberRoleRequest): Promise<VoidResult>;
  remove(request: RemoveMemberRequest): Promise<VoidResult>;
}

export function createHttpMemberService(): MemberService {
  return {
    async list(params = {}): Promise<Paginated<Member>> {
      const rows = await http.get<unknown>('members', 'list', {
        q: params.q,
        role: params.role,
        status: params.status,
        page: params.page,
        per_page: params.per_page,
      });
      const items = normalizeArray(rows, normalizeMember);
      const total = items.length;
      return {
        items,
        meta: {
          page: params.page ?? 1,
          per_page: params.per_page ?? items.length,
          total,
          total_pages: 1,
          has_more: false,
        },
      };
    },

    async invite(request): Promise<InviteMemberResult> {
      const { body } = await http.call('members', {
        action: 'invite',
        method: 'POST',
        body: { email: request.email.trim(), role: request.role },
      });
      invalidate('members');
      const payload = readObject(body.data);
      const role = payload.role;
      if (typeof payload.id !== 'string'
        || typeof payload.workspace_id !== 'string'
        || typeof payload.invitee_user_id !== 'string'
        || typeof payload.name !== 'string'
        || typeof payload.email !== 'string'
        || (role !== 'Admin' && role !== 'Editor' && role !== 'Viewer')
        || payload.status !== 'pending') {
        throw new ApiClientError({
          message: 'Respons undangan dari server tidak valid.',
          status: 502,
          code: 'MALFORMED_RESPONSE',
        });
      }
      return {
        invitation: {
          id: payload.id,
          workspace_id: payload.workspace_id,
          invitee_user_id: payload.invitee_user_id,
          name: payload.name,
          email: payload.email,
          role,
          source: 'email',
          status: 'pending',
          created_at: payload.created_at ? asStr(payload.created_at) : undefined,
        },
      };
    },

    async updateRole(request): Promise<VoidResult> {
      const { body } = await http.call('members', {
        action: 'update_role',
        method: 'POST',
        body: { id: request.id, role: request.role },
      });
      invalidate('members');
      return { message: asStr(body.message, 'Peran anggota berhasil diperbarui.') };
    },

    async remove(request): Promise<VoidResult> {
      const { body } = await http.call('members', {
        action: 'remove',
        method: 'POST',
        body: { id: request.id },
      });
      invalidate('members');
      return { message: asStr(body.message, 'Anggota berhasil dihapus dari workspace.') };
    },
  };
}

function invalidate(key: string): void {
  emit('data:invalidated', { reason: 'members-changed', keys: [key] });
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
