<?php
/**
 * ArsipKu - Pengaturan Akun
 */
$hasAvatar = !empty($currentUser['avatar']);
?>

<div class="ark-measure">
    <div class="ark-page-head">
        <div class="ark-page-head__text">
            <h1 class="ark-title-page">Pengaturan Akun</h1>
            <p class="ark-title-sub">Kelola profil pribadi dan keamanan akun Anda.</p>
        </div>
    </div>

    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Profil pribadi</h2>
        </div>

        <form id="form-account-profile" class="ark-form" method="post" action="api/account.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="remove_avatar" value="0">

            <div class="ark-profile-image">
                <span class="ark-avatar ark-avatar--preview">
                    <img class="ark-profile-image__preview" data-image-preview="account" src="<?= $hasAvatar ? htmlspecialchars($currentUser['avatar'], ENT_QUOTES, 'UTF-8') : '' ?>" alt=""<?= $hasAvatar ? '' : ' hidden' ?>>
                    <span data-image-fallback="account"<?= $hasAvatar ? ' hidden' : '' ?>><?= $initialsOf((string) $currentUser['name']) ?></span>
                </span>
                <div class="ark-profile-image__controls">
                    <span class="ark-label">Foto profil</span>
                    <div class="ark-row">
                        <button type="button" class="ark-btn ark-btn--secondary ark-btn--sm" data-trigger-image="account">Pilih foto</button>
                        <button type="button" class="ark-btn ark-btn--sm" data-remove-image="account"<?= $hasAvatar ? '' : ' hidden' ?>>Hapus foto</button>
                    </div>
                    <input class="ark-sr-only" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" data-image-input="account" tabindex="-1" aria-hidden="true">
                    <p class="ark-helper">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                </div>
            </div>

            <div class="ark-field">
                <label class="ark-label" for="account-name">Nama</label>
                <input class="ark-input" id="account-name" name="name" type="text" value="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="150" required>
            </div>

            <div class="ark-field">
                <label class="ark-label" for="account-email">Alamat email</label>
                <input class="ark-input" id="account-email" type="email" value="<?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                <p class="ark-helper">Alamat email akun tidak dapat diubah dari halaman ini.</p>
            </div>

            <div class="ark-form-actions">
                <button type="submit" class="ark-btn ark-btn--primary">Simpan Profil</button>
            </div>
        </form>
    </section>

    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Keamanan</h2>
            <span class="ark-section__hint">Gunakan kata sandi minimal 8 karakter.</span>
        </div>

        <form id="form-change-password" class="ark-form" method="post" action="api/account.php">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="ark-field">
                <label class="ark-label" for="current-password">Kata sandi saat ini</label>
                <input class="ark-input" id="current-password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="ark-field-grid">
                <div class="ark-field">
                    <label class="ark-label" for="new-password">Kata sandi baru</label>
                    <input class="ark-input" id="new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="ark-field">
                    <label class="ark-label" for="password-confirmation">Konfirmasi kata sandi baru</label>
                    <input class="ark-input" id="password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                </div>
            </div>
            <div class="ark-form-actions">
                <button type="submit" class="ark-btn ark-btn--primary">Ubah Kata Sandi</button>
            </div>
        </form>
    </section>
</div>
