<?php
/**
 * ArsipKu - Paket & Penyimpanan
 * ---------------------------------------------------------------------------
 * Lapisan Ark: tiga seksi datar (langganan, kuota, daftar paket). Tiga kartu
 * harga dengan border tebal bewarna diganti satu tabel perbandingan — harga
 * dan isi paket memang dibandingkan, dan tabel adalah bentuk paling jujur
 * untuk itu. Warna hanya dipakai pada badge status dan meter kuota.
 *
 * Kontrak JS yang dipertahankan: fetch ke api/workspaces.php
 * (action change_plan dengan plan + limit_bytes).
 */
$usagePercent = min(100, max(0, (int) round((float) $wsStats['usage_percentage'])));
$usageState = $usagePercent >= 100 ? ' is-critical' : (!empty($wsStats['is_warning']) ? ' is-warning' : '');
$currentPlanName = $currentWs['plan_name'];

// Sumber tunggal daftar paket; limit_bytes harus sama dengan yang dikirim ke API.
$plans = [
    [
        'name' => 'Personal Pro',
        'for' => 'Untuk arsip pribadi & keluarga',
        'price' => 'Rp 49.000',
        'limit' => 10 * 1024 * 1024 * 1024,
        'includes' => '10 GB cloud storage · satu pengguna · enkripsi AES-256',
    ],
    [
        'name' => 'Enterprise Business',
        'for' => 'Untuk perusahaan & tim berkembang',
        'price' => 'Rp 249.000',
        'limit' => 50 * 1024 * 1024 * 1024,
        'includes' => '50 GB cloud storage · hingga 25 anggota · izin per peran · riwayat versi penuh',
    ],
    [
        'name' => 'Edu & Institution',
        'for' => 'Untuk sekolah & yayasan sosial',
        'price' => 'Rp 149.000',
        'limit' => 25 * 1024 * 1024 * 1024,
        'includes' => '25 GB cloud storage · anggota tanpa batas · dukungan teknis prioritas',
    ],
];
?>

<div class="ark-measure">
    <div class="ark-page-head">
        <div class="ark-page-head__text">
            <h1 class="ark-title-page">Paket &amp; Penyimpanan</h1>
            <p class="ark-title-sub">
                <?= htmlspecialchars($currentPlanName) ?> ·
                <?= htmlspecialchars($wsStats['formatted_size']) ?> dari <?= htmlspecialchars($wsStats['formatted_limit']) ?> terpakai
            </p>
        </div>
    </div>

    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Langganan</h2>
            <span class="ark-section__hint">Ditagih bulanan, bisa berpindah paket kapan saja.</span>
        </div>

        <div class="ark-list">
            <div class="ark-list__row">
                <span class="ark-list__main">
                    <span class="ark-list__title"><?= htmlspecialchars($currentPlanName) ?></span>
                    <span class="ark-list__meta">Aktif sejak <?= date('d F Y', strtotime($currentWs['created_at'])) ?></span>
                </span>
                <span class="ark-list__end">
                    <span class="ark-badge ark-badge--success"><span class="ark-badge__dot"></span>Berlangganan aktif</span>
                </span>
            </div>
            <div class="ark-list__row">
                <span class="ark-meter<?= $usageState ?> ark-grow" style="--ark-meter-value:<?= $usagePercent ?>%"
                      role="progressbar" aria-valuenow="<?= $usagePercent ?>" aria-valuemin="0" aria-valuemax="100"
                      aria-valuetext="<?= htmlspecialchars($wsStats['formatted_size']) ?> dari <?= htmlspecialchars($wsStats['formatted_limit']) ?> terpakai">
                    <span class="ark-meter__label">Kapasitas terpakai</span>
                    <span class="ark-meter__track"><span class="ark-meter__fill"></span></span>
                    <span class="ark-meter__value ark-num"><?= htmlspecialchars($wsStats['formatted_size']) ?> / <?= htmlspecialchars($wsStats['formatted_limit']) ?> · <?= $usagePercent ?>%</span>
                </span>
            </div>
        </div>
    </section>

    <section class="ark-section ark-section--ruled">
        <div class="ark-section__head">
            <h2 class="ark-section__title">Semua paket</h2>
            <span class="ark-section__hint">Beralih paket mengubah batas kuota workspace, bukan isi arsip.</span>
        </div>

        <div class="ark-table-wrap">
            <table class="ark-table">
                <caption class="ark-sr-only">Perbandingan paket langganan</caption>
                <thead class="ark-table__head">
                    <tr>
                        <th scope="col">Paket</th>
                        <th scope="col" class="ark-table__col--hide-md">Yang didapat</th>
                        <th scope="col" class="ark-th--num">Harga / bulan</th>
                        <th scope="col" class="ark-th--actions"><span class="ark-sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody class="ark-table__body">
                    <?php foreach ($plans as $plan): ?>
                        <?php $isActive = $plan['name'] === $currentPlanName; ?>
                        <tr>
                            <td>
                                <span class="ark-file__main">
                                    <span class="ark-file__name"><?= htmlspecialchars($plan['name']) ?></span>
                                    <span class="ark-file__meta ark-row">
                                        <?php if ($isActive): ?>
                                            <span class="ark-badge ark-badge--accent">Paket aktif</span>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($plan['for']) ?></span>
                                    </span>
                                </span>
                            </td>
                            <td class="ark-table__col--hide-md"><span class="ark-meta"><?= htmlspecialchars($plan['includes']) ?></span></td>
                            <td class="ark-td--num ark-num"><?= htmlspecialchars($plan['price']) ?></td>
                            <td class="ark-td--actions">
                                <?php if ($isActive): ?>
                                    <span class="ark-meta">Sedang dipakai</span>
                                <?php else: ?>
                                    <button type="button" class="ark-btn ark-btn--sm" onclick="fetch('api/workspaces.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'change_plan', plan:'<?= addslashes($plan['name']) ?>', limit_bytes: <?= (int) $plan['limit'] ?>})}).then(r=>r.json()).then(d=>{showToast(d.message); setTimeout(()=>location.reload(), 400);});">
                                        <span class="ark-btn__label">Beralih ke paket ini</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
