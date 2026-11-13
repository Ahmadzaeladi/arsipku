# DOKUMENTASI PROYEK: ARSIPKU (SAAS DIGITAL ARCHIVE MANAGEMENT SYSTEM)

Dokumen ini disusun untuk mempermudah AI Agent atau pengembang berikutnya dalam memahami arsitektur, struktur kode, skema database, status fitur, dan instruksi pengembangan lanjutan pada aplikasi **ArsipKu**.

---

## 1. IKHTISAR PRODUK & KONSEP MULTI-TENANT

**ArsipKu** adalah SaaS Digital Archive Management System yang dirancang untuk menyimpan, mengelola, mencari, mempratinjau, dan membagikan dokumen digital secara aman bagi individu, perusahaan, sekolah, dan organisasi.

### Konsep Multi-Tenant:
- Setiap pengguna memiliki akun dan dapat menjadi anggota di beberapa workspace.
- Data setiap workspace (dokumen, folder, anggota, kuota penyimpanan, dan konfigurasi) terisolasi secara ketat (`workspace_id`).
- Pengguna dapat berpindah antar-workspace secara instan tanpa perlu login ulang melalui **Workspace Switcher** pada sidebar.
- Contoh workspace bawaan (seeded):
  1. `pt-abc-solusindo` (Perusahaan, 50 GB)
  2. `personal-ahmad` (Personal, 10 GB)
  3. `sekolah-harapan-bangsa` (Institusi Pendidikan, 25 GB)

---

## 2. ATURAN ARSITEKTUR & TECH STACK

> **PENTING / STRICT RULE:**
> Aplikasi ini dibangun menggunakan **PHP Native MURNI** tanpa menggunakan framework apa pun (Tanpa Laravel, Tanpa Symfony, Tanpa CodeIgniter). Jangan memasang atau mengintroduksi framework PHP ke dalam repositori ini.

- **Aturan sumber data:** data workspace, dokumen, folder, anggota, statistik, dan aktivitas harus
  berasal dari MySQL/MariaDB melalui PHP/PDO. Kegagalan koneksi atau endpoint tidak boleh diganti
  data buatan/fixture/SQLite. `localStorage` hanya menyimpan preferensi UI dan konteks workspace,
  bukan data bisnis. Berkas fisik tetap berada di `uploads/`, dengan metadata di MySQL.
- **Autentikasi:** akun berada di tabel MySQL `users`; login memverifikasi hash password dan
  menyimpan identitas sesi PHP. Halaman aplikasi dan API bisnis mewajibkan sesi aktif. Tidak ada
  profil sintetis dari konfigurasi/environment atau registrasi publik.
- **Backend Runtime:** PHP 8.x Native (OOP Services, PDO MySQL, Fileinfo, JSON).
- **Database:** MySQL / MariaDB (`arsipku`) yang diinisialisasi otomatis saat pertama kali aplikasi dijalankan (`Database::getConnection()`). Skema mandiri juga tersedia di `database.sql`.
- **Styling & UI:** Tailwind CSS (Modern SaaS Indigo Palette) + Custom CSS (`assets/css/style.css`).
- **Desain UI/UX:** Terinspirasi dari kesederhanaan Linear, Notion, dan Google Drive.
- **Ikonografi:** SVG inline teroptimasi berkecepatan tinggi dengan fallback Lucide.
- **Frontend Interactivity (legacy, vanilla):** Pure Vanilla JavaScript modular tanpa library eksternal berat:
  - `assets/js/app.js`: Manajemen modal, toast notification, dropdown, drawer mobile, shortcut keyboard (`Ctrl+K`).
  - `assets/js/documents.js`: Live search, filter berkas, sorting, toggle tabel/grid, multi-select toolbar, aksi dokumen.
  - `assets/js/uploader.js`: Drag-and-drop dropzone, upload progress live, antrean multi-berkas.
  - `assets/js/preview.js`: Viewer PDF, gambar, kode/teks, dan fallback card dokumen.
- **Frontend Layer TypeScript (opsional, lihat §8):** sumber TypeScript `frontend/` dapat dibundel dengan esbuild. Layout aktif saat ini memakai Vanilla JS di `assets/js/`; bundel TypeScript tidak dimuat otomatis. Semua service TypeScript yang dibangun hanya menggunakan API PHP, tanpa mock atau fixture.
- **Design System + Kerangka Aplikasi Ark (baru, lihat §10):** CSS tangan tiga lapis ber-token `--ark-*` + Vanilla JS ES5-compat tanpa framework, tanpa Tailwind, tanpa langkah build. Semua `<link>`/`<script>` biasa; layer ini hidup berdampingan dengan `style.css` selama masa adopsi. **Sejak migrasi `views/layouts/app.php`, kerangka Ark (sidebar, drawer, header, kontainer) dipakai di seluruh halaman aplikasi, sedangkan isi halaman masih Tailwind.**

---

## 3. STRUKTUR DIREKTORI

```
c:\Arsip digital\
├── assets/
│   ├── css/
│   │   ├── style.css            # Styling kustom lama, scrollbar, animasi modal & toast
│   │   ├── tokens.css           # Design system layer 1/3 - hanya variabel --ark-*
│   │   ├── base.css             # Design system layer 2/3 - reset + resep tipografi
│   │   └── components/          # Design system layer 3/3 - 16 berkas, satu per komponen
│   │                            #   (buttons forms menu table badge tabs modal toast
│   │                            #    tooltip empty-state loader breadcrumb page
│   │                            #    + shell sidebar header)
│   └── js/
│       ├── app.js               # Core app UI lama: modals, drawer, toast, keyboard shortcuts
│       ├── documents.js         # Filter, sorting, list/grid toggle, multi-select, actions
│       ├── uploader.js          # Drag & drop upload handler, live progress, queue
│       ├── preview.js           # Preview modal controller (PDF, images, text, fallback)
│       ├── icons.js             # Registry ikon tunggal `window.ArkIcons`; dibaca juga oleh src/Helpers.php
│       ├── app-shell.js         # Kerangka aplikasi ark: nav aktif, drawer, rail, dropdown, Ctrl+K
│       ├── design-system.js     # Peraga design system (menu, modal, toast di halaman pratinjau)
│       └── dist/
│           └── arsipku.js       # Hasil bundel TypeScript (§8) + arsipku.js.map
├── docs/
│   ├── api-contract.md          # Kontrak API yang diharapkan frontend (12 domain + activities)
│   ├── design-system.md         # Token, tipografi, ruang, kontrak komponen, peta adopsi (§10)
│   ├── design-system.html       # Halaman pratinjau komponen + state (statis, tanpa build)
│   ├── app-shell.html           # Kerangka aplikasi berjalan (sidebar + header + kontainer konten)
│   └── frontend-audit.md        # Audit stack frontend + rencana migrasi HTML/CSS/Vanilla JS (menunggu persetujuan)
├── frontend/                    # Layer TypeScript (lihat §8) - tanpa framework UI
│   ├── main.ts                  # Entry bundel -> assets/js/dist/arsipku.js
│   ├── build.mjs                # esbuild (iife, target es2020, sourcemap)
│   ├── package.json             # scripts: typecheck / build / build:watch / check
│   ├── tsconfig.json
│   ├── types/                   # Model TS per domain (api, document, workspace, ...)
│   ├── lib/                     # config, endpoints, http, errors, events, format,
│   │                            #   normalize, prefs, workspaceContext
│   ├── services/                # service domain yang seluruhnya memakai API HTTP PHP
│   ├── hooks/                   # reactive primitives + useDocuments, useTrash, ...
│   └── ui/                      # bootstrap, documentList, forms, uploadQueue, meters, overlays
├── .env                         # Konfigurasi database MySQL (DB_USER, DB_PASS, dll)
├── database.sql                 # Skema lengkap & initial seeder MySQL
├── uploads/                     # Direktori penyimpanan file fisik & sample demo
├── src/
│   ├── Config.php               # Konfigurasi aplikasi, path upload, batas upload & loader .env
│   ├── Database.php             # PDO Connection & MySQL Migration/Seed logic
│   ├── Helpers.php              # Format bytes, tanggal Indonesia, badge Hue, ikon SVG (baca icons.js), sanitisasi
│   └── Services/
│       ├── WorkspaceService.php # Pengelolaan workspace & statistik storage
│       ├── DocumentService.php  # Operasi CRUD dokumen, filter, search, riwayat aktivitas
│       ├── FolderService.php    # Hierarki folder, subfolder, dan breadcrumbs
│       └── MemberService.php    # Pengelolaan anggota tim & peran hak akses
├── api/
│   ├── documents.php            # Endpoint AJAX dokumen (search, rename, move, star, delete)
│   ├── upload.php               # Endpoint upload berkas multipart (penyimpanan fisik + DB)
│   ├── folders.php              # Endpoint folder (create, rename, delete)
│   ├── workspaces.php           # Endpoint workspace switcher & update
│   └── members.php              # Endpoint undang anggota & role
├── views/
│   ├── layouts/
│   │   ├── app.php              # Master AppShell Ark: sidebar, drawer, header, breadcrumb, kontainer isi
│   │   └── modals.php           # Modals global (Upload, Rename, Folder, Preview, Delete) — masih Tailwind
│   ├── components/
│   │   ├── document-table.php   # Daftar dokumen sebagai .ark-table (baris 40px, ikon netral)
│   │   ├── document-grid.php    # Varian kisi daftar dokumen sebagai .ark-tile-grid
│   │   ├── folder-card.php      # Folder sebagai .ark-tile + .ark-menu tindakan
│   │   └── empty-state.php      # .ark-empty; aksi lewat $emptyAction (array), bukan HTML mentah
│   └── pages/
│       ├── dashboard.php        # Beranda datar: kepala halaman, dokumen terbaru, tile folder, aktivitas, meter
│       ├── documents.php        # Semua Dokumen: page-head + .ark-toolbar + daftar/kisi
│       ├── folders.php          # Folder: tile subfolder + tabel isi (jalur ada di breadcrumb header)
│       ├── recent.php           # Halaman Dokumen Terbaru
│       ├── favorites.php        # Halaman Dokumen Favorit
│       ├── shared.php           # Halaman Dibagikan kepada Saya
│       ├── trash.php            # Halaman Sampah (.ark-notice retensi + restore/hapus permanen)
│       ├── members.php          # Anggota: page-head + .ark-table peran/status (avatar inisial)
│       ├── settings.php         # Pengaturan: .ark-measure + .ark-form + .ark-notice--danger
│       └── billing.php          # Paket & penyimpanan: langganan, .ark-meter, tabel perbandingan paket
├── tests/
│   └── run_tests.php            # Automated test suite (18 pengujian routing & service)
├── index.php                    # Front Controller & Native Router
├── project.md                   # Dokumentasi panduan arsitektur & AI agent handoff
└── README.md                    # Petunjuk instalasi & menjalankan aplikasi
```

---

## 4. SKEMA DATABASE MYSQL

Database menggunakan MySQL / MariaDB dengan nama default `arsipku` (dapat disesuaikan di `.env` / `src/Config.php`). Database dan tabel dibuat secara otomatis jika belum ada:

### 1. `workspaces`
- `id` (TEXT, PK): Identifier workspace (contoh: `ws_abc`).
- `name` (TEXT): Nama workspace.
- `slug` (TEXT, UNIQUE): Slug URL (contoh: `pt-abc-solusindo`).
- `type` (TEXT): Tipe workspace (`personal`, `company`, `school`).
- `storage_limit_bytes` (INTEGER): Batas kuota penyimpanan dalam bytes (contoh: 53687091200 untuk 50 GB).
- `plan_name` (TEXT): Nama paket langganan.
- `created_at` (DATETIME).

### 2. `folders`
- `id` (TEXT, PK): Identifier folder (contoh: `fld_abc_adm`).
- `workspace_id` (TEXT, FK -> workspaces.id).
- `parent_id` (TEXT, NULLABLE): Mendukung hierarki subfolder tak terbatas.
- `name` (TEXT): Nama folder.
- `color` (TEXT): Label warna (`indigo`, `emerald`, `blue`, `amber`, `purple`).
- `icon` (TEXT): Ikon folder.
- `created_at` (DATETIME), `updated_at` (DATETIME), `deleted_at` (DATETIME, NULLABLE).

### 3. `documents`
- `id` (TEXT, PK): Identifier dokumen (contoh: `doc_abc_01`).
- `workspace_id` (TEXT, FK -> workspaces.id).
- `folder_id` (TEXT, NULLABLE): Menunjuk ke folder penempatan (`NULL` = Arsip Utama).
- `name` (TEXT): Nama tampilan berkas (contoh: `Surat_Perjanjian_Kerjasama_2026.pdf`).
- `original_name` (TEXT): Nama berkas asli saat diunggah.
- `mime_type` (TEXT): MIME type (contoh: `application/pdf`).
- `extension` (TEXT): Ekstensi berkas huruf kecil (contoh: `pdf`, `png`, `xlsx`).
- `size_bytes` (INTEGER): Ukuran dalam bytes.
- `storage_path` (TEXT): Nama berkas fisik di dalam direktori `uploads/`.
- `uploaded_by` (TEXT): ID pengguna yang mengunggah.
- `owner_name` (TEXT): Nama lengkap pemilik berkas.
- `is_favorite` (INTEGER, 0/1): Status favorit pengguna aktif.
- `is_shared` (INTEGER, 0/1): Status berkas dibagikan.
- `shared_permission` (TEXT): Tingkat hak akses (`view`, `edit`).
- `created_at` (DATETIME), `updated_at` (DATETIME), `deleted_at` (DATETIME, NULLABLE untuk soft-delete ke Sampah).

### 4. `members`
- `id` (TEXT, PK): Identifier anggota.
- `workspace_id` (TEXT, FK -> workspaces.id).
- `name` (TEXT), `email` (TEXT), `avatar` (TEXT).
- `role` (TEXT): `Owner`, `Admin`, `Editor`, `Viewer`.
- `status` (TEXT): `active`, `invited`.
- `joined_at` (DATETIME).

### 4a. `member_invitations` dan `workspace_invite_links`
- Undangan email berbasis akun terdaftar disimpan terpisah sampai penerima menerima.
- Share link menyimpan hash token, role, aturan persetujuan, masa berlaku, kuota, dan status pencabutan.
- Permintaan dari link dengan persetujuan Owner/Admin tetap tidak mendapat akses sebelum disetujui.
- Instalasi database yang sudah ada perlu menjalankan `migrations/20261113_member_invitations.sql`.

### 5. `activities`
- `id` (TEXT, PK): Identifier aktivitas.
- `workspace_id` (TEXT, FK -> workspaces.id).
- `user_name` (TEXT), `user_avatar` (TEXT).
- `action_type` (TEXT): `upload`, `rename`, `folder_create`, `delete`, `share`, `restore`.
- `description` (TEXT): Catatan kronologis aktivitas.
- `created_at` (DATETIME).

---

## 5. STATUS FITUR (PHASE 1 & PHASE 2 COMPLETE, PHASE 3 FRONTEND LAYER SIAP INTEGRASI)

| Fitur | Status | Catatan Implementasi |
|---|---|---|
| **Design System** | Selesai & Teruji | Tailwind CSS palette (Indigo/Slate), Tipografi Inter, Komponen Reusable, Rounded corners, Soft shadows, `:focus-visible` ring aksesibel. |
| **AppShell & Navigation** | Selesai & Teruji | Desktop sidebar (collapsible mode), Mobile drawer responsive, Breadcrumb trail, Search bar global (`Ctrl+K` & Enter redirect). |
| **Workspace Switcher** | Selesai & Teruji | Multi-tenant switching via dropdown, sesi dipertahankan via `$_SESSION['active_workspace']`, dan modal `+ Buat Workspace Baru` (`#modal-create-workspace`). |
| **Dashboard** | Selesai & Teruji | Sapaan ramah pengguna, 3 ringkasan metrik (Total Dokumen, Jumlah Folder, Pemakaian Storage yang terhubung langsung ke halaman terkait), Akses Cepat Folder, Tabel Dokumen Terbaru, Feed Aktivitas. |
| **Semua Dokumen** | Selesai & Teruji | Tampilan Tabel & Grid (dengan toggle & memori `localStorage`), Live Search, Filter Tipe Berkas, Filter Tanggal, Filter Sorting, Multi-select contextual toolbar (Pindahkan, Hapus, Batalkan). |
| **Manajemen Folder** | Selesai & Teruji | Struktur hierarki subfolder, Breadcrumbs dinamis, Modal Buat Folder, Modal Ubah Nama Folder (`#modal-rename-folder`), Modal Pindahkan Dokumen ke Folder lain. |
| **Upload Dokumen** | Selesai & Teruji | Modal dialog drag & drop, multi-file queue, progress bar interaktif, proteksi ekstensi, penyimpanan file fisik nyata di `uploads/`, auto-retensi nama asli. |
| **Ubah Nama (Rename)** | Selesai & Teruji | Modal dialog pre-filled nama, proteksi ekstensi file agar tidak terhapus, validasi karakter, update database seketika. |
| **Pratinjau Dokumen (Preview)** | Selesai & Teruji | Modal viewer untuk PDF (`<iframe>`), Gambar (`<img>`), Kode/Teks (`<pre>`), Fallback Card informatif untuk file Excel/Word/ZIP, Panel Metadata berkas lengkap. |
| **Favorit, Terbaru & Dibagikan** | Selesai & Teruji | Toggling bintang favorit via AJAX, sorting kronologis berkas terbaru, indikator hak akses berkas dibagikan. |
| **Tempat Sampah (Trash)** | Selesai & Teruji | Soft-delete berkas, Pulihkan dokumen (Restore), Hapus Permanen, Tombol *Kosongkan Tempat Sampah* (`#modal-empty-trash`), Multi-restore & Multi-permanent-delete. |
| **Anggota & Peran** | Selesai & Teruji | Daftar anggota, dropdown ubah peran anggota langsung (`Admin`, `Editor`, `Viewer`), modal undang anggota baru via AJAX. |
| **Pengaturan & Paket** | Selesai & Teruji | Ubah nama & tipe workspace, dialog hapus workspace aman dengan konfirmasi ketik nama (`#modal-delete-workspace`), tombol pemilihan dan aktivasi paket langganan secara interaktif. |
| **Layer Frontend TypeScript (§8)** | Opsional, bukan frontend aktif | Service memakai API PHP saja. Modul mock/fixture dan bundle lama sudah dihapus; layout aktif memakai Vanilla JS. |
| **Kontrak API 12 domain** | Terdokumentasi | `docs/api-contract.md` - endpoint yang diharapkan, request/response, pagination, format error; backend belum diasumsikan ada. |
| **Design System (§10)** | Selesai & Terverifikasi · dipakai layout | Token `--ark-*` + 3 lapis CSS tangan + 16 kontrak komponen + halaman pratinjau `docs/design-system.html`; tanpa Tailwind, tanpa framework UI, tanpa build. Dimuat oleh `views/layouts/app.php`. Referensi: `docs/design-system.md`. |
| **Kerangka Aplikasi Ark / App Shell (§10.4)** | Selesai & Terverifikasi · **sudah dipakai layout** | Sidebar 248px (rail 72px, drawer < 1024px), header compact 56px dengan breadcrumb + pencarian global + dropdown akun, kontainer konten `.ark-content`. CSS: `shell.css`, `sidebar.css`, `header.css`; JS: `icons.js`, `app-shell.js`. Pratinjau: `docs/app-shell.html`. Migrasi `views/layouts/app.php` sudah disetujui (`docs/frontend-audit.md` §11) dan sudah dikerjakan; **kesembilan `views/pages/*` sudah Ark**, yang masih Tailwind hanya `views/layouts/modals.php` dan markup yang dihasilkan JS. |
| **Refase frontend "dewasa" (Fase 3)** | Berjalan | Laporan audit: `docs/design-refactor-audit.md` (status per tahap di §6). Tahap selesai: **satu registry ikon** (`assets/js/icons.js` dibaca `src/Helpers.php`, stroke 1.75, nav 18 / aksi 16, ikon tidak lagi memakai kelas `w-N`; dijaga 7 assert `tests/run_tests.php`), **token & tipografi** (bodi 14, judul halaman 24/600, seksi 16/600, baris 40px, bayangan hanya untuk lapisan melayang), **primitif komposisi datar** (`components/page.css`: page head, seksi + hairline, list, meter, tile, toolbar, chip, notice, split — nol bayangan, ikon netral), dan **seluruh halaman isi** (`document-table.php`, `document-grid.php`, `folder-card.php`, `empty-state.php` semuanya Ark; `stats-card.php` dihapus; Beranda/Dokumen/Folder/Terbaru/Favorit/Dibagikan/Sampah/Anggota/Pengaturan/Langganan dirakit dari primitif itu, tanpa kelas `slate-*`/`indigo-*` di dalam `.ark-content`). Breadcrumb hanya satu: di header, memakai `$breadcrumbs` saat berada di subfolder. Anggota jadi `.ark-table` dengan avatar inisial; Pengaturan dan Langganan memakai pembatas lebar `.ark-measure` + primitif form baru (`.ark-form`, `.ark-field-grid`, `.ark-form-actions`, `.ark-select--fit`), dan tiga kartu harga diganti satu tabel perbandingan paket. Tahap berikutnya: modal + emoji, badge `getFileMeta()` & markup hasil JS, pencabutan Tailwind CDN. |

---

## 6. CARA MENJALANKAN & MENGUJI APLIKASI

### Menjalankan Server Lokal:
Pastikan Anda berada di direktori project `c:\Arsip digital`, lalu jalankan server bawaan PHP:
```powershell
```
Buka browser dan akses:
```
http://localhost:8000
```

### Memeriksa Lapisan Frontend TypeScript (opsional):
```powershell
cd frontend
npm install
npm run typecheck
```
Source TypeScript tidak dimuat oleh layout aplikasi saat ini. Jika adapter TypeScript dipakai
nantinya, request domain hanya boleh menuju endpoint PHP yang membaca/menulis MySQL; endpoint
yang belum tersedia harus gagal eksplisit, bukan mengisi UI dengan data buatan.

### Menjalankan Pratinjau Design System:
Halaman komponen berdiri sendiri (tidak menyentuh router maupun basis data):
```
http://localhost:8000/docs/design-system.html
```
Berkas ini statis - hanya memuat `assets/css/tokens.css`, `base.css`, 16 berkas `assets/css/components/*.css`, `assets/js/icons.js`, dan `assets/js/design-system.js`. Tidak ada CDN, tidak ada langkah build. Aturan dan kontrak tiap komponen ada di `docs/design-system.md`.

### Menjalankan Pratinjau Kerangka Aplikasi:
```
http://localhost:8000/docs/app-shell.html
```
Kerangka aplikasi lengkap (sidebar + workspace switcher + header + kontainer konten) dengan konten placeholder. Sama-sama statis: memuat `tokens.css`, `base.css`, komponen yang dipakai, lalu `assets/js/icons.js` + `assets/js/app-shell.js`.

- `?page=favorites` (atau `documents`, `folders`, `recent`, `shared`, `trash`, `members`, `settings`, `billing`, `dashboard`) memindah state navigasi aktif - di aplikasi nyata nilai ini datang dari `$page` yang dirender server sebagai `aria-current="page"`.
- Lebar jendela menentukan mode: `>= 1280px` inline + tombol rail, `1024-1279px` inline, `< 1024px` drawer off-canvas dengan scrim, `< 768px` header compact (breadcrumb diganti judul halaman, aksi jadi ikon saja).
- `Ctrl+K` / `Cmd+K` memusatkan kolom pencarian; `Escape` menutup dropdown lalu drawer.
- Perhatian: halaman pratinjau ini tidak memuat `assets/js/app.js`, jadi state rail (`localStorage['arsipku_sidebar_collapsed']`) tidak bentrok dengan skrip lama. Di aplikasi keduanya memang dimuat bersamaan, dan itu aman karena cabang sidebar/drawer `app.js` sudah tidak menemukan elemen penjaganya.

### Kerangka Ark di Aplikasi:
`http://localhost:8000` sekarang memakai kerangka yang sama lewat `views/layouts/app.php`: data workspace, statistik penyimpanan, daftar navigasi, dan menu dropdown diambil dari backend asli, bukan placeholder. Kolom pencarian global, pergantian workspace, tombol Unggah, dan toast masih dikendalikan controller lama (`app.js`) - lihat tabel jembatan di `docs/design-system.md` §5.15.

### Menjalankan Automated Test Suite:
Jalankan script pengujian:
```powershell
php tests/run_tests.php
```
*Hasil yang diharapkan: Seluruh 18 pengujian routing dan database service berstatus `[PASS]`.*

### Memeriksa Sintaks PHP:
```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
*Hasil yang diharapkan: `No syntax errors detected` di seluruh berkas.*

---

## 7. CATATAN EVALUASI & PENYEMPURNAAN UI (10 ASPEK)

1. **Konsistensi Spacing, Typography, Warna, & Border:**
   - Standarisasi ukuran judul halaman (`text-xl sm:text-2xl font-bold tracking-tight`), subjudul (`text-xs sm:text-sm text-slate-500`), judul seksi (`text-xs font-bold text-slate-400 uppercase tracking-wider`).
   - Border radius seragam: `rounded-2xl` untuk card/kontainer, `rounded-xl` untuk tombol/input.
2. **Hierarki Visual:**
   - Kartu metrik di Dashboard kini clickable menuju halaman targetnya (`url('documents')`, `url('folders')`, `url('billing')`).
3. **Navigasi & Pencarian:**
   - Input `#global-search` di header kini langsung me-redirect pengguna ke `page=documents&q=...` saat tombol `Enter` ditekan, serta melakukan auto-sync saat pengguna sudah berada di halaman dokumen.
4. **Responsivitas:**
   - Tabel responsif menyembunyikan kolom sekunder di layar kecil tanpa memotong kolom utama atau menu aksi.
   - Floating action toolbar menyesuaikan padding dan lebar pada layar mobile.
5. **State (Empty, Loading, Error):**
   - Halaman dokumen memiliki empty state khusus pencarian dengan tombol reset filter jika kata kunci tidak ditemukan.
   - Preview modal memiliki animasi loading spinner terpusat dan error fallback.
6. **Aksesibilitas & Focus State:**
   - Menambahkan `:focus-visible` global dengan ring kontras indigo (`outline: 2px solid #4F46E5`), input focus ring `rgba(79, 70, 229, 0.18)`, dan penanganan preferensi `prefers-reduced-motion`.
7. **Interaksi Dropdown, Dialog, Toast:**
   - Outside click dismiss otomatis menutup dropdown terbuka.
   - Shortcut `Escape` menutup modal dan dropdown.
   - Modal rename folder (`#modal-rename-folder`), modal buat workspace (`#modal-create-workspace`), modal kosongkan sampah (`#modal-empty-trash`), dan modal hapus workspace (`#modal-delete-workspace`).
8. **Duplikasi Komponen:**
   - Menyatukan logika multi-action dokumen untuk move, delete, restore, dan permanent delete dalam endpoint `api/documents.php`.
9. **Konsistensi Data Antarhalaman:**
   - Kuota penyimpanan dan jumlah dokumen selalu sinkron antara Dashboard, Sidebar storage widget, Halaman Pengaturan, dan Halaman Billing.
10. **Tombol dengan Perilaku Nyata:**
    - Semua tombol statis sebelumnya kini memiliki aksi nyata: ganti paket di billing, ubah peran anggota tim di members, kosongkan sampah, ubah nama folder, buat workspace baru, dan tanda baca notifikasi.

---

## 8. LAYER FRONTEND TYPESCRIPT & KONTRAK API (PERSIAPAN INTEGRASI BACKEND)

`frontend/` adalah lapisan TypeScript framework-agnostic yang disiapkan agar UI bisa dipindah ke backend apa pun (Laravel + PostgreSQL, atau PHP native yang ada sekarang) tanpa menulis ulang komponen. Aturan ketat proyek tetap berlaku: **tidak ada framework PHP** dan **tidak ada framework UI** (bukan React/Vue/Next). Satu-satunya dependensi build adalah `esbuild` + `typescript` (devDependencies).

### 8.1 Alur lapisan

```
types/  ->  lib/  ->  services/  ->  hooks/  ->  ui/  ->  main.ts
```

- **`types/`** - model TS murni (tanpa runtime): envelope `api.ts`, `document`, `workspace`, `folder`, `member`, `auth`, `upload`, `version`, `permission`, `trash`, `favorite`, `search`, `subscription`, `activity`.
- **`lib/`** - `config.ts` (API, workspace, dan timeout), `endpoints.ts` (peta file `api/*.php` + status `live`/`planned`), `http.ts` (transport: header, CSRF, timeout, retry GET-only), `errors.ts` (envelope → `ApiError`), `events.ts` (bus), `format.ts`, `normalize.ts`, `prefs.ts`, `workspaceContext.ts`.
- **`services/`** - service domain yang seluruhnya memakai HTTP ke API PHP. Tidak ada fallback in-memory; kegagalan endpoint diteruskan sebagai error.
- **`hooks/`** - `reactive.ts` (`createStore`, `createResource` dengan deduplikasi request + auto-refetch saat `data:invalidated`, `debounce`) dan hook per domain (`useDocuments`, `useFolders`, `useTrash`, `useUploadQueue`, `useSearch`, `useFavorites`, `useMembers`, `useWorkspace`, `useStorage`, `useSession`, `useActivities`).
- **`ui/`** - adapter yang menempel pada DOM yang sudah dirender view PHP (`documentList`, `forms`, `uploadQueue`, `meters`, `overlays`) dan `bootstrap.ts` yang menerbitkan `window.ArsipKu`.

### 8.2 Build dan flag runtime

```powershell
cd frontend
npm install
npm run check          # typecheck + build -> assets/js/dist/arsipku.js
npm run build:watch    # rebuild otomatis
```

Build TypeScript menghasilkan satu `<script>` klasik. **Status saat ini: `views/layouts/app.php` tidak memuat bundel TypeScript** (layout memuat `icons.js`, `app-shell.js`, `app.js`, `documents.js`, `uploader.js`, `preview.js`). Jika adapter TS dipakai di lingkungan pengembangan, bundel harus dibangun dan dimuat secara eksplisit; request tetap menuju API PHP.

```php
<script src="assets/js/app.js"></script>
<?php if (($_GET['ui'] ?? '') === 'ts'): ?>
  <script src="assets/js/dist/arsipku.js"></script>
<?php else: ?>
  <script src="assets/js/documents.js"></script>
  <script src="assets/js/uploader.js"></script>
<?php endif; ?>
<script src="assets/js/preview.js"></script>
```

`app.js` (modal/toast/dropdown/shortcut) tetap dipakai; adapter TS hanya menambahkan dirinya di atas DOM yang sama. UI TS **tidak** mengambil alih secara otomatis:

| Flag | Efek |
|---|---|
| `?ui=ts`, `window.__ARSIPKU__.ui = 'ts'`, atau `<body data-arsipku-ui="ts">` | TS adapter mengambil alih list/grid, modal, upload queue, meter storage. Jangan muat `documents.js`/`uploader.js` bersamaan (double-binding). |
| `?ws=<slug>` | Konteks workspace aktif (transport, bukan otorisasi). |
| `window.ArsipKu.{ hooks, takeover, destroy }` | Inspeksi/debug dari console. |

### 8.3 Status endpoint (jangan berasumsi endpoint sudah ada)

`lib/endpoints.ts` membagi 14 domain menjadi:

- **`live`** (sudah ada di PHP native hari ini): `auth.php` (CSRF, session, login, registrasi email, logout), `workspaces.php`, `members.php` (undangan email, notifikasi, share link, persetujuan), `documents.php`, `folders.php`, `upload.php`.
- **`planned`** (belum tersedia; request ke domain ini akan gagal dan tidak memakai data pengganti): `download.php`, `versions.php`, `permissions.php`, `favorites.php`, `search.php`, `trash.php`, `billing.php`, `activities.php`.

Bentuk request/response/pagination/error untuk seluruh domain - termasuk yang `planned` - didokumentasikan di **[`docs/api-contract.md`](docs/api-contract.md)**. Kontrak itu yang menjadi acuan saat backend Laravel/PostgreSQL atau PHP native tahap berikutnya dibangun; client sudah menangani envelope `{success, message, data, meta, links, code, errors}` dan beberapa kunci payload warisan (`documents`, `folders`, `members`, `workspaces`, `stats`, `uploaded`, `is_favorite`, `count`).

### 8.4 Aturan otorisasi yang dipegang frontend

- Setiap request ber-`?ws=<slug>` hanya sebagai **konteks transport**; client tidak pernah memutuskan hak akses dan tidak menyembunyikan data berdasarkan peran.
- Server wajib memverifikasi keanggotaan workspace pada setiap aksi - termasuk `workspaces.php?action=switch` - dan membalas `403 FORBIDDEN` bila bukan anggota; frontend menampilkannya sebagai pesan error, bukan mencoba ulang.
- Sesi kedaluwarsa (`401`) memicu `auth:expired` → notifikasi "Sesi berakhir" dan menghentikan retry.

### 8.5 Catatan audit (2026-10-28)

`docs/frontend-audit.md` mengaudit lapisan ini terhadap syarat stack baru (HTML5 + CSS3 + Vanilla JS, tanpa Tailwind/TypeScript) dan menemukan tiga hal yang perlu diketahui sebelum melanjutkan:

1. Bundel `assets/js/dist/arsipku.js` **belum dirujuk view mana pun** (lihat §8.2), jadi aplikasi yang berjalan hari ini masih 100% vanilla JS + Tailwind CDN untuk konten halaman.
2. Tailwind CDN masih aktif di `views/layouts/app.php:84` dan kelas utilitas masih di-emit dari `src/Helpers.php` untuk **badge tipe berkas** (`getFileMeta()` → `bg-rose-50 …`) — CSS pengganti wajib menyertakan nama kelas itu. Ikon sudah tidak lagi: `renderSvgIcon()` kini mengeluarkan `class="ark-icon ark-icon--NN"` yang didefinisikan `assets/css/base.css`, bukan kelas `w-N h-N` Tailwind. Kerangka (sidebar/header/drawer/kontainer) sudah pindah ke Ark; CDN ini masih dibutuhkan isi halaman dan `modals.php`.
3. Keputusan §11 audit **sudah disetujui sebagian**: migrasi `views/layouts/app.php` ke kerangka Ark sudah dikerjakan dan terverifikasi (lihat §10.3–§10.4). Migrasi isi halaman (`views/pages/*`, `views/partials/*`, `modals.php`) dan pencabutan Tailwind CDN belum dilakukan dan tetap menunggu keputusan per halaman.

---

## 9. PANDUAN PENGEMBANGAN LANJUTAN (NEXT AGENT ROADMAP)

Bagi AI Agent atau pengembang yang akan melanjutkan proyek ke **Phase 3 - 5**:

1. **Autentikasi lanjutan**:
   - Login MySQL dan sesi sudah tersedia; registrasi publik, pemulihan password, serta manajemen profil masih belum tersedia.
   - Akun awal dibuat melalui `reset_data.php`, yang sengaja menghapus data lama. Password hanya dikirim melalui environment `ARSIPKU_RESET_PASSWORD`.
2. **Abstraksi Cloud Object Storage (S3 / R2 / GCS)**:
   - Berkas fisik saat ini disimpan di folder `uploads/` melalui `api/upload.php`.
   - Pengembang dapat membuat `src/Services/StorageDriverInterface.php` agar dapat dengan mudah beralih dari local disk ke Amazon S3 atau Cloudflare R2 tanpa mengubah controller.
3. **Riwayat Versi Dokumen (Version Control)**:
   - Tambahkan tabel `document_versions` (`id`, `document_id`, `version_number`, `size_bytes`, `storage_path`, `created_by`, `created_at`) untuk mencatat setiap kali dokumen dengan nama sama diunggah ulang.
4. **Export & Unduh Folder sebagai ZIP**:
   - Manfaatkan modul PHP `ZipArchive` bawaan untuk memungkinkan pengguna mengunduh seluruh isi folder dalam satu file arsip ZIP secara instan.
5. **Implementasikan endpoint `planned` sesuai kontrak**:
   - Urutan yang disarankan ada di `docs/api-contract.md` §15: `download` (ticket bertanda tangan, kedaluwarsa ≤ 5 menit) → `favorites` → `versions` → `billing` → `permissions`/`trash` → `search`/`activities`.
   - Setelah sebuah file `api/*.php` tersedia, cukup ubah `status` domain tersebut menjadi `'live'` di `frontend/lib/endpoints.ts`; tidak ada perubahan pada service, hook, atau UI.

---

## 10. DESIGN SYSTEM (LAPISAN VISUAL BARU, BELUM DIPAKAI VIEW)

Jawaban untuk syarat "HTML5 + CSS3 + Vanilla JS, tanpa Tailwind/Bootstrap/React/TypeScript": lapisan visual ditulis sebagai CSS tangan tiga lapis dengan token `--ark-*`, bukan sebagai framework. Detail lengkap: **[`docs/design-system.md`](docs/design-system.md)**; pratinjau interaktif: **[`docs/design-system.html`](docs/design-system.html)**.

### 10.1 Lapisan dan urutan muat

```
assets/css/tokens.css  ->  assets/css/base.css  ->  assets/css/components/*.css
```

`tokens.css` hanya variabel (`:root` + `@media (pointer: coarse)`), `base.css` reset + resep tipografi per peran + 5 helper layout, `components/` 16 berkas satu-per-komponen: `buttons forms menu table badge tabs modal toast tooltip empty-state loader breadcrumb page` + `shell sidebar header`. Prefix `--ark-`/`.ark-` dipilih agar tidak bertabrakan dengan `--color-*` dan kelas lama di `assets/css/style.css` — keduanya bisa dimuat bersamaan selama masa adopsi. Tidak ada `@import`, tidak ada langkah build.

Di atas tiga lapis CSS itu ada satu lapis JS Vanilla yang ikut ditulis tangan:

```
assets/js/icons.js   ->  assets/js/app-shell.js   (produksi)
assets/js/icons.js   ->  assets/js/design-system.js   (halaman pratinjau)
```

`icons.js` adalah registry ikon tunggal — **49 bentuk (`PATHS`) + 8 nama lama (`ALIAS`) + daftar ikon solid (`FILLED`)** — yang mengubah `<span data-icon="nama">` menjadi inline SVG 24×24, stroke `currentColor`, `stroke-width: 1.75`. Dictionary 35 ikon yang dulu hidup di `src/Helpers.php` sudah dihapus: `arkIconRegistry()` membaca berkas JS ini (regex atas tiga blok, dikache statis per request) dan `arkIconSvg()` menghasilkan byte yang sama persis dengan `ArkIcons.markup()`; `tests/run_tests.php` menjaga paritas itu plus memastikan tidak ada view yang minta nama tak terdaftar. Ukuran diatur lewat token `--ark-icon-size` per komponen dan kelas `.ark-icon--NN` (12/14/16/18/20/24/28/32) untuk ikon yang di-emit PHP — `renderSvgIcon()` memetakan kelas warisan `w-3…w-8` ke kelas itu dan meneruskan kelas lainnya. Kontrak lengkap: `docs/design-system.md` §4.

### 10.2 Isi

- **Token**: 9 surface, 9 text (dengan rasio kontras terdokumentasi), 6 border, keluarga aksen/danger/success/warning (fill/hover/press/subtle/border), 6 hue tipe berkas yang **disamakan dengan `src/Helpers.php::getFileMeta()`**, skala ruang 4px, radius, elevasi, z-index, motion, dimensi layout/kontrol (`--ark-sidebar-w` 248 / `--ark-sidebar-w-collapsed` 72 / `--ark-header-h` 56).
- **Tipografi**: baseline UI 14px, lantai 11px, judul halaman 24/600 sebagai puncak halaman isi, seksi 16/600, metadata 12–13px; angka metrik juga 24 dengan `tabular-nums`. Peran diekspos sebagai kelas (`.ark-title-page`, `.ark-label-caps`, `.ark-meta`, …) agar view PHP tidak perlu mengganti tag.
- **Komponen**: 16 kontrak dengan markup + matriks state. State dibaca dari atribut/ARIA (`[aria-busy]`, `[aria-invalid]`, `[aria-selected]`, `[aria-expanded]`, `[disabled]`); kelas state hanya `.is-selected`, `.is-dragover`, `.is-open`, `.is-animating`, `.ark-* .is-active`.
- **Aksesibilitas**: kontras WCAG 2.2 AA dengan dua deviasi yang sengaja didokumentasikan (`--ark-text-tertiary` 2.9:1 untuk non-esensial, `--ark-border-default` 1.3:1 karena border bukan satu-satunya penanda), ring `:focus-visible` global, target sentuh 44px via `pointer: coarse`, `prefers-reduced-motion`, live region toast.

### 10.3 Status integrasi

Sejak migrasi layout, `views/layouts/app.php` memuat `tokens.css` → `base.css` → komponen yang dipakai kerangka dan halaman isi (`buttons forms menu badge breadcrumb tooltip loader tabs table empty-state shell page sidebar header`) → `icons.js` → `app-shell.js`, **sebelum** blok skrip lama. `assets/js/design-system.js` tetap hanya peraga di halaman pratinjau dan **tidak** dimuat view. Yang dipakai produksi hanyalah `icons.js` + `app-shell.js` + CSS. Pratinjau diverifikasi di Chrome headless per seksi dan per viewport: tidak ada error konsol, tidak ada luber horizontal, semua ikon ter-hydrate. Peta adopsi Tailwind → Ark (termasuk kelas yang di-emit `src/Helpers.php`) ada di `docs/design-system.md` §8; **kerangka dan kesembilan halaman isi (`views/pages/*`) sudah dipindahkan; `views/layouts/modals.php`, markup yang dihasilkan JS (`app.js`, `preview.js`, `uploader.js`, `#multi-select-bar`) dan badge `getFileMeta()` belum** - itu langkah berikutnya, lalu Tailwind CDN dicabut. Verifikasi halaman × viewport (1440/1024/768/390, 40 kombinasi) dijalankan lewat Chrome DevTools Protocol: nol luber horizontal di luar wadah tabel, nol ikon tak ter-hydrate, nol kelas Tailwind di dalam `.ark-content__body`.

### 10.4 Kerangka Aplikasi (App Shell)

Layout utama aplikasi — sidebar, header, kontainer konten — dibangun di atas design system dan berjalan sebagai satu halaman statis: **[`docs/app-shell.html`](docs/app-shell.html)**, kontraknya di `docs/design-system.md` §5.13–§5.15.

| Area | Berkas | Isi |
|---|---|---|
| Layout tiga area + drawer | `components/shell.css` | `.ark-shell` memegang satu properti lokal `--ark-side-w` yang dibaca sidebar (`width`) dan kolom utama (`margin-left`), sehingga mode rail dan mode drawer tidak saling merebut. `.ark-content` (maks `--ark-content-max`) dan `.ark-panel` untuk isi halaman. |
| Navigasi | `components/sidebar.css` | Workspace switcher (`#ws-menu`), grup "Arsip" (Beranda, Semua Dokumen, Folder, Terbaru, Favorit, Dibagikan, Sampah) dan "Workspace" (Anggota, Pengaturan, Langganan), pemakai storage di footer. |
| Bar alat | `components/header.css` | Breadcrumb, judul compact di ponsel, pencarian global dengan hint `Ctrl K`, aksi kontekstual, dropdown akun. `z-index: calc(var(--ark-z-sticky) + 1)` — diperlukan karena kepala tabel yang di-stick memakai z-index sama dan letaknya setelah header di DOM. |
| Perilaku | `js/app-shell.js` | IIFE tanpa dependensi: state nav aktif, dropdown workspace/akun, toggle drawer + scrim + `body.ark-drawer-lock`, mode rail, pencarian mobile, `Ctrl/Cmd+K`, `Escape`. |

Aturan yang dijaga: **sidebar 248px**, **header compact 56px**, navigasi netral tanpa latar berwarna (state aktif = `--ark-surface-selected` + teks primer + bobot 500), radius kontrol 6px, tanpa dekorasi berlebih. Pada pratinjau statis area konten hanya placeholder; di aplikasi `.ark-content` sudah membungkus view Ark.

**Tumpukan**: `.ark-content` adalah stacking context (`position: relative; z-index: 0` di `components/shell.css`), jadi `z-index` di dalam view — termasuk `.dropdown-menu` Tailwind `z-40` — tidak bisa menutupi header (11) atau sidebar (31). Gantiannya: overlay tingkat halaman wajib dirender di luar `.ark-content`. `#toast-container`, `#multi-select-bar` dan seluruh modal di `views/layouts/modals.php` sudah begitu; `#modal-invite-member` yang semula ikut dirender `views/pages/members.php` sudah dipindah ke `modals.php` (modal #13) agar tidak terjebak di bawah chrome.

**Status nav dimiliki server**: `views/layouts/app.php` merender `aria-current="page"` dari `$page` dan menaruh nilai yang sama di `data-shell-page`; `app-shell.js` hanya mencerminkannya dan tidak pernah memvalidasi hak akses. Migrasi layout **sudah disetujui dan dikerjakan** (`docs/frontend-audit.md` §11) dengan empat jembatan lama yang sengaja dipertahankan - `#global-search`, `.ws-switch-item` + `data-ws-slug`, `onclick="openModal(...)"`, `#toast-container`/`showToast` — semuanya dijelaskan di `docs/design-system.md` §5.15. Sisa pekerjaan: `modals.php` (+ emoji yang masih dipakai di sana), markup yang dihasilkan JS, badge `getFileMeta()`, dan `frontend/ui/meters.ts` yang masih mencari `.sidebar-storage-box` (sekarang inert karena footer Ark memakai `.ark-usage`/`.ark-progress`).
