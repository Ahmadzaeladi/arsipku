# Audit desain ArsipKu — de-korasi lapisan konten

Tanggal: 2026-10-28. Cakupan: 15 berkas markup + 4 skrip runtime + `src/Helpers.php`.
Metode: inventaris token dengan skrip (`class="..."` di `views/*/*.php`, `src/Helpers.php`,
`assets/js/{app,documents,uploader,preview}.js`), lalu pembacaan tiap berkas untuk
klasifikasi pola. Angka di bawah adalah hasil hitungan, bukan perkiraan.

## 1. Angka dasar

| Ukuran | Nilai |
|---|---|
| Atribut `class` di markup | 725 |
| Token kelas unik | 455 |
| Berkas yang memakai Tailwind | 22 (10 halaman, 5 komponen, 2 layout, Helpers, 4 JS runtime, `style.css`) |
| Registri ikon yang berjalan bersamaan | **2** (`src/Helpers.php::renderSvgIcon` 35 ikon, `assets/js/icons.js` 47 ikon) |
| Kelas warna latar/hue dipakai | 29 unik, 129 pemakaian |
| Radius yang berbeda untuk pola yang sama | 4 (`rounded`, `rounded-lg`, `rounded-xl`, `rounded-2xl`) |
| Nilai jarak tidak kelipatan 4 | `py-3.5` ×22, `py-2.5` ×34, `px-3.5` ×21, `gap-2.5`, `p-1.5`, `py-1.5`, `px-2.5`, `mt-0.5`, `py-14` |
| Ukuran teks arbitrer | `text-[10px]` ×8, `text-[11px]` ×11 (8 dari 15 berkas) |
| Emoji | 4 (`views/layouts/modals.php:37,41,240,243`) |
| Gradients | 0 |

## 2. Temuan

### A. Dua sistem ikon dalam satu layar
`renderSvgIcon()` (dipakai semua halaman) menggambar stroke **2px** dengan bentuk sendiri;
`icons.js` (kerangka) memakai stroke **1.6** dengan bentuk lain. Konsep yang sama
berbeda gambar di dua sisi halaman yang sama — `trash`, `settings`, `share`, `clock`,
`users`, `image`, `table`, `download`, `pencil`. Ini sumber rasa "generic AI icon set"
paling terasa: bukan bentuknya, tetapi ketidakkonsistenannya.

### B. Card sebagai jawaban layout default
Setiap blok halaman dibungkus `bg-white rounded-2xl border shadow-xs`: panel aktivitas
(`dashboard.php:106`), toolbar filter (`documents.php:34`), tabel (`document-table.php:7`),
kartu kosong (`empty-state.php:6`), setelan (`settings.php:18,50`), langganan
(`billing.php:18`), anggota (`members.php:22`). Card bersarang juga nyata: segmented
control `p-1 bg-slate-100 rounded-xl` di dalam kartu toolbar (`documents.php:76`), kotak
ikon di dalam baris (`document-table.php:49`), footer ber-border di dalam kartu dokumen
(`document-grid.php:81`). Dan satu card **per dokumen** di view grid (`document-grid.php:14`).

### C. Ikon di dalam kotak berwarna — lima ukuran, tiga radius
| Perekahan | Ukuran kotak | Radius | Hue |
|---|---|---|---|
| `stats-card.php:17` | 48px | `rounded-2xl` | indigo / emerald / amber / blue |
| `empty-state.php:7` | 56px | `rounded-2xl` | indigo (hardcoded) |
| `document-grid.php:17` | 48px | `rounded-xl` | hue per tipe berkas |
| `folder-card.php:18` | 40px | `rounded-xl` | 5 hue acak per folder |
| `document-table.php:49` | 32px | `rounded-lg` | hue per tipe berkas |

Pola yang sama digambar lima cara. Hue-nya tidak membawa makna: tipe berkas sudah
diketahui dari nama berkas dan kolom metadata.

### D. Ikon di samping setiap label teks
8 pasangan ikon+label di tiap dropdown baris (`document-table.php:105-128`,
`document-grid.php:36-59`, `folder-card.php:38-45`), 11 ikon `check` sebelum teks fitur
(`billing.php:70-72,108-111,142-144`), ikon `w-6 h-6` di dalam `h1` di 7 halaman
(`folders.php:32`, `recent.php:9`, `favorites.php:9`, `shared.php:9`, `trash.php:10`,
`members.php:9`, `settings.php:9`, `billing.php:9`), `chevron-right` setelah tautan yang
sudah bertuliskan "Lihat Semua Folder". Tidak satu pun menambah informasi.

### E. Skala tipografi tidak punya hierarki
Judul halaman `text-xl sm:text-2xl font-bold` (20→24px, bobot 700) di semua halaman.
Judul seksi tersebar di tiga ukuran dan semuanya UPPERCASE + `tracking-wider`: 14px
(`dashboard.php:63,81,105`), 12px (`folders.php:56,70`), 18px (`billing.php:22`).
Isi tabel `text-xs` (12px) dan metadata `text-[10px]/[11px]`. Body 12px membuat halaman
terasa seperti laporan padat, bukan alat kerja.

### F. Jarak dan radius liar
Lapisan konten mencampur 4px-aligned (8/12/16/20/24) dengan 2px-aligned (10/14) dan
arbitrer (`py-14`). Radius 6/8/12/16/9999 dipakai bergantian tanpa aturan; kartu
`rounded-2xl` (16px) adalah radius terbesar di aplikasi dan dipakai untuk sesuatu yang
statis.

### G. Bayangan dan animasi dekoratif
`shadow-xs` pada 42 elemen, `shadow-2xl` 14×, plus `btn-interactive` di `style.css:123`
yang memberi `transition` + `active:scale(.98)` ke semua tombol. `group-hover:scale-105`
pada kotak ikon statis (`stats-card.php:17`), `transition-all duration-300` pada bar
meter (`billing.php:41`). Animasi ada untuk rasa, bukan untuk status.

### H. Emoji pada kontrol produksi
`📁` di dalam `<option>` pemilih folder (`modals.php:37,41,240,243`).

### I. Tier z-index konflik
Komponen dropdown yang sama memakai `z-30` (`folder-card.php:36`, `document-grid.php:33`)
dan `z-40` (`document-table.php:102`). Sudah dikandaskan dengan menjadikan
`.ark-content` stacking context, tapi nilai di dalam view tetap perlu satu tier.

### J. Komposisi dashboard = analytics template
Selamat-datang banner + grid 3 kartu metrik (dokumen/folder/storage) + grid folder +
panel aktivitas. Untuk sistem manajemen dokumen, tiga hal pertama tidak menjawab
pertanyaan pengguna ("apa yang baru masuk, di mana letaknya, apa yang perlu saya lakukan").

## 3. Aturan target yang dipakai implementasi

| Aspek | Aturan |
|---|---|
| Ikon | Satu registri (`assets/js/icons.js`), stroke 1.75, warna `currentColor` netral. Ukuran: nav 18, toolbar/aksi tabel 16, empty state 24. Tanpa kotak berwarna. |
| Ikon berlabel | Hanya jika ikon mengidentifikasi objek (file/folder) atau kontrol ikon-only. Aksi yang punya label teks: tanpa ikon. |
| Card | Hanya untuk kelompok bermakna atau elemen interaktif mandiri. Radius 8, border `--ark-border-default`, **tanpa shadow**, padding 16–20. Tidak ada card di dalam card. |
| Seksi | Judul seksi 16/600 + jarak; pemisah berupa whitespace atau 1px `--ark-border-subtle`, bukan kotak. |
| Tipografi | Halaman 24/600 · seksi 16/600 · body 14/400 · sekunder 12–13/400 · angka metrik 24 tabular. |
| Jarak | Kelipatan 4px saja (2px hanya untuk hairline/offset optik kecil). |
| Tombol | Primer satu per layar (tinggi 36–40); sekunder/tertier 32–36; tanpa shadow, tanpa gradient, tanpa scale-on-press. |
| Tabel dokumen | Baris 40px, pemisah `--ark-border-subtle`, ikon berkas netral, metadata sekunder, aksi kontekstual ikon-only. |
| Badge | Hanya untuk status yang punya konsekuensi (share, favorite, role, peringatan kuota). Bukan untuk metadata biasa. |
| Motion | Hanya status (loading, open/close, hover). Tidak ada transform dekoratif. |
| Tier z | Satu nilai untuk menu baris: `--ark-z-dropdown` (20). |

## 4. Keputusan sadar yang menyimpang dari angka mentah brief

1. **Body 14px** — brief minta 14, lapisan Ark lama memakai 13 "enterprise density".
   Mengikuti brief: 14px default, metadata 12–13px, dan baris tabel tetap rapat (40px),
   sehingga densitas Linear dipertahankan lewat tinggi baris, bukan lewat ukuran huruf.
2. **Judul halaman 24/600** — Ark sebelumnya memaksakan plafon 20px. Diubah mengikuti
   brief; hierarki tetap bertingkat (24 → 16 → 14) dan tidak ada yang lebih besar
   kecuali angka metrik.
3. **Ikon bertipe berkas tetap ada, tanpa warna** — brief meminta ikon berkas netral,
   tetapi menghilangkan bentuk ikon sama sekali membuat PDF vs ZIP tidak terbaca cepat.
   Bentuk dipertahankan, kotak berwarna dan hue dihapus.
4. **View grid dokumen dipertahankan** — satu kartu per dokumen dihapus dari daftar
   (view tabel jadi utama), tetapi tile grid diberi border tunggal tanpa shadow,
   tanpa ikon besar, dan tanpa footer ber-border.
5. **Tier tombol 28/32/40, bukan 36** — brief menyebut 36–40 untuk primer dan 32–36
   untuk sekunder. Skala Ark tidak punya angka 36 dan menambahnya berarti menambah
   token di tengah skala 4px yang sudah bulat: primer naik ke 40 (`--lg`), sekunder
   tetap 32 (`--md`), toolbar tetap 28 (`--sm`). Pada head halaman yang juga memuat
   kolom pencarian, semua kontrol ditahan di 32 agar satu baris tidak berlapis.

## 5. Tahap implementasi

Ikon → token & tipografi → primitif seksi/list/meter → dashboard → komponen list/grid →
9 halaman → modals + emoji → JS runtime & Helpers badge → cabut Tailwind CDN → verifikasi
browser 1440/1024/768/390 + `php tests/run_tests.php` → dokumentasi.

---

## 6. Status eksekusi

Diperbarui tiap tahap selesai; angka §1 sengaja dibiarkan sebagai kondisi awal.

| Tahap | Status | Isi |
|---|---|---|
| Ikon | **Selesai** (2026-10-28) | Temuan A & C selesai untuk sisi registry: `assets/js/icons.js` satu-satunya sumber (49 bentuk + 8 alias), `src/Helpers.php` membacanya, dictionary 35 ikon dihapus. Stroke 1.75 seragam, ukuran nav 18 / aksi 16, kelas `w-N` tak lagi di-emit untuk ikon. Paritas server↔browser dijaga `tests/run_tests.php`. |
| Token & tipografi | **Selesai** (2026-10-28) | Temuan E, F. `tokens.css` §6–§12: bodi 14, judul halaman 24/600, seksi 16/600, baris 40px, `--ark-tile-min` 220, disiplin radius 6/8/12, dan §9 yang membatasi bayangan untuk lapisan melayang saja. `base.css` (`body`, `h1`, resep `.ark-title-*`) mengikuti. |
| Primitif datar | **Selesai** (2026-10-28) | Pengganti temuan B: `assets/css/components/page.css` — `.ark-page-head`, `.ark-section` (+`--ruled`), `.ark-list` (+`--inset`, `--dense`, `__row--link`), `.ark-meter`, `.ark-tile-grid`/`.ark-tile`. Semua datar: nol bayangan, ikon netral 16px, pemisah hairline. Kontrak di `docs/design-system.md` §5.16 + demo `#halaman` di pratinjau. |
| Dashboard | **Selesai** (2026-10-28) | Temuan J. `views/pages/dashboard.php` tidak lagi punya grid kartu statistik: `.ark-page-head` (Beranda + satu aksi primer), tabel dokumen terbaru, `.ark-tile-grid` pintasan folder, lalu `.ark-split` daftar aktivitas + satu `.ark-meter` kuota. Angka metrik dibaca dari tipografi, bukan dari kotak. |
| Komponen list/grid | **Selesai** (2026-10-28) | Temuan B, C, D. `document-table.php`, `document-grid.php`, `folder-card.php`, `empty-state.php` ditulis ulang di lapisan Ark; kotak ikon bewarna hilang (ikon garis netral 16px, hue hanya di badge); `stats-card.php` dihapus. Kontrak JS lama (`#select-all-docs`, `.doc-checkbox`, `.btn-action-*`, `.btn-toggle-favorite`, `#view-list-container`) tetap utuh. |
| 9 halaman isi | **Selesai** (2026-10-28) | Temuan D, G. Semua `views/pages/*.php` dirakit dari primitif datar: nol kelas `slate-*`/`indigo-*` di dalam `.ark-content__body` (dijaga pemeriksaan sweep 40 kombinasi halaman × viewport). Anggota jadi `.ark-table` dengan avatar inisial; Pengaturan & Langganan pakai pembatas lebar `.ark-measure` + primitif form baru (`.ark-form`, `.ark-field-grid`, `.ark-form-actions`, `.ark-select--fit`, resep `.ark-mono`); tiga kartu harga bergaris tebal diganti satu tabel perbandingan paket; breadcrumb hanya satu (di header). |
| Modals & emoji | Belum | Temuan H. |
| JS runtime & badge | Belum | Includes inline SVG di `app.js`/`preview.js`/`uploader.js` dan hue `getFileMeta()`. |
| Cabut Tailwind CDN | Belum | Syarat terima: nol rujukan `cdn.tailwindcss.com`. |
