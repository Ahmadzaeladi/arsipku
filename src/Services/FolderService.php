<?php
/**
 * ArsipKu - Folder Service
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Helpers.php';

class FolderService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByWorkspace(string $workspaceId, ?string $parentId = null): array
    {
        if ($parentId === null) {
            $stmt = $this->db->prepare("
                SELECT f.*, 
                    (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id AND d.deleted_at IS NULL) as doc_count,
                    (SELECT COUNT(*) FROM folders sf WHERE sf.parent_id = f.id AND sf.deleted_at IS NULL) as subfolder_count
                FROM folders f
                WHERE f.workspace_id = ? AND f.parent_id IS NULL AND f.deleted_at IS NULL
                ORDER BY f.name ASC
            ");
            $stmt->execute([$workspaceId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT f.*, 
                    (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id AND d.deleted_at IS NULL) as doc_count,
                    (SELECT COUNT(*) FROM folders sf WHERE sf.parent_id = f.id AND sf.deleted_at IS NULL) as subfolder_count
                FROM folders f
                WHERE f.workspace_id = ? AND f.parent_id = ? AND f.deleted_at IS NULL
                ORDER BY f.name ASC
            ");
            $stmt->execute([$workspaceId, $parentId]);
        }
        return $stmt->fetchAll();
    }

    public function getAllFlat(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, parent_id 
            FROM folders 
            WHERE workspace_id = ? AND deleted_at IS NULL 
            ORDER BY name ASC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function getById(string $id, string $workspaceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT f.*,
                (SELECT COUNT(*) FROM documents d WHERE d.folder_id = f.id AND d.deleted_at IS NULL) as doc_count
            FROM folders f
            WHERE f.id = ? AND f.workspace_id = ? AND f.deleted_at IS NULL
        ");
        $stmt->execute([$id, $workspaceId]);
        $folder = $stmt->fetch();
        return $folder ?: null;
    }

    public function getBreadcrumbs(string $workspaceId, ?string $folderId): array
    {
        $breadcrumbs = [];
        $currentId = $folderId;

        while ($currentId) {
            $folder = $this->getById($currentId, $workspaceId);
            if (!$folder) {
                break;
            }
            array_unshift($breadcrumbs, [
                'id' => $folder['id'],
                'name' => $folder['name'],
                'parent_id' => $folder['parent_id']
            ]);
            $currentId = $folder['parent_id'];
        }

        return $breadcrumbs;
    }

    public function create(string $workspaceId, string $name, ?string $parentId = null, string $color = 'indigo'): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException('Nama folder tidak boleh kosong.');
        }

        $id = 'fld_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            INSERT INTO folders (id, workspace_id, parent_id, name, color, icon, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'folder', ?, ?)
        ");
        $stmt->execute([$id, $workspaceId, $parentId ?: null, $name, $color, $now, $now]);

        // Record activity
        $this->recordActivity($workspaceId, 'folder_create', 'Membuat folder baru "' . $name . '"');

        return $this->getById($id, $workspaceId);
    }

    public function rename(string $id, string $workspaceId, string $newName): bool
    {
        $newName = trim($newName);
        if (empty($newName)) {
            throw new InvalidArgumentException('Nama folder tidak boleh kosong.');
        }

        $folder = $this->getById($id, $workspaceId);
        if (!$folder) {
            throw new RuntimeException('Folder tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            UPDATE folders 
            SET name = ?, updated_at = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $res = $stmt->execute([$newName, $now, $id, $workspaceId]);

        if ($res) {
            $this->recordActivity($workspaceId, 'rename', 'Mengubah nama folder dari "' . $folder['name'] . '" menjadi "' . $newName . '"');
        }

        return $res;
    }

    public function delete(string $id, string $workspaceId): bool
    {
        $folder = $this->getById($id, $workspaceId);
        if (!$folder) {
            throw new RuntimeException('Folder tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');
        // Soft delete folder
        $stmt = $this->db->prepare("
            UPDATE folders 
            SET deleted_at = ? 
            WHERE id = ? AND workspace_id = ?
        ");
        $res = $stmt->execute([$now, $id, $workspaceId]);

        // Also move documents in this folder to root or soft delete
        // In ArsipKu, we keep documents intact or soft delete
        $this->db->prepare("
            UPDATE documents 
            SET deleted_at = ? 
            WHERE folder_id = ? AND workspace_id = ? AND deleted_at IS NULL
        ")->execute([$now, $id, $workspaceId]);

        if ($res) {
            $this->recordActivity($workspaceId, 'delete', 'Memindahkan folder "' . $folder['name'] . '" ke Sampah');
        }

        return $res;
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
