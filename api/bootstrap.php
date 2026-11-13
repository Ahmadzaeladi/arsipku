<?php
/**
 * Shared authentication gate for protected API endpoints.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Services/AuthService.php';

header('Content-Type: application/json; charset=utf-8');

$authService = new AuthService();
if (!$authService->current()) {
    jsonResponse(['success' => false, 'message' => 'Sesi tidak aktif. Silakan login kembali.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && !$authService->isSameOriginRequest()) {
    jsonResponse(['success' => false, 'message' => 'Permintaan lintas origin ditolak.'], 403);
}
