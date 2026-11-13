/**
 * ArsipKu - storage meter adapter.
 *
 * The sidebar widget is rendered server-side from $wsStats today. This reproduces the
 * same markup from the typed usage, and it reads `storage.usage ?? workspace.usage`:
 * api/billing.php is a planned endpoint, so the live meter comes from the workspace
 * stats workspaces.php already returns rather than from a guess.
 */

import type { StorageUsage } from '../types/subscription';
import type { AppHooks } from '../hooks';
import { clampPercent, escapeHtml, formatBytes } from '../lib/format';
import { icon } from './icons';

export function usageMarkup(usage: StorageUsage): string {
  const percentage = clampPercent(usage.usage_percentage);
  const bar = usage.is_warning || usage.is_over_quota ? 'bg-amber-500' : 'bg-indigo-600';
  return `
    <div class="flex items-center justify-between text-xs mb-1.5">
      <span class="font-medium text-slate-700 flex items-center gap-1.5 group-hover:text-indigo-600 transition-colors">
        ${icon('hard-drive', 'w-3.5 h-3.5 text-indigo-600')}
        Penyimpanan
      </span>
      <span class="font-semibold text-slate-800">${percentage}%</span>
    </div>
    <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
      <div class="h-1.5 rounded-full transition-all duration-300 ${bar}" style="width: ${Math.max(4, percentage)}%"></div>
    </div>
    <p class="text-[11px] text-slate-500 mt-1.5">
      ${escapeHtml(formatBytes(usage.used_bytes))} dari ${escapeHtml(formatBytes(usage.limit_bytes))}${usage.trash_included ? ' • termasuk Sampah' : ''}
    </p>
    ${usage.is_over_quota
      ? `<p class="text-[11px] font-medium text-rose-600 mt-1">Menyimpan melebihi kuota paket.</p>`
      : ''}`;
}

export function mountStorageMeter(hooks: AppHooks, selector = '.sidebar-storage-box'): () => void {
  const node = document.querySelector<HTMLElement>(selector);
  if (!node) return () => {};

  const render = (): void => {
    node.innerHTML = usageMarkup(hooks.storage.usage.get() ?? hooks.workspace.usage.get());
  };

  const unsubscriptions = [hooks.workspace.usage.subscribe(render), hooks.storage.usage.subscribe(render)];
  render();

  return () => {
    for (const unsubscribe of unsubscriptions) unsubscribe();
  };
}
