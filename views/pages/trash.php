<?php
/**
 * ArsipKu - Sampah (Trash)
 */
$isTrashView = true;
?>
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Tempat Sampah</h1>
        <p class="ark-title-sub">
            <?= count($documents) ?> dokumen dihapus · dapat dipulihkan sebelum dibersihkan permanen
        </p>
    </div>
    <?php if (!empty($documents) && $currentWorkspaceRole !== 'Viewer'): ?>
        <div class="ark-page-head__actions">
            <button type="button" class="ark-btn ark-btn--danger" onclick="openModal('modal-empty-trash')">
                <span data-icon="trash"></span><span class="ark-btn__label">Kosongkan Tempat Sampah</span>
            </button>
        </div>
    <?php endif; ?>
</div>

<div class="ark-notice ark-notice--warning">
    <span class="ark-notice__icon" data-icon="info"></span>
    <span class="ark-notice__text">
        <strong>Retensi arsip:</strong> dokumen di sini tidak muncul di folder utama, tetapi masih tersimpan.
        Tekan <strong>Pulihkan dokumen</strong> pada baris berkas untuk mengembalikannya ke posisi asal.
    </span>
</div>

<?php if (empty($documents)): ?>
    <?php
    $emptyIcon = 'trash';
    $emptyTitle = 'Tempat sampah kosong';
    $emptyDesc = 'Tidak ada dokumen yang berada di dalam folder sampah saat ini.';
    include __DIR__ . '/../components/empty-state.php';
    ?>
<?php else: ?>
    <?php include __DIR__ . '/../components/document-table.php'; ?>
<?php endif; ?>
