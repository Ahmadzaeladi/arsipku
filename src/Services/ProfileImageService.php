<?php
/**
 * Validate and store small account/workspace profile images.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Config.php';

class ProfileImageService
{
    private const MAX_SIZE = 2097152;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(array $file, string $kind): string
    {
        if (!in_array($kind, ['avatar', 'workspace'], true)) {
            throw new InvalidArgumentException('Jenis gambar profil tidak valid.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > self::MAX_SIZE) {
            throw new InvalidArgumentException('Ukuran gambar maksimal 2 MB.');
        }

        $temporaryPath = $file['tmp_name'] ?? '';
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Berkas gambar tidak valid.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporaryPath);
        $imageInfo = @getimagesize($temporaryPath);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])
            || !is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mime) {
            throw new InvalidArgumentException('Gunakan gambar JPG, PNG, atau WebP yang valid.');
        }
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096 || $width * $height > 16777216) {
            throw new InvalidArgumentException('Dimensi gambar terlalu besar.');
        }

        $filename = $kind . '_' . bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $targetPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($temporaryPath, $targetPath)) {
            throw new RuntimeException('Gambar tidak dapat disimpan. Coba lagi.');
        }

        return $filename;
    }

    public function delete(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }
        if (!preg_match('/^(?:avatar|workspace)_[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$/', $filename)) {
            throw new InvalidArgumentException('Lokasi gambar profil tidak valid.');
        }

        $path = UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        if (is_link($path)) {
            throw new RuntimeException('Lokasi gambar profil tidak dapat diakses.');
        }
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Gambar profil lama tidak dapat dihapus.');
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'Ukuran gambar maksimal 2 MB.';
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            return 'Pilih gambar terlebih dahulu.';
        }
        return 'Gambar gagal diunggah. Coba lagi.';
    }
}
