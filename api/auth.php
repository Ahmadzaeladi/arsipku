<?php
/**
 * ArsipKu - session API.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Services/AuthService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $auth = new AuthService();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_REQUEST['action'] ?? 'me';

    if ($method === 'GET') {
        if ($action === 'csrf') {
            jsonResponse(['success' => true, 'data' => ['csrf_token' => $auth->csrfToken()]]);
        }
        if ($action === 'me' || $action === 'session') {
            jsonResponse(['success' => true, 'data' => $auth->sessionData()]);
        }
        jsonResponse(['success' => false, 'message' => 'Aksi autentikasi tidak dikenal.'], 404);
    }

    if ($method !== 'POST' || !$auth->isSameOriginRequest()) {
        jsonResponse(['success' => false, 'message' => 'Metode atau origin tidak diizinkan.'], 403);
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput ?: '', true);
    $data = is_array($data) ? $data : $_POST;
    $action = $data['action'] ?? $action;

    if (!$auth->verifiesCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['_csrf'] ?? null))) {
        jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
    }

    if ($action === 'login') {
        $login = (string) ($data['username'] ?? $data['email'] ?? '');
        if (!$auth->login($login, (string) ($data['password'] ?? ''))) {
            jsonResponse(['success' => false, 'message' => 'Username atau password salah.'], 401);
        }
        jsonResponse(['success' => true, 'data' => $auth->sessionData(), 'message' => 'Login berhasil.']);
    }

    if ($action === 'register') {
        $session = $auth->register(
            (string) ($data['name'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['password'] ?? ''),
            (string) ($data['password_confirmation'] ?? '')
        );
        jsonResponse(['success' => true, 'data' => $session, 'message' => 'Akun berhasil dibuat.']);
    }

    if ($action === 'logout') {
        $auth->logout();
        jsonResponse(['success' => true, 'message' => 'Anda telah keluar.']);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi autentikasi belum tersedia.'], 501);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
