/**
 * ArsipKu - session hook.
 *
 * Identity is read once at boot and then only by explicit actions: the backend has
 * no push channel, and `session()` is cheap. A 401 anywhere in the app arrives as an
 * `auth:expired` event, which this hook re-broadcasts so the UI can show one dialog
 * instead of a stack of toasts.
 */

import type { AuthSession, LoginRequest, RegisterRequest, User } from '../types/auth';
import type { ApiClientError } from '../lib/errors';
import { toApiError } from '../lib/errors';
import { on } from '../lib/events';
import { createStore, type Store } from './reactive';
import type { AuthService } from '../services/auth';

export interface SessionState {
  status: 'unknown' | 'anonymous' | 'authenticated';
  session: AuthSession | null;
  error: ApiClientError | null;
}

export interface SessionHook {
  state: Store<SessionState>;
  bootstrap(): Promise<SessionState>;
  login(request: LoginRequest): Promise<boolean>;
  register(request: RegisterRequest): Promise<boolean>;
  logout(): Promise<void>;
  updateProfile(request: { name?: string; title?: string; avatar_url?: string }): Promise<User | null>;
  /** The UI should open its expired-dialog from here, not from a toast. */
  onExpired(listener: () => void): () => void;
}

export function useSession(auth: AuthService): SessionHook {
  const state = createStore<SessionState>({ status: 'unknown', session: null, error: null });

  async function read(): Promise<SessionState> {
    try {
      const session = await auth.session();
      const next: SessionState = {
        status: session.authenticated && session.user ? 'authenticated' : 'anonymous',
        session,
        error: null,
      };
      state.set(next);
      return next;
    } catch (err) {
      const error = toApiError(err, 'auth.php?action=session');
      // A dead session is still an answer: the login screen is the right destination.
      const next: SessionState = { status: 'anonymous', session: null, error };
      state.set(next);
      return next;
    }
  }

  return {
    state,
    bootstrap: read,

    async login(request) {
      try {
        await auth.login(request);
        await read();
        return true;
      } catch (err) {
        state.update((current) => ({ ...current, error: toApiError(err, 'auth.php?action=login') }));
        return false;
      }
    },

    async register(request) {
      try {
        await auth.register(request);
        await read();
        return true;
      } catch (err) {
        state.update((current) => ({ ...current, error: toApiError(err, 'auth.php?action=register') }));
        return false;
      }
    },

    async logout() {
      await auth.logout();
      state.set({ status: 'anonymous', session: null, error: null });
    },

    async updateProfile(request) {
      try {
        const user = await auth.updateProfile(request);
        // Keep the rendered identity in step with the write.
        const current = state.get().session;
        state.set({
          status: 'authenticated',
          session: current ? { ...current, user } : null,
          error: null,
        });
        return user;
      } catch (err) {
        state.update((c) => ({ ...c, error: toApiError(err, 'auth.php?action=update_profile') }));
        return null;
      }
    },

    onExpired(listener) {
      return on('auth:expired', () => listener());
    },
  };
}
