<?php
/**
 * ArsipKu - Folders API
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/WorkspaceService.php';
require_once __DIR__ . '/../src/Services/FolderService.php';

try {
    $workspaceService = new WorkspaceService();
    $currentWs = $workspaceService->getCurrentWorkspace();
    $workspaceId = $currentWs['id'];
    $folderService = new FolderService();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_REQUEST['action'] ?? 'list';

    if ($method === 'GET') {
        $parentId = $_GET['parent_id'] ?? null;
        if ($parentId === 'root') $parentId = null;

        if ($action === 'breadcrumbs') {
            $folderId = $_GET['folder_id'] ?? null;
            $crumbs = $folderService->getBreadcrumbs($workspaceId, $folderId);
            jsonResponse(['success' => true, 'data' => $crumbs]);
        }

        if ($action === 'all_flat') {
            $folders = $folderService->getAllFlat($workspaceId);
            jsonResponse(['success' => true, 'data' => $folders]);
        }

        $folders = $folderService->getByWorkspace($workspaceId, $parentId);
        jsonResponse(['success' => true, 'data' => $folders]);
    }

    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $data = $jsonData ?: $_POST;

        $action = $data['action'] ?? $action;

        switch ($action) {
            case 'create':
                $name = $data['name'] ?? '';
                $parentId = $data['parent_id'] ?? null;
                if ($parentId === 'root' || empty($parentId)) $parentId = null;
                $color = $data['color'] ?? 'indigo';

                $folder = $folderService->create($workspaceId, $name, $parentId, $color);
                jsonResponse([
                    'success' => true,
                    'message' => 'Folder berhasil dibuat',
                    'data' => $folder
                ]);
                break;

            case 'rename':
                $id = $data['id'] ?? '';
                $name = $data['name'] ?? '';
                $folderService->rename($id, $workspaceId, $name);
                jsonResponse([
                    'success' => true,
                    'message' => 'Nama folder berhasil diubah'
                ]);
                break;

            case 'delete':
                $id = $data['id'] ?? '';
                $folderService->delete($id, $workspaceId);
                jsonResponse([
                    'success' => true,
                    'message' => 'Folder dipindahkan ke Sampah'
                ]);
                break;

            default:
                jsonResponse(['success' => false, 'message' => 'Aksi folder tidak valid'], 400);
        }
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
