<?php
/**
 * ArsipKu - Dokumen Favorit
 */
?>
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Dokumen Favorit</h1>

    </div>
</div>

<?php if (empty($documents)): ?>
<?php
    $emptyIcon = 'star';
    $emptyTitle = 'Belum ada dokumen favorit';
    $emptyDesc = 'Tandai dokumen penting dengan bintang pada baris daftar agar mudah diakses kembali.';
    $emptyAction = ['label' => 'Lihat semua dokumen', 'icon' => 'file-text', 'href' => url('documents')];
    include __DIR__ . '/../components/empty-state.php';
    ?>
<?php else: ?>
<?php include __DIR__ . '/../components/document-table.php'; ?>
<?php endif; ?>