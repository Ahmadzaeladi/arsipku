<?php
/** @var array|null $authenticatedUser */
/** @var array|null $joinInfo */
/** @var string|null $joinError */
/** @var string $joinToken */
/** @var string $csrfToken */
$loginUrl = 'index.php?' . http_build_query(['page' => 'login', 'invite' => $joinToken], '', '&', PHP_QUERY_RFC3986);
$registerUrl = 'index.php?' . http_build_query(['page' => 'register', 'invite' => $joinToken], '', '&', PHP_QUERY_RFC3986);
$returnUrl = $authenticatedUser ? 'index.php' : 'index.php?page=login';
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
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: var(--ark-surface-app);
        }
        .join-scrim {
            position: fixed;
            inset: 0;
            background: var(--ark-surface-scrim);
            backdrop-filter: blur(5px);
        }
        .join-dialog {
            position: relative;
            z-index: 1;
            width: min(100%, 460px);
            padding: 28px;
            border: 1px solid var(--ark-border-default);
            border-radius: var(--ark-radius-xl);
            background: var(--ark-surface-canvas);
            box-shadow: var(--ark-shadow-lg);
            animation: join-appear var(--ark-dur-base) var(--ark-ease-entrance) both;
        }
        .join-brand {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 26px;
            color: var(--ark-text-primary);
            font-size: var(--ark-text-md);
            font-weight: var(--ark-weight-semibold);
        }
        .join-brand img { width: 30px; height: 30px; object-fit: contain; }
        .join-heading { margin-bottom: 20px; }
        .join-heading h1 { margin-bottom: 5px; font-size: var(--ark-text-2xl); }
        .join-heading p, .join-description { color: var(--ark-text-secondary); font-size: var(--ark-text-sm); }
        .join-workspace {
            display: grid;
            gap: 5px;
            margin: 20px 0 14px;
            padding: 16px;
            border: 1px solid var(--ark-border-default);
            border-radius: var(--ark-radius-lg);
            background: var(--ark-surface-app);
        }
        .join-workspace__label {
            color: var(--ark-text-secondary);
            font-size: var(--ark-text-xs);
        }
        .join-workspace__name {
            color: var(--ark-text-primary);
            font-size: var(--ark-text-lg);
            font-weight: var(--ark-weight-semibold);
            overflow-wrap: anywhere;
        }
        .join-role {
            display: inline-flex;
            width: fit-content;
            margin-top: 5px;
            padding: 4px 9px;
            border: 1px solid var(--ark-border-accent);
            border-radius: var(--ark-radius-full);
            background: var(--ark-accent-subtle);
            color: var(--ark-text-accent);
            font-size: var(--ark-text-xs);
            font-weight: var(--ark-weight-medium);
        }
        .join-error {
            margin-top: 14px;
            padding: 11px 12px;
            border: 1px solid var(--ark-danger-border);
            border-radius: var(--ark-radius-md);
            background: var(--ark-danger-subtle);
            color: var(--ark-text-danger);
            font-size: var(--ark-text-sm);
        }
        .join-actions { display: grid; gap: 10px; margin-top: 22px; }
        .join-actions .ark-btn { width: 100%; }
        .join-feedback {
            margin-top: 14px;
            color: var(--ark-text-success);
            font-size: var(--ark-text-sm);
        }
        .join-feedback--error { color: var(--ark-text-danger); }
        .join-result { text-align: center; }
        .join-result__mark {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            margin: 4px auto 16px;
            border-radius: var(--ark-radius-full);
            background: var(--ark-success-subtle);
            color: var(--ark-success);
            font-size: var(--ark-text-xl);
            font-weight: var(--ark-weight-semibold);
        }
        .join-result--pending .join-result__mark {
            background: var(--ark-accent-subtle);
            color: var(--ark-text-accent);
        }
        .join-result h2 { margin-bottom: 8px; font-size: var(--ark-text-xl); }
        .join-result p { color: var(--ark-text-secondary); font-size: var(--ark-text-sm); }
        .join-result .ark-btn { width: 100%; margin-top: 22px; }
        @keyframes join-appear {
            from { opacity: 0; transform: translateY(8px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @media (prefers-reduced-motion: reduce) {
            .join-dialog { animation: none; }
        }
    </style>
</head>
<body>
    <div class="join-scrim" aria-hidden="true"></div>
    <main class="join-dialog" role="dialog" aria-modal="true" aria-labelledby="join-title">
        <div class="join-brand">
            <img src="assets/images/logo.png" alt="">
            <span>ArsipKu</span>
        </div>

        <div class="join-heading">
            <h1 id="join-title">Undangan workspace</h1>
            <p>Anda diundang untuk berkolaborasi di ArsipKu.</p>
        </div>

        <?php if ($joinError !== null): ?>
            <p class="join-error" role="alert"><?= htmlspecialchars($joinError, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="join-actions">
                <a class="ark-btn ark-btn--primary" href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                    Kembali ke ArsipKu
                </a>
            </div>
        <?php elseif ($joinInfo !== null): ?>
            <div id="join-invitation-prompt">
                <section class="join-workspace" aria-label="Detail undangan">
                    <span class="join-workspace__label">Workspace tujuan</span>
                    <strong class="join-workspace__name"><?= htmlspecialchars($joinInfo['workspace_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="join-role">Role <?= htmlspecialchars($joinInfo['role'], ENT_QUOTES, 'UTF-8') ?></span>
                </section>
                <p class="join-description">
                    <?= $joinInfo['approval_required']
                        ? 'Permintaan bergabung akan dikirim kepada Owner/Admin untuk disetujui.'
                        : 'Setelah konfirmasi, Anda akan langsung menjadi anggota workspace.' ?>
                </p>
                <div class="join-actions">
                    <?php if ($authenticatedUser): ?>
                        <button type="button" class="ark-btn ark-btn--primary" data-join-link
                            data-token="<?= htmlspecialchars($joinToken, ENT_QUOTES, 'UTF-8') ?>">
                            <?= $joinInfo['approval_required'] ? 'Minta Bergabung' : 'Terima Undangan' ?>
                        </button>
                        <a class="ark-btn ark-btn--secondary" href="index.php">Kembali</a>
                    <?php else: ?>
                        <a class="ark-btn ark-btn--primary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">
                            Masuk untuk Bergabung
                        </a>
                        <a class="ark-btn ark-btn--secondary" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">
                            Buat Akun ArsipKu
                        </a>
                    <?php endif; ?>
                </div>
                <p id="join-feedback" class="join-feedback" role="status" aria-live="polite" hidden></p>
            </div>
            <section id="join-result" class="join-result" role="status" aria-live="polite" tabindex="-1" hidden>
                <span class="join-result__mark" aria-hidden="true">✓</span>
                <h2 id="join-result-title"></h2>
                <p id="join-result-message"></p>
                <a id="join-result-action" class="ark-btn ark-btn--primary"></a>
            </section>
        <?php endif; ?>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
