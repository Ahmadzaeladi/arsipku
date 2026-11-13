<?php
/**
 * ArsipKu - Component: Folder Tile
 * ---------------------------------------------------------------------------
 * Satu .ark-tile untuk grid folder. Induk wajib menyediakan .ark-tile-grid.
 * Warna folder tidak lagi menentukan latar ikon: ikon garis netral, sama
 * seperti berkas, karena tipe sudah terbaca dari konteksnya.
 */
$folderId = (string) $folder['id'];
$folderIdHtml = htmlspecialchars($folderId);
$folderNameHtml = htmlspecialchars((string) $folder['name']);
?>
<article class="ark-tile">
    <div class="ark-tile__head">
        <span class="ark-tile__icon" data-icon="folder"></span>
        <div class="ark-tile__main">
            <a class="ark-tile__name ark-truncate" href="<?= url('folders', ['folder_id' => $folderId]) ?>" title="<?= $folderNameHtml ?>">
                <?= $folderNameHtml ?>
            </a>
            <span class="ark-tile__meta"><?= (int) ($folder['doc_count'] ?? 0) ?> dokumen</span>
        </div>
    </div>

    <div class="ark-tile__actions">
        <div class="ark-menu-wrap">
            <button type="button" class="ark-icon-btn ark-icon-btn--sm" data-menu="#folder-menu-<?= $folderIdHtml ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="folder-menu-<?= $folderIdHtml ?>" aria-label="Tindakan untuk <?= $folderNameHtml ?>">
                <span data-icon="dots"></span>
            </button>

            <div class="ark-menu ark-menu--right" id="folder-menu-<?= $folderIdHtml ?>" role="menu" hidden>
                <a href="<?= url('folders', ['folder_id' => $folderId]) ?>" class="ark-menu__item" role="menuitem">
                    <span data-icon="eye"></span><span class="ark-menu__text">Buka folder</span>
                </a>
                <button type="button" class="ark-menu__item" role="menuitem" onclick="document.getElementById('rename-folder-id').value = '<?= addslashes($folderId) ?>'; document.getElementById('rename-folder-name').value = '<?= addslashes($folderNameHtml) ?>'; openModal('modal-rename-folder');">
                    <span data-icon="pencil"></span><span class="ark-menu__text">Ubah nama</span>
                </button>
                <div class="ark-menu__separator" role="separator"></div>
                <button type="button" class="ark-menu__item ark-menu__item--danger" role="menuitem" onclick="if(confirm('Pindahkan folder ini beserta isinya ke Sampah?')) { fetch('api/folders.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete', id:'<?= addslashes($folderId) ?>'})}).then(r=>r.json()).then(d=>{showToast(d.message); location.reload();}); }">
                    <span data-icon="trash"></span><span class="ark-menu__text">Hapus folder</span>
                </button>
            </div>
        </div>
    </div>
</article>
