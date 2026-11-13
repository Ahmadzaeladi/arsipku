<?php
/**
 * ArsipKu - Component: Empty State
 *
 * Kontrak variabel (set sebelum include):
 *   $emptyIcon   nama ikon dari assets/js/icons.js, default 'file'
 *   $emptyTitle  apa yang kosong
 *   $emptyDesc   kenapa kosong + apa langkah berikutnya
 *   $emptyHint   opsional, catatan kecil di bawah deskripsi
 *   $emptyAction opsional, array: ['label' => ..., 'icon' => ..., 'onclick' => ...]
 *                atau ['label' => ..., 'href' => ...]. Satu aksi saja.
 */

$action = !empty($emptyAction) ? $emptyAction : null;
?>
<div class="ark-empty">
    <span class="ark-empty__icon" data-icon="<?= htmlspecialchars($emptyIcon ?? 'file') ?>"></span>
    <h3 class="ark-empty__title"><?= htmlspecialchars($emptyTitle ?? 'Belum ada data') ?></h3>
    <p class="ark-empty__desc"><?= htmlspecialchars($emptyDesc ?? 'Mulai simpan dan kelola arsip dokumen digital Anda dengan aman.') ?></p>
    <?php if (!empty($emptyHint)): ?>
        <p class="ark-empty__hint"><?= htmlspecialchars($emptyHint) ?></p>
    <?php endif; ?>

    <?php if ($action !== null): ?>
        <div class="ark-empty__actions">
            <?php if (!empty($action['href'])): ?>
                <a class="ark-btn ark-btn--primary" href="<?= htmlspecialchars($action['href']) ?>">
                    <?php if (!empty($action['icon'])): ?><span data-icon="<?= htmlspecialchars($action['icon']) ?>"></span><?php endif; ?>
                    <?= htmlspecialchars($action['label']) ?>
                </a>
            <?php else: ?>
                <button type="button" class="ark-btn ark-btn--primary" onclick="<?= htmlspecialchars($action['onclick'] ?? '') ?>">
                    <?php if (!empty($action['icon'])): ?><span data-icon="<?= htmlspecialchars($action['icon']) ?>"></span><?php endif; ?>
                    <?= htmlspecialchars($action['label']) ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
