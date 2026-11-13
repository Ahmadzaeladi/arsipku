/**
 * ArsipKu - permissions service.
 * Target endpoint: api/permissions.php (planned; docs/api-contract.md #8).
 *
 * The frontend reads capabilities; it never grants them. `effective()` is advisory
 * copy used to hide or dim controls, and every write the UI offers is still checked
 * by the server. A 403 is never retried or "worked around" here.
 */

import type {
  AccessGrant,
  CreateShareLinkRequest,
  EffectivePermissions,
  GrantAccessRequest,
  ListGrantsRequest,
  PermissionMatrix,
  RevokeAccessRequest,
  ResourceType,
  ShareLink,
  UpdateShareLinkRequest,
} from '../types/permission';
import type { DocumentRow } from '../types/document';
import type { VoidResult } from '../types/api';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import {
  asBool,
  asInt,
  asNullStr,
  asStr,
  normalizeArray,
  normalizeDocument,
} from '../lib/normalize';

export interface PermissionService {
  effective(resourceType: ResourceType, resourceId: string): Promise<EffectivePermissions>;
  grants(request: ListGrantsRequest): Promise<AccessGrant[]>;
  grant(request: GrantAccessRequest): Promise<AccessGrant>;
  revoke(request: RevokeAccessRequest): Promise<VoidResult>;
  /** Fallback used when api/permissions.php is absent: derive flags from the row. */
  fromDocumentRow(row: DocumentRow): EffectivePermissions;
  createShareLink(request: CreateShareLinkRequest): Promise<ShareLink>;
  updateShareLink(request: UpdateShareLinkRequest): Promise<VoidResult>;
  matrix(): Promise<PermissionMatrix>;
}

export function createHttpPermissionService(): PermissionService {
  return {
    async effective(resourceType, resourceId): Promise<EffectivePermissions> {
      const payload = await http.get<unknown>('permissions', 'effective', {
        resource_type: resourceType,
        resource_id: resourceId,
      });
      return normalizeEffective(resourceType, resourceId, readObject(payload));
    },

    async grants(request): Promise<AccessGrant[]> {
      const rows = await http.get<unknown>('permissions', 'grants', {
        resource_type: request.resource_type,
        resource_id: request.resource_id,
      });
      return normalizeArray(rows, normalizeGrant);
    },

    async grant(request): Promise<AccessGrant> {
      const { body } = await http.call('permissions', {
        action: 'grant',
        method: 'POST',
        body: {
          resource_type: request.resource_type,
          resource_id: request.resource_id,
          grantee_type: request.grantee_type,
          grantee_id: request.grantee_id ?? null,
          permission: request.permission,
          expires_at: request.expires_at ?? null,
          cascade: request.cascade ?? false,
        },
      });
      invalidate(request.resource_id);
      return normalizeGrant(readObject(body.data ?? body));
    },

    async revoke(request): Promise<VoidResult> {
      const { body } = await http.call('permissions', {
        action: 'revoke',
        method: 'POST',
        body: { grant_id: request.grant_id },
      });
      invalidate(request.grant_id);
      return { message: asStr(body.message, 'Akses dicabut.') };
    },

    /**
     * Today's only permission facts live on the document row: is_shared plus
     * shared_permission. This maps those to a conservative capability set - edit is
     * never implied, and workspace membership is not inferable from a row.
     */
    fromDocumentRow(row): EffectivePermissions {
      const document = normalizeDocument(row);
      return {
        resource_type: 'document',
        resource_id: document.id,
        role: null,
        permission: document.is_shared ? document.shared_permission : null,
        source: document.is_shared ? 'link' : 'none',
        capabilities: {
          view: true,
          download: true,
          share: false,
          rename: false,
          move: false,
          delete: false,
        },
        denied_actions: ['share', 'rename', 'move', 'delete', 'permanent_delete'],
      };
    },

    async createShareLink(request): Promise<ShareLink> {
      const { body } = await http.call('permissions', {
        action: 'link_create',
        method: 'POST',
        body: {
          resource_type: request.resource_type,
          resource_id: request.resource_id,
          permission: request.permission ?? 'view',
          expires_in_days: request.expires_in_days ?? null,
          password: request.password,
        },
      });
      return normalizeShareLink(readObject(body.data ?? body));
    },

    async updateShareLink(request): Promise<VoidResult> {
      const { body } = await http.call('permissions', {
        action: 'link_update',
        method: 'POST',
        body: {
          token: request.token,
          permission: request.permission,
          expires_at: request.expires_at ?? null,
          revoke: request.revoke ?? false,
        },
      });
      return { message: asStr(body.message, 'Tautan berbagi diperbarui.') };
    },

    async matrix(): Promise<PermissionMatrix> {
      const payload = readObject(await http.get<unknown>('permissions', 'matrix'));
      return {
        roles: normalizeArray(payload.roles, (row) => asStr(row.value ?? row) as PermissionMatrix['roles'][number]),
        actions: normalizeArray(payload.actions, (row) => asStr(row.value ?? row) as PermissionMatrix['actions'][number]),
        grants: readObject(payload.grants) as PermissionMatrix['grants'],
      };
    },
  };
}

export function normalizeEffective(
  resourceType: ResourceType,
  resourceId: string,
  row: Record<string, unknown>,
): EffectivePermissions {
  const capabilities = readObject(row.capabilities);
  return {
    resource_type: resourceType,
    resource_id: resourceId,
    role: row.role ? asStr(row.role) as EffectivePermissions['role'] : null,
    permission: row.permission === 'edit' ? 'edit' : row.permission === 'view' ? 'view' : null,
    source: (asStr(row.source) || 'none') as EffectivePermissions['source'],
    capabilities: Object.fromEntries(
      Object.entries(capabilities).map(([key, value]) => [key, asBool(value)]),
    ) as EffectivePermissions['capabilities'],
    denied_actions: normalizeArray(row.denied_actions, (entry) => asStr(entry.value ?? entry) as never),
  };
}

function normalizeGrant(row: Record<string, unknown>): AccessGrant {
  return {
    id: asStr(row.id),
    workspace_id: asStr(row.workspace_id),
    resource_type: asStr(row.resource_type) as ResourceType,
    resource_id: asStr(row.resource_id),
    grantee_type: asStr(row.grantee_type, 'member') as AccessGrant['grantee_type'],
    grantee_id: asNullStr(row.grantee_id),
    grantee_label: asStr(row.grantee_label),
    permission: row.permission === 'edit' ? 'edit' : 'view',
    granted_by: asStr(row.granted_by),
    granted_by_name: asStr(row.granted_by_name),
    granted_at: asStr(row.granted_at),
    expires_at: asNullStr(row.expires_at),
  };
}

function normalizeShareLink(row: Record<string, unknown>): ShareLink {
  return {
    token: asStr(row.token),
    url: asStr(row.url),
    resource_type: asStr(row.resource_type) as ResourceType,
    resource_id: asStr(row.resource_id),
    permission: row.permission === 'edit' ? 'edit' : 'view',
    is_public: asBool(row.is_public),
    password_protected: asBool(row.password_protected),
    expires_at: asNullStr(row.expires_at),
    access_count: asInt(row.access_count),
    created_at: asStr(row.created_at),
  };
}

function invalidate(key: string): void {
  emit('data:invalidated', { reason: 'permissions-changed', keys: [`permissions:${key}`] });
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
