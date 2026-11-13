<?php
/**
 * Authenticated document download and preview endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Services/AuthService.php';
require_once __DIR__ . '/../src/Services/WorkspaceService.php';
require_once __DIR__ . '/../src/Services/DocumentService.php';

function documentDownloadError(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo $message;
    exit;
}

if (!(new AuthService())->current()) {
    documentDownloadError(401, 'Sesi tidak aktif. Silakan login kembali.');
}

$id = (string) ($_GET['id'] ?? '');
if ($id === '') {
    documentDownloadError(404, 'Dokumen tidak ditemukan.');
}

try {
    $workspace = (new WorkspaceService())->getCurrentWorkspace();
    $documentService = new DocumentService();
    $document = $documentService->getById($id, (string) $workspace['id']);
    if (!$document) {
        documentDownloadError(404, 'Dokumen tidak ditemukan.');
    }

    $filePath = $documentService->resolveStoredDocument((string) $document['storage_path']);
    if ($filePath === null) {
        documentDownloadError(404, 'Berkas dokumen tidak tersedia.');
    }
    $fileSize = filesize($filePath);
    if ($fileSize === false || !is_readable($filePath)) {
        documentDownloadError(404, 'Berkas dokumen tidak tersedia.');
    }

    $extension = strtolower((string) $document['extension']);
    $inlineTypes = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'txt' => 'text/plain; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'log' => 'text/plain; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'text/plain; charset=utf-8',
    ];
    $requestedDisposition = (string) ($_GET['disposition'] ?? 'attachment');
    $inline = $requestedDisposition === 'inline' && isset($inlineTypes[$extension]);
    $filename = (string) $document['name'];
    $fallbackFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    if (!is_string($fallbackFilename) || $fallbackFilename === '') {
        $fallbackFilename = 'document.' . $extension;
    }
    $utf8Filename = str_replace(["\r", "\n"], '', $filename);

    header('Content-Type: ' . ($inline ? $inlineTypes[$extension] : 'application/octet-stream'));
    header('Content-Length: ' . (string) $fileSize);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
        . '; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\'' . rawurlencode($utf8Filename));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    readfile($filePath);
} catch (DocumentAuthorizationException $e) {
    documentDownloadError(403, 'Anda tidak memiliki akses ke dokumen ini.');
} catch (Throwable $e) {
    error_log('Document download failure: ' . $e->getMessage());
    documentDownloadError(500, 'Berkas dokumen tidak dapat dimuat saat ini.');
}
