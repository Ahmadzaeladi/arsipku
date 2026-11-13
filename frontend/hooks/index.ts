/**
 * ArsipKu - hooks entry point.
 *
 * One object holds every domain hook, wired to the service registry, so a page boot
 * creates them together and tears them down together. Hooks own state that is read
 * continuously (lists, filters, queue, meters); one-shot modal actions such as
 * version restore or share-link editing call `services.*` directly - a hook per
 * button would only hide the await.
 */

import { getServices, type Services } from '../services/registry';
import { useNotifications, type NotificationsHook } from './notifications';
import { useSession, type SessionHook } from './useSession';
import { useStorage, type StorageHook } from './useStorage';
import { useWorkspace, type WorkspaceHook } from './useWorkspace';
import { useDocuments, type DocumentsHook } from './useDocuments';
import { useFolders, type FoldersHook } from './useFolders';
import { useMembers, type MembersHook } from './useMembers';
import { useFavorites, type FavoritesHook } from './useFavorites';
import { useTrash, type TrashHook } from './useTrash';
import { useSearch, type SearchHook } from './useSearch';
import { useUploadQueue, type UploadQueueHook } from './useUploadQueue';
import { useActivities, type ActivitiesHook } from './useActivities';
import type { DocumentFilters } from '../types/document';

export interface AppHooks {
  services: Services;
  notifications: NotificationsHook;
  session: SessionHook;
  workspace: WorkspaceHook;
  documents: DocumentsHook;
  folders: FoldersHook;
  members: MembersHook;
  favorites: FavoritesHook;
  trash: TrashHook;
  search: SearchHook;
  uploads: UploadQueueHook;
  storage: StorageHook;
  activities: ActivitiesHook;
  destroy(): void;
}

export interface HooksOptions {
  /** Slug from the URL/body attribute, so a deep link lands on the right tenant. */
  workspaceSlug?: string | null;
  documentFilters?: DocumentFilters;
}

export function createHooks(services: Services = getServices(), options: HooksOptions = {}): AppHooks {
  const hooks: AppHooks = {
    services,
    notifications: useNotifications(),
    session: useSession(services.auth),
    workspace: useWorkspace(services.workspaces),
    documents: useDocuments(services.documents, services.favorites, services.trash, {
      ...options.documentFilters,
      ws: options.workspaceSlug ?? undefined,
    }),
    folders: useFolders(services.folders),
    members: useMembers(services.members),
    favorites: useFavorites(services.favorites),
    trash: useTrash(services.trash),
    search: useSearch(services.search),
    uploads: useUploadQueue(services.uploads),
    storage: useStorage(services.subscription),
    activities: useActivities(services.activities),
    destroy() {
      hooks.notifications.destroy();
      hooks.workspace.destroy();
      hooks.documents.destroy();
      hooks.folders.destroy();
      hooks.members.destroy();
      hooks.favorites.destroy();
      hooks.trash.destroy();
      hooks.search.destroy();
      hooks.uploads.destroy();
      hooks.storage.destroy();
      hooks.activities.destroy();
    },
  };
  return hooks;
}

export * from './reactive';
export * from './notifications';
export type { SessionHook, SessionState } from './useSession';
export type { WorkspaceHook } from './useWorkspace';
export type { DocumentsHook, DetailState } from './useDocuments';
export type { FoldersHook } from './useFolders';
export type { MembersHook } from './useMembers';
export type { FavoritesHook } from './useFavorites';
export type { TrashHook } from './useTrash';
export type { SearchHook } from './useSearch';
export type { UploadQueueHook } from './useUploadQueue';
export type { StorageHook } from './useStorage';
export type { ActivitiesHook } from './useActivities';
