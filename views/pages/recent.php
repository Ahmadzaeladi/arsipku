<?php
/**
 * ArsipKu - Dokumen Terbaru
 */
?>
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Dokumen Terbaru</h1>

    </div>
    <?php if ($canWriteWorkspaceContent): ?>
    <div class="ark-page-head__actions">
        <button type="button" class="ark-btn ark-btn--primary ark-btn--lg" onclick="openModal('modal-upload')">
            <span data-icon="upload"></span><span class="ark-btn__label">Unggah Dokumen</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($documents)): ?>
<?php
    $emptyIcon = 'clock';
    $emptyTitle = 'Belum ada aktivitas dokumen terbaru';
    $emptyDesc = 'Dokumen yang baru saja Anda buat atau ubah akan muncul di daftar ini secara kronologis.';
    $emptyAction = $canWriteWorkspaceContent
        ? ['label' => 'Unggah dokumen', 'icon' => 'upload', 'onclick' => "openModal('modal-upload')"]
        : null;
    include __DIR__ . '/../components/empty-state.php';
    ?>
<?php else: ?>
<?php include __DIR__ . '/../components/document-table.php'; ?>
<?php endif; ?>