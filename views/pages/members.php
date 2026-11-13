<?php
/**
 * ArsipKu - Anggota Workspace
 * ---------------------------------------------------------------------------
 * Lapisan Ark: kepala halaman + satu tabel datar (tanpa kartu, tanpa bayangan).
 * Avatar memakai inisial seperti di header, jadi tidak ada gambar eksternal.
 * Peran Owner tampil sebagai badge, peran lain sebagai select inline — aksi
 * tetap terlihat, bukan hover-only.
 *
 * Kontrak JS yang dipertahankan: openModal('modal-invite-member') dan fetch
 * ke api/members.php (action update_role / remove).
 */
$memberCount = count($members);
$canManageMembers = in_array($currentWorkspaceRole ?? null, ['Owner', 'Admin'], true);
?>

<div class="ark-page-head">
    <div class="ark-page-head__text">
        <h1 class="ark-title-page">Anggota Workspace</h1>
        <p class="ark-title-sub">
            <?= $memberCount ?> anggota di <?= htmlspecialchars($currentWs['name']) ?> — atur peran dan akses
            kolaborasi.
        </p>
    </div>
    <?php if ($canManageMembers): ?>
    <div class="ark-page-head__actions">
        <button type="button" class="ark-btn ark-btn--primary ark-btn--lg" onclick="openModal('modal-invite-member')">
            <span data-icon="plus"></span><span class="ark-btn__label">Undang lewat Email</span>
        </button>
        <button type="button" class="ark-btn ark-btn--secondary ark-btn--lg"
            onclick="openModal('modal-create-invite-link')">
            <span data-icon="link"></span><span class="ark-btn__label">Buat Share Link</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<section class="ark-section ark-section--ruled">
    <div class="ark-section__head">
        <h2 class="ark-section__title">Daftar anggota</h2>
    </div>

    <div class="ark-table-wrap">
        <table class="ark-table">
            <caption class="ark-sr-only">Anggota workspace beserta peran dan statusnya</caption>
            <thead class="ark-table__head">
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Peran</th>
                    <th scope="col" class="ark-table__col--hide-sm">Status</th>
                    <th scope="col" class="ark-table__col--hide-md">Bergabung</th>
                    <th scope="col" class="ark-th--actions"><span class="ark-sr-only">Tindakan</span></th>
                </tr>
            </thead>
            <tbody class="ark-table__body">
                <?php if ($memberCount === 0): ?>
                <tr>
                    <td colspan="5">
                        <div class="ark-empty ark-empty--inline">
                            <span class="ark-empty__icon" data-icon="users"></span>
                            <div class="ark-stack" style="gap:2px">
                                <p class="ark-empty__title">Belum ada anggota</p>
                                <p class="ark-helper">Undang rekan kerja agar bisa mengarsip dan bekerja di workspace
                                    ini.</p>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($members as $m): ?>
                <?php $isOwner = $m['role'] === 'Owner'; ?>
                <tr>
                    <td>
                        <div class="ark-file">
                            <span class="ark-avatar" aria-hidden="true"><?= $initialsOf((string) $m['name']) ?></span>
                            <span class="ark-file__main">
                                <span class="ark-file__name"><?= htmlspecialchars($m['name']) ?></span>
                                <span class="ark-file__meta"><?= htmlspecialchars($m['email']) ?></span>
                            </span>
                        </div>
                    </td>

                    <td>
                        <?php if ($isOwner): ?>
                        <span class="ark-badge ark-badge--accent">Owner</span>
                        <?php elseif ($canManageMembers): ?>
                        <select class="ark-select ark-select--sm ark-select--fit"
                            aria-label="Peran untuk <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-update-member-role data-id="<?= htmlspecialchars($m['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <option value="Admin" <?= $m['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="Editor" <?= $m['role'] === 'Editor' ? 'selected' : '' ?>>Editor</option>
                            <option value="Viewer" <?= $m['role'] === 'Viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                        <?php else: ?>
                        <span class="ark-badge"><?= htmlspecialchars($m['role'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>

                    <td class="ark-table__col--hide-sm">
                        <?php if ($m['status'] === 'active'): ?>
                        <span class="ark-badge ark-badge--success"><span class="ark-badge__dot"></span>Aktif</span>
                        <?php else: ?>
                        <span class="ark-badge ark-badge--warning"><span class="ark-badge__dot"></span>Menunggu
                            konfirmasi</span>
                        <?php endif; ?>
                    </td>

                    <td class="ark-table__col--hide-md"><span
                            class="ark-meta"><?= date('d M Y', strtotime($m['joined_at'])) ?></span></td>

                    <td class="ark-td--actions">
                        <?php if (!$isOwner && $canManageMembers): ?>
                        <button type="button" class="ark-btn ark-btn--danger-ghost ark-btn--sm" data-remove-member
                            data-id="<?= htmlspecialchars($m['id'], ENT_QUOTES, 'UTF-8') ?>">Keluarkan</button>
                        <?php elseif (!$isOwner): ?>
                        <span class="ark-meta">—</span>
                        <?php else: ?>
                        <span class="ark-meta">Pemilik workspace</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManageMembers): ?>
<section class="ark-section ark-section--ruled">
    <div class="ark-section__head">
        <h2 class="ark-section__title">Undangan dan permintaan bergabung</h2>
        <span class="ark-section__hint">Anggota baru hanya mendapat akses setelah undangan diterima atau permintaan
            disetujui.</span>
    </div>
    <div class="ark-table-wrap">
        <table class="ark-table">
            <caption class="ark-sr-only">Undangan dan permintaan anggota yang menunggu</caption>
            <thead class="ark-table__head">
                <tr>
                    <th scope="col">Pengguna</th>
                    <th scope="col">Role</th>
                    <th scope="col">Sumber</th>
                    <th scope="col">Dibuat</th>
                    <th scope="col" class="ark-th--actions">Tindakan</th>
                </tr>
            </thead>
            <tbody class="ark-table__body">
                <?php if (empty($pendingMemberInvitations)): ?>
                <tr>
                    <td colspan="5">
                        <p class="ark-helper">Tidak ada undangan atau permintaan yang menunggu.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($pendingMemberInvitations as $invitation): ?>
                <tr>
                    <td>
                        <span
                            class="ark-file__name"><?= htmlspecialchars($invitation['invitee_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span
                            class="ark-file__meta"><?= htmlspecialchars($invitation['invitee_email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><span class="ark-badge"><?= htmlspecialchars($invitation['role'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td><?= $invitation['source'] === 'email' ? 'Undangan email' : 'Permintaan dari link' ?></td>
                    <td><span
                            class="ark-meta"><?= htmlspecialchars(date('d M Y', strtotime($invitation['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td class="ark-td--actions">
                        <?php if ($invitation['source'] === 'link'): ?>
                        <button type="button" class="ark-btn ark-btn--primary ark-btn--sm"
                            data-resolve-invitation="accept"
                            data-id="<?= htmlspecialchars($invitation['id'], ENT_QUOTES, 'UTF-8') ?>">Setujui</button>
                        <?php endif; ?>
                        <?php if ($invitation['source'] === 'link'): ?>
                        <button type="button" class="ark-btn ark-btn--danger-ghost ark-btn--sm"
                            data-resolve-invitation="reject"
                            data-id="<?= htmlspecialchars($invitation['id'], ENT_QUOTES, 'UTF-8') ?>">Tolak</button>
                        <?php else: ?>
                        <button type="button" class="ark-btn ark-btn--danger-ghost ark-btn--sm" data-cancel-invitation
                            data-id="<?= htmlspecialchars($invitation['id'], ENT_QUOTES, 'UTF-8') ?>">Batalkan</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="ark-section ark-section--ruled">
    <details>
        <summary class="ark-section__title" style="cursor:pointer">
            Kelola share link (<?= count($workspaceInviteLinks) ?>)
        </summary>
        <p class="ark-helper" style="margin:8px 0 16px">Link menyimpan role dan aturan persetujuan saat dibuat. Link
            yang dinonaktifkan atau kedaluwarsa tidak bisa digunakan.</p>
        <div class="ark-table-wrap">
            <table class="ark-table">
                <caption class="ark-sr-only">Share link workspace</caption>
                <thead class="ark-table__head">
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Persetujuan</th>
                        <th scope="col">Penggunaan</th>
                        <th scope="col">Berlaku sampai</th>
                        <th scope="col" class="ark-th--actions">Status</th>
                    </tr>
                </thead>
                <tbody class="ark-table__body">
                    <?php if (empty($workspaceInviteLinks)): ?>
                    <tr>
                        <td colspan="5">
                            <p class="ark-helper">Belum ada share link.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($workspaceInviteLinks as $link): ?>
                    <?php $linkActive = $link['revoked_at'] === null && strtotime($link['expires_at']) > time() && ($link['max_uses'] === null || (int) $link['uses_count'] < (int) $link['max_uses']); ?>
                    <tr>
                        <td><span class="ark-badge"><?= htmlspecialchars($link['role'], ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td><?= $link['approval_required'] ? 'Perlu persetujuan' : 'Langsung bergabung' ?></td>
                        <td><?= (int) $link['uses_count'] ?><?= $link['max_uses'] !== null ? ' / ' . (int) $link['max_uses'] : '' ?>
                        </td>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($link['expires_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="ark-td--actions">
                            <?php if ($linkActive): ?>
                            <span class="ark-badge ark-badge--success">Aktif</span>
                            <button type="button" class="ark-btn ark-btn--danger-ghost ark-btn--sm"
                                data-revoke-invite-link
                                data-id="<?= htmlspecialchars($link['id'], ENT_QUOTES, 'UTF-8') ?>">Nonaktifkan</button>
                            <?php else: ?>
                            <span class="ark-badge ark-badge--warning">Tidak aktif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </details>
</section>
<?php endif; ?>