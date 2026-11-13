<?php
/**
 * ArsipKu - Account profile and password API.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/ProfileImageService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Hanya menerima request POST.'], 405);
}

$auth = new AuthService();
if (!$auth->verifiesCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null))) {
    jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
}

try {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $images = new ProfileImageService();
        $newAvatarPath = null;
        if (isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newAvatarPath = $images->store($_FILES['avatar'], 'avatar');
        }

        $removeAvatar = ($newAvatarPath === null && ($_POST['remove_avatar'] ?? '0') === '1');
        try {
            $result = $auth->updateProfile(
                (string) ($_POST['name'] ?? ''),
                $newAvatarPath,
                $removeAvatar
            );
        } catch (Throwable $e) {
            if ($newAvatarPath !== null) {
                $images->delete($newAvatarPath);
            }
            throw $e;
        }

        $nextAvatarPath = $newAvatarPath
            ?? ($removeAvatar ? null : $result['previous_avatar_path']);
        if ($result['previous_avatar_path'] !== $nextAvatarPath) {
            $images->delete($result['previous_avatar_path']);
        }

        jsonResponse(['success' => true, 'message' => 'Profil akun berhasil diperbarui.']);
    }

    if ($action === 'change_password') {
        $auth->changePassword(
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );
        jsonResponse(['success' => true, 'message' => 'Kata sandi berhasil diubah.']);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi akun tidak dikenali.'], 400);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    error_log('Account update failure: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Pengaturan akun tidak dapat disimpan saat ini.'], 500);
}
