/**
 * ArsipKu - members hook.
 *
 * Role names are display metadata; the server decides what a role may do. All
 * invitation, link, role, and removal writes are enforced by the PHP API.
 */

import type {
  AssignableRole,
  InviteMemberRequest,
  InviteMemberResult,
  Member,
  MemberListParams,
  RemoveMemberRequest,
} from '../types/member';
import type { Paginated } from '../types/api';
import { emit } from '../lib/events';
import { toApiError } from '../lib/errors';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { MemberService } from '../services/members';

export interface MembersHook {
  page: Resource<Paginated<Member>>;
  filters: Store<MemberListParams>;
  busy: Store<boolean>;
  invite(request: InviteMemberRequest): Promise<InviteMemberResult | null>;
  updateRole(id: string, role: AssignableRole): Promise<boolean>;
  remove(request: RemoveMemberRequest): Promise<boolean>;
  destroy(): void;
}

export function useMembers(members: MemberService, initial: MemberListParams = {}): MembersHook {
  const filters = createStore<MemberListParams>({ per_page: 20, ...initial });
  const busy = createStore(false);
  const page = createResource(() => members.list(filters.get()), { keys: ['members', 'workspace-stats'] });

  async function write<T>(run: () => Promise<T>): Promise<T | null> {
    busy.set(true);
    try {
      return await run();
    } catch (err) {
      emit('toast', { message: toApiError(err).message, type: 'error' });
      return null;
    } finally {
      busy.set(false);
    }
  }

  return {
    page,
    filters,
    busy,

    async invite(request) {
      const result = await write(() => members.invite(request));
      if (result) emit('toast', { message: `Undangan untuk ${result.invitation.email} terkirim.`, type: 'success' });
      return result;
    },

    async updateRole(id, role) {
      return (await write(() => members.updateRole({ id, role }))) !== null;
    },

    async remove(request) {
      return (await write(() => members.remove(request))) !== null;
    },

    destroy: () => page.destroy(),
  };
}
