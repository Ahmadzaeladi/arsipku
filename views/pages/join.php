<?php
/** @var array|null $authenticatedUser */
/** @var array|null $joinInfo */
/** @var string|null $joinError */
/** @var string $joinToken */
/** @var string $csrfToken */
$joinUrl = 'index.php?' . http_build_query(['page' => 'join', 'token' => $joinToken], '', '&', PHP_QUERY_RFC3986);
$loginUrl = 'index.php?' . http_build_query(['page' => 'login', 'invite' => $joinToken], '', '&', PHP_QUERY_RFC3986);
$registerUrl = 'index.php?' . http_build_query(['page' => 'register', 'invite' => $joinToken], '', '&', PHP_QUERY_RFC3986);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Undangan Workspace — ArsipKu</title>
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: var(--ark-bg, #f8fafc); }
        .join { width: min(100%, 440px); padding: 32px; border: 1px solid var(--ark-border, #e2e8f0); border-radius: 16px; background: #fff; }
        .join h1 { margin: 0 0 8px; font-size: 24px; }
        .join p { color: #64748b; }
        .join-actions { display: grid; gap: 10px; margin-top: 24px; }
        .join-actions a, .join-actions button { width: 100%; justify-content: center; }
        .join-error { padding: 10px 12px; border-radius: 8px; color: #b91c1c; background: #fef2f2; }
    </style>
</head>
<body>
    <main class="join">
        <h1>Undangan workspace</h1>
        <?php if ($joinError !== null): ?>
            <p class="join-error" role="alert"><?= htmlspecialchars($joinError, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="index.php?page=login">Masuk ke ArsipKu</a>
        <?php elseif ($joinInfo !== null): ?>
            <p>Anda diundang untuk bergabung ke <strong><?= htmlspecialchars($joinInfo['workspace_name'], ENT_QUOTES, 'UTF-8') ?></strong> sebagai <strong><?= htmlspecialchars($joinInfo['role'], ENT_QUOTES, 'UTF-8') ?></strong>.</p>
            <p><?= $joinInfo['approval_required'] ? 'Permintaan Anda akan menunggu persetujuan Owner/Admin.' : 'Anda dapat langsung bergabung setelah mengonfirmasi.' ?></p>
            <div class="join-actions">
                <?php if ($authenticatedUser): ?>
                    <button type="button" class="ark-btn ark-btn--primary" data-join-link data-token="<?= htmlspecialchars($joinToken, ENT_QUOTES, 'UTF-8') ?>">
                        <?= $joinInfo['approval_required'] ? 'Minta Bergabung' : 'Terima dan Bergabung' ?>
                    </button>
                    <a class="ark-btn" href="index.php">Batal</a>
                <?php else: ?>
                    <a class="ark-btn ark-btn--primary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Masuk untuk Bergabung</a>
                    <a class="ark-btn" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Daftar dengan Email</a>
                <?php endif; ?>
            </div>
            <div id="join-feedback" role="status" aria-live="polite"></div>
        <?php endif; ?>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
