<?php
/**
 * ArsipKu - MySQL-backed authentication service.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

class AuthService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function current(): ?array
    {
        $userId = $_SESSION['authenticated_user_id'] ?? null;
        if (!is_string($userId) || $userId === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, username, name, email, avatar_path FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            unset($_SESSION['authenticated_user_id'], $_SESSION['authenticated_user']);
            return null;
        }

        $user = $this->profile($row);
        $_SESSION['authenticated_user'] = $user;
        return $user;
    }

    public function login(string $username, string $password): bool
    {
        $login = strtolower(trim($username));
        $stmt = $this->db->prepare(
            'SELECT id, username, name, email, password_hash, avatar_path FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            return false;
        }

        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $update = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Sesi tidak dapat diperbarui.');
        }
        $_SESSION['authenticated_user_id'] = $row['id'];
        $_SESSION['authenticated_user'] = $this->profile($row);
        $this->setInitialWorkspace($row['email']);
        $this->csrfToken();
        return true;
    }

    public function register(string $name, string $email, string $password, string $passwordConfirmation): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 150 karakter.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            throw new InvalidArgumentException('Alamat email tidak valid.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Kata sandi harus memiliki minimal 8 karakter.');
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            throw new InvalidArgumentException('Konfirmasi kata sandi tidak cocok.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Email sudah terdaftar. Silakan masuk dengan akun Anda.');
            }

            $id = 'usr_' . bin2hex(random_bytes(12));
            $username = $this->uniqueUsername($email);
            $stmt = $this->db->prepare("
                INSERT INTO users (id, username, name, email, password_hash, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $username,
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                date('Y-m-d H:i:s'),
            ]);

            $workspaceName = $name . ' Workspace';
            $baseSlug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $workspaceName), '-'));
            $slug = $this->uniqueWorkspaceSlug($baseSlug !== '' ? $baseSlug : 'workspace');
            $workspaceId = 'ws_' . bin2hex(random_bytes(10));
            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                INSERT INTO workspaces (id, name, slug, type, storage_limit_bytes, plan_name, created_at)
                VALUES (?, ?, ?, 'personal', ?, 'Personal Pro', ?)
            ");
            $stmt->execute([$workspaceId, $workspaceName, $slug, 10 * 1024 * 1024 * 1024, $now]);

            $memberId = 'mem_' . bin2hex(random_bytes(10));
            $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff';
            $stmt = $this->db->prepare("
                INSERT INTO members (id, workspace_id, name, email, avatar, role, status, joined_at)
                VALUES (?, ?, ?, ?, ?, 'Owner', 'active', ?)
            ");
            $stmt->execute([$memberId, $workspaceId, $name, $email, $avatar, $now]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Sesi tidak dapat diperbarui.');
        }
        $_SESSION['authenticated_user_id'] = $id;
        $_SESSION['authenticated_user'] = $this->profile([
            'id' => $id,
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'avatar_path' => null,
        ]);
        $_SESSION['active_workspace'] = $slug;
        $this->csrfToken();
        return $this->sessionData();
    }

    public function logout(): void
    {
        unset(
            $_SESSION['authenticated_user_id'],
            $_SESSION['authenticated_user'],
            $_SESSION['active_workspace'],
            $_SESSION['csrf_token']
        );
        if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
            throw new RuntimeException('Sesi tidak dapat diakhiri dengan aman.');
        }
        if (!headers_sent()) {
            setcookie('XSRF-TOKEN', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->isHttps(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    public function sessionData(): array
    {
        $user = $this->current();
        if (!$user) {
            return [
                'authenticated' => false,
                'user' => null,
                'active_workspace' => null,
                'workspaces' => [],
            ];
        }

        $stmt = $this->db->prepare("
            SELECT w.id, w.name, w.slug, w.type, w.logo_path, m.role AS user_role
            FROM members m
            INNER JOIN workspaces w ON w.id = m.workspace_id
            WHERE m.email = ? AND m.status = 'active'
            ORDER BY w.created_at ASC
        ");
        $stmt->execute([$user['email']]);
        $workspaces = $stmt->fetchAll();
        $activeWorkspace = $_SESSION['active_workspace'] ?? ($workspaces[0]['slug'] ?? null);

        return [
            'authenticated' => true,
            'user' => $user,
            'active_workspace' => $activeWorkspace,
            'workspaces' => $workspaces,
        ];
    }

    public function updateProfile(string $name, ?string $avatarPath = null, bool $removeAvatar = false): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 150 karakter.');
        }
        if ($avatarPath !== null && !preg_match('/^avatar_[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$/', $avatarPath)) {
            throw new InvalidArgumentException('Foto profil tidak valid.');
        }

        $user = $this->current();
        if (!$user) {
            throw new RuntimeException('Sesi tidak aktif. Silakan masuk kembali.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT email, avatar_path FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('Akun tidak ditemukan.');
            }

            $previousAvatarPath = $row['avatar_path'] ?: null;
            $nextAvatarPath = $avatarPath !== null
                ? $avatarPath
                : ($removeAvatar ? null : $previousAvatarPath);
            $avatarChanged = $nextAvatarPath !== $previousAvatarPath;

            $stmt = $this->db->prepare('UPDATE users SET name = ?, avatar_path = ? WHERE id = ?');
            $stmt->execute([$name, $nextAvatarPath, $user['id']]);

            $stmt = $this->db->prepare('UPDATE members SET name = ? WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$name, $row['email']]);
            if ($avatarChanged) {
                $memberAvatar = $nextAvatarPath !== null
                    ? 'uploads/' . rawurlencode($nextAvatarPath)
                    : '';
                $stmt = $this->db->prepare('UPDATE members SET avatar = ? WHERE LOWER(email) = LOWER(?)');
                $stmt->execute([$memberAvatar, $row['email']]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return [
            'user' => $this->current(),
            'previous_avatar_path' => $previousAvatarPath,
        ];
    }

    public function changePassword(string $currentPassword, string $newPassword, string $confirmation): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Kata sandi baru harus memiliki minimal 8 karakter.');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('Konfirmasi kata sandi tidak cocok.');
        }

        $user = $this->current();
        if (!$user) {
            throw new RuntimeException('Sesi tidak aktif. Silakan masuk kembali.');
        }

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $passwordHash = $stmt->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            throw new InvalidArgumentException('Kata sandi saat ini tidak sesuai.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            throw new RuntimeException('Kata sandi baru tidak dapat diamankan.');
        }
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $user['id']]);
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Sesi tidak dapat diperbarui setelah kata sandi diganti.');
        }
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (!headers_sent()) {
            setcookie('XSRF-TOKEN', $_SESSION['csrf_token'], [
                'expires' => 0,
                'path' => '/',
                'secure' => $this->isHttps(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $_SESSION['csrf_token'];
    }

    public function verifiesCsrf(?string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        return is_string($expected) && $expected !== ''
            && is_string($token) && hash_equals($expected, $token);
    }

    public function isSameOriginRequest(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '') {
            return true;
        }

        $originParts = parse_url($origin);
        $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
        $requestScheme = $this->isHttps() ? 'https' : 'http';
        $requestParts = parse_url($requestScheme . '://' . $hostHeader);
        if (!is_array($originParts) || !is_array($requestParts)) {
            return false;
        }

        $originPort = $originParts['port'] ?? (($originParts['scheme'] ?? '') === 'https' ? 443 : 80);
        $requestPort = $requestParts['port'] ?? ($requestScheme === 'https' ? 443 : 80);

        return strtolower((string) ($originParts['scheme'] ?? '')) === $requestScheme
            && strtolower((string) ($originParts['host'] ?? '')) === strtolower((string) ($requestParts['host'] ?? ''))
            && $originPort === $requestPort;
    }

    private function profile(array $row): array
    {
        $avatarPath = $row['avatar_path'] ?? null;
        $avatar = is_string($avatarPath)
            && preg_match('/^avatar_[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$/', $avatarPath)
            ? 'uploads/' . rawurlencode($avatarPath)
            : '';

        return [
            'id' => (string) $row['id'],
            'username' => (string) $row['username'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'role' => 'Owner',
            'avatar' => $avatar,
        ];
    }

    private function uniqueUsername(string $email): string
    {
        $base = strtolower((string) strstr($email, '@', true));
        $base = trim((string) preg_replace('/[^a-z0-9._-]+/', '-', $base), '._-');
        if ($base === '') {
            $base = 'user';
        }
        $base = substr($base, 0, 48);
        $candidate = $base;
        $suffix = 0;
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        do {
            $stmt->execute([$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $suffix++;
            $candidate = substr($base, 0, 48 - strlen((string) $suffix) - 1) . '-' . $suffix;
        } while ($suffix < 1000);
        throw new RuntimeException('Username tidak dapat dibuat. Coba lagi.');
    }

    private function uniqueWorkspaceSlug(string $base): string
    {
        $base = substr($base, 0, 150);
        $candidate = $base;
        $suffix = 0;
        $stmt = $this->db->prepare('SELECT id FROM workspaces WHERE slug = ? LIMIT 1');
        do {
            $stmt->execute([$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $suffix++;
            $candidate = substr($base, 0, 150 - strlen((string) $suffix) - 1) . '-' . $suffix;
        } while ($suffix < 1000);
        throw new RuntimeException('Nama workspace tidak dapat digunakan. Coba lagi.');
    }

    private function setInitialWorkspace(string $email): void
    {
        $stmt = $this->db->prepare("
            SELECT w.slug
            FROM members m
            INNER JOIN workspaces w ON w.id = m.workspace_id
            WHERE m.email = ? AND m.status = 'active'
            ORDER BY w.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $slug = $stmt->fetchColumn();
        if (is_string($slug)) {
            $_SESSION['active_workspace'] = $slug;
        }
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    }
}
