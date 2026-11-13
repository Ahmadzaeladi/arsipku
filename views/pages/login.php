<?php
/** @var string $csrfToken */
/** @var string|null $loginError */
/** @var string $inviteToken */
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ArsipKu</title>
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="login-backdrop" aria-hidden="true"></div>
    <div class="login-scene" aria-hidden="true">
        <img class="login-illustration" src="assets/images/ilu.png" alt="">
    </div>

    <header class="login-brand" aria-label="ArsipKu">
        <img class="login-brand__logo" src="assets/images/logo.png" alt="ArsipKu">
        <p class="login-brand__tagline">Kelola Arsip, Lebih Mudah dan Teratur</p>
    </header>

    <main class="login-panel">
        <svg class="login-panel__mark" viewBox="0 0 112 100" fill="none" aria-hidden="true">
            <path
                d="M12 20a10 10 0 0 1 10-10h28l11 11h29a10 10 0 0 1 10 10v48a10 10 0 0 1-10 10H22a10 10 0 0 1-10-10V20Z"
                fill="#204F9A" />
            <path d="M5 31a10 10 0 0 1 10-10h28l11 11h29a10 10 0 0 1 10 10v37a10 10 0 0 1-10 10H15A10 10 0 0 1 5 79V31Z"
                fill="#397BCF" />
            <path d="M30 35a6 6 0 0 1 6-6h40a6 6 0 0 1 6 6v35H30V35Z" fill="#fff" />
            <path d="M43 45h27M43 55h27M43 65h19" stroke="#75A8E8" stroke-width="5" stroke-linecap="round" />
        </svg>
        <h1>Selamat Datang di ArsipKu!</h1>
        <p class="login-panel__intro">Masuk untuk melanjutkan ke akun Anda</p>

        <?php if ($loginError !== null): ?>
        <div class="login-error" role="alert"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" action="index.php?page=login">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($inviteToken !== ''): ?>
            <input type="hidden" name="invite" value="<?= htmlspecialchars($inviteToken, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <label class="login-field" for="username">
                <svg class="login-field__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 20a8 8 0 0 1 16 0v1H4v-1Z"></path>
                </svg>
                <span class="ark-sr-only">Username atau Email</span>
                <input id="username" name="username" type="text" autocomplete="username"
                    placeholder="Username atau Email" required autofocus>
            </label>

            <label class="login-field" for="password">
                <svg class="login-field__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path
                        d="M7 10V7a5 5 0 0 1 10 0v3h1a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h1Zm2 0h6V7a3 3 0 0 0-6 0v3Zm3 4a2 2 0 0 0-1 3.73V19h2v-1.27A2 2 0 0 0 12 14Z">
                    </path>
                </svg>
                <span class="ark-sr-only">Password</span>
                <input id="password" name="password" type="password" autocomplete="current-password"
                    placeholder="Password" required>
                <button class="login-field__toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"
                    aria-controls="password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"></path>
                        <circle cx="12" cy="12" r="2.6"></circle>
                        <path class="login-eye-slash" d="m3 3 18 18"></path>
                    </svg>
                </button>
            </label>

            <button type="submit" class="login-submit">
                <span>Masuk</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"
                    stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14"></path>
                    <path d="m13 5 7 7-7 7"></path>
                </svg>
            </button>
        </form>

        <div class="login-divider"><span>atau</span></div>
        <div class="login-security">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="m12 2 9 4v6c0 5.3-3.8 8.7-9 10-5.2-1.3-9-4.7-9-10V6l9-4Z"></path>
                <path d="m8 12 2.5 2.5L16 9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round"></path>
            </svg>
            <span>Keamanan Data Terjamin</span>
        </div>
        <p class="login-register">
            Belum punya akun?
            <a
                href="index.php?page=register<?= $inviteToken !== '' ? '&amp;invite=' . rawurlencode($inviteToken) : '' ?>">Daftar
                sekarang</a>
        </p>
    </main>

    <script>
    (function() {
        var toggle = document.querySelector('.login-field__toggle');
        var password = document.getElementById('password');
        var slash = document.querySelector('.login-eye-slash');

        toggle.addEventListener('click', function() {
            var isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', String(!isVisible));
            toggle.setAttribute('aria-label', isVisible ? 'Tampilkan password' : 'Sembunyikan password');
            slash.style.display = isVisible ? '' : 'none';
        });
    }());
    </script>
</body>

</html>