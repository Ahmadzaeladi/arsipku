/**
 * ArsipKu - folders hook.
 *
 * The tree is derived client-side from allFlat() (services/folders.ts), so one
 * request feeds the sidebar, the picker, and the breadcrumb trail. Writes rely on
 * the services' own `data:invalidated` events to refetch - the hook never double-fires.
 */

import type {
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
import { toApiError } from '../lib/errors';
import { createResource, createStore, type Resource, type Store } from './reactive';
import type { FolderService } from '../services/folders';

/** Both payload keys returned by the folder API. */
const FOLDER_KEYS = ['folders', 'folders-flat'];

export interface FoldersHook {
  tree: Resource<FolderTreeNode[]>;
  flat: Resource<FolderFlat[]>;
  /** Direct children of the folder the view is currently inside. */
  children: Resource<Folder[]>;
  currentFolder: Store<string | null>;
  create(request: CreateFolderRequest): Promise<Folder | null>;
  rename(request: RenameFolderRequest): Promise<boolean>;
  move(request: MoveFolderRequest): Promise<boolean>;
  remove(request: DeleteFolderRequest): Promise<boolean>;
  enter(folderId: string | null): Promise<void>;
  destroy(): void;
}

export function useFolders(folders: FolderService, initialParent: string | null = null): FoldersHook {
  const currentFolder = createStore<string | null>(initialParent);
  const tree = createResource(() => folders.tree(), { keys: FOLDER_KEYS });
  const flat = createResource(() => folders.allFlat(), { keys: FOLDER_KEYS });
  const children = createResource(
    () => folders.list({ parent_id: currentFolder.get() } as FolderListParams),
    { keys: FOLDER_KEYS },
  );

  async function write<T>(run: () => Promise<T>): Promise<T | null> {
    try {
      return await run();
    } catch (err) {
      emit('toast', { message: toApiError(err).message, type: 'error' });
      return null;
    }
  }

  return {
    tree,
    flat,
    children,
    currentFolder,

    create: (request) => write(() => folders.create(request)),

    async rename(request) {
      return (await write(() => folders.rename(request))) !== null;
    },

    async move(request) {
      return (await write(() => folders.move(request))) !== null;
    },

    async remove(request) {
      return (await write(() => folders.remove(request))) !== null;
    },

    async enter(folderId) {
      currentFolder.set(folderId);
      await children.refocus(() => folders.list({ parent_id: folderId }));
    },

    destroy() {
      tree.destroy();
      flat.destroy();
      children.destroy();
    },
  };
}
