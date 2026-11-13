/**
 * ArsipKu - upload queue hook.
 *
 * One AbortController per batch: api/upload.php is one-file-per-request today, so
 * cancelling stops the files that have not started and abandons the current one.
 * Queue rows are keyed by `localIdOf(file)`, which is what the service reports in
 * its progress events, so the UI and the transport agree without extra plumbing.
 */

import type { Document } from '../types/document';
import type { UploadLimits, UploadProgress, UploadQueueItem, UploadResult } from '../types/upload';
import { emit } from '../lib/events';
import { toApiError } from '../lib/errors';
import { createResource, createStore, type Resource, type Store } from './reactive';
import { localIdOf, type UploadService } from '../services/uploads';

export interface UploadQueueHook {
  items: Store<UploadQueueItem[]>;
  limits: Resource<UploadLimits>;
  uploading: Store<boolean>;
  /** Documents the server confirmed, newest first; the list view keys off these. */
  uploaded: Store<Document[]>;
  add(files: Iterable<File>): void;
  remove(localId: string): void;
  clear(): void;
  start(folderId?: string | null): Promise<UploadResult | null>;
  cancel(): void;
  destroy(): void;
}

export function useUploadQueue(uploads: UploadService): UploadQueueHook {
  const items = createStore<UploadQueueItem[]>([]);
  const uploading = createStore(false);
  const uploaded = createStore<Document[]>([]);
  const limits = createResource(() => uploads.limits());
  let controller: AbortController | null = null;

  function patch(localId: string, next: Partial<UploadQueueItem>): void {
    items.update((current) => current.map((item) => (item.local_id === localId ? { ...item, ...next } : item)));
  }

  function onProgress(progress: UploadProgress): void {
    patch(progress.local_id, {
      state: progress.state,
      loaded_bytes: progress.loaded_bytes,
      percent: progress.percent,
      ...(progress.message ? { error: progress.message } : {}),
    });
  }

  return {
    items,
    limits,
    uploading,
    uploaded,

    add(files) {
      const known = new Set(items.get().map((item) => item.local_id));
      const caps = limits.get().data;
      const added: UploadQueueItem[] = [];
      for (const file of files) {
        const localId = localIdOf(file);
        if (known.has(localId)) continue;
        const check = uploads.validate(file, caps ?? undefined);
        added.push({
          local_id: localId,
          file,
          state: check.ok ? 'queued' : 'failed',
          loaded_bytes: 0,
          total_bytes: file.size,
          percent: 0,
          ...(check.ok ? {} : { error: check.reason }),
        });
      }
      items.update((current) => [...current, ...added]);
    },

    /** Matches the legacy `window.removeFromQueue(localId)` affordance. */
    remove(localId) {
      items.update((current) => current.filter((item) => item.local_id !== localId));
    },

    clear() {
      items.update((current) => current.filter((item) => item.state === 'uploading'));
    },

    async start(folderId = null) {
      const pending = items.get().filter((item) => item.state === 'queued');
      if (!pending.length || uploading.get()) return null;

      controller = new AbortController();
      uploading.set(true);
      for (const item of pending) patch(item.local_id, { state: 'checking' });

      try {
        const result = await uploads.upload({
          files: pending.map((item) => item.file),
          folder_id: folderId,
          signal: controller.signal,
          onProgress,
        });
        // Progress events already moved each row to done/failed by local_id;
        // the result is only kept for the documents list and the toast copy.
        uploaded.set([...result.uploaded, ...uploaded.get()]);
        if (controller?.signal.aborted) {
          for (const item of items.get()) {
            if (item.state === 'queued' || item.state === 'checking') patch(item.local_id, { state: 'canceled' });
          }
        }
        emit('toast', {
          message: result.uploaded.length
            ? `${result.uploaded.length} dokumen berhasil diunggah.`
            : 'Tidak ada dokumen yang berhasil diunggah.',
          type: result.failed.length ? 'warning' : 'success',
        });
        return result;
      } catch (err) {
        const error = toApiError(err);
        for (const item of items.get()) {
          if (item.state === 'checking' || item.state === 'uploading') patch(item.local_id, { state: 'failed', error: error.message });
        }
        emit('toast', { message: error.message, type: 'error' });
        return null;
      } finally {
        controller = null;
        uploading.set(false);
        void limits.reload();
      }
    },

    cancel() {
      controller?.abort();
    },

    destroy: () => limits.destroy(),
  };
}
