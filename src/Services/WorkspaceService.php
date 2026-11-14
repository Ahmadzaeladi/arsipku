<?php
/**
 * ArsipKu - Workspace Service
 * PHP Native, No Framework
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Helpers.php';

class WorkspaceAuthorizationException extends RuntimeException
{
}

class WorkspaceConflictException extends RuntimeException
{
}

class WorkspaceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*
            FROM workspaces w
            INNER JOIN members m ON m.workspace_id = w.id
            WHERE LOWER(m.email) = LOWER(?) AND m.status = 'active'
            ORDER BY w.created_at ASC
        ");
        $stmt->execute([currentUser()['email']]);
        return $stmt->fetchAll();
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM workspaces WHERE slug = ?");
        $stmt->execute([$slug]);
        $ws = $stmt->fetch();
        return $ws ?: null;
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM workspaces WHERE id = ?");
        $stmt->execute([$id]);
        $ws = $stmt->fetch();
        return $ws ?: null;
    }

    public function getCurrentWorkspace(): array
    {
        $slug = $_GET['ws'] ?? $_SESSION['active_workspace'] ?? null;
        
        $workspace = null;
        if ($slug) {
            $stmt = $this->db->prepare("
                SELECT w.*
                FROM workspaces w
                INNER JOIN members m ON m.workspace_id = w.id
                WHERE w.slug = ? AND LOWER(m.email) = LOWER(?) AND m.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$slug, currentUser()['email']]);
            $workspace = $stmt->fetch() ?: null;
        }

        if (!$workspace) {
            $all = $this->getAll();
            $workspace = !empty($all) ? $all[0] : null;
            if (!$workspace) {
                throw new RuntimeException('Workspace belum tersedia di database MySQL.');
            }
        }

        $_SESSION['active_workspace'] = $workspace['slug'];
        return $workspace;
    }

    public function switchWorkspace(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT w.*
            FROM workspaces w
            INNER JOIN members m ON m.workspace_id = w.id
            WHERE w.slug = ? AND LOWER(m.email) = LOWER(?) AND m.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$slug, currentUser()['email']]);
        $ws = $stmt->fetch() ?: null;
        if ($ws) {
            $_SESSION['active_workspace'] = $ws['slug'];
            return $ws;
        }
        return null;
    }

    public function getStats(string $workspaceId): array
    {
        $ws = $this->getById($workspaceId);
        if (!$ws) {
            throw new RuntimeException('Workspace tidak ditemukan di database MySQL.');
        }
        $limit = (int) $ws['storage_limit_bytes'];

        // Document count and total size
        $stmtDoc = $this->db->prepare("
            SELECT COUNT(*) as total_docs, COALESCE(SUM(size_bytes), 0) as total_bytes
            FROM documents 
            WHERE workspace_id = ? AND deleted_at IS NULL
        ");
        $stmtDoc->execute([$workspaceId]);
        $docStats = $stmtDoc->fetch();

        // Folder count
        $stmtFld = $this->db->prepare("
            SELECT COUNT(*) as total_folders 
            FROM folders 
            WHERE workspace_id = ? AND deleted_at IS NULL
        ");
        $stmtFld->execute([$workspaceId]);
        $totalFolders = (int)$stmtFld->fetchColumn();

        $totalDocs = (int)$docStats['total_docs'];
        $totalBytes = (int)$docStats['total_bytes'];
        $percentage = $limit > 0 ? min(100, round(($totalBytes / $limit) * 100, 1)) : 0;

        return [
            'total_documents' => $totalDocs,
            'total_folders' => $totalFolders,
            'total_size_bytes' => $totalBytes,
            'formatted_size' => formatBytes($totalBytes),
            'storage_limit_bytes' => $limit,
            'formatted_limit' => formatBytes($limit),
            'usage_percentage' => $percentage,
            'is_warning' => $percentage >= 80,
            'plan_name' => $ws['plan_name']
        ];
    }

    public function update(string $id, array $data): bool
    {
        $this->assertWorkspaceOwner($id);
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Nama workspace tidak boleh kosong.');
        }
        if (!in_array($type, ['company', 'personal', 'school'], true)) {
            throw new InvalidArgumentException('Kategori workspace tidak valid.');
        }

        if (array_key_exists('logo_path', $data)) {
            $logoPath = $data['logo_path'];
            if ($logoPath !== null && (!is_string($logoPath) || !preg_match('/^workspace_[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$/', $logoPath))) {
                throw new InvalidArgumentException('Logo workspace tidak valid.');
            }
            $stmt = $this->db->prepare("
                UPDATE workspaces
                SET name = ?, type = ?, logo_path = ?
                WHERE id = ?
            ");
            return $stmt->execute([$name, $type, $logoPath, $id]);
        }

        $stmt = $this->db->prepare("
            UPDATE workspaces
            SET name = ?, type = ?
            WHERE id = ?
        ");
        return $stmt->execute([$name, $type, $id]);
    }

    private function assertWorkspaceOwner(string $workspaceId): void
    {
        $stmt = $this->db->prepare("
            SELECT role
            FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, currentUser()['email']]);
        if ($stmt->fetchColumn() !== 'Owner') {
            throw new WorkspaceAuthorizationException('Hanya Owner workspace yang dapat mengubah pengaturan workspace.');
        }
    }

    public function create(string $name, string $type = 'company'): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException('Nama workspace tidak boleh kosong.');
        }

        // Generate slug
        $baseSlug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $baseSlug = trim($baseSlug, '-');
        if (empty($baseSlug)) {
            $baseSlug = 'workspace-' . substr(md5(uniqid()), 0, 6);
        }

        $slug = $baseSlug;
        $counter = 1;
        while ($this->getBySlug($slug)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $id = 'ws_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
        $limits = [
            'personal' => 10 * 1024 * 1024 * 1024,
            'company' => 50 * 1024 * 1024 * 1024,
            'school' => 25 * 1024 * 1024 * 1024
        ];
        $plans = [
            'personal' => 'Personal Pro',
            'company' => 'Enterprise Business',
            'school' => 'Edu & Institution'
        ];

        $limitBytes = $limits[$type] ?? $limits['company'];
        $planName = $plans[$type] ?? 'Enterprise Business';
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            INSERT INTO workspaces (id, name, slug, type, storage_limit_bytes, plan_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id, $name, $slug, $type, $limitBytes, $planName, $now]);

        // Add creator as Owner member
        $user = currentUser();
        $memId = 'mem_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
        $stmtMem = $this->db->prepare("
            INSERT INTO members (id, workspace_id, name, email, avatar, role, status, joined_at)
            VALUES (?, ?, ?, ?, ?, 'Owner', 'active', ?)
        ");
        $stmtMem->execute([$memId, $id, $user['name'], $user['email'], $user['avatar'], $now]);

        // Automatically switch active session to new workspace
        $_SESSION['active_workspace'] = $slug;

        return $this->getById($id);
    }

    public function changePlan(string $workspaceId, string $planName, int $limitBytes): bool
    {
        $this->assertWorkspaceOwner($workspaceId);
        $stmt = $this->db->prepare("
            UPDATE workspaces 
            SET plan_name = ?, storage_limit_bytes = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$planName, $limitBytes, $workspaceId]);
    }

    public function delete(string $workspaceId): bool
    {
        $user = currentUser();
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            if (!$stmt->fetchColumn()) {
                throw new WorkspaceAuthorizationException('Akun tidak ditemukan.');
            }

            $stmt = $this->db->prepare("
                SELECT role
                FROM members
                WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$workspaceId, $user['email']]);
            if ($stmt->fetchColumn() !== 'Owner') {
                throw new WorkspaceAuthorizationException('Hanya Owner workspace yang dapat menghapus workspace ini.');
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT workspace_id)
                FROM members
                WHERE LOWER(email) = LOWER(?) AND status = 'active'
            ");
            $stmt->execute([$user['email']]);
            if ((int) $stmt->fetchColumn() <= 1) {
                throw new WorkspaceConflictException('Workspace terakhir di akun Anda tidak dapat dihapus.');
            }

            $stmt = $this->db->prepare('DELETE FROM workspaces WHERE id = ?');
            $stmt->execute([$workspaceId]);
            if ($stmt->rowCount() !== 1) {
                throw new WorkspaceConflictException('Workspace tidak ditemukan atau sudah dihapus.');
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $stmt = $this->db->prepare("
            SELECT w.slug
            FROM workspaces w
            INNER JOIN members m ON m.workspace_id = w.id
            WHERE LOWER(m.email) = LOWER(?) AND m.status = 'active'
            ORDER BY w.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$user['email']]);
        $nextSlug = $stmt->fetchColumn();
        if (is_string($nextSlug)) {
            $_SESSION['active_workspace'] = $nextSlug;
        }
        return true;
    }
}
