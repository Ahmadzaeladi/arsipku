/**
 * ArsipKu - presentation helpers.
 * Ports src/Helpers.php so the browser produces the same strings as the PHP pages
 * (Indonesian month names, "2.3 MB", "3 jam lalu"). Pure functions only: no DOM,
 * no state, and no server-computed labels are trusted.
 */

import type { FileKind } from '../types/document';

const MONTHS_ID = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

export function formatBytes(bytes: number, precision = 1): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
  const pow = Math.min(BYTE_UNITS.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
  const value = bytes / 1024 ** pow;
  return `${round(value, precision)} ${BYTE_UNITS[pow]}`;
}

/** PHP rounds half-up; Math.round matches for the values we display. */
function round(value: number, precision: number): string {
  const fixed = value.toFixed(precision);
  return fixed.replace(/\.0$/, '');
}

/** "24 Maret 2026, 14:30". Empty/invalid input renders as "-" like the PHP helper. */
export function formatIndoDate(value: string | null | undefined): string {
  const date = parseDate(value);
  if (!date) return '-';
  return `${date.getDate()} ${MONTHS_ID[date.getMonth()]} ${date.getFullYear()}, ${
    String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

/** Date only, for table columns: "24 Maret 2026". */
export function formatIndoDay(value: string | null | undefined): string {
  const date = parseDate(value);
  if (!date) return '-';
  return `${date.getDate()} ${MONTHS_ID[date.getMonth()]} ${date.getFullYear()}`;
}

export function timeAgoIndo(value: string | null | undefined, now = Date.now()): string {
  const date = parseDate(value);
  if (!date) return '-';
  const diff = Math.floor((now - date.getTime()) / 1000);
  if (diff < 0) return formatIndoDay(value);
  if (diff < 60) return 'Baru saja';
  if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
  if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`;
  if (diff < 2592000) return `${Math.floor(diff / 604800)} minggu lalu`;
  return formatIndoDay(value);
}

export function parseDate(value: string | null | undefined): Date | null {
  if (!value) return null;
  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? null : date;
}

export function extensionOf(fileName: string): string {
  const dot = fileName.lastIndexOf('.');
  return dot > 0 ? fileName.slice(dot + 1).toLowerCase() : '';
}

/** Buckets match DocumentService's type filter and the icon set in Helpers.php. */
export function fileKind(extension: string): FileKind {
  switch (extension.toLowerCase().replace(/^\./, '')) {
    case 'pdf': return 'pdf';
    case 'doc': case 'docx': return 'word';
    case 'xls': case 'xlsx': case 'csv': return 'spreadsheet';
    case 'ppt': case 'pptx': return 'presentation';
    case 'png': case 'jpg': case 'jpeg': case 'webp': case 'svg': case 'gif': return 'image';
    case 'zip': case 'rar': case '7z': return 'archive';
    case 'txt': case 'log': case 'md': case 'json': case 'xml': return 'text';
    default: return 'other';
  }
}

const KIND_LABEL: Record<FileKind, string> = {
  pdf: 'PDF',
  word: 'Word',
  spreadsheet: 'Spreadsheet',
  presentation: 'Presentasi',
  image: 'Gambar',
  archive: 'Arsip ZIP',
  text: 'Teks',
  other: 'Berkas',
};

export function kindLabel(kind: FileKind): string {
  return KIND_LABEL[kind];
}

/** Mirrors Helpers::getFileMeta(): only these render inside the preview modal. */
export function isPreviewable(extension: string): boolean {
  const kind = fileKind(extension);
  return kind === 'pdf' || kind === 'image' || kind === 'text';
}

export function isInlineText(extension: string): boolean {
  return ['txt', 'csv', 'log', 'json', 'xml', 'md'].includes(extension.toLowerCase());
}

/** Icon keys accepted by Helpers::renderSvgIcon() / the sprite in ui/icons.ts. */
export function kindIcon(extension: string): string {
  switch (extension.toLowerCase()) {
    case 'pdf': return 'file-text';
    case 'doc': case 'docx': return 'file-text';
    case 'xls': case 'xlsx': case 'csv': return 'table';
    case 'png': case 'jpg': case 'jpeg': case 'webp': case 'svg': return 'image';
    case 'zip': return 'archive';
    case 'txt': return 'file-code';
    default: return 'file';
  }
}

/** Tailwind chip classes, mirroring Helpers::getFileMeta()['bg'] so views stay identical. */
export function kindBackground(extension: string): string {
  switch (fileKind(extension)) {
    case 'pdf': return 'bg-rose-50 text-rose-600 border-rose-200';
    case 'word': return 'bg-blue-50 text-blue-600 border-blue-200';
    case 'spreadsheet': return 'bg-emerald-50 text-emerald-600 border-emerald-200';
    case 'image': return 'bg-amber-50 text-amber-600 border-amber-200';
    case 'archive': return 'bg-purple-50 text-purple-600 border-purple-200';
    default: return 'bg-slate-50 text-slate-600 border-slate-200';
  }
}

export function clampPercent(value: number): number {
  if (!Number.isFinite(value)) return 0;
  return Math.min(100, Math.max(0, value));
}

export function usageTone(percentage: number): 'normal' | 'warning' | 'critical' {
  if (percentage >= 95) return 'critical';
  return percentage >= 80 ? 'warning' : 'normal';
}

/** "Sisa 12 hari" copy for trash rows; null when the server gives no purge date. */
export function daysUntil(value: string | null | undefined, now = Date.now()): number | null {
  const date = parseDate(value);
  if (!date) return null;
  return Math.ceil((date.getTime() - now) / 86_400_000);
}

export function formatIdr(amount: number): string {
  if (!Number.isFinite(amount)) return 'Rp0';
  return `Rp${Math.round(amount).toLocaleString('id-ID')}`;
}

/**
 * The legacy UI builds rows with innerHTML. Anything interpolated must pass
 * through here - document names are user-controlled.
 */
export function escapeHtml(value: unknown): string {
  const raw = value === null || value === undefined ? '' : String(value);
  return raw
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
