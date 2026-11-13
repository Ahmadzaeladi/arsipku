/**
 * ArsipKu - service registry.
 *
 * Every service reads and writes through the PHP API. Missing endpoints fail as
 * HTTP errors; no in-memory or fixture implementation is substituted.
 */

import { createHttpActivityService, type ActivityService } from './activities';
import { createHttpAuthService, type AuthService } from './auth';
import { createHttpDocumentService, type DocumentService } from './documents';
import { createHttpFavoriteService, type FavoriteService } from './favorites';
import { createHttpFolderService, type FolderService } from './folders';
import { createHttpMemberService, type MemberService } from './members';
import { createHttpPermissionService, type PermissionService } from './permissions';
import { createHttpSearchService, type SearchService } from './search';
import { createHttpSubscriptionService, type SubscriptionService } from './subscription';
import { createHttpTrashService, type TrashService } from './trash';
import { createHttpUploadService, type UploadService } from './uploads';
import { createHttpVersionService, type VersionService } from './versions';
import { createHttpWorkspaceService, type WorkspaceService } from './workspaces';

export interface Services {
  auth: AuthService;
  workspaces: WorkspaceService;
  members: MemberService;
  documents: DocumentService;
  folders: FolderService;
  uploads: UploadService;
  versions: VersionService;
  permissions: PermissionService;
  favorites: FavoriteService;
  search: SearchService;
  trash: TrashService;
  subscription: SubscriptionService;
  activities: ActivityService;
}

let cached: Services | null = null;

export function getServices(): Services {
  if (cached === null) {
    cached = {
      auth: createHttpAuthService(),
      workspaces: createHttpWorkspaceService(),
      members: createHttpMemberService(),
      documents: createHttpDocumentService(),
      folders: createHttpFolderService(),
      uploads: createHttpUploadService(),
      versions: createHttpVersionService(),
      permissions: createHttpPermissionService(),
      favorites: createHttpFavoriteService(),
      search: createHttpSearchService(),
      trash: createHttpTrashService(),
      subscription: createHttpSubscriptionService(),
      activities: createHttpActivityService(),
    };
  }
  return cached;
}
