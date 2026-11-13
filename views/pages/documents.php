<?php
/**
 * ArsipKu - Semua Dokumen Page View
 * ---------------------------------------------------------------------------
 * Halaman datar: kepala halaman, toolbar penyaring, lalu daftar.
 * #doc-search / #filter-* / #btn-view-* / #view-*-container adalah kontrak
 * assets/js/documents.js — jangan ganti nama atau struktur dasarnya.
 */

$currentQ = $_GET['q'] ?? '';
$currentType = $_GET['type'] ?? '';
$currentDate = $_GET['date_range'] ?? '';
$currentSort = $_GET['sort'] ?? 'updated_desc';
$hasFilter = !empty($currentQ) || !empty($currentType) || !empty($currentDate);
?>

<!-- Kepala halaman -->
<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Semua Dokumen</h1>
        <p class="ark-title-sub">
            <?= count($documents) ?> arsip di workspace ini ·
            <?= htmlspecialchars($currentWs['name']) ?>
        </p>
    </div>
    <div class="ark-page-head__actions">
        <button type="button" class="ark-btn" onclick="openModal('modal-create-folder')">
            <span data-icon="folder-plus"></span><span class="ark-btn__label">Buat Folder</span>
        </button>
        <button type="button" class="ark-btn ark-btn--primary ark-btn--lg" onclick="openModal('modal-upload')">
            <span data-icon="upload"></span><span class="ark-btn__label">Unggah Dokumen</span>
        </button>
    </div>
</div>

<!-- Toolbar: pencarian, penyaring, pemilih tampilan -->
<div class="ark-toolbar">
    <div class="ark-toolbar__group">
        <div class="ark-search ark-toolbar__search">
            <span class="ark-search__icon" data-icon="search"></span>
            <input type="search" id="doc-search" class="ark-search__field" value="<?= htmlspecialchars($currentQ) ?>" placeholder="Cari dokumen..." aria-label="Kata kunci dokumen">
        </div>

        <select id="filter-type" class="ark-select ark-select--sm ark-select--fit" aria-label="Tipe berkas">
            <option value="">Semua tipe berkas</option>
            <option value="pdf" <?= $currentType === 'pdf' ? 'selected' : '' ?>>Dokumen PDF</option>
            <option value="word" <?= $currentType === 'word' ? 'selected' : '' ?>>Word Document</option>
            <option value="spreadsheet" <?= $currentType === 'spreadsheet' ? 'selected' : '' ?>>Spreadsheet Excel</option>
            <option value="image" <?= $currentType === 'image' ? 'selected' : '' ?>>Foto &amp; Gambar</option>
            <option value="archive" <?= $currentType === 'archive' ? 'selected' : '' ?>>Arsip ZIP</option>
            <option value="txt" <?= $currentType === 'txt' ? 'selected' : '' ?>>Teks Dokumen</option>
        </select>

        <select id="filter-date" class="ark-select ark-select--sm ark-select--fit" aria-label="Rentang waktu">
            <option value="">Semua waktu</option>
            <option value="today" <?= $currentDate === 'today' ? 'selected' : '' ?>>Hari ini</option>
            <option value="7days" <?= $currentDate === '7days' ? 'selected' : '' ?>>7 hari terakhir</option>
            <option value="30days" <?= $currentDate === '30days' ? 'selected' : '' ?>>30 hari terakhir</option>
            <option value="this_year" <?= $currentDate === 'this_year' ? 'selected' : '' ?>>Tahun ini</option>
        </select>

        <select id="filter-sort" class="ark-select ark-select--sm ark-select--fit" aria-label="Urutan">
            <option value="updated_desc" <?= $currentSort === 'updated_desc' ? 'selected' : '' ?>>Terbaru diubah</option>
            <option value="updated_asc" <?= $currentSort === 'updated_asc' ? 'selected' : '' ?>>Terlama diubah</option>
            <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Nama A - Z</option>
            <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Nama Z - A</option>
            <option value="size_desc" <?= $currentSort === 'size_desc' ? 'selected' : '' ?>>Ukuran terbesar</option>
            <option value="size_asc" <?= $currentSort === 'size_asc' ? 'selected' : '' ?>>Ukuran terkecil</option>
        </select>
    </div>

    <div class="ark-toolbar__group">
        <div class="ark-segmented ark-segmented--sm" role="group" aria-label="Tampilan daftar">
            <button type="button" id="btn-view-list" class="ark-segmented__item" aria-pressed="true"><span data-icon="list"></span>Daftar</button>
            <button type="button" id="btn-view-grid" class="ark-segmented__item" aria-pressed="false"><span data-icon="layout-grid"></span>Kisi</button>
        </div>
    </div>
</div>

<!-- Penyaring aktif -->
<?php if ($hasFilter): ?>
    <div class="ark-cluster" style="gap:var(--ark-space-2)">
        <?php if (!empty($currentQ)): ?>
            <span class="ark-badge ark-badge--neutral ark-chip">
                "<?= htmlspecialchars($currentQ) ?>"
                <a href="<?= url('documents', ['type' => $currentType, 'date_range' => $currentDate, 'sort' => $currentSort]) ?>" aria-label="Hapus kata kunci pencarian"><span data-icon="x"></span></a>
            </span>
        <?php endif; ?>
        <?php if (!empty($currentType)): ?>
            <span class="ark-badge ark-badge--neutral ark-chip">
                Tipe: <?= htmlspecialchars(strtoupper($currentType)) ?>
                <a href="<?= url('documents', ['q' => $currentQ, 'date_range' => $currentDate, 'sort' => $currentSort]) ?>" aria-label="Hapus penyaring tipe"><span data-icon="x"></span></a>
            </span>
        <?php endif; ?>
        <?php if (!empty($currentDate)): ?>
            <span class="ark-badge ark-badge--neutral ark-chip">
                <?= htmlspecialchars($currentDate) ?>
                <a href="<?= url('documents', ['q' => $currentQ, 'type' => $currentType, 'sort' => $currentSort]) ?>" aria-label="Hapus penyaring waktu"><span data-icon="x"></span></a>
            </span>
        <?php endif; ?>
        <a class="ark-section__more" href="<?= url('documents') ?>">Bersihkan semua</a>
    </div>
<?php endif; ?>

<!-- Daftar dokumen: satu komponen tabel, satu komponen kisi -->
<?php if (empty($documents)): ?>
    <?php
    $emptyIcon = 'file-text';
    $emptyTitle = !empty($currentQ) ? 'Tidak ada dokumen yang cocok' : 'Belum ada dokumen di arsip ini';
    $emptyDesc = !empty($currentQ)
        ? 'Periksa kembali ejaan kata kunci atau ubah penyaring tipe dan waktu berkas.'
        : 'Unggah dokumen pertama Anda untuk mulai mengelola arsip secara terstruktur.';
    $emptyAction = !empty($currentQ) || $hasFilter
        ? ['label' => 'Bersihkan penyaring', 'icon' => 'x', 'href' => url('documents')]
        : ['label' => 'Unggah dokumen', 'icon' => 'upload', 'onclick' => "openModal('modal-upload')"];
    include __DIR__ . '/../components/empty-state.php';
    ?>
<?php else: ?>
    <div id="view-list-container">
        <?php include __DIR__ . '/../components/document-table.php'; ?>
    </div>
    <div id="view-grid-container" hidden>
        <?php include __DIR__ . '/../components/document-grid.php'; ?>
    </div>
<?php endif; ?>
