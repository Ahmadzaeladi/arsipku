# ArsipKu — Audit Frontend & Rencana Migrasi ke HTML5 + CSS3 + Vanilla JS

Status dokumen: **audit historis** (dibuat 2026-10-28; bukan deskripsi lengkap kondisi terkini).
Pembaruan 2026-11-13: backend aktif memakai PDO MySQL; file SQLite lokal dan modul mock/fixture
TypeScript telah dihapus. Service TypeScript yang tersisa hanya memakai API PHP dan tidak menjadi
frontend aktif karena layout memuat Vanilla JS. Rekomendasi lama yang menyebut mock sebagai opsi
tidak berlaku lagi.

Target yang diminta (kontrak audit ini):

> Wajib: HTML5, CSS3, Vanilla JavaScript (ES6+).
> Dilarang: React, TypeScript, Tailwind CSS, Bootstrap, Vue, Angular, Next.js, framework komponen frontend apa pun.

Aturan yang dipatuhi selama audit: tidak menulis ulang aplikasi, tidak menghapus berkas, tidak mengubah logika bisnis backend, tidak memasang framework baru, tidak membuat abstraksi yang tidak perlu.

---

## 1. Struktur proyek yang ada

```
index.php                Front controller + router switch($_GET['page']) — 134 baris
views/
  layouts/app.php        437 baris — AppShell + Tailwind CDN + tailwind.config inline
  layouts/modals.php     620 baris — semua modal global
  components/*.php       5 komponen (document-table, document-grid, folder-card, stats-card, empty-state)
  pages/*.php            11 halaman
assets/
  css/style.css          153 baris — CSS tulisan tangan (satu-satunya)
  js/app.js              10.4 kb — modal, toast, drawer, sidebar, shortcut
  js/documents.js        24.1 kb — search/filter/multi-select/semua aksi dokumen
  js/uploader.js          9.6 kb — dropzone + XHR upload + queue
  js/preview.js           9.4 kb — viewer PDF/gambar/teks
api/*.php                5 endpoint, total 24 action (lihat §6)
src/                     Config, Database (PDO MySQL), Helpers, Services/*
frontend/                62 berkas .ts, 5 704 baris — lapisan TypeScript (lihat §2.3)
docs/api-contract.md     Kontrak 14 domain, netral-framework
frontend/node_modules     34 MB — @esbuild, esbuild, typescript
```

Total berkas non-`node_modules`: ±25 613 baris, di mana 5 704 baris (22%) adalah lapisan TypeScript yang saat ini **tidak terpakai oleh aplikasi yang berjalan**.

---

## 2. Framework, library, dan dependensi yang ditemukan

### 2.1 Yang melanggar daftar "dilarang"

| Temuan | Bukti | Dampak |
|---|---|---|
| **Tailwind CSS via Play CDN** | [views/layouts/app.php:41](file:///C:/Arsip%20digital/views/layouts/app.php#L41) `<script src="https://cdn.tailwindcss.com">` | JIT CSS dijalankan browser: ~120 kb JS, FOUC tiap muat ulang, butuh internet, dan Tailwind sendiri menandainya bukan untuk produksi. |
| **Tailwind theme dobel** | [views/layouts/app.php:43-69](file:///C:/Arsip%20digital/views/layouts/app.php#L43) (`tailwind.config`) vs [assets/css/style.css:6-16](file:///C:/Arsip%20digital/assets/css/style.css#L6) (`:root` var) | Dua sumber kebenaran untuk warna indigo/slate yang sama. Berubah di satu tempat = desain tidak konsisten. |
| **TypeScript sebagai bahasa sumber** | 62 berkas `frontend/**/*.ts`, `tsconfig.json`, devDep `typescript@5.6` | Dilarang eksplisit. Isinya justru lapisan bisnis yang paling bernilai (lihat §3), jadi kasus ini "ubah bahasa", bukan "buang kode". |
| **esbuild** | [frontend/build.mjs](file:///C:/Arsip%20digital/frontend/build.mjs) | Bukan framework UI, tapi jadi tidak perlu kalau sumbernya JS murni + `<script type="module">`. |

### 2.2 Yang aman / tidak melanggar

- **Tidak ada** React, Vue, Angular, Next.js, Bootstrap, jQuery, library chart, atau runtime JS eksternal lain. Nol dependensi runtime di `assets/js/`.
- Ikon = SVG inline dari `src/Helpers.php` (bukan library). Catatan: "fallback Lucide" yang disebut `project.md` §2 tidak ada implementasinya — tidak ada berkas/`import` Lucide di repo. Klaim dokumentasi itu salah.
- Google Fonts `Inter` via `@import` di [assets/css/style.css:3](file:///C:/Arsip%20digital/assets/css/style.css#L3) — render-blocking, perlu `<link>` + `display=swap` atau stack sistem.
- PHP native murni, tanpa framework (aturan ketat repo, §2 `project.md`) — **tetuh**, dan audit ini tidak mengusik bagian itu.

### 2.3 Dua sistem frontend berjalan paralel

| | Legacy vanilla JS | Lapisan TypeScript |
|---|---|---|
| Muat di aplikasi | **Ya** (`app.php:432-435`) | **Tidak** — tidak ada `<script>` yang merujuk `assets/js/dist/arsipku.js` |
| Scope | Global (`let selectedDocIds`, `function escapeHtml`) | Modul, 1 IIFE bundel |
| Render list | Tidak pernah; andalkan PHP + `location.reload()` | Bangun baris di client (`ui/documentList.ts`) |
| Transport | 20 blok `fetch()` tersalin | 1 klien (`lib/http.ts`) + retry + timeout + CSRF |
| Error | `catch → toast "Terjadi kesalahan jaringan"` | `ApiError` ber-`code`, event `api:error`/`auth:expired` |
| Mock | Tidak ada | Tidak ada; modul mock/fixture telah dihapus |

---

## 3. Logika bisnis & integrasi API yang layak dipertahankan

Ini **aset**, bukan hutang. Semua bersifat netral-framework; hanya bahasanya yang perlu diubah (TS → JS + JSDoc).

| Aset | Isi | Nilai saat migrasi |
|---|---|---|
| `frontend/lib/http.ts` | Header, timeout (GET 15s / tulis 30s / upload 120s), retry GET-only 2× backoff 400 ms, `credentials:'same-origin'`, baca CSRF cookie | Pembungkus `fetch()` tunggal pengganti 20 blok tersalin |
| `frontend/lib/errors.ts` | Peta status→`code`, `MESSAGE_HINTS` | Pesan error spesifik (413 kuota, 401 sesi) bukan "jaringan error" generik |
| `frontend/lib/endpoints.ts` | Peta 14 domain + status `live`/`planned` | Satu tempat untuk menandai endpoint yang belum ada |
| `frontend/lib/{format,normalize,prefs,workspaceContext,events}.ts` | `formatBytes`, `timeAgoIndo`, `escapeHtml`, pembacaan envelope, `localStorage` keys, event bus | `formatBytes`/`timeAgoIndo` hari ini juga ada di `src/Helpers.php` (PHP) → satu-satunya duplikasi yang **sah** (server vs client) |
| `frontend/services/*` (13 domain) | Mapping 1:1 ke 24 action `api/*.php` | Kontrak call-point; jangan ditulis ulang |
| Mock services | Dihapus | Frontend tidak mengganti error API dengan fixture atau state bisnis buatan |
| `frontend/hooks/reactive.ts` | `createStore`, `createResource` (dedupe + auto-refetch), `debounce` | Reaktif tanpa framework — ini justru yang bikin migrasi tidak perlu React |
| `docs/api-contract.md` | 14 domain: request/response/pagination/error | Acuan backend berikutnya, tidak terikat frontend |

**Duplikasi yang harus disatuhkan** (bukan abstraksi baru, cuma satu pemilik):

- `escapeHtml`: `assets/js/preview.js:200` **dan** `frontend/lib/format.ts:159` → dua implikasi berbeda; versi preview.js dipakai untuk menyisipkan nama berkas ke `innerHTML`.
- Toast: `app.js` (`showToast`) **dan** `hooks/notifications.ts` → dua DOM writer untuk container yang sama.
- State mode "pilih" (multi-select) hidup di `documents.js`, toolbar-nya dirender PHP; logika `select-all-docs` tidak punya sumber tunggal.

---

## 4. Komponen yang perlu ditulis ulang / dikonversi

| Komponen | Pekerjaan | Ukuran sebenarnya |
|---|---|---|
| Styling (Tailwind → CSS3) | Subset utilitas + varian, bukan tulis-ulang markup | **666** atribut `class`, **457** token utilitas unik, di 11 halaman + 5 komponen + 2 layout |
| Varian utilitas | Yang harus ikut diimplementasikan | `hover:` 129, `focus:` 21, `sm:` 40, `lg:` 17, `md:` 8, `group-hover` 4, `transition` 45, `animate` 1, nilai arbitrer `[...]` 13 (`h-[90vh]`, `z-[9999]`, `text-[10px]`, `max-w-[220px]`, `max-h-[90vh]`) |
| Kelas Tailwind yang di-hardcode di JS | Toggle view/active-state | 41 pemanggilan `classList.*`, 8 nama utilitas literal (`bg-white`, `text-indigo-600`, `shadow-xs`, `border-indigo-600`, `text-amber-500`, `text-slate-300/400/500`) — mis. [assets/js/documents.js:57-63](file:///C:/Arsip%20digital/assets/js/documents.js#L57) |
| Kelas Tailwind yang di-emit **backend** | `src/Helpers.php:80-131` palet badge (`bg-rose-50 text-rose-600 border-rose-200`), `141-145` ukuran ikon SVG via `$classes` | Tidak terlihat oleh grep di `views/`. Karena aturan "jangan ubah logika backend", CSS wajib menyediakan nama-nama ini |
| Search & filter | Ganti navigasi penuh dengan fetch + History API | `setupSearchAndFilters()` [documents.js:69-115](file:///C:/Arsip%20digital/assets/js/documents.js#L69) |
| Aksi dokumen (rename/move/delete/restore) | Ganti `location.reload()` dengan update DOM lokal + rollback | 10 titik reload |
| Render baris daftar | Pilih satu pemilik markup (server **atau** client) | `views/components/document-table.php` (140) vs `frontend/ui/documentList.ts` (514) |
| Modal | Aksesibilitas + fokus | `views/layouts/modals.php` (620 baris), `openModal/closeModal` di `app.js:98-101` |
| Upload queue | Pertahankan XHR (progress tidak bisa dari `fetch`), pindahkan ke klien API bersama | `uploader.js:218-219` |
| 62 berkas `.ts` | Konversi mekanis: buang `interface`/`type`/enum → objek const, pasang JSDoc | Tidak ada perubahan perilaku |

---

## 5. Dependensi yang tidak perlu

| Hapus / ganti | Alasan |
|---|---|
| `https://cdn.tailwindcss.com` | Dilarang eksplisit + runtime CSS-in-JS di browser, FOUC, tidak bisa offline |
| `tailwind.config` inline di `app.php` | Tema harus pindah ke `:root` CSS variable (satu sumber) |
| `typescript` + `esbuild` (34 MB `node_modules`) | Setelah sumber jadi `.js` + `<script type="module">`, tidak ada langkah build. Repo jadi nol-dependensi seperti backend |
| `assets/js/dist/arsipku.js` + `.map` (810 kb) | Tidak dirujuk view mana pun = aset mati; akan tergantikan oleh `assets/js/src/` |
| `@import` Google Fonts di CSS | Render-blocking; ganti `<link rel="preload">` atau font stack sistem |
| Klaim "fallback Lucide" di `project.md` §2 | Tidak ada kodenya; dokumentasi menyesatkan agen berikutnya |

Tidak ada dependensi runtime lain yang bisa dibuang — `assets/js/` hari ini sudah murni vanilla.

---

## 6. Status integrasi backend: **sudah terintegrasi, tapi tidak lengkap**

**Yang sudah nyata:**
- SSR penuh: `index.php` meroute `page=` → service PHP → view; list, breadcrumb, statistik dirender server.
- 5 endpoint AJAX, 24 action, semuanya dipanggil JS hari ini:
  - `api/documents.php` — `detail, rename, move, delete, restore, permanent_delete, empty_trash, toggle_favorite, multi_action`
  - `api/folders.php` — `create, rename, delete, all_flat, breadcrumbs`
  - `api/workspaces.php` — `current, switch, create, update, delete, change_plan`
  - `api/members.php` — `invite, update_role, remove`
  - `api/upload.php` — multipart, XHR, `FormData`
- Context workspace lewat **sesi PHP** (`$_SESSION['active_workspace']`), bukan parameter URL.

**Yang belum ada / bermasalah:**
1. **Autentikasi sudah ditambahkan sejak audit ini dibuat.** Akun diambil dari tabel MySQL `users`; login/logout memakai sesi PHP dan CSRF. Registrasi publik dan reset password belum tersedia. Lihat [`docs/api-contract.md`](api-contract.md) untuk kontrak yang berlaku.
2. **Tidak ada CSRF sama sekali.** `src/Config.php:11` hanya `session_start()`. Klien TS membaca cookie `XSRF-TOKEN` ([http.ts:315-318](file:///C:/Arsip%20digital/frontend/lib/http.ts#L315)) yang **tidak pernah dibuat backend** → header selalu kosong. Legacy JS tidak mengirimnya sama sekali.
3. **Dua model konteks bertabrakan:** legacy JS mengandalkan sesi (tidak pernah mengirim `?ws=`); lapisan TS mengirim `?ws=<slug>` di setiap request. Kalau keduanya aktif serentak, perpindahan workspace bisa berbeda antara yang dirender PHP dan yang diambil AJAX.
4. **8 domain tanpa backend:** download, versions, permissions, favorites (per-user), search, trash, billing, activities — ditandai `planned` di `frontend/lib/endpoints.ts` dan sudah dikontrak di `docs/api-contract.md`.
5. **Tidak ada otorisasi server-side per aksi** — endpoint memproses `id` apa pun dari klien. Ini risiko keamanan nyata, bukan cuma catatan arsitektur.

---

## 7. Masalah UI/UX (berbasis bukti)

1. **"Live search" adalah navigasi halaman penuh.** [documents.js:104](file:///C:/Arsip%20digital/assets/js/documents.js#L104) `window.location.href = url.toString()` setelah debounce 500 ms. Setiap jeda ketik = reload total: scroll hilang, seleksi hilang, sidebar collapse hilang, Tailwind CDN + Google Fonts diunduh ulang. Ini masalah terbesar sekaligus yang paling mudah dibuktikan.
2. **Muat ulang setelah setiap mutasi.** 10 titik `location.reload()` (documents.js 220, 248, 278, 400, 483, 534, 589, 620, 647; uploader.js 211), sebagian besar dibungkus `setTimeout(..., 400)` — jeda 400 ms itu memicu klik ganda dan terasa lemot, tanpa optimistic UI, tanpa spinner.
3. **Aksesibilitas praktis nol di markup server.** Di `views/` + `index.php` + `assets/`: `aria-*` **0**, `role=` **0**, `tabindex` **0**, `alt=` 5, `sr-only` 5, `<label>` 19 — untuk 132 `<button>` dan 30 `onclick` inline. Modal tidak `role="dialog"`/`aria-modal`; `app.js` fokus ke input pertama (baris 98-101) tapi **tidak ada focus trap**, jadi Tab lolos ke halaman di belakang dialog. `#select-all-docs` dan tombol ikon-only tanpa label yang bisa dibaca.
4. **Semua kegagalan terbaca sama.** Pola `catch → showToast('Terjadi kesalahan jaringan')` menelan status HTTP: 401 sesi kedaluwarsa, 403 bukan anggota, 413 berkas kebesaran, kuota habis, dan 500 semuanya tampil sebagai satu pesan jaringan yang menyesatkan.
5. **FOUC berulang.** Play CDN baru mengompilasi utilitas setelah JS jalan; karena aplikasi sering reload (butir 1 & 2), halaman putih berkedip tampak terus-menerus.
6. **Skala desain tidak terkendali.** 457 utilitas + 13 nilai arbitrer. `text-[10px]` dan `text-[11px]` dipakai untuk metadata (di bawah ambang 12 px yang nyaman dibaca), `z-[9999]` berarti tidak ada skala z-index → urutan tumpuk modal/toast/drawer bergantung keberuntungan, bukan aturan.
7. **Target sentuh kecil.** `py-1.5` (±28 px) dan `px-3 py-1` untuk tombol aksi tabel — di bawah pedoman 44 px, dan 8 `div.cursor-pointer` yang sebenarnya bukan tombol.
8. **Markup kembar server vs JS.** Baris dokumen dirender `document-table.php`/`document-grid.php`; preview, queue upload, dan toast menyusun `innerHTML` string di JS. Tidak ada satu pemilik template baris → konten dan gaya gampang melayang.
9. **Bahaya sanitasi.** `preview.js` punya `escapeHtml` lokal (baris 200) dan string HTML digabung lewat template literal; setiap sisipan baru mudah lupa di-escape. Nama berkas berasal dari unggahan pengguna = input tak tepercaya.
10. **Polutan global.** `selectedDocIds`, `escapeHtml`, `showToast`, `closeModal` dkk. di global scope, dimuat tanpa `defer`/modul — pengarang skrip berikutnya bisa menimpa tanpa sengaja.
11. **Font & gerak.** `@import` font menghambat render; `prefers-reduced-motion` dan `:focus-visible` sudah ditangani dengan benar di `style.css` (nilai positif yang **wajib** dipertahankan), tapi memakai `!important` yang nantinya bersaing dengan kelas komponen baru.
12. **Tanpa tema gelap** (`dark:` 0 pemakaian) dan tanpa status muat (skeleton) di halaman mana pun.

---

## 8. Strategi migrasi ke HTML + CSS + Vanilla JS

Prinsip: **setiap fase bisa di-rollback sendiri, tanpa menghapus berkas, tanpa mengubah endpoint.**

### Fase 0 — Baseline & kontrak (aman, tanpa risiko)
- Kunci tangkapan layar 11 halaman + modal (list, grid, folder, trash, members, settings, billing, dashboard, preview).
- Kunci daftar **62 DOM id** yang dirujuk JS (`getElementById` di legacy + TS) — inilah kontrak antara view PHP dan JS; bila satu berubah, fitur mati diam-diam.
- Kunci **daftar utilitas Tailwind yang benar-benar dipakai** (ekstraksi 457 token + kelas yang di-emit `src/Helpers.php`).
- Tidak ada berkas yang diubah selain dokumen ini.

### Fase 1 — Fondasi CSS3 tulisan tangan
`assets/css/tokens.css` (warna, skala ruang, radius, bayangan, z-index, tipografi) + `base.css` (reset, tipografi, scrollbar, `:focus-visible`, `prefers-reduced-motion`) + `components/*.css` (button, card, badge, input, modal, toast, drawer, table). Pindahkan `tailwind.config` ke variabel CSS → satu sumber kebenaran. Tidak ada markup yang tersentuh.

### Fase 2 — Cabut Tailwind dengan "utility subset" (kunci rendahnya risiko)
Alih-alih menulis ulang 666 atribut `class`, tulis satu berkas `assets/css/utilities.css` yang mengimplementasikan **persis** 457 token yang terpakai — termasuk varian `hover:/focus:/sm:/md:/lg:/group-hover` (129 + 21 + 40 + 8 + 17 + 4 pemakaian) dan nilai arbitrer yang terpakai, plus kelas yang di-emit `Helpers.php`.
- Ganti baris `<script src="https://cdn.tailwindcss.com">` + blok `tailwind.config` dengan `<link rel="stylesheet" href="assets/css/utilities.css">`, di belakang flag perbandingan (`?tw=cdn`) agar rollback cuma soal URL.
- Verifikasi: diff visual tiap halaman vs baseline.
- Fase ini saja sudah memenuhi "CSS3, tanpa Tailwind" tanpa menyentuh PHP view dan tanpa menyentuh backend.

### Fase 2b — Refactor semantik bertahap (opsional, kualitas jangka panjang)
Ganti utilitas dengan kelas komponen (`.btn`, `.card`, `.badge--danger`) per berkas, dimulai dari 4 template paling sering dipakai (`app.php`, `modals.php`, `document-table.php`, `documents.php`). `utilities.css` dibiarkan hidup selama transisi supaya tidak ada halaman yang rusak di tengah jalan. Ini yang menghapus 457 token — bukan Fase 2.

### Fase 3 — Vanilla JS ES6+ modular (bahasa: JavaScript, bukan TypeScript)
- Pindahkan logika yang dipertahankan ke `assets/js/src/` sebagai `.js`: `core/` (config, http, errors, events, format, prefs), `api/` (13 service), `mock/`, `state/` (store/resource/debounce), `ui/` (list, modal, upload, preview), `main.js` sebagai satu `<script type="module">`.
- Konversi 62 `.ts`: buang `interface`/`type`, ganti `enum` dengan objek const beku, tambahkan `/** @typedef */` + JSDoc di fungsi publik. **Perilaku tidak diubah**, hanya sintaks.
- Legacy `app.js/documents.js/uploader.js/preview.js` tetap di tempat dan pensiun satu per satu, tiap modul baru mengambil alih DOM id-nya lewat flag yang sudah ada (semangat `?ui=ts`, diganti `?ui=esm`). Tidak ada berkas yang dihapus.
- Batasan yang harus diputuskan: `<script type="module">` butuh HTTP (CORS menolak modul lewat `file://`). Kalau demo statis `file://` masih dibutuhkan, satu-satunya pilihan tanpa bundler adalah tetap memakai skrip klasik non-modular.

### Fase 4 — Perbaikan UX yang butuh lapisan baru
Search/filter via `fetch` + `history.replaceState` (tanpa reload), mutasi optimistis dengan rollback saat gagal, status muat (skeleton) dan status error yang benar-benar berbeda per kode, `role="dialog"` + `aria-modal` + focus trap + restore focus di modal, `aria-label` untuk tombol ikon, skala z-index dan target sentuh 44 px, satu template baris untuk server & client.

### Fase 5 — Bersih-bersih & dokumentasi
Setelah tak ada yang merujuk: arsipkan `frontend/` (jangan hapus tanpa persetujuan), buang `assets/js/dist/`, kosongkan `node_modules`, koreksi `project.md` §2 (Tailwind "palette", klaim Lucide) dan §8 (lapisan TS), serta sinkronkan `docs/api-contract.md` §14 bila flag berubah.

---

## 9. Struktur folder frontend yang diusulkan

```
assets/
├── css/
│   ├── tokens.css          # warna, skala, radius, shadow, z-index, tipografi, motion
│   ├── base.css            # reset, elemen dasar, :focus-visible, reduced-motion, scrollbar
│   ├── utilities.css       # FASE 2: subset utilitas pengganti Tailwind (auto-terverifikasi vs baseline)
│   ├── components/         # .btn .card .badge .input .modal .toast .drawer .table .empty-state
│   │   ├── buttons.css
│   │   ├── cards.css
│   │   ├── forms.css
│   │   ├── modal.css
│   │   ├── table.css
│   │   └── toast.css
│   └── style.css           # tetap: isi lama pindah, berkas tidak dihapus
├── js/
│   ├── src/
│   │   ├── main.js         # satu entry <script type="module">
│   │   ├── core/           # config.js http.js errors.js events.js format.js prefs.js workspace.js
│   │   ├── api/            # documents.js folders.js workspaces.js members.js upload.js
│   │   │                 # + trash.js favorites.js search.js versions.js permissions.js
│   │   │                 #   subscription.js activities.js auth.js  (1:1 dgn docs/api-contract.md)
│   │   ├── mock/           # store.js fixtures.js index.js  (UI jalan tanpa backend)
│   │   ├── state/          # store.js resource.js debounce.js
│   │   ├── ui/             # dom.js list.js modals.js forms.js upload.js preview.js meters.js
│   │   └── templates/      # documentRow.js dialog.js  (satu pemilik markup baris)
│   ├── app.js documents.js uploader.js preview.js   # legacy, bertahan sampai tiap modul mengambil alih
│   └── dist/              # kosong setelah Fase 5 (tidak dihapus tanpa persetujuan)
```

Aturan yang membuat struktur ini tetap ramping: **tidak ada lapisan tambahan** di luar `core → api → state → ui`; `mock/` hanya dipakai lewat flag; satu folder `ui/` saja (tanpa "containers", tanpa "presenters", tanpa hooks tiruan React).

---

## 10. Risiko yang bisa mematahkan fungsi yang ada

| # | Risiko | Keparahan | Mitigasi |
|---|---|---|---|
| 1 | Utilitas Tailwind yang luput dari subset → elemen "hilang gaya" diam-diam (bukan error) | Tinggi | Subset dihasilkan dari daftar 457 token **plus** `src/Helpers.php`; diff visual 11 halaman per fase; flag `?tw=cdn` untuk membandingkan |
| 2 | Varian `hover:/focus:/group-hover/sm:` tidak ikut diimplementasikan → interaksi lenyap | Tinggi | 129/21/4/40 pemakaian dihitung & dicentang satu-satu; jangan cuma menyalakan kelas state |
| 3 | JS memuat/membuang nama utilitas lewat `classList` (41 titik) → toggle view/active mati saat kelas diganti | Tinggi | Pertahankan alias utilitas lama sampai Fase 3; lalu pindah ke kelas state (`.is-active`) |
| 4 | `src/Helpers.php` meng-emit kelas utilitas, dan aturan melarang mengubah backend → CSS wajib cocok dengan PHP | Tinggi | Perlakukan nama kelas PHP sebagai kontrak; subset CSS wajib memuat `bg-rose-50 text-rose-600 border-rose-200` dll. |
| 5 | `<script type="module">` mati saat dibuka lewat `file://` (CORS) → demo statis hilang | Sedang | Putuskan di awal: butuh `file://` (pakai skrip klasik) atau cukup `php -S` (pakai modul) |
| 6 | Kehilangan jaminan tipe saat TS → JS → bug bentuk data tak tertangkap | Sedang | JSDoc + `@typedef` di `api/` dan `core/`, `normalize.js` tetap memfilter field tak dikenal, uji envelope tiap action |
| 7 | Dua model konteks (`$_SESSION['active_workspace']` vs `?ws=`) aktif serentak → data halaman dan AJAX beda workspace | Tinggi | Satu sumber saja: sesi sebagai otoritas; `?ws=` hanya untuk transisi; legacy JS jangan diberi `ws=` |
| 8 | Menggabungkan render client (Fase 4) dengan render server → baris dobel atau id hilang; 62 DOM id adalah kontrak | Tinggi | Per halaman, jangan serentak; id baru memakai prefix (`arsipku-*`) supaya tidak bentrok |
| 9 | Otorisasi server belum ada → memindahkan render ke client menggandakan permukaan `id` | Tinggi | Tetap butuh pemeriksaan anggota di server (docs/api-contract.md §0); jangan klaim migrasi frontend memperbaiki keamanan |
| 10 | `escapeHtml` dua implikasi beda perilaku → XSS saat salah satu dipakai | Sedang | Satu utilitas escape; audit semua titik `innerHTML` di preview.js/uploadQueue sebelum dipindah |
| 11 | Regresi tak teruji: tidak ada test JS satu pun, `tests/run_tests.php` cuma routing+service PHP | Tinggi | Baseline visual + checklist manual 24 action per fase; jangan mulai Fase 3 tanpa itu |
| 12 | Menghapus `node_modules`/`frontend/` ikut menghilangkan mock & klien teruji | Sedang | Arsipkan, jangan hapus; pindahkan isinya dulu ke `assets/js/src/` |
| 13 | `@import` font pindah ke `<link>` tapi Inter tidak tersedia offline → metric geser | Rendah | Font stack sistem + `size-adjust`, atau self-host |
| 14 | Aturan repo (PHP native, tanpa framework) mudah tercampur dengan aturan frontend saat implementasi | Rendah | `project.md` §2 dipisah menjadi "aturan backend" dan "aturan frontend" |

---

## 11. Keputusan yang menunggu persetujuan

1. **Nasib `frontend/*.ts`:** (a) konversi ke ES modules + JSDoc di `assets/js/src/` lalu `frontend/` diarsipkan — *disarankan*, karena klien API/mock/reactive-nya yang termahal untuk ditulis ulang; atau (b) `frontend/` tetap tak tersentuh dan vanilla JS ditulis ulang dari nol — duplikasi, dua kontrak API berbeda.
2. **Tailwind:** Fase 2 (utility subset, risiko rendah, 457 token tetap hidup) atau langsung Fase 2b (kelas semantik, markup bersih, risiko tinggi). Disarankan 2 lalu 2b.
3. **Modul vs klasik:** apakah demo `file://` masih syarat? Menentukan Fase 3.
4. **Pemilik markup daftar:** tetap SSR + reload, atau render client + History API? Menentukan besar kecilnya Fase 4.
5. **Flag eksplorasi (`?tw=cdn`, `?ui=esm`, `?mock=1`) boleh hidup sementara di `app.php`?** Ini satu-satunya sentuhan yang mendekati view PHP — murni `<script>`/`<link>`, tanpa menyentuh logika.

**Status 2026-10-28.** Satu tindakan dari daftar ini sudah disetujui dan dikerjakan — bukan nomor 1–5, tapi migrasi yang menunggu di atasnya: `views/layouts/app.php` dipindahkan ke kerangka Ark (`tokens.css` → `base.css` → 10 komponen → `icons.js` + `app-shell.js`), tanpa mengubah endpoint, view halaman, `modals.php`, maupun logika bisnis. Empat kontrak lama (`#global-search`, `.ws-switch-item` + `data-ws-slug`, `openModal(...)`, `#toast-container`/`showToast`) dipertahankan di dalam markup Ark supaya `app.js` tetap berkuasa atas perilaku — rinciannya di `docs/design-system.md` §5.15, status lengkapnya di `project.md` §10.3–§10.4. Kelima keputusan di atas masih terbuka; pencabutan Tailwind CDN (nomor 2) baru boleh terjadi setelah isi halaman ikut pindah.

---

## 12. Fungsi yang wajib tetap berjalan (checklist verifikasi pasca-migrasi)

Frontend only — endpoint & view PHP tidak boleh berubah bentuk. Baris bertanda **[kerangka ✓]** sudah diverifikasi ulang sejak migrasi `views/layouts/app.php` ke Ark (2026-10-28); sisanya masih mengacu markup lama dan belum dicentang.

- [ ] Router `index.php` 11 halaman (`dashboard, documents, folders, recent, favorites, shared, trash, members, settings, billing`) — termasuk filter lewat query (`q, type, date_range, sort, folder_id`) dan halaman trash dengan hitungan retensi
- [ ] **[kerangka ✓]** Sidebar collapse + preferensi `localStorage['arsipku_sidebar_collapsed']` — kini dimiliki `app-shell.js` (rail 72px); cabang lama `app.js` tidak aktif karena `#btn-toggle-sidebar` sudah tidak ada
- [ ] **[kerangka ✓]** Drawer mobile + overlay — `.ark-drawer-scrim` + `body.ark-drawer-lock`, fokus masuk ke nav
- [ ] Toggle list/grid + `localStorage['arsipku_doc_view']` (kelas aktif: `bg-white text-indigo-600 shadow-xs`)
- [ ] `Ctrl+K` / `/` fokus ke `#global-search`; `Escape` menutup modal/dropdown; klik luar menutup dropdown
- [ ] Toast (container + animasi `toastIn/toastOut`, tipe success/error/warning)
- [ ] Modal: create/rename folder, rename dokumen, move, hapus, hapus permanen, `#modal-empty-trash`, `#modal-delete-workspace` (konfirmasi ketik nama), `#modal-create-workspace`, `#modal-preview`
- [ ] Multi-select: `#select-all-docs`, toolbar `#multi-select-bar` (Pindahkan / Hapus / Batalkan / Restore / Hapus permanen)
- [ ] 24 action AJAX di §6, dengan pesan sukses dari `message` server
- [ ] Upload: dropzone (`dragover` → `.dropzone.dragover`), queue multi-file, progress bar XHR nyata, `uploads/` + `_1/_2` retensi nama, validasi ekstensi
- [ ] Preview: PDF `iframe`, gambar, teks/kode `<pre>`, kartu fallback untuk xlsx/zip, panel metadata
- [ ] **[kerangka ✓]** Indikator storage (meter di sidebar + billing + settings) dari `usage_percentage`/`is_warning` — footer Ark merender `.ark-usage`/`.ark-progress` dari data yang sama. Dua catatan turunannya: `frontend/ui/meters.ts` masih mencari `.sidebar-storage-box` yang tidak lagi ada (meter TS di sidebar inert; billing/settings tidak terpengaruh), dan karena `.ark-content` kini stacking context (`z-index: 0`) semua overlay harus dirender di luar kontainer itu — `#modal-invite-member` sudah dipindah dari `views/pages/members.php` ke `views/layouts/modals.php` untuk itu.
- [ ] Favorit toggle bintang (tanpa reload penuh), indikator `is_shared`/`shared_permission`
- [ ] Aksesibilitas minimum yang sudah ada jangan hilang: `lang="id"`, `:focus-visible`, `prefers-reduced-motion`, `sr-only`, `<label>`
- [ ] Mock mode tetap bisa menjalankan UI penuh tanpa backend
