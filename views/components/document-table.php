<?php
/**
 * ArsipKu - Component: Document Table (List View)
 * ---------------------------------------------------------------------------
 * Lapisan Ark: .ark-table di dalam .ark-table-wrap (datar, tanpa bayangan,
 * baris 40px, isi 14px). Ikon berkas netral — tanpa kotak bewarna; hue tipe
 * berkas hanya dipakai badge, bukan sebagai latar.
 *
 * Kontrak JS yang tidak boleh berubah (assets/js/documents.js, app.js,
 * preview.js): #select-all-docs, .doc-checkbox + [data-doc-id],
 * .document-row[data-doc-id], .btn-toggle-favorite, .btn-action-*,
 * .ark-fav[aria-pressed] dan ikon bintang di dalam [data-icon].
 */
$isTrash = !empty($isTrashView);
$colCount = $isTrash ? 6 : 7;
?>
<div class="ark-table-wrap">
    <table class="ark-table">
        <caption class="ark-sr-only"><?= $isTrash ? 'Dokumen di tempat sampah' : 'Daftar dokumen' ?></caption>
        <thead class="ark-table__head">
            <tr>
                <th class="ark-th--check" scope="col">
                    <span class="ark-check">
                        <input class="ark-check__input" type="checkbox" id="select-all-docs" aria-label="Pilih semua dokumen">
                        <span class="ark-check__box"></span>
                    </span>
                </th>
                <th scope="col" class="ark-th--file-name<?= !$isTrash ? ' ark-th--file-name--favorite' : '' ?>">Nama dokumen</th>
                <?php if (!$isTrash): ?>
                    <th scope="col" class="ark-th--center ark-table__col--hide-md">Folder</th>
                <?php endif; ?>
                <th scope="col" class="ark-th--center ark-table__col--hide-md">Pemilik</th>
                <th scope="col" class="ark-th--center">Diperbarui</th>
                <th scope="col" class="ark-th--center ark-table__col--hide-sm">Ukuran</th>
                <th scope="col" class="ark-th--center ark-th--actions">Aksi</th>
            </tr>
        </thead>
        <tbody class="ark-table__body">
            <?php if (empty($documents)): ?>
                <tr>
                    <td colspan="<?= $colCount ?>">
                        <div class="ark-empty ark-empty--inline">
                            <span class="ark-empty__icon" data-icon="file"></span>
                            <div class="ark-stack" style="gap:2px">
                                <p class="ark-empty__title">Tidak ada dokumen yang ditemukan</p>
                                <p class="ark-helper">Ubah kata kunci atau bersihkan penyaring untuk melihat arsip lainnya.</p>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <tr class="document-row" data-doc-id="<?= $doc['id'] ?>">
                        <td class="ark-td--check">
                            <span class="ark-check">
                                <input class="ark-check__input doc-checkbox" type="checkbox" data-doc-id="<?= $doc['id'] ?>" aria-label="Pilih <?= htmlspecialchars($doc['name']) ?>">
                                <span class="ark-check__box"></span>
                            </span>
                        </td>

                        <td>
                            <div class="ark-file">
                                <?php if (!$isTrash): ?>
                                    <button type="button" class="ark-icon-btn ark-icon-btn--sm ark-fav btn-toggle-favorite" data-doc-id="<?= $doc['id'] ?>" aria-pressed="<?= !empty($doc['is_favorite']) ? 'true' : 'false' ?>" title="<?= !empty($doc['is_favorite']) ? 'Hapus dari favorit' : 'Tandai favorit' ?>">
                                        <span data-icon="<?= !empty($doc['is_favorite']) ? 'star-fill' : 'star' ?>"></span>
                                    </button>
                                <?php endif; ?>

                                <span class="ark-file__glyph" data-icon="<?= $doc['meta']['icon'] ?>"></span>

                                <span class="ark-file__main">
                                    <button type="button" class="ark-file__name ark-truncate btn-action-preview" data-doc-id="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?></button>
                                    <span class="ark-file__meta"><?= htmlspecialchars($doc['owner_name']) ?> · <?= htmlspecialchars($doc['time_ago']) ?></span>
                                </span>
                            </div>
                        </td>

                        <?php if (!$isTrash): ?>
                            <td class="ark-td--center ark-table__col--hide-md">
                                <?php if (!empty($doc['folder_name'])): ?>
                                    <?= htmlspecialchars($doc['folder_name']) ?>
                                <?php else: ?>
                                    <span class="ark-meta">Arsip Utama</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>

                        <td class="ark-td--center ark-table__col--hide-md"><?= htmlspecialchars($doc['owner_name']) ?></td>

                        <td class="ark-td--center"><span class="ark-meta" title="<?= htmlspecialchars($doc['formatted_date']) ?>"><?= htmlspecialchars($doc['time_ago']) ?></span></td>

                        <td class="ark-td--center ark-table__col--hide-sm ark-num"><?= htmlspecialchars($doc['formatted_size']) ?></td>

                        <td class="ark-td--center ark-td--actions">
                            <div class="ark-menu-wrap">
                                <button type="button" class="ark-icon-btn ark-icon-btn--sm" data-menu="#doc-menu-<?= $doc['id'] ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="doc-menu-<?= $doc['id'] ?>" aria-label="Tindakan untuk <?= htmlspecialchars($doc['name']) ?>">
                                    <span data-icon="dots"></span>
                                </button>

                                <div class="ark-menu ark-menu--right" id="doc-menu-<?= $doc['id'] ?>" role="menu" hidden>
                                    <?php if ($isTrash): ?>
                                        <button type="button" class="ark-menu__item btn-action-restore" role="menuitem" data-doc-id="<?= $doc['id'] ?>">
                                            <span data-icon="restore"></span><span class="ark-menu__text">Pulihkan dokumen</span>
                                        </button>
                                        <button type="button" class="ark-menu__item ark-menu__item--danger btn-action-permanent-delete" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>">
                                            <span data-icon="trash"></span><span class="ark-menu__text">Hapus permanen</span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="ark-menu__item btn-action-preview" role="menuitem" data-doc-id="<?= $doc['id'] ?>">
                                            <span data-icon="eye"></span><span class="ark-menu__text">Pratinjau</span>
                                        </button>
                                        <a href="<?= $doc['download_url'] ?>" download="<?= htmlspecialchars($doc['name']) ?>" class="ark-menu__item" role="menuitem">
                                            <span data-icon="download"></span><span class="ark-menu__text">Unduh dokumen</span>
                                        </a>
                                        <button type="button" class="ark-menu__item btn-action-rename" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>" data-doc-ext="<?= htmlspecialchars($doc['extension']) ?>">
                                            <span data-icon="pencil"></span><span class="ark-menu__text">Ubah nama</span>
                                        </button>
                                        <button type="button" class="ark-menu__item btn-action-move" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-folder-id="<?= $doc['folder_id'] ?? '' ?>">
                                            <span data-icon="move"></span><span class="ark-menu__text">Pindahkan</span>
                                        </button>
                                        <button type="button" class="ark-menu__item btn-action-share" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>">
                                            <span data-icon="share"></span><span class="ark-menu__text">Bagikan tautan</span>
                                        </button>
                                        <div class="ark-menu__separator" role="separator"></div>
                                        <button type="button" class="ark-menu__item ark-menu__item--danger btn-action-delete" role="menuitem" data-doc-id="<?= $doc['id'] ?>" data-doc-name="<?= htmlspecialchars($doc['name']) ?>">
                                            <span data-icon="trash"></span><span class="ark-menu__text">Pindahkan ke sampah</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
