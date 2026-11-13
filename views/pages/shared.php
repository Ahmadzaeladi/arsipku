<?php
/**
 * ArsipKu - Dibagikan kepada Saya
 */
?>
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Dibagikan kepada Saya</h1>
        <p class="ark-title-sub">
            <?= count($documents) ?> arsip yang dibagikan anggota workspace lain dengan hak akses khusus
        </p>
    </div>
</div>

<?php if (empty($documents)): ?>
    <?php
    $emptyIcon = 'share';
    $emptyTitle = 'Belum ada dokumen yang dibagikan';
    $emptyDesc = 'Arsip yang dibagikan kolega Anda akan tampil di sini, terpisah dari dokumen milik sendiri.';
    include __DIR__ . '/../components/empty-state.php';
    ?>
<?php else: ?>
    <?php include __DIR__ . '/../components/document-table.php'; ?>
<?php endif; ?>
