<?php
/**
 * ArsipKu - Folder Page View
 * ---------------------------------------------------------------------------
 * Halaman datar: subfolder sebagai .ark-tile, isi folder sebagai .ark-table.
 * Jalur folder ditampilkan oleh breadcrumb di header (views/layouts/app.php),
 * jadi halaman ini tidak mengulang navigasi yang sama.
 */
$isSubFolder = isset($currentFolder);
$folderName = $isSubFolder ? (string) $currentFolder['name'] : 'Folder Saya';
?>

<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page"><?= htmlspecialchars($folderName) ?></h1>

    </div>

    <?php if ($canWriteWorkspaceContent): ?>
    <div class="ark-page-head__actions">
        <button type="button" class="ark-btn" onclick="openModal('modal-create-folder')">
            <span data-icon="folder-plus"></span><span
                class="ark-btn__label"><?= $isSubFolder ? 'Buat Subfolder' : 'Folder Baru' ?></span>
        </button>
        <button type="button" class="ark-btn ark-btn--primary ark-btn--lg" onclick="openModal('modal-upload')">
            <span data-icon="upload"></span><span class="ark-btn__label">Unggah ke Folder Ini</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($folders)): ?>
<section class="ark-section">
    <div class="ark-section__head">
        <h2 class="ark-section__title"><?= $isSubFolder ? 'Subfolder di folder ini' : 'Daftar folder utama' ?></h2>
        <span class="ark-meta ark-num"><?= count($folders) ?> folder</span>
    </div>
    <div class="ark-tile-grid">
        <?php foreach ($folders as $folder): ?>
        <?php include __DIR__ . '/../components/folder-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="ark-section ark-section--ruled">
    <div class="ark-section__head">
        <h2 class="ark-section__title">Dokumen di folder ini</h2>
        <span class="ark-meta ark-num"><?= count($documents) ?> dokumen</span>
    </div>

    <?php if (empty($documents)): ?>
    <?php
        $emptyIcon = 'folder';
        $emptyTitle = 'Folder ini masih kosong';
        $emptyDesc = 'Belum ada dokumen yang tersimpan di folder ini. Unggah berkas untuk mengisi arsip folder ini.';
        $emptyAction = $canWriteWorkspaceContent
            ? ['label' => 'Unggah dokumen ke sini', 'icon' => 'upload', 'onclick' => "openModal('modal-upload')"]
            : null;
        include __DIR__ . '/../components/empty-state.php';
        ?>
    <?php else: ?>
    <?php include __DIR__ . '/../components/document-table.php'; ?>
    <?php endif; ?>
</section>