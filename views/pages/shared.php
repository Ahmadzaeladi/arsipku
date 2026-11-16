<?php
/**
 * ArsipKu - Dibagikan kepada Saya
 */
?>
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Dibagikan kepada Saya</h1>

    </div>
</div>

<?php if (empty($documents)): ?>
<?php
    $emptyIcon = 'share';
    $emptyTitle = 'Belum ada dokumen yang dibagikan';
    $emptyDesc = 'Dokumen yang dibagikan kepada akun Anda oleh anggota workspace akan tampil di sini.';
    include __DIR__ . '/../components/empty-state.php';
    ?>
<?php else: ?>
<?php include __DIR__ . '/../components/document-table.php'; ?>
<?php endif; ?>