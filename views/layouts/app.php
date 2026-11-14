<?php
/**
 * ArsipKu - Master AppShell Layout
 * Modern SaaS Digital Archive Management System
 * PHP Native, No Framework
 *
 * Kerangka (sidebar + header + kontainer konten) memakai lapisan desain Ark
 * (assets/css/tokens.css -> base.css -> components/*, assets/js/icons.js +
 * app-shell.js). Isi halaman (views/pages/*) dan modal masih memakai Tailwind
 * sampai migrasi konten disetujui - kedua sistem kelas sengaja hidup
 * berdampingan selama masa adopsi.
 */

require_once __DIR__ . '/../../src/Config.php';
require_once __DIR__ . '/../../src/Helpers.php';

$page = $page ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Digital Archive';
if (!isset($currentWs, $allWorkspaces, $wsStats) || !is_array($currentWs) || !is_array($wsStats)) {
    throw new LogicException('Data workspace wajib dimuat dari database sebelum layout dirender.');
}
$currentUser = currentUser();
$canWriteWorkspaceContent = in_array($currentWorkspaceRole ?? null, ['Owner', 'Admin', 'Editor'], true);
$canManageWorkspace = ($currentWorkspaceRole ?? null) === 'Owner';

/** Inisial dua huruf untuk penanda workspace dan avatar (tanpa gambar eksternal). */
$initialsOf = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
    if (count($parts) > 1) {
        $initials .= mb_strtoupper(mb_substr($parts[1], 0, 1));
    }
    return $initials !== '' ? $initials : 'WS';
};

$usagePercent = (int) max(0, min(100, (int) round((float) $wsStats['usage_percentage'])));

/** Navigasi sidebar: [kunci halaman, label, ikon Ark]. Kelas `data-nav` dibaca app-shell.js. */
$navGroups = [
    'Arsip' => [
        ['dashboard', 'Beranda', 'home'],
        ['documents', 'Semua Dokumen', 'archive'],
        ['folders', 'Folder', 'folder'],
        ['recent', 'Terbaru', 'clock'],
        ['favorites', 'Favorit', 'star-fill'],
        ['shared', 'Dibagikan', 'share'],
        ['trash', 'Sampah', 'trash'],
    ],
    'Workspace' => [
        ['members', 'Anggota', 'users'],
        ['settings', 'Pengaturan', 'settings'],
        // ['billing', 'Langganan', 'credit-card'],
    ],
];

/* Hanya counter yang sudah tersedia di server ($wsStats). Angka lain butuh
   query tambahan dan sengaja tidak dikarang. */
$navCounts = [
    'documents' => (int) ($wsStats['total_documents'] ?? 0),
    'folders' => (int) ($wsStats['total_folders'] ?? 0),
];

if (!isset($csrfToken) || !is_string($csrfToken) || $csrfToken === '') {
    $csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — ArsipKu</title>

    <!-- Tailwind CSS CDN: masih dipakai isi halaman + modal sampai migrasi konten. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: {
                        50: '#EEF2FF',
                        100: '#E0E7FF',
                        500: '#6366F1',
                        600: '#4F46E5',
                        700: '#4338CA',
                    },
                    app: {
                        bg: '#F8FAFC',
                        surface: '#FFFFFF',
                        subtle: '#F1F5F9',
                        border: '#E2E8F0',
                        borderStrong: '#CBD5E1',
                    }
                },
                boxShadow: {
                    'xs': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                    'subtle': '0 1px 3px 0 rgba(0, 0, 0, 0.06), 0 1px 2px 0 rgba(0, 0, 0, 0.04)',
                }
            }
        }
    }
    </script>

    <!-- Sistem lama: scrollbar, modal, toast, toolbar multi-select -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Design system Ark. Urutan wajib: token -> base -> komponen. Tanpa @import, tanpa build. -->
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/components/menu.css">
    <link rel="stylesheet" href="assets/css/components/badge.css">
    <link rel="stylesheet" href="assets/css/components/breadcrumb.css">
    <link rel="stylesheet" href="assets/css/components/tooltip.css">
    <link rel="stylesheet" href="assets/css/components/loader.css">
    <link rel="stylesheet" href="assets/css/components/tabs.css">
    <link rel="stylesheet" href="assets/css/components/table.css">
    <link rel="stylesheet" href="assets/css/components/empty-state.css">
    <link rel="stylesheet" href="assets/css/components/shell.css">
    <link rel="stylesheet" href="assets/css/components/page.css">
    <link rel="stylesheet" href="assets/css/components/sidebar.css">
    <link rel="stylesheet" href="assets/css/components/header.css">
</head>

<body class="antialiased">

    <!-- Toast Notification Container (dipakai window.showToast di app.js) -->
    <div id="toast-container"
        class="fixed top-5 right-5 z-[9999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none *:pointer-events-auto">
    </div>

    <!-- data-shell-page = fallback saat URL tidak membawa ?page= (halaman root). -->
    <div class="ark-shell" data-shell-page="<?= htmlspecialchars($page) ?>">

        <!-- ========================================== -->
        <!-- SIDEBAR: 248px, rail 72px, drawer < 1024px  -->
        <!-- ========================================== -->
        <aside class="ark-sidebar" id="app-nav" aria-label="Navigasi utama">
            <div class="ark-sidebar__head">
                <div class="ark-menu-wrap ark-menu-wrap--block ark-ws">
                    <button type="button" class="ark-ws__trigger" data-menu="#ws-menu" aria-haspopup="menu"
                        aria-expanded="false" aria-controls="ws-menu">
                        <span class="ark-ws__mark" aria-hidden="true">
                            <?php if (!empty($currentWs['logo_path'])): ?>
                            <img src="<?= htmlspecialchars('uploads/' . rawurlencode($currentWs['logo_path']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="">
                            <?php else: ?>
                            <?= $initialsOf((string) $currentWs['name']) ?>
                            <?php endif; ?>
                        </span>
                        <span class="ark-ws__meta">
                            <span class="ark-ws__name"><?= htmlspecialchars($currentWs['name']) ?></span>
                            <span class="ark-ws__plan"><?= htmlspecialchars($wsStats['formatted_size']) ?> /
                                <?= htmlspecialchars($wsStats['formatted_limit']) ?></span>
                        </span>
                        <span class="ark-menu__chevron" data-icon="chevron-down"></span>
                    </button>

                    <div class="ark-menu" id="ws-menu" role="menu" aria-label="Ganti workspace" hidden>
                        <p class="ark-menu__label">Workspace Anda</p>
                        <?php foreach ($allWorkspaces as $ws): ?>
                        <?php $isCurrent = $ws['slug'] === $currentWs['slug']; ?>
                        <!-- .ws-switch-item = jembatan ke controller lama di app.js (fetch api/workspaces.php). -->
                        <a class="ark-menu__item ws-switch-item<?= $isCurrent ? ' is-selected' : '' ?>" role="menuitem"
                            href="<?= url($page, ['ws' => $ws['slug']]) ?>"
                            data-ws-slug="<?= htmlspecialchars($ws['slug']) ?>"
                            <?= $isCurrent ? ' aria-selected="true"' : '' ?>>
                            <span class="ark-ws__item-mark" aria-hidden="true">
                                <?php if (!empty($ws['logo_path'])): ?>
                                <img src="<?= htmlspecialchars('uploads/' . rawurlencode($ws['logo_path']), ENT_QUOTES, 'UTF-8') ?>"
                                    alt="">
                                <?php else: ?>
                                <?= $initialsOf((string) $ws['name']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="ark-menu__text"><?= htmlspecialchars($ws['name']) ?></span>
                            <?php if ($isCurrent): ?>
                            <span class="ark-ws__tick" data-icon="check"></span>
                            <?php else: ?>
                            <span
                                class="ark-menu__meta"><?= htmlspecialchars(formatBytes((int) $ws['storage_limit_bytes'], 0)) ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                        <div class="ark-menu__separator" role="separator"></div>
                        <a class="ark-menu__item" role="menuitem" href="<?= url('members') ?>">
                            <span data-icon="users"></span>
                            <span class="ark-menu__text">Kelola anggota</span>
                        </a>
                        <a class="ark-menu__item" role="menuitem" href="<?= url('settings') ?>">
                            <span data-icon="settings"></span>
                            <span class="ark-menu__text">Pengaturan workspace</span>
                        </a>
                        <?php if (($currentWorkspaceRole ?? null) !== 'Owner' && count($allWorkspaces) > 1): ?>
                        <div class="ark-menu__separator" role="separator"></div>
                        <button type="button" class="ark-menu__item ark-menu__item--danger" role="menuitem"
                            data-leave-workspace>
                            <span data-icon="log-out"></span>
                            <span class="ark-menu__text">Keluar dari workspace</span>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="ark-menu__item" role="menuitem"
                            onclick="openModal('modal-create-workspace')">
                            <span data-icon="plus"></span>
                            <span class="ark-menu__text">Buat workspace baru</span>
                        </button>
                    </div>
                </div>

                <button type="button" class="ark-icon-btn ark-icon-btn--sm ark-sidebar__collapse" data-drawer="collapse"
                    data-ark-tooltip="Ciutkan navigasi" aria-label="Ciutkan navigasi">
                    <span data-icon="panel-left"></span>
                </button>
                <button type="button" class="ark-icon-btn ark-icon-btn--sm ark-sidebar__close" data-drawer="close"
                    aria-label="Tutup navigasi">
                    <span data-icon="x"></span>
                </button>
            </div>

            <nav class="ark-sidebar__nav">
                <?php foreach ($navGroups as $groupLabel => $navItems): ?>
                <div class="ark-nav__group">
                    <p class="ark-nav__group-label ark-label-caps"><?= htmlspecialchars($groupLabel) ?></p>
                    <div class="ark-nav__list">
                        <?php foreach ($navItems as [$navKey, $navLabel, $navIcon]): ?>
                        <a class="ark-nav__item" data-nav="<?= htmlspecialchars($navKey) ?>" href="<?= url($navKey) ?>"
                            <?= $page === $navKey ? ' aria-current="page"' : '' ?>
                            data-ark-tooltip="<?= htmlspecialchars($navLabel) ?>" data-ark-tooltip-position="right">
                            <span class="ark-nav__icon" data-icon="<?= htmlspecialchars($navIcon) ?>"></span>
                            <span class="ark-nav__text"><?= htmlspecialchars($navLabel) ?></span>
                            <?php if (isset($navCounts[$navKey])): ?>
                            <span
                                class="ark-badge ark-badge--count"><?= number_format($navCounts[$navKey], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </nav>

            <div class="ark-sidebar__foot">
                <div class="ark-usage">
                    <div class="ark-usage__row">
                        <span>Penyimpanan</span>
                        <span class="ark-usage__value"><?= $usagePercent ?>%</span>
                    </div>
                    <div class="ark-progress ark-progress--thin" role="progressbar"
                        aria-label="Pemakaian penyimpanan workspace" aria-valuenow="<?= $usagePercent ?>"
                        aria-valuemin="0" aria-valuemax="100"
                        style="--ark-progress-value: <?= max(2, $usagePercent) ?>%">
                        <span
                            class="ark-progress__bar<?= $wsStats['is_warning'] ? ' ark-progress__bar--warning' : '' ?>"></span>
                    </div>
                    <div class="ark-usage__row">
                        <span><?= htmlspecialchars($wsStats['formatted_size']) ?> dari
                            <?= htmlspecialchars($wsStats['formatted_limit']) ?></span>
                    </div>
                    <a class="ark-usage__link" href="<?= url('billing') ?>">
                        <span data-icon="credit-card"></span>
                        Tambah kapasitas
                    </a>
                </div>
            </div>
        </aside>

        <div class="ark-drawer-scrim" data-drawer-scrim></div>

        <!-- ========================================== -->
        <!-- KOLOM UTAMA: header compact + konten        -->
        <!-- ========================================== -->
        <div class="ark-main">
            <header class="ark-header">
                <div class="ark-header__trail">
                    <button type="button" class="ark-icon-btn" data-drawer="menu" aria-label="Buka navigasi"
                        aria-expanded="false" aria-controls="app-nav">
                        <span data-icon="list"></span>
                    </button>

                    <!-- Satu breadcrumb per halaman. Untuk halaman folder, jalur
                         subfolder ($breadcrumbs dari index.php) dipakai di sini supaya
                         view tidak perlu mengulang navigasi yang sama. -->
                    <nav class="ark-crumbs-nav ark-header__crumbs" aria-label="Breadcrumb">
                        <ol class="ark-crumbs">
                            <li class="ark-crumbs__item">
                                <a class="ark-crumbs__link"
                                    href="<?= url('dashboard') ?>"><?= htmlspecialchars($currentWs['name']) ?></a>
                            </li>
                            <?php if (!empty($breadcrumbs)): ?>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                            <li class="ark-crumbs__sep" aria-hidden="true"><span data-icon="chevron-right"></span></li>
                            <?php if ($crumb['id'] === ($currentFolder['id'] ?? '')): ?>
                            <li class="ark-crumbs__item"><span class="ark-crumbs__current"
                                    aria-current="page"><?= htmlspecialchars($crumb['name']) ?></span></li>
                            <?php else: ?>
                            <li class="ark-crumbs__item"><a class="ark-crumbs__link"
                                    href="<?= url('folders', ['folder_id' => $crumb['id']]) ?>"><?= htmlspecialchars($crumb['name']) ?></a>
                            </li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <li class="ark-crumbs__sep" aria-hidden="true"><span data-icon="chevron-right"></span></li>
                            <li class="ark-crumbs__item"><span class="ark-crumbs__current"
                                    aria-current="page"><?= htmlspecialchars($pageTitle) ?></span></li>
                            <?php endif; ?>
                        </ol>
                    </nav>

                    <span class="ark-header__title"><?= htmlspecialchars($pageTitle) ?></span>
                </div>

                <form class="ark-search ark-header__search" action="index.php" method="get" role="search"
                    aria-label="Pencarian workspace">
                    <input type="hidden" name="page" value="documents">
                    <input type="hidden" name="ws"
                        value="<?= htmlspecialchars((string) $currentWs['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <button class="ark-header__search-submit" type="submit" aria-label="Cari">
                        <span class="ark-search__icon" data-icon="search"></span>
                    </button>
                    <input type="search" id="global-search" name="q" class="ark-search__field"
                        value="<?= $page === 'documents' ? htmlspecialchars((string) ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>"
                        placeholder="Cari dokumen, folder, atau pemilik..." aria-label="Cari di workspace ini">
                    <kbd class="ark-kbd ark-search__hint">Ctrl K</kbd>
                </form>

                <div class="ark-header__divider" aria-hidden="true"></div>

                <div class="ark-header__actions">
                    <button type="button" class="ark-icon-btn ark-header__search-toggle" data-search-toggle
                        aria-label="Cari" aria-expanded="false">
                        <span data-icon="search"></span>
                    </button>

                    <div class="ark-menu-wrap">
                        <button type="button" class="ark-icon-btn" data-menu="#notification-menu" aria-haspopup="menu"
                            aria-expanded="false" aria-controls="notification-menu" aria-label="Undangan workspace"
                            data-ark-tooltip="Undangan workspace">
                            <span data-icon="bell"></span>
                            <?php if (!empty($memberNotifications)): ?>
                            <span
                                class="absolute top-1.5 right-1.5 min-w-4 h-4 px-1 rounded-full bg-indigo-600 text-white text-[10px] leading-4 text-center ring-2 ring-white"
                                data-notif-dot><?= count($memberNotifications) ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="ark-menu ark-menu--right" id="notification-menu" role="menu"
                            aria-label="Undangan workspace" hidden>
                            <p class="ark-menu__label">Undangan workspace</p>
                            <?php if (empty($memberNotifications)): ?>
                            <p class="ark-menu__item"><span class="ark-menu__text">Tidak ada undangan yang menunggu
                                    jawaban.</span></p>
                            <?php else: ?>
                            <?php foreach ($memberNotifications as $invitation): ?>
                            <div class="ark-menu__item ark-invitation-notice"
                                data-invitation="<?= htmlspecialchars($invitation['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="ark-menu__text">
                                    <strong>Undangan untuk bergabung</strong><br>
                                    Workspace:
                                    <?= htmlspecialchars($invitation['workspace_name'], ENT_QUOTES, 'UTF-8') ?><br>
                                    Dari:
                                    <?= htmlspecialchars($invitation['inviter_name'] ?: 'Pengguna workspace', ENT_QUOTES, 'UTF-8') ?><br>
                                    Role:
                                    <strong><?= htmlspecialchars($invitation['role'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </span>
                                <span class="flex gap-1 mt-2">
                                    <button type="button" class="ark-btn ark-btn--primary ark-btn--sm"
                                        data-invitation-action="accept"
                                        data-id="<?= htmlspecialchars($invitation['id'], ENT_QUOTES, 'UTF-8') ?>">Terima
                                        undangan</button>
                                    <button type="button" class="ark-btn ark-btn--sm" data-invitation-action="decline"
                                        data-id="<?= htmlspecialchars($invitation['id'], ENT_QUOTES, 'UTF-8') ?>">Tolak</button>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($canWriteWorkspaceContent): ?>
                    <button type="button" class="ark-btn ark-btn--primary ark-btn--sm"
                        onclick="openModal('modal-upload')">
                        <span data-icon="upload-cloud"></span>
                        <span class="ark-btn__label">Unggah</span>
                    </button>
                    <?php endif; ?>

                    <div class="ark-menu-wrap">
                        <button type="button" class="ark-account__trigger" data-menu="#account-menu"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="account-menu">
                            <span class="ark-avatar" aria-hidden="true">
                                <?php if (!empty($currentUser['avatar'])): ?>
                                <img class="ark-avatar__image"
                                    src="<?= htmlspecialchars($currentUser['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                <?php else: ?>
                                <?= $initialsOf((string) $currentUser['name']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="ark-account__name"><?= htmlspecialchars($currentUser['name']) ?></span>
                            <span class="ark-menu__chevron" data-icon="chevron-down"></span>
                        </button>
                        <div class="ark-menu ark-menu--right" id="account-menu" role="menu" aria-label="Akun" hidden>
                            <div class="ark-account__id">
                                <span class="ark-avatar ark-avatar--lg" aria-hidden="true">
                                    <?php if (!empty($currentUser['avatar'])): ?>
                                    <img class="ark-avatar__image"
                                        src="<?= htmlspecialchars($currentUser['avatar'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="">
                                    <?php else: ?>
                                    <?= $initialsOf((string) $currentUser['name']) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="ark-account__id-text">
                                    <span
                                        class="ark-account__id-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                                    <span
                                        class="ark-account__id-mail"><?= htmlspecialchars($currentUser['email']) ?></span>
                                </span>
                            </div>
                            <a class="ark-menu__item" role="menuitem" href="<?= url('account') ?>">
                                <span data-icon="settings"></span>
                                <span class="ark-menu__text">Pengaturan akun</span>
                            </a>
                            <a class="ark-menu__item" role="menuitem" href="<?= url('billing') ?>">
                                <span data-icon="credit-card"></span>
                                <span class="ark-menu__text">Paket langganan</span>
                            </a>
                            <div class="ark-menu__separator" role="separator"></div>
                            <form method="post" action="index.php?page=logout">
                                <input type="hidden" name="_csrf"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="ark-menu__item ark-menu__item--danger" role="menuitem">
                                    <span data-icon="log-out"></span>
                                    <span class="ark-menu__text">Keluar</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Ritme vertikal blok isi dimiliki .ark-content__body (gap 24px),
                jadi view halaman tidak perlu memberi margin sendiri. -->
            <main class="ark-content">
                <div class="ark-content__body">
                    <?php if (isset($viewFile) && file_exists($viewFile)): ?>
                    <?php include $viewFile; ?>
                    <?php else: ?>
                    <p class="ark-helper">Halaman tidak ditemukan.</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Floating Multi-Select Toolbar (dikendalikan assets/js/documents.js) -->
    <?php if ($canWriteWorkspaceContent): ?>
    <div id="multi-select-bar" class="fixed bottom-6 inset-x-0 mx-auto max-w-lg z-40 px-4">
        <div
            class="bg-slate-900 text-white rounded-2xl shadow-2xl p-3 flex items-center justify-between border border-slate-800">
            <div class="flex items-center gap-2 text-xs pl-2">
                <span id="multi-selected-count"
                    class="w-5 h-5 rounded-full bg-indigo-500 text-white font-bold flex items-center justify-center text-[10px]">0</span>
                <span class="font-medium">Item dipilih</span>
            </div>
            <?php endif; ?>
            <div class="flex items-center gap-2">
                <?php if ($page === 'trash'): ?>
                <button type="button" id="btn-multi-restore"
                    class="btn-interactive px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-medium text-white flex items-center gap-1.5">
                    <?= renderSvgIcon('refresh', 'w-3.5 h-3.5') ?>
                    <span>Pulihkan</span>
                </button>
                <button type="button" id="btn-multi-perm-delete"
                    class="btn-interactive px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-xs font-medium text-white flex items-center gap-1.5">
                    <?= renderSvgIcon('trash', 'w-3.5 h-3.5') ?>
                    <span>Hapus Permanen</span>
                </button>
                <?php else: ?>
                <button type="button" id="btn-multi-move"
                    class="btn-interactive px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 border border-slate-700 flex items-center gap-1.5"
                    onclick="if(selectedDocIds.size > 0) openModal('modal-move');">
                    <?= renderSvgIcon('move', 'w-3.5 h-3.5') ?>
                    <span>Pindahkan</span>
                </button>
                <button type="button" id="btn-multi-delete"
                    class="btn-interactive px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-xs font-medium text-white flex items-center gap-1.5">
                    <?= renderSvgIcon('trash', 'w-3.5 h-3.5') ?>
                    <span>Hapus</span>
                </button>
                <?php endif; ?>
                <button type="button" id="btn-multi-cancel"
                    class="px-3 py-1.5 rounded-xl text-slate-400 hover:text-white text-xs font-medium">
                    Batalkan
                </button>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php include __DIR__ . '/modals.php'; ?>

    <!-- Application Scripts -->
    <script src="assets/js/icons.js"></script>
    <script src="assets/js/app-shell.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/documents.js"></script>
    <script src="assets/js/uploader.js"></script>
    <script src="assets/js/preview.js"></script>
    <script src="assets/js/profile-settings.js"></script>
</body>

</html>