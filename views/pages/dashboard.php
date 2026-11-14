<?php
/**
 * ArsipKu - Dashboard Page View (Beranda)
 * ---------------------------------------------------------------------------
 * Halaman datar: kepala halaman + ringkasan satu baris teks, lalu seksi
 * dokumen terbaru, pintasan folder, aktivitas, dan satu meter penyimpanan.
 * Tidak ada grid kartu statistik: angka cukup dibaca dari tipografi, dan
 * kuota diukur dengan .ark-meter (bukan donat/gradient).
 */
$usagePercent = min(100, max(0, (int) round((float) $wsStats['usage_percentage'])));
$usageState = $usagePercent >= 100 ? ' is-critical' : (!empty($wsStats['is_warning']) ? ' is-warning' : '');
$recentForTable = array_slice($recentDocuments, 0, 6);
?>

<!-- Kepala halaman: satu judul, satu baris ringkasan, satu aksi primer -->
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Beranda</h1>
        <p class="ark-title-sub">
            <?= htmlspecialchars($currentWs['name']) ?> ·
            <?= (int) $wsStats['total_documents'] ?> dokumen ·
            <?= (int) $wsStats['total_folders'] ?> folder
        </p>
    </div>
    <div class="ark-page-head__actions">
        <?php if ($canWriteWorkspaceContent): ?>
        <button type="button" class="ark-btn" onclick="openModal('modal-create-folder')">
            <span data-icon="folder-plus"></span><span class="ark-btn__label">Buat Folder</span>
        </button>
        <button type="button" class="ark-btn ark-btn--primary ark-btn--lg" onclick="openModal('modal-upload')">
            <span data-icon="upload"></span><span class="ark-btn__label">Unggah Dokumen</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Dokumen terbaru -->
<section class="ark-section ark-section--ruled">
    <div class="ark-section__head">
        <h2 class="ark-section__title">Dokumen terbaru</h2>
        <a class="ark-section__more" href="<?= url('documents') ?>">Lihat semua dokumen</a>
    </div>

    <?php if (empty($recentForTable)): ?>
        <?php
        $emptyIcon = 'file-text';
        $emptyTitle = 'Belum ada dokumen tersimpan';
        $emptyDesc = 'Mulai simpan dokumen penting seperti surat, kontrak, dan laporan ke workspace ini.';
        $emptyAction = $canWriteWorkspaceContent
            ? ['label' => 'Unggah dokumen pertama', 'icon' => 'upload', 'onclick' => "openModal('modal-upload')"]
            : null;
        include __DIR__ . '/../components/empty-state.php';
        ?>
    <?php else: ?>
        <?php $documents = $recentForTable; include __DIR__ . '/../components/document-table.php'; ?>
    <?php endif; ?>
</section>

<!-- Pintasan folder -->
<section class="ark-section">
    <div class="ark-section__head">
        <h2 class="ark-section__title">Pintasan folder</h2>
        <a class="ark-section__more" href="<?= url('folders') ?>">Kelola folder</a>
    </div>

    <?php if (empty($quickFolders)): ?>
        <p class="ark-helper"><?= $canWriteWorkspaceContent
            ? 'Belum ada folder. Buat folder untuk memisahkan arsip per proyek atau periode.'
            : 'Belum ada folder di workspace ini.' ?></p>
    <?php else: ?>
        <div class="ark-tile-grid">
            <?php foreach ($quickFolders as $folder): ?>
                <?php include __DIR__ . '/../components/folder-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Aktivitas + penyimpanan: dua kolom di layar lebar, satu kolom di bawahnya -->
<div class="ark-split">
    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Aktivitas terkini</h2>
            <a class="ark-section__more" href="<?= url('recent') ?>">Lihat aktivitas</a>
        </div>

        <?php if (empty($recentActivities)): ?>
            <p class="ark-helper">Belum ada riwayat aktivitas pada workspace ini.</p>
        <?php else: ?>
            <div class="ark-list ark-list--inset">
                <?php foreach ($recentActivities as $act): ?>
                    <div class="ark-list__row">
                        <span class="ark-avatar" aria-hidden="true"><?= $initialsOf((string) $act['user_name']) ?></span>
                        <span class="ark-list__main">
                            <span class="ark-list__title"><strong><?= htmlspecialchars($act['user_name']) ?></strong> <?= htmlspecialchars(trim(str_replace($act['user_name'], '', $act['description']))) ?></span>
                        </span>
                        <span class="ark-list__end"><?= htmlspecialchars($act['time_ago']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Penyimpanan</h2>
            <a class="ark-section__more" href="<?= url('billing') ?>">Kelola kuota</a>
        </div>
        <div class="ark-list ark-list--inset">
            <div class="ark-list__row">
                <span class="ark-meter<?= $usageState ?> ark-grow" style="--ark-meter-value:<?= $usagePercent ?>%"
                      role="progressbar" aria-valuenow="<?= $usagePercent ?>" aria-valuemin="0" aria-valuemax="100"
                      aria-valuetext="<?= htmlspecialchars($wsStats['formatted_size']) ?> dari <?= htmlspecialchars($wsStats['formatted_limit']) ?> terpakai">
                    <span class="ark-meter__label">Terpakai</span>
                    <span class="ark-meter__track"><span class="ark-meter__fill"></span></span>
                    <span class="ark-meter__value ark-num"><?= htmlspecialchars($wsStats['formatted_size']) ?> / <?= htmlspecialchars($wsStats['formatted_limit']) ?></span>
                </span>
            </div>
        </div>
    </section>
</div>
