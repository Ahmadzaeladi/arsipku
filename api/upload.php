<?php
/**
 * ArsipKu - File Upload API
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/WorkspaceService.php';
require_once __DIR__ . '/../src/Services/DocumentService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Hanya menerima request POST'], 405);
}

try {
    $workspaceService = new WorkspaceService();
    $currentWs = $workspaceService->getCurrentWorkspace();
    $workspaceId = $currentWs['id'];
    $documentService = new DocumentService();

    $folderId = $_POST['folder_id'] ?? null;
    if ($folderId === 'root' || empty($folderId)) {
        $folderId = null;
    }

    if (empty($_FILES)) {
        jsonResponse(['success' => false, 'message' => 'Tidak ada file yang diunggah'], 400);
    }

    $uploadedFiles = [];
    $errors = [];

    // Standardize file array whether single or multiple
    $files = [];
    if (isset($_FILES['file'])) {
        $files[] = $_FILES['file'];
    } elseif (isset($_FILES['files'])) {
        if (is_array($_FILES['files']['name'])) {
            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                $files[] = [
                    'name' => $_FILES['files']['name'][$i],
                    'type' => $_FILES['files']['type'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'error' => $_FILES['files']['error'][$i],
                    'size' => $_FILES['files']['size'][$i],
                ];
            }
        } else {
            $files[] = $_FILES['files'];
        }
    }

    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File {$file['name']} gagal diunggah (Error Code: {$file['error']})";
            continue;
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = "Ukuran file {$file['name']} melampaui batas maksimal " . formatBytes(MAX_UPLOAD_SIZE);
            continue;
        }

        $origName = basename($file['name']);
        $pathInfo = pathinfo($origName);
        $ext = strtolower($pathInfo['extension'] ?? '');

        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            $errors[] = "Format file .{$ext} tidak didukung untuk {$origName}";
            continue;
        }

        // Generate safe unique storage filename while retaining readability
        $safeStorageName = 'up_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
        $targetPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . $safeStorageName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            $errors[] = "Gagal memindahkan file {$origName} ke direktori penyimpanan";
            continue;
        }

        // Determine MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $targetPath) ?: ($file['type'] ?: 'application/octet-stream');
        finfo_close($finfo);

        // Create record in MySQL database
        $newDoc = $documentService->create([
            'workspace_id' => $workspaceId,
            'folder_id' => $folderId,
            'name' => $origName, // Retain original filename automatically as required
            'original_name' => $origName,
            'mime_type' => $mimeType,
            'extension' => $ext,
            'size_bytes' => (int)$file['size'],
            'storage_path' => $safeStorageName
        ]);

        $uploadedFiles[] = $newDoc;
    }

    if (empty($uploadedFiles) && !empty($errors)) {
        jsonResponse(['success' => false, 'message' => implode('; ', $errors)], 400);
    }

    jsonResponse([
        'success' => true,
        'message' => count($uploadedFiles) . ' file berhasil diunggah',
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
