<?php
/**
 * ArsipKu - Pengaturan Workspace
 * ---------------------------------------------------------------------------
 * Lapisan Ark: halaman datar selebar .ark-measure, tanpa kartu "form" dan
 * tanpa kartu merah untuk bahaya. Seksi dipisahkan oleh hairline + ruang;
 * risiko hanya diberi warna lewat .ark-notice--danger, bukan latar blok.
 *
 * Kontrak JS yang dipertahankan: #ws-name-input, #ws-type-select dan fetch
 * ke api/workspaces.php (action update / hapus lewat modal-delete-workspace).
 */
$canEditWorkspace = ($currentWorkspaceRole ?? null) === 'Owner';
$canDeleteWorkspace = ($currentWorkspaceRole ?? null) === 'Owner';
$hasWorkspaceToKeep = count($allWorkspaces) > 1;
?>

<div class="ark-measure">
    <div class="ark-page-head">
        <div class="ark-page-head__text">
            <h1 class="ark-title-page">Pengaturan Workspace</h1>

        </div>
    </div>

    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Informasi umum</h2>

        </div>

        <?php if ($canEditWorkspace): ?>
        <form id="form-workspace-profile" class="ark-form" method="post" action="api/workspaces.php"
            enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="remove_logo" value="0">
            <div class="ark-field-grid">
                <div class="ark-field">
                    <label class="ark-label" for="ws-name-input">Nama workspace</label>
                    <input class="ark-input" type="text" id="ws-name-input" name="name"
                        value="<?= htmlspecialchars($currentWs['name']) ?>" required maxlength="60">
                    <p class="ark-helper">Gunakan nama yang dikenali tim, misalnya nama perusahaan atau proyek.</p>
                </div>

                <div class="ark-field">
                    <label class="ark-label" for="ws-type-select">Kategori</label>
                    <select class="ark-select" id="ws-type-select" name="type">
                        <option value="company" <?= $currentWs['type'] === 'company' ? 'selected' : '' ?>>Perusahaan /
                            korporat</option>
                        <option value="personal" <?= $currentWs['type'] === 'personal' ? 'selected' : '' ?>>Personal /
                            individu</option>
                        <option value="school" <?= $currentWs['type'] === 'school' ? 'selected' : '' ?>>Institusi
                            pendidikan / sekolah</option>
                    </select>
                </div>
            </div>

            <div class="ark-field">
                <span class="ark-label">Logo workspace</span>
                <div class="ark-profile-image">
                    <span class="ark-ws__mark ark-ws__mark--preview">
                        <?php if (!empty($currentWs['logo_path'])): ?>
                        <img class="ark-profile-image__preview" data-image-preview="workspace"
                            src="<?= htmlspecialchars('uploads/' . rawurlencode($currentWs['logo_path']), ENT_QUOTES, 'UTF-8') ?>"
                            alt="">
                        <span data-image-fallback="workspace"
                            hidden><?= $initialsOf((string) $currentWs['name']) ?></span>
                        <?php else: ?>
                        <img class="ark-profile-image__preview" data-image-preview="workspace" alt="" hidden>
                        <span data-image-fallback="workspace"><?= $initialsOf((string) $currentWs['name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <div class="ark-profile-image__controls">
                        <button type="button" class="ark-btn ark-btn--secondary ark-btn--sm"
                            data-trigger-image="workspace">Pilih logo</button>
                        <input class="ark-sr-only" type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                            data-image-input="workspace" tabindex="-1" aria-hidden="true">
                        <button type="button" class="ark-btn ark-btn--sm" data-remove-image="workspace"
                            <?= !empty($currentWs['logo_path']) ? '' : ' hidden' ?>>Hapus logo</button>
                        <p class="ark-helper">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                    </div>
                </div>
            </div>

            <div class="ark-form-actions">
                <button type="submit" class="ark-btn ark-btn--primary">
                    <span class="ark-btn__label">Simpan Perubahan</span>
                </button>
            </div>
        </form>
        <?php else: ?>
        <dl class="ark-field-grid">
            <div class="ark-field">
                <dt class="ark-label">Nama workspace</dt>
                <dd><?= htmlspecialchars($currentWs['name']) ?></dd>
            </div>
            <div class="ark-field">
                <dt class="ark-label">Kategori</dt>
                <dd><?= htmlspecialchars([
                        'company' => 'Perusahaan / korporat',
                        'personal' => 'Personal / individu',
                        'school' => 'Institusi pendidikan / sekolah',
                    ][$currentWs['type']] ?? 'Lainnya') ?></dd>
            </div>
        </dl>
        <?php if (!empty($currentWs['logo_path'])): ?>
        <div class="ark-profile-image">
            <span class="ark-ws__mark ark-ws__mark--preview">
                <img class="ark-profile-image__preview"
                    src="<?= htmlspecialchars('uploads/' . rawurlencode($currentWs['logo_path']), ENT_QUOTES, 'UTF-8') ?>"
                    alt="">
            </span>
            <span class="ark-helper">Logo workspace</span>
        </div>
        <?php endif; ?>
        <p class="ark-helper">Hanya Owner workspace yang dapat mengubah nama, kategori, dan logo.</p>
        <?php endif; ?>
    </section>

    <?php if ($canDeleteWorkspace): ?>
    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Penghapusan workspace</h2>
            <span class="ark-section__hint">Hanya Owner yang dapat menghapus workspace.</span>
        </div>
        <?php if ($hasWorkspaceToKeep): ?>
        <div class="ark-notice ark-notice--danger">
            <span class="ark-notice__icon" data-icon="alert-triangle"></span>
            <span class="ark-notice__text"><strong>Tindakan ini tidak dapat dibatalkan.</strong> Folder, dokumen arsip,
                dan keanggotaan tim dihapus permanen. Pastikan workspace lain masih tersedia di akun Anda.</span>
        </div>
        <button type="button" class="ark-btn ark-btn--danger" onclick="openModal('modal-delete-workspace')">
            <span data-icon="trash"></span><span class="ark-btn__label">Hapus Workspace Ini</span>
        </button>
        <?php else: ?>
        <p class="ark-helper">Workspace ini adalah satu-satunya workspace di akun Anda, sehingga tidak dapat dihapus.
        </p>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Penghapusan workspace</h2>
        </div>
        <p class="ark-helper">Hanya Owner workspace yang dapat menghapus workspace ini. Hubungi Owner jika diperlukan.
        </p>
    </section>
    <?php endif; ?>
</div>