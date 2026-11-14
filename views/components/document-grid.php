<?php
/**
 * ArsipKu - Component: Document Grid (Tile View)
 * ---------------------------------------------------------------------------
 * Varian kisi dari daftar dokumen: .ark-tile-grid berisi .ark-tile.
 * Bentuknya kartu, isinya tetap sama dengan tabel — nama, meta sekunder,
 * ikon berkas netral tanpa kotak bewarna, dan menu tindakan yang sama.
 *
 * Kontrak JS identik dengan document-table.php: .document-row[data-doc-id],
 * .btn-toggle-favorite[aria-pressed], .btn-action-*, [data-menu].
 */
$isTrash = !empty($isTrashView);
?>
<div class="ark-tile-grid">
    <?php if (empty($documents)): ?>
        <div class="ark-empty ark-empty--flush" style="grid-column: 1 / -1;">
            <span class="ark-empty__icon" data-icon="file"></span>
            <p class="ark-empty__title">Tidak ada dokumen yang ditemukan</p>
            <p class="ark-empty__desc">Ubah kata kunci atau bersihkan penyaring untuk melihat arsip lainnya.</p>
        </div>
    <?php else: ?>
        <?php foreach ($documents as $doc): ?>
            <article class="document-row ark-tile" data-doc-id="<?= $doc['id'] ?>">
                <div class="ark-tile__head">
                    <span class="ark-tile__icon" data-icon="<?= $doc['meta']['icon'] ?>"></span>
                    <div class="ark-tile__main">
                        <button type="button" class="ark-tile__name ark-truncate btn-action-preview" data-doc-id="<?= $doc['id'] ?>" title="<?= htmlspecialchars($doc['name']) ?>"><?= htmlspecialchars($doc['name']) ?></button>
                        <span class="ark-tile__meta"><?= htmlspecialchars($doc['formatted_size']) ?> · <?= htmlspecialchars($doc['time_ago']) ?></span>
                    </div>
                </div>

                <div class="ark-tile__actions">
                    <?php if (!$isTrash): ?>
                        <button type="button" class="ark-icon-btn ark-icon-btn--sm ark-fav btn-toggle-favorite" data-doc-id="<?= $doc['id'] ?>" aria-pressed="<?= !empty($doc['is_favorite']) ? 'true' : 'false' ?>" title="<?= !empty($doc['is_favorite']) ? 'Hapus dari favorit' : 'Tandai favorit' ?>">
                            <span data-icon="<?= !empty($doc['is_favorite']) ? 'star-fill' : 'star' ?>"></span>
                        </button>
                    <?php endif; ?>

                    <?php if (!$isTrash || !empty($doc['can_delete'])): ?>
                    <div class="ark-menu-wrap">
                        <button type="button" class="ark-icon-btn ark-icon-btn--sm" data-menu="#doc-tile-menu-<?= $doc['id'] ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="doc-tile-menu-<?= $doc['id'] ?>" aria-label="Tindakan untuk <?= htmlspecialchars($doc['name']) ?>">
                            <span data-icon="dots"></span>
                        </button>

                        <div class="ark-menu ark-menu--right" id="doc-tile-menu-<?= $doc['id'] ?>" role="menu" hidden>
                            <?php if ($isTrash): ?>
                                <?php if (!empty($doc['can_delete'])): ?>
                                    <button type="button" class="ark-menu__item btn-action-restore" role="menuitem" data-doc-id="<?= $doc['id'] ?>">
                                        <span data-icon="restore"></span><span class="ark-menu__text">Pulihkan dokumen</span>
                                    </button>
                                    <button type="button" class="ark-menu__item ark-menu__item--danger btn-action-permanent-delete" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>">
                                        <span data-icon="trash"></span><span class="ark-menu__text">Hapus permanen</span>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" class="ark-menu__item btn-action-preview" role="menuitem" data-doc-id="<?= $doc['id'] ?>">
                                    <span data-icon="eye"></span><span class="ark-menu__text">Pratinjau</span>
                                </button>
                                <a href="<?= $doc['download_url'] ?>" download="<?= htmlspecialchars($doc['name']) ?>" class="ark-menu__item" role="menuitem">
                                    <span data-icon="download"></span><span class="ark-menu__text">Unduh dokumen</span>
                                </a>
                                <?php if (!empty($doc['can_edit'])): ?>
                                    <button type="button" class="ark-menu__item btn-action-rename" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>" data-doc-ext="<?= htmlspecialchars($doc['extension']) ?>">
                                        <span data-icon="pencil"></span><span class="ark-menu__text">Ubah nama</span>
                                    </button>
                                    <button type="button" class="ark-menu__item btn-action-move" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-folder-id="<?= $doc['folder_id'] ?? '' ?>">
                                        <span data-icon="move"></span><span class="ark-menu__text">Pindahkan</span>
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($doc['can_share'])): ?>
                                    <button type="button" class="ark-menu__item btn-action-share" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>">
                                        <span data-icon="share"></span><span class="ark-menu__text">Atur berbagi</span>
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($doc['can_delete'])): ?>
                                    <div class="ark-menu__separator" role="separator"></div>
                                    <button type="button" class="ark-menu__item ark-menu__item--danger btn-action-delete" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>">
                                        <span data-icon="trash"></span><span class="ark-menu__text">Pindahkan ke sampah</span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
