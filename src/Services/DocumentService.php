<?php
/**
 * ArsipKu - Document Service
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Helpers.php';

class DocumentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getDocuments(string $workspaceId, array $filters = []): array
    {
        $sql = "
            SELECT d.*, f.name as folder_name, f.color as folder_color
            FROM documents d
            LEFT JOIN folders f ON d.folder_id = f.id
            WHERE d.workspace_id = :workspace_id
        ";
        $params = [':workspace_id' => $workspaceId];

        // Trash filter
        if (!empty($filters['is_trash'])) {
            $sql .= " AND d.deleted_at IS NOT NULL";
        } else {
            $sql .= " AND d.deleted_at IS NULL";
        }

        // Folder filter
        if (isset($filters['folder_id'])) {
            if ($filters['folder_id'] === 'root') {
                $sql .= " AND d.folder_id IS NULL";
            } elseif (!empty($filters['folder_id'])) {
                $sql .= " AND d.folder_id = :folder_id";
                $params[':folder_id'] = $filters['folder_id'];
            }
        }

        // Favorite filter
        if (!empty($filters['is_favorite'])) {
            $sql .= " AND d.is_favorite = 1";
        }

        // Shared filter
        if (!empty($filters['is_shared'])) {
            $sql .= " AND d.is_shared = 1";
        }

        // Search query
        if (!empty($filters['q'])) {
            $sql .= " AND (d.name LIKE :search_q OR d.original_name LIKE :search_q OR d.owner_name LIKE :search_q)";
            $params[':search_q'] = '%' . trim($filters['q']) . '%';
        }

        // File type filter
        if (!empty($filters['type'])) {
            switch (strtolower($filters['type'])) {
                case 'pdf':
                    $sql .= " AND d.extension = 'pdf'";
                    break;
                case 'word':
                    $sql .= " AND d.extension IN ('doc', 'docx')";
                    break;
                case 'spreadsheet':
                case 'excel':
                    $sql .= " AND d.extension IN ('xls', 'xlsx', 'csv')";
                    break;
                case 'image':
                    $sql .= " AND d.extension IN ('png', 'jpg', 'jpeg', 'webp', 'svg')";
                    break;
                case 'archive':
                case 'zip':
                    $sql .= " AND d.extension IN ('zip', 'rar', '7z')";
                    break;
                case 'txt':
                    $sql .= " AND d.extension = 'txt'";
                    break;
            }
        }

        // Date range filter
        if (!empty($filters['date_range'])) {
            $now = time();
            switch ($filters['date_range']) {
                case 'today':
                    $todayStart = date('Y-m-d 00:00:00');
                    $sql .= " AND d.created_at >= '{$todayStart}'";
                    break;
                case '7days':
                    $sevenDaysAgo = date('Y-m-d H:i:s', $now - (7 * 86400));
                    $sql .= " AND d.updated_at >= '{$sevenDaysAgo}'";
                    break;
                case '30days':
                    $thirtyDaysAgo = date('Y-m-d H:i:s', $now - (30 * 86400));
                    $sql .= " AND d.updated_at >= '{$thirtyDaysAgo}'";
                    break;
                case 'this_year':
                    $yearStart = date('Y-01-01 00:00:00');
                    $sql .= " AND d.created_at >= '{$yearStart}'";
                    break;
            }
        }

        // Owner filter
        if (!empty($filters['owner'])) {
            $sql .= " AND d.owner_name = :owner";
            $params[':owner'] = $filters['owner'];
        }

        // Sorting
        $sort = $filters['sort'] ?? 'updated_desc';
        switch ($sort) {
            case 'updated_asc':
                $sql .= " ORDER BY d.updated_at ASC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY d.name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY d.name DESC";
                break;
            case 'size_desc':
                $sql .= " ORDER BY d.size_bytes DESC";
                break;
            case 'size_asc':
                $sql .= " ORDER BY d.size_bytes ASC";
                break;
            case 'updated_desc':
            default:
                $sql .= " ORDER BY d.updated_at DESC";
                break;
        }

        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $documents = $stmt->fetchAll();

        // Enrich with helper formatted attributes
        foreach ($documents as &$doc) {
            $doc['formatted_size'] = formatBytes((int)$doc['size_bytes']);
            $doc['formatted_date'] = formatIndoDate($doc['updated_at']);
            $doc['time_ago'] = timeAgoIndo($doc['updated_at']);
            $doc['meta'] = getFileMeta($doc['extension']);
            $doc['download_url'] = 'uploads/' . rawurlencode($doc['storage_path']);
        }

        return $documents;
    }

    public function getById(string $id, string $workspaceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, f.name as folder_name, f.color as folder_color
            FROM documents d
            LEFT JOIN folders f ON d.folder_id = f.id
            WHERE d.id = ? AND d.workspace_id = ?
        ");
        $stmt->execute([$id, $workspaceId]);
        $doc = $stmt->fetch();
        if ($doc) {
            $doc['formatted_size'] = formatBytes((int)$doc['size_bytes']);
            $doc['formatted_date'] = formatIndoDate($doc['updated_at']);
            $doc['formatted_created'] = formatIndoDate($doc['created_at']);
            $doc['time_ago'] = timeAgoIndo($doc['updated_at']);
            $doc['meta'] = getFileMeta($doc['extension']);
            $doc['download_url'] = 'uploads/' . rawurlencode($doc['storage_path']);
            return $doc;
        }
        return null;
    }

    public function create(array $data): array
    {
        $id = 'doc_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
        $now = date('Y-m-d H:i:s');
        $user = currentUser();

        $stmt = $this->db->prepare("
            INSERT INTO documents (
                id, workspace_id, folder_id, name, original_name, mime_type,
                extension, size_bytes, storage_path, uploaded_by, owner_name,
                is_favorite, is_shared, shared_permission, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'view', ?, ?)
        ");

        $stmt->execute([
            $id,
            $data['workspace_id'],
            $data['folder_id'] ?: null,
            $data['name'],
            $data['original_name'],
            $data['mime_type'],
            strtolower($data['extension']),
            (int)$data['size_bytes'],
            $data['storage_path'],
            $user['id'],
            $user['name'],
            $now,
            $now
        ]);

        $this->recordActivity($data['workspace_id'], 'upload', 'Mengunggah dokumen "' . $data['name'] . '"');

        return $this->getById($id, $data['workspace_id']);
    }

    public function rename(string $id, string $workspaceId, string $newName): array
    {
        $doc = $this->getById($id, $workspaceId);
        if (!$doc) {
            throw new RuntimeException('Dokumen tidak ditemukan.');
        }

        $newName = trim($newName);
        if (empty($newName)) {
            throw new InvalidArgumentException('Nama dokumen tidak boleh kosong.');
        }

        // Ensure extension is retained if user did not provide it
        $currentExt = strtolower($doc['extension']);
        $pathInfo = pathinfo($newName);
        if (empty($pathInfo['extension']) || strtolower($pathInfo['extension']) !== $currentExt) {
            $newName = $pathInfo['filename'] . '.' . $currentExt;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            UPDATE documents 
            SET name = ?, updated_at = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $stmt->execute([$newName, $now, $id, $workspaceId]);

        $this->recordActivity($workspaceId, 'rename', 'Mengubah nama dokumen dari "' . $doc['name'] . '" menjadi "' . $newName . '"');

        return $this->getById($id, $workspaceId);
    }

    public function moveToFolder(string $id, string $workspaceId, ?string $folderId): bool
    {
        $doc = $this->getById($id, $workspaceId);
        if (!$doc) {
            throw new RuntimeException('Dokumen tidak ditemukan.');
        }

        $targetFolderId = (!empty($folderId) && $folderId !== 'root') ? $folderId : null;

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            UPDATE documents 
            SET folder_id = ?, updated_at = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $res = $stmt->execute([$targetFolderId, $now, $id, $workspaceId]);

        if ($res) {
            $this->recordActivity($workspaceId, 'move', 'Memindahkan dokumen "' . $doc['name'] . '"');
        }

        return $res;
    }

    public function toggleFavorite(string $id, string $workspaceId): bool
    {
        $doc = $this->getById($id, $workspaceId);
        if (!$doc) {
            throw new RuntimeException('Dokumen tidak ditemukan.');
        }

        $newFav = $doc['is_favorite'] ? 0 : 1;
        $stmt = $this->db->prepare("
            UPDATE documents 
            SET is_favorite = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $res = $stmt->execute([$newFav, $id, $workspaceId]);

        if ($res) {
            $action = $newFav ? 'Menambahkan dokumen "' . $doc['name'] . '" ke favorit' : 'Menghapus dokumen "' . $doc['name'] . '" dari favorit';
            $this->recordActivity($workspaceId, 'favorite', $action);
        }

        return (bool)$newFav;
    }

    public function softDelete(string $id, string $workspaceId): bool
    {
        $doc = $this->getById($id, $workspaceId);
        if (!$doc) {
            throw new RuntimeException('Dokumen tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            UPDATE documents 
            SET deleted_at = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $res = $stmt->execute([$now, $id, $workspaceId]);

        if ($res) {
            $this->recordActivity($workspaceId, 'delete', 'Memindahkan dokumen "' . $doc['name'] . '" ke Sampah');
        }

        return $res;
    }

    public function restoreFromTrash(string $id, string $workspaceId): bool
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents WHERE id = ? AND workspace_id = ? AND deleted_at IS NOT NULL
        ");
        $stmt->execute([$id, $workspaceId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            throw new RuntimeException('Dokumen tidak ditemukan di sampah.');
        }

        $now = date('Y-m-d H:i:s');
        $stmtUpdate = $this->db->prepare("
            UPDATE documents 
            SET deleted_at = NULL, updated_at = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $res = $stmtUpdate->execute([$now, $id, $workspaceId]);

        if ($res) {
            $this->recordActivity($workspaceId, 'restore', 'Memulihkan dokumen "' . $doc['name'] . '" dari Sampah');
        }

        return $res;
    }

    public function permanentDelete(string $id, string $workspaceId): bool
    {
        $stmt = $this->db->prepare("
            SELECT * FROM documents WHERE id = ? AND workspace_id = ?
        ");
        $stmt->execute([$id, $workspaceId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            throw new RuntimeException('Dokumen tidak ditemukan.');
        }

        // Attempt to remove physical file if not sample file
        if (!empty($doc['storage_path']) && !str_starts_with($doc['storage_path'], 'sample_')) {
            $filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . $doc['storage_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $stmtDel = $this->db->prepare("DELETE FROM documents WHERE id = ? AND workspace_id = ?");
        return $stmtDel->execute([$id, $workspaceId]);
    }

    public function emptyTrash(string $workspaceId): int
    {
        $stmt = $this->db->prepare("
            SELECT id, storage_path FROM documents 
            WHERE workspace_id = ? AND deleted_at IS NOT NULL
        ");
        $stmt->execute([$workspaceId]);
        $trashDocs = $stmt->fetchAll();

        foreach ($trashDocs as $doc) {
            if (!empty($doc['storage_path']) && !str_starts_with($doc['storage_path'], 'sample_')) {
                $filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . $doc['storage_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        $stmtDel = $this->db->prepare("
            DELETE FROM documents 
            WHERE workspace_id = ? AND deleted_at IS NOT NULL
        ");
        $stmtDel->execute([$workspaceId]);
        $count = count($trashDocs);

        if ($count > 0) {
            $this->recordActivity($workspaceId, 'delete', "Mengosongkan tempat sampah ({$count} dokumen dihapus permanen)");
        }

        return $count;
    }

    public function multiAction(string $workspaceId, string $action, array $documentIds, ?string $targetFolderId = null): array
    {
        $successCount = 0;
        foreach ($documentIds as $id) {
            try {
                if ($action === 'delete') {
                    if ($this->softDelete($id, $workspaceId)) $successCount++;
                } elseif ($action === 'restore') {
                    if ($this->restoreFromTrash($id, $workspaceId)) $successCount++;
                } elseif ($action === 'permanent_delete') {
                    if ($this->permanentDelete($id, $workspaceId)) $successCount++;
                } elseif ($action === 'move') {
                    if ($this->moveToFolder($id, $workspaceId, $targetFolderId)) $successCount++;
                }
            } catch (Exception $e) {
                // Continue with next
            }
        }

        return ['success' => true, 'affected_count' => $successCount];
    }

    public function getRecentActivities(string $workspaceId, int $limit = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM activities 
            WHERE workspace_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $workspaceId, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $activities = $stmt->fetchAll();

        foreach ($activities as &$act) {
            $act['time_ago'] = timeAgoIndo($act['created_at']);
        }

        return $activities;
    }

    private function recordActivity(string $workspaceId, string $type, string $description): void
    {
        $actId = 'act_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
        $user = currentUser();
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            INSERT INTO activities (id, workspace_id, user_name, user_avatar, action_type, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$actId, $workspaceId, $user['name'], $user['avatar'], $type, $description, $now]);
    }
}
