<?php
/**
 * ArsipKu - SaaS Digital Archive Management System
 * Config & Constants
 * PHP Native, No Framework
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Directory Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');

// Muat berkas .env jika tersedia di ROOT_PATH
$envFile = ROOT_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($envLines !== false) {
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $envKey = trim($parts[0]);
                $envVal = trim(trim($parts[1]), "\"'");
                if (!isset($_SERVER[$envKey]) && !isset($_ENV[$envKey])) {
                    putenv("{$envKey}={$envVal}");
                    $_ENV[$envKey] = $envVal;
                    $_SERVER[$envKey] = $envVal;
                }
            }
        }
    }
}

// ============================================================================
// KONFIGURASI DATABASE (MySQL)
// ============================================================================
// Anda dapat menyesuaikan DB_USER dan DB_PASS langsung di bawah ini
// ATAU melalui berkas .env pada root proyek.
// ============================================================================
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') !== false ? (int)getenv('DB_PORT') : 3306);
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'arsipku');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') !== false ? getenv('DB_CHARSET') : 'utf8mb4');

// Application Constants
define('APP_NAME', 'ArsipKu');
define('APP_VERSION', '1.0.0');
define('APP_DESCRIPTION', 'SaaS Digital Archive Management System');

// Max upload size in bytes (50MB)
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024);

// Allowed mime types & extensions
define('ALLOWED_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
    'png', 'jpg', 'jpeg', 'webp', 'svg', 'txt', 'csv', 'zip'
]);

// Pastikan direktori uploads tersedia
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

function currentUser(): array
{
    $user = $_SESSION['authenticated_user'] ?? null;
    if (!is_array($user) || empty($user['id']) || empty($user['email']) || empty($user['name'])) {
        throw new LogicException('Pengguna aktif tidak tersedia di sesi autentikasi.');
    }

    return $user;
}
