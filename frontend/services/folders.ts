/**
 * ArsipKu - folder service.
 * Target endpoint: api/folders.php (live, action-style).
 *
 * `move()` maps to an action the script does not implement yet (it currently accepts
 * create/rename/delete only). Until the router grows, move requests fail explicitly.
 */

import type { VoidResult } from '../types/api';
import type {
  Breadcrumb,
  CreateFolderRequest,
  DeleteFolderRequest,
  Folder,
  FolderFlat,
  FolderListParams,
  FolderTreeNode,
  MoveFolderRequest,
  RenameFolderRequest,
} from '../types/folder';
import { emit } from '../lib/events';
import * as http from '../lib/http';
import { asStr, normalizeArray, normalizeFolder, normalizeFolderFlat } from '../lib/normalize';

export interface FolderService {
  /** Direct children of `parent_id`. Omit or 'root' for the top level. */
  list(params?: FolderListParams): Promise<Folder[]>;
  /** Every non-trashed folder, flat: used to build pickers and trees. */
  allFlat(): Promise<FolderFlat[]>;
  breadcrumbs(folderId?: string | null): Promise<Breadcrumb[]>;
  create(request: CreateFolderRequest): Promise<Folder>;
  rename(request: RenameFolderRequest): Promise<VoidResult>;
  move(request: MoveFolderRequest): Promise<VoidResult>;
  remove(request: DeleteFolderRequest): Promise<VoidResult>;
  /** Nested navigation structure derived from allFlat(). */
  tree(): Promise<FolderTreeNode[]>;
}

export function createHttpFolderService(): FolderService {
  const flatCacheKey = 'folders-flat';

  return {
    async list(params = {}): Promise<Folder[]> {
      const rows = await http.get<unknown>('folders', 'list', {
        parent_id: params.parent_id ?? 'root',
        page: params.page,
        per_page: params.per_page,
      });
      return normalizeArray(rows, normalizeFolder);
    },

    async allFlat(): Promise<FolderFlat[]> {
      const rows = await http.get<unknown>('folders', 'all_flat');
      return normalizeArray(rows, normalizeFolderFlat);
    },

    async breadcrumbs(folderId = null): Promise<Breadcrumb[]> {
      const rows = await http.get<unknown>('folders', 'breadcrumbs', {
        folder_id: folderId ?? undefined,
      });
      return normalizeArray<Breadcrumb>(rows, (row) => ({
        id: asStr(row.id),
        name: asStr(row.name),
        parent_id: row.parent_id === null || row.parent_id === undefined ? null : asStr(row.parent_id),
      }));
    },

    async create(request): Promise<Folder> {
      const { body } = await http.call('folders', {
        action: 'create',
        method: 'POST',
        body: {
          name: request.name.trim(),
          parent_id: request.parent_id ?? null,
          color: request.color ?? 'indigo',
        },
      });
      invalidate(flatCacheKey);
      return normalizeFolder(readObject(body.data));
    },

    async rename(request): Promise<VoidResult> {
      const { body } = await http.call('folders', {
        action: 'rename',
        method: 'POST',
        body: { id: request.id, name: request.name.trim() },
      });
      invalidate(flatCacheKey);
      return { message: asStr(body.message, 'Nama folder berhasil diubah.') };
    },

    async move(request): Promise<VoidResult> {
      const { body } = await http.call('folders', {
        action: 'move',
        method: 'POST',
        body: { id: request.id, parent_id: request.parent_id ?? 'root' },
      });
      invalidate(flatCacheKey);
      return { message: asStr(body.message, 'Folder berhasil dipindahkan.') };
    },

    async remove(request): Promise<VoidResult> {
      const { body } = await http.call('folders', {
        action: 'delete',
        method: 'POST',
        body: { id: request.id, cascade: request.cascade ?? true },
      });
      invalidate(flatCacheKey);
      return { message: asStr(body.message, 'Folder dipindahkan ke Sampah.') };
    },

    async tree(): Promise<FolderTreeNode[]> {
      return buildTree(await this.allFlat());
    },
  };
}

/**
 * Builds the navigation tree from the flat folder list. Counts are deliberately
 * left out: a client-side count would need a request per folder, so list() supplies
 * `doc_count` per level instead.
 */
export function buildTree(flat: FolderFlat[]): FolderTreeNode[] {
  const nodes = new Map<string, FolderTreeNode>();
  for (const folder of flat) {
    nodes.set(folder.id, { id: folder.id, name: folder.name, parent_id: folder.parent_id, children: [], depth: 0 });
  }

  const roots: FolderTreeNode[] = [];
  for (const folder of flat) {
    const node = nodes.get(folder.id);
    if (!node) continue;
    const parent = folder.parent_id ? nodes.get(folder.parent_id) : undefined;
    if (parent) {
      node.depth = parent.depth + 1;
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }

  for (const node of roots) sortInPlace(node);
  return roots;
}

function sortInPlace(node: FolderTreeNode): void {
  node.children.sort((a, b) => a.name.localeCompare(b.name, 'id'));
  for (const child of node.children) sortInPlace(child);
}

function invalidate(key: string): void {
  emit('data:invalidated', { reason: 'folder-changed', keys: [key] });
}

function readObject(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}
