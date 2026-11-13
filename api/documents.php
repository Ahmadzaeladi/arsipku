<?php
/**
 * ArsipKu - Documents API
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/WorkspaceService.php';
require_once __DIR__ . '/../src/Services/DocumentService.php';

try {
    $workspaceService = new WorkspaceService();
    $currentWs = $workspaceService->getCurrentWorkspace();
    $workspaceId = $currentWs['id'];
    $documentService = new DocumentService();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_REQUEST['action'] ?? 'list';

    if ($method === 'GET') {
        if ($action === 'detail') {
            $id = $_GET['id'] ?? '';
            $doc = $documentService->getById($id, $workspaceId);
            if (!$doc) {
                jsonResponse(['success' => false, 'message' => 'Dokumen tidak ditemukan'], 404);
            }
            jsonResponse(['success' => true, 'data' => $doc]);
        }
        if ($action === 'share_info') {
            $id = (string) ($_GET['id'] ?? '');
            jsonResponse(['success' => true, 'data' => $documentService->getShareInfo($id, $workspaceId)]);
        }

        // List with filters
        $filters = [
            'q' => $_GET['q'] ?? null,
            'type' => $_GET['type'] ?? null,
            'date_range' => $_GET['date_range'] ?? null,
            'folder_id' => $_GET['folder_id'] ?? null,
            'sort' => $_GET['sort'] ?? 'updated_desc',
            'is_favorite' => !empty($_GET['favorite']),
            'is_shared' => !empty($_GET['shared']),
            'is_trash' => !empty($_GET['trash']),
        ];

        $docs = $documentService->getDocuments($workspaceId, $filters);
        jsonResponse([
            'success' => true,
            'data' => $docs,
            'count' => count($docs)
        ]);
    }

    if ($method === 'POST') {
        // Read JSON payload or Form data
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $data = $jsonData ?: $_POST;

        $action = $data['action'] ?? $action;
        if ($action === 'share') {
            if (!$authService->verifiesCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['_csrf'] ?? null))) {
                jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
            }
            $recipients = $data['recipients'] ?? [];
            if (!is_array($recipients)) {
                jsonResponse(['success' => false, 'message' => 'Daftar penerima tidak valid.'], 422);
            }
            $result = $documentService->saveSharing(
                (string) ($data['id'] ?? ''),
                $workspaceId,
                (string) ($data['scope'] ?? ''),
                $recipients,
                (string) ($data['permission'] ?? 'view')
            );
            jsonResponse([
                'success' => true,
                'message' => $result['scope'] === 'selected'
                    ? 'Akses dokumen diperbarui untuk anggota terpilih.'
                    : 'Akses dokumen dikembalikan ke seluruh workspace.',
                'data' => $result,
            ]);
        }

        switch ($action) {
            case 'rename':
                $id = $data['id'] ?? '';
                $newName = $data['name'] ?? '';
                $updated = $documentService->rename($id, $workspaceId, $newName);
                jsonResponse([
                    'success' => true,
                    'message' => 'Nama dokumen berhasil diubah',
                    'data' => $updated
                ]);
                break;

            case 'move':
                $id = $data['id'] ?? '';
                $folderId = $data['folder_id'] ?? null;
                $documentService->moveToFolder($id, $workspaceId, $folderId);
                jsonResponse([
                    'success' => true,
                    'message' => 'Dokumen berhasil dipindahkan'
                ]);
                break;

            case 'toggle_favorite':
                $id = $data['id'] ?? '';
                $isFav = $documentService->toggleFavorite($id, $workspaceId);
                jsonResponse([
                    'success' => true,
                    'is_favorite' => $isFav,
                    'message' => $isFav ? 'Ditambahkan ke Favorit' : 'Dihapus dari Favorit'
                ]);
                break;

            case 'delete':
                $id = $data['id'] ?? '';
                $documentService->softDelete($id, $workspaceId);
                jsonResponse([
                    'success' => true,
                    'message' => 'Dokumen dipindahkan ke Sampah'
                ]);
                break;

            case 'restore':
                $id = $data['id'] ?? '';
                $documentService->restoreFromTrash($id, $workspaceId);
                jsonResponse([
                    'success' => true,
                    'message' => 'Dokumen berhasil dipulihkan'
                ]);
                break;

            case 'permanent_delete':
                $id = $data['id'] ?? '';
                $documentService->permanentDelete($id, $workspaceId);
                jsonResponse([
                    'success' => true,
                    'message' => 'Dokumen telah dihapus permanen'
                ]);
                break;

            case 'empty_trash':
                $count = $documentService->emptyTrash($workspaceId);
                jsonResponse([
                    'success' => true,
                    'message' => "Tempat sampah berhasil dikosongkan ({$count} dokumen dihapus)",
                    'count' => $count
                ]);
                break;

            case 'multi_action':
                $subAction = $data['sub_action'] ?? 'delete';
                $ids = $data['ids'] ?? [];
                $targetFolderId = $data['target_folder_id'] ?? null;

                if (!is_array($ids) || empty($ids)) {
                    jsonResponse(['success' => false, 'message' => 'Pilih setidaknya satu dokumen'], 400);
                }

                $res = $documentService->multiAction($workspaceId, $subAction, $ids, $targetFolderId);
                jsonResponse([
                    'success' => true,
                    'message' => "Berhasil memproses {$res['affected_count']} dokumen",
                    'data' => $res
                ]);
                break;

            default:
                jsonResponse(['success' => false, 'message' => 'Aksi tidak dikenali'], 400);
        }
    }
} catch (DocumentAuthorizationException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 403);
} catch (DocumentNotFoundException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
