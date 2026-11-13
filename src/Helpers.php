<?php
/**
 * ArsipKu - Helpers
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/Config.php';

function formatBytes(int $bytes, int $precision = 1): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function formatIndoDate(string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return '-';
    }
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $hari = date('j', $timestamp);
    $bln = $bulan[(int)date('n', $timestamp)];
    $thn = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "{$hari} {$bln} {$thn}, {$jam}";
}

function timeAgoIndo(string $datetime): string
{
    if (empty($datetime)) return '-';
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "{$mins} menit lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "{$hours} jam lalu";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "{$days} hari lalu";
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return "{$weeks} minggu lalu";
    } else {
        return date('d M Y', $time);
    }
}

function getFileMeta(string $ext): array
{
    $ext = strtolower(trim($ext, '.'));
    switch ($ext) {
        case 'pdf':
            return [
                'type' => 'PDF',
                'icon' => 'file-text',
                'bg' => 'bg-rose-50 text-rose-600 border-rose-200',
                'previewable' => true
            ];
        case 'doc':
        case 'docx':
            return [
                'type' => 'Word',
                'icon' => 'file-text',
                'bg' => 'bg-blue-50 text-blue-600 border-blue-200',
                'previewable' => false
            ];
        case 'xls':
        case 'xlsx':
        case 'csv':
            return [
                'type' => 'Spreadsheet',
                'icon' => 'table',
                'bg' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                'previewable' => false
            ];
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'webp':
        case 'svg':
            return [
                'type' => 'Gambar',
                'icon' => 'image',
                'bg' => 'bg-amber-50 text-amber-600 border-amber-200',
                'previewable' => true
            ];
        case 'zip':
        case 'rar':
        case '7z':
            return [
                'type' => 'Arsip ZIP',
                'icon' => 'archive',
                'bg' => 'bg-purple-50 text-purple-600 border-purple-200',
                'previewable' => false
            ];
        case 'txt':
            return [
                'type' => 'Teks',
                'icon' => 'file-code',
                'bg' => 'bg-slate-50 text-slate-600 border-slate-200',
                'previewable' => true
            ];
        default:
            return [
                'type' => strtoupper($ext) ?: 'File',
                'icon' => 'file',
                'bg' => 'bg-slate-50 text-slate-600 border-slate-200',
                'previewable' => false
            ];
    }
}

/**
 * Registry ikon = assets/js/icons.js. Bentuk SVG TIDAK pernah diduplikasi ke PHP;
 * berkas itu dibaca sekali per request (statis) lalu dipakai bersama oleh render
 * server dan ArkIcons di browser. Format file dipatenkan di header icons.js.
 *
 * @return array{paths: array<string,string>, alias: array<string,string>, filled: array<string,bool>}
 */
function arkIconRegistry(): array
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    $registry = ['paths' => [], 'alias' => [], 'filled' => []];
    $source = @file_get_contents(__DIR__ . '/../assets/js/icons.js');
    if ($source === false) {
        return $registry;
    }

    $blocks = [
        'paths' => '/var PATHS\s*=\s*\{(.*?)\};/s',
        'alias' => '/var ALIAS\s*=\s*\{(.*?)\};/s',
    ];
    foreach ($blocks as $key => $pattern) {
        if (preg_match($pattern, $source, $block)
            && preg_match_all("/^[ \t]*'([a-z0-9-]+)'[ \t]*:[ \t]*'(.*)'[ \t]*,?[ \t]*$/mi", $block[1], $entries, PREG_SET_ORDER)) {
            foreach ($entries as $entry) {
                $registry[$key][$entry[1]] = $entry[2];
            }
        }
    }

    if (preg_match('/var FILLED\s*=\s*\[(.*?)\];/s', $source, $block)) {
        preg_match_all("/'([a-z0-9-]+)'/i", $block[1], $names);
        foreach ($names[1] as $name) {
            $registry['filled'][$name] = true;
        }
    }

    return $registry;
}

/**
 * SVG inline untuk satu nama ikon. Atribut dan urutannya identik dengan
 * ArkIcons.markup() supaya markup hasil server dan hasil browser sama.
 * Nama tak dikenal jatuh ke ikon 'file' (perilaku lama: tanpa kotak kosong).
 */
function arkIconSvg(string $name, string $classes = ''): string
{
    $registry = arkIconRegistry();
    if (isset($registry['paths'][$name])) {
        $key = $name;
    } else {
        $key = $registry['alias'][$name] ?? 'file';
    }
    if (!isset($registry['paths'][$key])) {
        return '';
    }

    $filled = isset($registry['filled'][$key]);
    $class = trim('ark-icon ' . $classes);

    return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="' . ($filled ? 'currentColor' : 'none')
        . '" stroke="' . ($filled ? 'none' : 'currentColor')
        . '" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $registry['paths'][$key] . '</svg>';
}

/**
 * Kelas warisan Tailwind (w-4 h-4 text-slate-400) diterima dari halaman yang
 * belum dimigrasi: token ukuran dipetakan ke .ark-icon--NN, sisanya diteruskan.
 */
function renderSvgIcon(string $name, string $classes = 'w-5 h-5'): string
{
    $sizes = ['3' => '12', '3.5' => '14', '4' => '16', '5' => '20', '6' => '24', '7' => '28', '8' => '32'];
    $size = '20';
    $keep = [];

    foreach (preg_split('/\s+/', trim($classes)) ?: [] as $class) {
        if ($class === '') {
            continue;
        }
        if (preg_match('/^(?:w|h|size)-[\d.]+$/', $class)) {
            $token = substr($class, strpos($class, '-') + 1);
            if (isset($sizes[$token])) {
                $size = $sizes[$token];
            }
            continue;
        }
        $keep[] = $class;
    }

    return arkIconSvg($name, 'ark-icon--' . $size . ($keep ? ' ' . implode(' ', $keep) : ''));
}

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function url(string $page = 'dashboard', array $params = []): string
{
    $ws = $_SESSION['active_workspace'] ?? 'pt-abc-solusindo';
    $query = array_merge(['page' => $page, 'ws' => $ws], $params);
    return 'index.php?' . http_build_query($query);
}
