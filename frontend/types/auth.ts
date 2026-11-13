/**
 * ArsipKu - Authentication models.
 * Account and session data are loaded from MySQL through api/auth.php
 * (see docs/api-contract.md, section 1).
 */

/** Account holder. `id` is the workspace-agnostic user id (`usr_...`). */
export interface User {
  id: string;
  name: string;
  email: string;
  /** Display label from the legacy profile, e.g. "Lead Architect & Owner". */
  title?: string;
  avatar_url: string;
  email_verified_at: string | null;
  created_at?: string;
  updated_at?: string;
}

/** Returned by GET /api/auth.php?action=me. */
export interface AuthSession {
  authenticated: boolean;
  user: User | null;
  /** Slug of the workspace the server resolved for this session. */
  active_workspace: string | null;
  /** Workspaces the user can enter. Server-filtered, never client-decided. */
  workspaces: AuthWorkspaceSummary[];
}

export interface AuthWorkspaceSummary {
  id: string;
  name: string;
  slug: string;
  type: 'personal' | 'company' | 'school';
  /** Role the server holds for this user in this workspace. */
  role: MemberRoleName;
}

export type MemberRoleName = 'Owner' | 'Admin' | 'Editor' | 'Viewer';

export interface LoginRequest {
  email: string;
  password: string;
  /** Optional, forwarded for a future devices table. */
  device_name?: string;
  remember?: boolean;
}

export interface LoginResponse {
  user: User;
  active_workspace: string;
  /**
   * Session transport is a SameSite=Lax httpOnly cookie. `token` is only
   * present when the backend runs in bearer mode; never store it in
   * localStorage when the cookie flow is available.
   */
  token?: string;
  expires_in?: number;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  /** Creates the first workspace owned by the new user when supplied. */
  workspace_name?: string;
  workspace_type?: 'personal' | 'company' | 'school';
}

export interface ForgotPasswordRequest {
  email: string;
}

export interface ResetPasswordRequest {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ChangePasswordRequest {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface UpdateProfileRequest {
  name?: string;
  email?: string;
  title?: string;
  avatar_url?: string;
}
