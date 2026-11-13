<?php
/**
 * ArsipKu - Workspace member and invitation service.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Config.php';

class MemberAuthorizationException extends RuntimeException
{
}

class MemberConflictException extends RuntimeException
{
}

class MemberService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getMembers(string $workspaceId): array
    {
        $this->assertWorkspaceMember($workspaceId);
        $stmt = $this->db->prepare("
            SELECT * FROM members
            WHERE workspace_id = ?
            ORDER BY
                CASE role
                    WHEN 'Owner' THEN 1
                    WHEN 'Admin' THEN 2
                    WHEN 'Editor' THEN 3
                    ELSE 4
                END,
                joined_at ASC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function getCurrentRole(string $workspaceId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT role FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, currentUser()['email']]);
        $role = $stmt->fetchColumn();
        return is_string($role) ? $role : null;
    }

    public function inviteByEmail(string $workspaceId, string $email, string $role): array
    {
        $this->assertWorkspaceManager($workspaceId);
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        $this->assertAssignableRole($role);

        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $invitee = $stmt->fetch();
        if (!$invitee) {
            throw new InvalidArgumentException('Email belum terdaftar. Undangan email hanya bisa dikirim ke akun yang sudah ada.');
        }
        if ($invitee['id'] === currentUser()['id']) {
            throw new InvalidArgumentException('Anda tidak dapat mengundang akun sendiri.');
        }

        $stmt = $this->db->prepare("
            SELECT id, status FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status IN ('active', 'invited')
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $email]);
        $existingMember = $stmt->fetch();
        if ($existingMember) {
            throw new MemberConflictException($existingMember['status'] === 'active'
                ? 'Pengguna tersebut sudah menjadi anggota workspace.'
                : 'Pengguna tersebut sudah memiliki undangan yang belum diterima.');
        }

        $stmt = $this->db->prepare("
            SELECT id FROM member_invitations
            WHERE workspace_id = ? AND invitee_user_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $invitee['id']]);
        if ($stmt->fetch()) {
            throw new MemberConflictException('Undangan untuk pengguna tersebut masih menunggu jawaban.');
        }

        $id = $this->newId('minv');
        $createdAt = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            INSERT INTO member_invitations
                (id, workspace_id, invitee_user_id, invited_by_user_id, role, source, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'email', 'pending', ?)
        ");
        $stmt->execute([$id, $workspaceId, $invitee['id'], currentUser()['id'], $role, $createdAt]);

        return [
            'id' => $id,
            'workspace_id' => $workspaceId,
            'invitee_user_id' => $invitee['id'],
            'email' => $invitee['email'],
            'name' => $invitee['name'],
            'role' => $role,
            'source' => 'email',
            'status' => 'pending',
            'created_at' => $createdAt,
        ];
    }

    public function getMyInvitations(): array
    {
        $stmt = $this->db->prepare("
            SELECT i.id, i.workspace_id, i.role, i.created_at,
                   w.name AS workspace_name, w.slug AS workspace_slug,
                   u.name AS inviter_name
            FROM member_invitations i
            INNER JOIN workspaces w ON w.id = i.workspace_id
            LEFT JOIN users u ON u.id = i.invited_by_user_id
            WHERE i.invitee_user_id = ? AND i.status = 'pending' AND i.source = 'email'
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([currentUser()['id']]);
        return $stmt->fetchAll();
    }

    public function getWorkspaceInvitations(string $workspaceId): array
    {
        $this->assertWorkspaceManager($workspaceId);
        $stmt = $this->db->prepare("
            SELECT i.id, i.invitee_user_id, i.role, i.source, i.status, i.created_at,
                   u.name AS invitee_name, u.email AS invitee_email,
                   inviter.name AS inviter_name
            FROM member_invitations i
            INNER JOIN users u ON u.id = i.invitee_user_id
            LEFT JOIN users inviter ON inviter.id = i.invited_by_user_id
            WHERE i.workspace_id = ? AND i.status = 'pending'
            ORDER BY i.created_at ASC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function respondToInvitation(string $invitationId, bool $accept): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT i.*, u.name, u.email, w.slug AS workspace_slug
                FROM member_invitations i
                INNER JOIN users u ON u.id = i.invitee_user_id
                INNER JOIN workspaces w ON w.id = i.workspace_id
                WHERE i.id = ? AND i.invitee_user_id = ? AND i.source = 'email' AND i.status = 'pending'
                FOR UPDATE
            ");
            $stmt->execute([$invitationId, currentUser()['id']]);
            $invitation = $stmt->fetch();
            if (!$invitation) {
                throw new MemberConflictException('Undangan tidak ditemukan atau sudah ditanggapi.');
            }

            if ($accept) {
                $this->addMember($invitation);
            }
            $status = $accept ? 'accepted' : 'declined';
            $stmt = $this->db->prepare('UPDATE member_invitations SET status = ?, responded_at = ? WHERE id = ?');
            $stmt->execute([$status, date('Y-m-d H:i:s'), $invitationId]);
            $this->db->commit();
            return ['status' => $status, 'workspace_id' => $invitation['workspace_id'], 'workspace_slug' => $invitation['workspace_slug']];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function resolveWorkspaceInvitation(string $workspaceId, string $invitationId, bool $accept): array
    {
        $this->assertWorkspaceManager($workspaceId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT i.*, u.name, u.email
                FROM member_invitations i
                INNER JOIN users u ON u.id = i.invitee_user_id
                WHERE i.id = ? AND i.workspace_id = ? AND i.source = 'link' AND i.status = 'pending'
                FOR UPDATE
            ");
            $stmt->execute([$invitationId, $workspaceId]);
            $invitation = $stmt->fetch();
            if (!$invitation) {
                throw new MemberConflictException('Permintaan bergabung tidak ditemukan atau sudah diproses.');
            }
            if ($accept) {
                $this->addMember($invitation);
            }
            $status = $accept ? 'accepted' : 'rejected';
            $stmt = $this->db->prepare('UPDATE member_invitations SET status = ?, responded_at = ? WHERE id = ?');
            $stmt->execute([$status, date('Y-m-d H:i:s'), $invitationId]);
            $this->db->commit();
            return ['status' => $status, 'email' => $invitation['email']];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createInviteLink(string $workspaceId, string $role, bool $approvalRequired, int $expiresInDays = 7, ?int $maxUses = null): array
    {
        $this->assertWorkspaceManager($workspaceId);
        $this->assertAssignableRole($role);
        if ($expiresInDays < 1 || $expiresInDays > 30) {
            throw new InvalidArgumentException('Masa berlaku link harus antara 1 sampai 30 hari.');
        }
        if ($maxUses !== null && ($maxUses < 1 || $maxUses > 1000)) {
            throw new InvalidArgumentException('Batas penggunaan harus antara 1 sampai 1.000.');
        }

        $token = bin2hex(random_bytes(32));
        $id = $this->newId('link');
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+' . $expiresInDays . ' days');
        $stmt = $this->db->prepare("
            INSERT INTO workspace_invite_links
                (id, workspace_id, token_hash, role, approval_required, expires_at, max_uses, uses_count, created_by_user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
            $id,
            $workspaceId,
            hash('sha256', $token),
            $role,
            $approvalRequired ? 1 : 0,
            $expiresAt->format('Y-m-d H:i:s'),
            $maxUses,
            currentUser()['id'],
            $now->format('Y-m-d H:i:s'),
        ]);

        return [
            'id' => $id,
            'token' => $token,
            'invite_url' => 'index.php?' . http_build_query(['page' => 'join', 'token' => $token], '', '&', PHP_QUERY_RFC3986),
            'role' => $role,
            'approval_required' => $approvalRequired,
            'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
            'max_uses' => $maxUses,
        ];
    }

    public function getInviteLinkInfo(string $token): array
    {
        $link = $this->findUsableLink($token);
        return [
            'workspace_name' => $link['workspace_name'],
            'role' => $link['role'],
            'approval_required' => (bool) $link['approval_required'],
            'expires_at' => $link['expires_at'],
        ];
    }

    public function joinViaLink(string $token): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, w.name AS workspace_name, w.slug AS workspace_slug
                FROM workspace_invite_links l
                INNER JOIN workspaces w ON w.id = l.workspace_id
                WHERE l.token_hash = ?
                FOR UPDATE
            ");
            $stmt->execute([hash('sha256', $token)]);
            $link = $stmt->fetch();
            $this->assertLinkUsable($link);

            $user = currentUser();
            if ($this->isActiveMember($link['workspace_id'], $user['email'])) {
                throw new MemberConflictException('Anda sudah menjadi anggota workspace ini.');
            }
            $stmt = $this->db->prepare("
                SELECT id, status FROM member_invitations
                WHERE workspace_id = ? AND invitee_user_id = ? AND status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$link['workspace_id'], $user['id']]);
            $existing = $stmt->fetch();
            if ($existing) {
                throw new MemberConflictException('Permintaan bergabung Anda masih menunggu persetujuan.');
            }
            if ($link['max_uses'] !== null && (int) $link['uses_count'] >= (int) $link['max_uses']) {
                throw new MemberConflictException('Link undangan sudah mencapai batas penggunaan.');
            }

            $pending = (bool) $link['approval_required'];
            $invitationId = $this->newId('minv');
            $status = $pending ? 'pending' : 'accepted';
            $stmt = $this->db->prepare("
                INSERT INTO member_invitations
                    (id, workspace_id, invitee_user_id, invite_link_id, role, source, status, created_at, responded_at)
                VALUES (?, ?, ?, ?, ?, 'link', ?, ?, ?)
            ");
            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                $invitationId,
                $link['workspace_id'],
                $user['id'],
                $link['id'],
                $link['role'],
                $status,
                $now,
                $pending ? null : $now,
            ]);
            if (!$pending) {
                $this->addMember([
                    'workspace_id' => $link['workspace_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $link['role'],
                ]);
            }
            $stmt = $this->db->prepare('UPDATE workspace_invite_links SET uses_count = uses_count + 1 WHERE id = ?');
            $stmt->execute([$link['id']]);
            $this->db->commit();

            return [
                'status' => $status,
                'workspace_name' => $link['workspace_name'],
                'workspace_slug' => $link['workspace_slug'],
                'role' => $link['role'],
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getInviteLinks(string $workspaceId): array
    {
        $this->assertWorkspaceManager($workspaceId);
        $stmt = $this->db->prepare("
            SELECT id, role, approval_required, expires_at, max_uses, uses_count, created_at, revoked_at
            FROM workspace_invite_links
            WHERE workspace_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function revokeInviteLink(string $workspaceId, string $linkId): bool
    {
        $this->assertWorkspaceManager($workspaceId);
        $stmt = $this->db->prepare("
            UPDATE workspace_invite_links
            SET revoked_at = ?
            WHERE id = ? AND workspace_id = ? AND revoked_at IS NULL
        ");
        $stmt->execute([date('Y-m-d H:i:s'), $linkId, $workspaceId]);
        if ($stmt->rowCount() === 0) {
            throw new MemberConflictException('Link tidak ditemukan atau sudah dinonaktifkan.');
        }
        return true;
    }

    public function cancelEmailInvitation(string $workspaceId, string $invitationId): bool
    {
        $this->assertWorkspaceManager($workspaceId);
        $stmt = $this->db->prepare("
            UPDATE member_invitations
            SET status = 'cancelled', responded_at = ?
            WHERE id = ? AND workspace_id = ? AND source = 'email' AND status = 'pending'
        ");
        $stmt->execute([date('Y-m-d H:i:s'), $invitationId, $workspaceId]);
        if ($stmt->rowCount() === 0) {
            throw new MemberConflictException('Undangan tidak ditemukan atau sudah ditanggapi.');
        }
        return true;
    }

    public function updateRole(string $id, string $workspaceId, string $newRole): bool
    {
        $this->assertWorkspaceManager($workspaceId);
        $this->assertAssignableRole($newRole);
        $stmt = $this->db->prepare("
            UPDATE members
            SET role = ?
            WHERE id = ? AND workspace_id = ? AND role != 'Owner' AND status = 'active'
        ");
        $stmt->execute([$newRole, $id, $workspaceId]);
        return $stmt->rowCount() > 0;
    }

    public function remove(string $id, string $workspaceId): bool
    {
        $this->assertWorkspaceManager($workspaceId);
        $stmt = $this->db->prepare("
            SELECT email, role FROM members
            WHERE id = ? AND workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $workspaceId]);
        $member = $stmt->fetch();
        if (!$member || $member['role'] === 'Owner') {
            return false;
        }
        if (strtolower($member['email']) === strtolower(currentUser()['email'])) {
            throw new MemberConflictException('Anda tidak dapat mengeluarkan diri sendiri dari workspace.');
        }
        $this->db->beginTransaction();
        try {
            $userStmt = $this->db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $userStmt->execute([$member['email']]);
            $userId = $userStmt->fetchColumn();
            if ($userId) {
                $shareStmt = $this->db->prepare("
                    DELETE FROM document_shares
                    WHERE workspace_id = ? AND shared_with_user_id = ?
                ");
                $shareStmt->execute([$workspaceId, $userId]);
            }

            $deleteStmt = $this->db->prepare("
                DELETE FROM members
                WHERE id = ? AND workspace_id = ? AND role != 'Owner'
            ");
            $deleteStmt->execute([$id, $workspaceId]);
            $removed = $deleteStmt->rowCount() > 0;
            $this->db->commit();
            return $removed;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function findUsableLink(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new InvalidArgumentException('Link undangan tidak valid.');
        }
        $stmt = $this->db->prepare("
            SELECT l.*, w.name AS workspace_name
            FROM workspace_invite_links l
            INNER JOIN workspaces w ON w.id = l.workspace_id
            WHERE l.token_hash = ?
            LIMIT 1
        ");
        $stmt->execute([hash('sha256', $token)]);
        $link = $stmt->fetch();
        $this->assertLinkUsable($link);
        return $link;
    }

    private function assertLinkUsable($link): void
    {
        if (!$link || $link['revoked_at'] !== null) {
            throw new MemberConflictException('Link undangan tidak ditemukan atau sudah dinonaktifkan.');
        }
        if (strtotime($link['expires_at']) <= time()) {
            throw new MemberConflictException('Masa berlaku link undangan sudah habis.');
        }
        if ($link['max_uses'] !== null && (int) $link['uses_count'] >= (int) $link['max_uses']) {
            throw new MemberConflictException('Link undangan sudah mencapai batas penggunaan.');
        }
    }

    private function assertWorkspaceMember(string $workspaceId): void
    {
        $stmt = $this->db->prepare("
            SELECT id FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, currentUser()['email']]);
        if (!$stmt->fetch()) {
            throw new MemberAuthorizationException('Anda bukan anggota aktif workspace ini.');
        }
    }

    private function assertWorkspaceManager(string $workspaceId): void
    {
        $stmt = $this->db->prepare("
            SELECT role FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, currentUser()['email']]);
        $role = $stmt->fetchColumn();
        if (!in_array($role, ['Owner', 'Admin'], true)) {
            throw new MemberAuthorizationException('Hanya Owner atau Admin yang dapat mengelola anggota workspace.');
        }
    }

    private function assertAssignableRole(string $role): void
    {
        if (!in_array($role, ['Admin', 'Editor', 'Viewer'], true)) {
            throw new InvalidArgumentException('Role harus Admin, Editor, atau Viewer.');
        }
    }

    private function isActiveMember(string $workspaceId, string $email): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $email]);
        return (bool) $stmt->fetch();
    }

    private function addMember(array $member): void
    {
        if ($this->isActiveMember($member['workspace_id'], $member['email'])) {
            throw new MemberConflictException('Pengguna tersebut sudah menjadi anggota workspace.');
        }
        $name = trim((string) $member['name']);
        $email = strtolower(trim((string) $member['email']));
        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff';
        $stmt = $this->db->prepare("
            SELECT id FROM members
            WHERE workspace_id = ? AND LOWER(email) = LOWER(?) AND status = 'invited'
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$member['workspace_id'], $email]);
        $legacyInvitationId = $stmt->fetchColumn();
        if (is_string($legacyInvitationId)) {
            $stmt = $this->db->prepare("
                UPDATE members
                SET name = ?, email = ?, avatar = ?, role = ?, status = 'active', joined_at = ?
                WHERE id = ? AND workspace_id = ? AND status = 'invited'
            ");
            $stmt->execute([
                $name,
                $email,
                $avatar,
                $member['role'],
                date('Y-m-d H:i:s'),
                $legacyInvitationId,
                $member['workspace_id'],
            ]);
            return;
        }
        $stmt = $this->db->prepare("
            INSERT INTO members (id, workspace_id, name, email, avatar, role, status, joined_at)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
        ");
        $stmt->execute([
            $this->newId('mem'),
            $member['workspace_id'],
            $name,
            $email,
            $avatar,
            $member['role'],
            date('Y-m-d H:i:s'),
        ]);
    }

    private function newId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(10));
    }
}
