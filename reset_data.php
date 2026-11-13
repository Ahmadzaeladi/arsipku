<?php
/**
 * ArsipKu - CLI-only destructive reset.
 *
 * Required environment variable: ARSIPKU_RESET_PASSWORD.
 * The password is never accepted as a command-line argument or saved to source.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Database.php';

$options = getopt('', ['name:', 'username:']);
$name = isset($options['name']) ? (string) $options['name'] : '';
$username = isset($options['username']) ? (string) $options['username'] : '';
$password = getenv('ARSIPKU_RESET_PASSWORD');

if (!is_string($password) || $password === '') {
    fwrite(STDERR, "ARSIPKU_RESET_PASSWORD wajib diatur di environment; jangan berikan password sebagai argumen CLI.\n");
    exit(1);
}

if ($name === '' || $username === '') {
    fwrite(STDERR, "Pemakaian: php reset_data.php --name=\"Nama\" --username=username\n");
    exit(1);
}

fwrite(STDOUT, "PERINGATAN: seluruh data MySQL di " . DB_NAME . " dan file langsung di uploads/ akan dihapus.\n");
fwrite(STDOUT, "Tekan Y lalu Enter untuk melanjutkan: ");
$confirmation = fgets(STDIN);
if (strtoupper(trim((string) $confirmation)) !== 'Y') {
    fwrite(STDOUT, "Reset dibatalkan.\n");
    exit(2);
}

try {
    $result = Database::resetToClean($name, $username, $password);
    fwrite(STDOUT, "Reset berhasil.\n");
    fwrite(STDOUT, "Akun Owner: " . $result['user']['name'] . " (" . $result['user']['username'] . ")\n");
    fwrite(STDOUT, "Workspace: " . $result['workspace']['name'] . "\n");
    fwrite(STDOUT, "Berkas upload dihapus: " . $result['deleted_files'] . "\n");
    fwrite(STDOUT, "Aktivitas, folder, dokumen, anggota lama, dan akun lama telah dihapus.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Reset gagal: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    unset($password);
    putenv('ARSIPKU_RESET_PASSWORD');
}
