/**
 * ArsipKu - authentication service.
 *
 * The identity of the caller is a server fact. The frontend only asks "who am I"
 * and caches the answer for the current page; it never infers identity from a
 * cookie, a localStorage blob, or a role string.
 *
 * The endpoint and user records are backed by MySQL; calls fail explicitly when
 * authentication is unavailable.
 */

import type { VoidResult } from '../types/api';
import type {
  AuthSession,
  AuthWorkspaceSummary,
  ChangePasswordRequest,
  ForgotPasswordRequest,
  LoginRequest,
  LoginResponse,
  RegisterRequest,
  ResetPasswordRequest,
  UpdateProfileRequest,
  User,
} from '../types/auth';
import { ApiClientError } from '../lib/errors';
import * as http from '../lib/http';
import { asStr, normalizeArray, normalizeRole, normalizeUser, normalizeWorkspaceType } from '../lib/normalize';

export interface AuthService {
  /** Never throws on 401: an anonymous session is a valid answer. */
  session(): Promise<AuthSession>;
  /** Throws when the backend has no session yet. Use session() for a soft check. */
  user(): Promise<User>;
  login(request: LoginRequest): Promise<LoginResponse>;
  register(request: RegisterRequest): Promise<LoginResponse>;
  logout(): Promise<VoidResult>;
  forgotPassword(request: ForgotPasswordRequest): Promise<VoidResult>;
  resetPassword(request: ResetPasswordRequest): Promise<VoidResult>;
  changePassword(request: ChangePasswordRequest): Promise<VoidResult>;
  updateProfile(request: UpdateProfileRequest): Promise<User>;
}

const UNAUTHENTICATED_SESSION: AuthSession = {
  authenticated: false,
  user: null,
  active_workspace: null,
  workspaces: [],
};

export function createHttpAuthService(): AuthService {
  // Auth calls are never workspace scoped: `ws: null` suppresses the context param.
  const unscoped = { ws: null } as const;

  return {
    async session(): Promise<AuthSession> {
      try {
        const payload = await http.get<Record<string, unknown>>('auth', 'me', undefined, unscoped);
        return normalizeSession(payload);
      } catch (err) {
        if (err instanceof ApiClientError && err.isAuthError) return { ...UNAUTHENTICATED_SESSION };
        throw err;
      }
    },

    async user(): Promise<User> {
      const session = await this.session();
      if (!session.user) {
        throw new ApiClientError({
          message: 'Sesi belum aktif. Silakan masuk.',
          status: 401,
          code: 'UNAUTHENTICATED',
        });
      }
      return session.user;
    },

    async login(request): Promise<LoginResponse> {
      await http.get('auth', 'csrf', undefined, unscoped);
      const body = await http.post('auth', 'login', {
        email: request.email.trim(),
        password: request.password,
        device_name: request.device_name,
        remember: request.remember ?? false,
      }, unscoped);
      return normalizeLoginResponse(body);
    },

    async register(request): Promise<LoginResponse> {
      const body = await http.post('auth', 'register', { ...request }, unscoped);
      return normalizeLoginResponse(body);
    },

    async logout(): Promise<VoidResult> {
      await http.get('auth', 'csrf', undefined, unscoped);
      const body = await http.post('auth', 'logout', {}, unscoped);
      return { message: asStr(body.message, 'Anda telah keluar.') };
    },

    async forgotPassword(request): Promise<VoidResult> {
      const body = await http.post('auth', 'forgot_password', { email: request.email }, unscoped);
      return { message: asStr(body.message, 'Tautan pemulihan dikirim bila email terdaftar.') };
    },

    async resetPassword(request): Promise<VoidResult> {
      const body = await http.post('auth', 'reset_password', { ...request }, unscoped);
      return { message: asStr(body.message, 'Kata sandi diperbarui.') };
    },

    async changePassword(request): Promise<VoidResult> {
      const body = await http.post('auth', 'change_password', { ...request });
      return { message: asStr(body.message, 'Kata sandi diperbarui.') };
    },

    async updateProfile(request): Promise<User> {
      const payload = await http.post('auth', 'update_profile', { ...request });
      return normalizeUser(readObject(payload.data));
    },
  };
}

function normalizeSession(payload: unknown): AuthSession {
  const source = readObject(payload);
  const user = source.user && typeof source.user === 'object' ? normalizeUser(source.user as Record<string, unknown>) : null;
  return {
    authenticated: source.authenticated === undefined ? !!user : source.authenticated !== false,
    user,
    active_workspace: source.active_workspace ? asStr(source.active_workspace) : null,
    workspaces: normalizeArray<AuthWorkspaceSummary>(source.workspaces, (row) => ({
      id: asStr(row.id),
      name: asStr(row.name),
      slug: asStr(row.slug),
      type: normalizeWorkspaceType(row.type),
      role: normalizeRole(row.user_role ?? row.role),
    })),
  };
}

function normalizeLoginResponse(body: Record<string, unknown>): LoginResponse {
  const payload = readObject(body.data ?? body);
  const user = payload.user && typeof payload.user === 'object'
    ? normalizeUser(payload.user as Record<string, unknown>)
    : normalizeUser({});
  return {
    user,
    active_workspace: asStr(payload.active_workspace ?? payload.ws),
    token: payload.access_token ? asStr(payload.access_token) : payload.token ? asStr(payload.token) : undefined,
    expires_in: typeof payload.expires_in === 'number' ? payload.expires_in : undefined,
  };
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
