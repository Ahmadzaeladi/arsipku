<?php
/**
 * ArsipKu - Document Service
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Helpers.php';

class DocumentAuthorizationException extends RuntimeException
{
}

class DocumentNotFoundException extends RuntimeException
{
}

class DocumentService
{
    private PDO $db;
    private array $workspaceRoleCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getDocuments(string $workspaceId, array $filters = []): array
    {
        $user = currentUser();
        $role = $this->workspaceRole($workspaceId, $user);
        if ($role === null) {
            throw new DocumentAuthorizationException('Anda bukan anggota aktif workspace ini.');
        }

        $sql = "
            SELECT d.*, f.name as folder_name, f.color as folder_color,
                   (SELECT ds.permission
                    FROM document_shares ds
                    WHERE ds.document_id = d.id AND ds.shared_with_user_id = :recipient_user_id
                    LIMIT 1) AS recipient_permission
            FROM documents d
            LEFT JOIN folders f ON d.folder_id = f.id
            WHERE d.workspace_id = :workspace_id
        ";
        $params = [
            ':workspace_id' => $workspaceId,
            ':recipient_user_id' => $user['id'],
        ];

        if (!in_array($role, ['Owner', 'Admin'], true)) {
            $sql .= " AND (
                d.is_shared = 0
                OR d.uploaded_by = :viewer_owner_id
                OR EXISTS (
                    SELECT 1 FROM document_shares access_grant
                    WHERE access_grant.document_id = d.id
                      AND access_grant.workspace_id = d.workspace_id
                      AND access_grant.shared_with_user_id = :viewer_user_id
                )
            )";
            $params[':viewer_owner_id'] = $user['id'];
            $params[':viewer_user_id'] = $user['id'];
        }

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
            $sql .= " AND (
                d.name LIKE :search_name
                OR d.original_name LIKE :search_original_name
                OR d.owner_name LIKE :search_owner
                OR f.name LIKE :search_folder
            )";
            $searchTerm = '%' . trim($filters['q']) . '%';
            $params[':search_name'] = $searchTerm;
            $params[':search_original_name'] = $searchTerm;
            $params[':search_owner'] = $searchTerm;
            $params[':search_folder'] = $searchTerm;
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

        return $this->enrichDocuments($documents);
    }

    public function getSharedWithMe(string $workspaceId): array
    {
        $user = currentUser();
        if ($this->workspaceRole($workspaceId, $user) === null) {
            throw new DocumentAuthorizationException('Anda bukan anggota aktif workspace ini.');
        }

        $stmt = $this->db->prepare("
            SELECT d.*, f.name AS folder_name, f.color AS folder_color,
                   s.permission AS recipient_permission, sharer.name AS shared_by_name
            FROM document_shares s
            INNER JOIN documents d ON d.id = s.document_id AND d.workspace_id = s.workspace_id
            LEFT JOIN folders f ON d.folder_id = f.id
            LEFT JOIN users sharer ON sharer.id = s.shared_by_user_id
            WHERE s.workspace_id = ?
              AND s.shared_with_user_id = ?
              AND d.is_shared = 1
              AND d.deleted_at IS NULL
            ORDER BY d.updated_at DESC
        ");
        $stmt->execute([$workspaceId, $user['id']]);
        return $this->enrichDocuments($stmt->fetchAll());
    }

    public function getById(string $id, string $workspaceId): ?array
    {
        $user = currentUser();
        $stmt = $this->db->prepare("
            SELECT d.*, f.name as folder_name, f.color as folder_color,
                   (SELECT ds.permission
                    FROM document_shares ds
                    WHERE ds.document_id = d.id AND ds.shared_with_user_id = ?
                    LIMIT 1) AS recipient_permission
            FROM documents d
            LEFT JOIN folders f ON d.folder_id = f.id
            WHERE d.id = ? AND d.workspace_id = ? AND d.deleted_at IS NULL
        ");
        $stmt->execute([$user['id'], $id, $workspaceId]);
        $doc = $stmt->fetch();
        if (!$doc || !$this->canViewDocument($doc, $workspaceId, $user)) {
            return null;
        }

        $doc['formatted_created'] = formatIndoDate($doc['created_at']);
        return $this->enrichDocument($doc);
    }

    public function getShareInfo(string $id, string $workspaceId): array
    {
        $doc = $this->getById($id, $workspaceId);
        if (!$doc) {
            throw new DocumentNotFoundException('Dokumen tidak ditemukan.');
        }
        $this->assertCanManageSharing($doc, $workspaceId);

        $user = currentUser();
        $members = $this->db->prepare("
            SELECT u.id, u.name, u.email, m.role
            FROM members m
            INNER JOIN users u ON LOWER(u.email) = LOWER(m.email)
            WHERE m.workspace_id = ? AND m.status = 'active' AND u.id <> ?
            ORDER BY u.name ASC
        ");
        $members->execute([$workspaceId, $user['id']]);
        $shares = $this->db->prepare("
            SELECT shared_with_user_id, permission
            FROM document_shares
            WHERE document_id = ? AND workspace_id = ?
        ");
        $shares->execute([$id, $workspaceId]);
        $grants = [];
        foreach ($shares->fetchAll() as $grant) {
            $grants[$grant['shared_with_user_id']] = $grant['permission'];
        }

        $memberRows = [];
        foreach ($members->fetchAll() as $member) {
            $permission = $grants[$member['id']] ?? null;
            $memberRows[] = [
                'id' => (string) $member['id'],
                'name' => (string) $member['name'],
                'email' => (string) $member['email'],
                'role' => (string) $member['role'],
                'shared' => $permission !== null,
                'permission' => $permission,
            ];
        }

        return [
            'document_id' => (string) $doc['id'],
            'document_name' => (string) $doc['name'],
            'scope' => !empty($doc['is_shared']) ? 'selected' : 'workspace',
            'permission' => $doc['shared_permission'] === 'edit' ? 'edit' : 'view',
            'members' => $memberRows,
        ];
    }

    public function saveSharing(
        string $id,
        string $workspaceId,
        string $scope,
        array $recipientIds,
        string $permission
    ): array {
        $doc = $this->getById($id, $workspaceId);
        if (!$doc) {
            throw new DocumentNotFoundException('Dokumen tidak ditemukan.');
        }
        $this->assertCanManageSharing($doc, $workspaceId);

        if (!in_array($scope, ['workspace', 'selected'], true)) {
            throw new InvalidArgumentException('Cakupan berbagi tidak valid.');
        }
        if (!in_array($permission, ['view', 'edit'], true)) {
            throw new InvalidArgumentException('Hak akses harus berupa lihat atau edit.');
        }

        $user = currentUser();
        foreach ($recipientIds as $recipientId) {
            if (!is_string($recipientId) || $recipientId === '') {
                throw new InvalidArgumentException('Daftar penerima tidak valid.');
            }
        }
        $recipientIds = array_values(array_unique($recipientIds));
        if ($scope === 'selected' && !$recipientIds) {
            throw new InvalidArgumentException('Pilih setidaknya satu anggota atau ubah akses ke seluruh workspace.');
        }
        if (count($recipientIds) > 100) {
            throw new InvalidArgumentException('Maksimal 100 penerima untuk satu dokumen.');
        }

        if ($scope === 'selected') {
            $placeholders = implode(', ', array_fill(0, count($recipientIds), '?'));
            $memberStmt = $this->db->prepare("
                SELECT u.id
                FROM members m
                INNER JOIN users u ON LOWER(u.email) = LOWER(m.email)
                WHERE m.workspace_id = ? AND m.status = 'active' AND u.id <> ?
                  AND u.id IN ({$placeholders})
            ");
            $memberStmt->execute(array_merge([$workspaceId, $user['id']], $recipientIds));
            $activeIds = array_map('strval', $memberStmt->fetchAll(PDO::FETCH_COLUMN));
            sort($activeIds);
            $requestedIds = $recipientIds;
            sort($requestedIds);
            if ($activeIds !== $requestedIds) {
                throw new InvalidArgumentException('Semua penerima harus anggota aktif workspace ini.');
            }
        } else {
            $recipientIds = [];
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $delete = $this->db->prepare('DELETE FROM document_shares WHERE document_id = ? AND workspace_id = ?');
            $delete->execute([$id, $workspaceId]);

            if ($scope === 'selected') {
                $insert = $this->db->prepare("
                    INSERT INTO document_shares
                        (document_id, workspace_id, shared_with_user_id, shared_by_user_id, permission, created_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $createdAt = date('Y-m-d H:i:s');
                foreach ($recipientIds as $recipientId) {
                    $insert->execute([$id, $workspaceId, $recipientId, $user['id'], $permission, $createdAt]);
                }
            }

            $isRestricted = $scope === 'selected' ? 1 : 0;
            $documentPermission = $isRestricted ? $permission : 'view';
            $update = $this->db->prepare("
                UPDATE documents
                SET is_shared = ?, shared_permission = ?, updated_at = ?
                WHERE id = ? AND workspace_id = ?
            ");
            $update->execute([$isRestricted, $documentPermission, date('Y-m-d H:i:s'), $id, $workspaceId]);

            $this->recordActivity(
                $workspaceId,
                'share',
                $scope === 'selected'
                    ? 'Membagikan dokumen "' . $doc['name'] . '" kepada ' . count($recipientIds) . ' anggota'
                    : 'Mengembalikan dokumen "' . $doc['name'] . '" ke seluruh workspace'
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->getShareInfo($id, $workspaceId);
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
        $this->assertCanEdit($doc, $workspaceId);

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
        $this->assertCanEdit($doc, $workspaceId);

        $targetFolderId = (!empty($folderId) && $folderId !== 'root') ? $folderId : null;
        if ($targetFolderId !== null) {
            $folderStmt = $this->db->prepare("
                SELECT id FROM folders
                WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL
            ");
            $folderStmt->execute([$targetFolderId, $workspaceId]);
            if (!$folderStmt->fetchColumn()) {
                throw new InvalidArgumentException('Folder tujuan tidak tersedia di workspace ini.');
            }
        }

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
        $this->assertCanDelete($doc, $workspaceId);

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
        $this->assertCanDelete($doc, $workspaceId);

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
        $this->assertCanDelete($doc, $workspaceId);

        $this->deleteStoredDocument($doc['storage_path'] ?? '');

        $stmtDel = $this->db->prepare("DELETE FROM documents WHERE id = ? AND workspace_id = ?");
        return $stmtDel->execute([$id, $workspaceId]);
    }

    public function emptyTrash(string $workspaceId): int
    {
        $user = currentUser();
        $role = $this->workspaceRole($workspaceId, $user);
        if ($role === null) {
            throw new DocumentAuthorizationException('Anda bukan anggota aktif workspace ini.');
        }
        $sql = "
            SELECT id, storage_path FROM documents
            WHERE workspace_id = ? AND deleted_at IS NOT NULL
        ";
        $params = [$workspaceId];
        if (!in_array($role, ['Owner', 'Admin'], true)) {
            $sql .= " AND (is_shared = 0 OR uploaded_by = ?)";
            $params[] = $user['id'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $trashDocs = $stmt->fetchAll();

        foreach ($trashDocs as $doc) {
            $this->deleteStoredDocument($doc['storage_path'] ?? '');
        }

        $deleteSql = "DELETE FROM documents WHERE workspace_id = ? AND deleted_at IS NOT NULL";
        $deleteParams = [$workspaceId];
        if (!in_array($role, ['Owner', 'Admin'], true)) {
            $deleteSql .= " AND (is_shared = 0 OR uploaded_by = ?)";
            $deleteParams[] = $user['id'];
        }
        $stmtDel = $this->db->prepare($deleteSql);
        $stmtDel->execute($deleteParams);
        $count = count($trashDocs);

        if ($count > 0) {
            $this->recordActivity($workspaceId, 'delete', "Mengosongkan tempat sampah ({$count} dokumen dihapus permanen)");
        }

        return $count;
    }

    public function multiAction(string $workspaceId, string $action, array $documentIds, ?string $targetFolderId = null): array
    {
        if (!in_array($action, ['delete', 'restore', 'permanent_delete', 'move'], true)) {
            throw new InvalidArgumentException('Aksi massal tidak valid.');
        }
        foreach ($documentIds as $id) {
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException('Daftar dokumen tidak valid.');
            }
        }
        $documentIds = array_values(array_unique($documentIds));
        if (!$documentIds) {
            throw new InvalidArgumentException('Pilih setidaknya satu dokumen.');
        }

        foreach ($documentIds as $id) {
            if ($action === 'restore' || $action === 'permanent_delete') {
                $stmt = $this->db->prepare("
                    SELECT * FROM documents
                    WHERE id = ? AND workspace_id = ? AND deleted_at IS NOT NULL
                ");
                $stmt->execute([$id, $workspaceId]);
                $doc = $stmt->fetch();
            } else {
                $doc = $this->getById($id, $workspaceId);
            }
            if (!$doc) {
                throw new DocumentNotFoundException('Salah satu dokumen tidak ditemukan atau tidak dapat diakses.');
            }
            if ($action === 'move') {
                $this->assertCanEdit($doc, $workspaceId);
            } elseif ($action === 'delete' || $action === 'restore' || $action === 'permanent_delete') {
                $this->assertCanDelete($doc, $workspaceId);
            }
        }

        $successCount = 0;
        foreach ($documentIds as $id) {
            if ($action === 'delete' && $this->softDelete($id, $workspaceId)) {
                $successCount++;
            } elseif ($action === 'restore' && $this->restoreFromTrash($id, $workspaceId)) {
                $successCount++;
            } elseif ($action === 'permanent_delete' && $this->permanentDelete($id, $workspaceId)) {
                $successCount++;
            } elseif ($action === 'move' && $this->moveToFolder($id, $workspaceId, $targetFolderId)) {
                $successCount++;
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

    private function workspaceRole(string $workspaceId, array $user): ?string
    {
        $cacheKey = $workspaceId . ':' . $user['id'];
        if (array_key_exists($cacheKey, $this->workspaceRoleCache)) {
            return $this->workspaceRoleCache[$cacheKey];
        }
        $stmt = $this->db->prepare("
            SELECT role FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $user['email']]);
        $role = $stmt->fetchColumn();
        $this->workspaceRoleCache[$cacheKey] = is_string($role) ? $role : null;
        return $this->workspaceRoleCache[$cacheKey];
    }

    private function canViewDocument(array $doc, string $workspaceId, array $user): bool
    {
        $role = $this->workspaceRole($workspaceId, $user);
        if ($role === null) {
            return false;
        }
        if (!in_array($role, ['Owner', 'Admin'], true)
            && !empty($doc['is_shared'])
            && $doc['uploaded_by'] !== $user['id']
            && empty($doc['recipient_permission'])) {
            return false;
        }
        return true;
    }

    private function assertCanEdit(array $doc, string $workspaceId): void
    {
        if (empty($doc['is_shared'])) {
            return;
        }
        $user = currentUser();
        $role = $this->workspaceRole($workspaceId, $user);
        if (in_array($role, ['Owner', 'Admin'], true)
            || $doc['uploaded_by'] === $user['id']
            || ($doc['recipient_permission'] ?? null) === 'edit') {
            return;
        }
        throw new DocumentAuthorizationException('Anda hanya memiliki izin untuk melihat dokumen ini.');
    }

    private function assertCanDelete(array $doc, string $workspaceId): void
    {
        if (empty($doc['is_shared'])) {
            return;
        }
        $user = currentUser();
        $role = $this->workspaceRole($workspaceId, $user);
        if (in_array($role, ['Owner', 'Admin'], true) || $doc['uploaded_by'] === $user['id']) {
            return;
        }
        throw new DocumentAuthorizationException('Hanya pemilik dokumen atau Owner/Admin yang dapat menghapusnya.');
    }

    private function assertCanManageSharing(array $doc, string $workspaceId): void
    {
        $user = currentUser();
        $role = $this->workspaceRole($workspaceId, $user);
        if ($role === null) {
            throw new DocumentAuthorizationException('Anda bukan anggota aktif workspace ini.');
        }
        if (in_array($role, ['Owner', 'Admin'], true) || $doc['uploaded_by'] === $user['id']) {
            return;
        }
        throw new DocumentAuthorizationException('Hanya pemilik dokumen atau Owner/Admin yang dapat mengatur berbagi.');
    }

    private function enrichDocuments(array $documents): array
    {
        foreach ($documents as &$doc) {
            $doc = $this->enrichDocument($doc);
        }
        unset($doc);
        return $documents;
    }

    private function enrichDocument(array $doc): array
    {
        $user = currentUser();
        $role = $this->workspaceRole((string) $doc['workspace_id'], $user);
        $manager = in_array($role, ['Owner', 'Admin'], true) || $doc['uploaded_by'] === $user['id'];
        $restricted = !empty($doc['is_shared']);
        $doc['can_edit'] = !$restricted || $manager || ($doc['recipient_permission'] ?? null) === 'edit';
        $doc['can_delete'] = !$restricted || $manager;
        $doc['can_share'] = $manager;
        $doc['formatted_size'] = formatBytes((int) $doc['size_bytes']);
        $doc['formatted_date'] = formatIndoDate($doc['updated_at']);
        $doc['time_ago'] = timeAgoIndo($doc['updated_at']);
        $doc['meta'] = getFileMeta($doc['extension']);
        $doc['download_url'] = 'api/download.php?' . http_build_query([
            'id' => $doc['id'],
            'disposition' => 'attachment',
        ], '', '&', PHP_QUERY_RFC3986);
        $doc['preview_url'] = 'api/download.php?' . http_build_query([
            'id' => $doc['id'],
            'disposition' => 'inline',
        ], '', '&', PHP_QUERY_RFC3986);
        return $doc;
    }

    private function deleteStoredDocument(string $storagePath): void
    {
        if ($storagePath === '' || str_starts_with($storagePath, 'sample_')) {
            return;
        }
        $filePath = $this->resolveStoredDocument($storagePath);
        if ($filePath !== null && file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new RuntimeException('Berkas dokumen tidak dapat dihapus dari penyimpanan privat.');
            }
        }
    }

    public function resolveStoredDocument(string $storagePath): ?string
    {
        if ($storagePath === '' || basename($storagePath) !== $storagePath) {
            return null;
        }
        foreach ([DOCUMENT_UPLOAD_PATH, UPLOAD_PATH] as $directory) {
            $root = realpath($directory);
            $path = realpath($directory . DIRECTORY_SEPARATOR . $storagePath);
            if ($root !== false && $path !== false && is_file($path) && dirname($path) === $root) {
                return $path;
            }
        }
        return null;
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
