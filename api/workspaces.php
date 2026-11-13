<?php
/**
 * ArsipKu - Workspaces API
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/WorkspaceService.php';
require_once __DIR__ . '/../src/Services/ProfileImageService.php';

try {
    $workspaceService = new WorkspaceService();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_REQUEST['action'] ?? 'list';

    if ($method === 'GET') {
        if ($action === 'current') {
            $current = $workspaceService->getCurrentWorkspace();
            $stats = $workspaceService->getStats($current['id']);
            jsonResponse([
                'success' => true,
                'workspace' => $current,
                'stats' => $stats
            ]);
        }

        $workspaces = $workspaceService->getAll();
        jsonResponse([
            'success' => true,
            'data' => $workspaces
        ]);
    }

    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);
        $data = $jsonData ?: $_POST;

        $action = $data['action'] ?? $action;

        if ($action === 'switch') {
            $slug = $data['slug'] ?? '';
            $switched = $workspaceService->switchWorkspace($slug);
            if (!$switched) {
                jsonResponse(['success' => false, 'message' => 'Workspace tidak ditemukan'], 404);
            }
            jsonResponse([
                'success' => true,
                'message' => "Beralih ke workspace {$switched['name']}",
                'workspace' => $switched
            ]);
        }

        if ($action === 'update') {
            $current = $workspaceService->getCurrentWorkspace();
            $workspaceService->update($current['id'], [
                'name' => $data['name'] ?? $current['name'],
                'type' => $data['type'] ?? $current['type']
            ]);
            jsonResponse([
                'success' => true,
                'message' => 'Pengaturan workspace berhasil disimpan'
            ]);
        }

        if ($action === 'update_profile') {
            if (!$authService->verifiesCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['_csrf'] ?? null))) {
                jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
            }

            $current = $workspaceService->getCurrentWorkspace();
            $oldLogoPath = $current['logo_path'] ?? null;
            $newLogoPath = null;
            $logoChanged = false;
            $images = new ProfileImageService();
            if (isset($_FILES['logo']) && (int) ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newLogoPath = $images->store($_FILES['logo'], 'workspace');
                $logoChanged = true;
            } elseif (($data['remove_logo'] ?? '0') === '1') {
                $logoChanged = true;
            }

            $changes = [
                'name' => $data['name'] ?? $current['name'],
                'type' => $data['type'] ?? $current['type'],
            ];
            if ($logoChanged) {
                $changes['logo_path'] = $newLogoPath;
            }

            try {
                $workspaceService->update($current['id'], $changes);
            } catch (Throwable $e) {
                if ($newLogoPath !== null) {
                    $images->delete($newLogoPath);
                }
                throw $e;
            }

            $nextLogoPath = $logoChanged ? $newLogoPath : $oldLogoPath;
            if ($oldLogoPath !== $nextLogoPath) {
                $images->delete($oldLogoPath);
            }
            jsonResponse([
                'success' => true,
                'message' => 'Profil workspace berhasil diperbarui.'
            ]);
        }

        if ($action === 'create') {
            $name = $data['name'] ?? '';
            $type = $data['type'] ?? 'company';
            $newWs = $workspaceService->create($name, $type);
            jsonResponse([
                'success' => true,
                'message' => "Workspace \"{$newWs['name']}\" berhasil dibuat",
                'workspace' => $newWs
            ]);
        }

        if ($action === 'change_plan') {
            $current = $workspaceService->getCurrentWorkspace();
            $plan = $data['plan'] ?? 'Enterprise Business';
            $limitBytes = (int)($data['limit_bytes'] ?? (50 * 1024 * 1024 * 1024));
            $workspaceService->changePlan($current['id'], $plan, $limitBytes);
            jsonResponse([
                'success' => true,
                'message' => "Paket workspace berhasil diubah ke {$plan}"
            ]);
        }

        if ($action === 'delete') {
            $current = $workspaceService->getCurrentWorkspace();
            $confirmName = trim($data['confirm_name'] ?? '');
            if ($confirmName !== $current['name']) {
                jsonResponse(['success' => false, 'message' => 'Nama workspace yang diketik tidak cocok.'], 400);
            }

            $workspaceService->delete($current['id']);
            jsonResponse([
                'success' => true,
                'message' => "Workspace {$current['name']} berhasil dihapus"
            ]);
        }

        jsonResponse(['success' => false, 'message' => 'Aksi tidak dikenali'], 400);
    }
} catch (WorkspaceAuthorizationException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 403);
} catch (WorkspaceConflictException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 409);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
