# ArsipKu Design System

Referensi resmi lapisan visual aplikasi: token, tipografi, ruang, kontrak komponen, arsitektur CSS, dan peta adopsi dari Tailwind. Halaman pratinjau interaktifnya ada di [`docs/design-system.html`](design-system.html); berkas ini menjelaskan **aturan** di balik halaman itu.

Status: **aktif sebagai lapisan baru**. Tidak ada satu pun view PHP yang memakainya sekarang. Pemindahan halaman aplikasi ke lapisan ini menunggu persetujuan (lihat [`docs/frontend-audit.md`](frontend-audit.md) §11).

Stack terkunci: HTML5 + CSS3 + vanilla JS (ES6). Tidak ada Tailwind, Bootstrap, React, Vue, TypeScript, atau langkah build pada lapisan ini.

---

## 1. Prinsip

| Prinsip | Aturan operasional |
|---|---|
| Netral dulu, warna berarti | Putih + abu-abu mengerjakan ±95% tampilan. Satu aksen indigo (`#4F46E5`) hanya untuk aksi utama dan state terpilih. Merah/kuning/hijau hanya untuk status. |
| Konten adalah hierarki | Nama berkas dan tanggal memimpin. Judul halaman maksimum 20px; 24px khusus angka metrik. Densitas baris 44px. |
| State lewat atribut | `[aria-busy]`, `[aria-invalid]`, `[aria-selected]`, `[aria-expanded]`, `[disabled]`. Kelas state hanya untuk hal yang tidak bisa diwakili ARIA: `.is-selected`, `.is-dragover`, `.is-open`, `.is-animating`, `.is-leaving`, `.is-active`. |
| Kedalaman nyaris nol | Hierarki dikerjakan border 1px + ruang. Bayangan dipakai hanya oleh lapis yang benar-benar melayang: menu, modal, toast, card melayang. Tidak ada gradien, tidak ada blur dekoratif. |
| Satu sumber nilai | Komponen tidak menulis hex, px, atau ms literal. Nilai baru = token baru di `tokens.css`. |

---

## 2. Arsitektur CSS

```
assets/css/
├── tokens.css          Layer 1/3  — hanya variabel (:root + @media pointer)
├── base.css            Layer 2/3  — reset, default elemen, resep tipografi, 5 helper layout
└── components/         Layer 3/3  — 16 berkas, satu komponen per berkas
    ├── buttons.css     ├── menu.css      ├── modal.css     ├── loader.css
    ├── forms.css       ├── table.css     ├── toast.css     ├── breadcrumb.css
    ├── tabs.css        ├── badge.css     ├── tooltip.css   ├── empty-state.css
    ├── shell.css       ├── sidebar.css   ├── header.css
    └── page.css
```

Tiga berkas terakhir adalah **kerangka aplikasi** (§5.13–5.15): `shell.css` memegang tata letak + breakpoint, `sidebar.css` dan `header.css` hanya mengurus isinya.

Sisi JS sama tipisnya:

```
assets/js/
├── icons.js           satu-satunya peta bentuk ikon (window.ArkIcons)
├── app-shell.js       perilaku kerangka aplikasi — siap dipakai view
└── design-system.js   perilaku halaman pratinjau — khusus docs/, bukan view
```

**Urutan muat wajib** `tokens.css → base.css → components/*.css`, semuanya `<link>` biasa (tanpa `@import`, tanpa build):

```php
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/components/buttons.css">
<!-- ... dst, satu baris per komponen yang dipakai halaman -->
```

Aturan lapisan:

- `tokens.css` tidak punya selektor selain `:root` dan `@media (pointer: coarse)`.
- `base.css` tidak mengenal nama komponen mana pun, kecuali 4 override ring fokus (`.ark-input`, `.ark-select`, `.ark-search__field`, `.ark-check__input`).
- `components/*.css` hanya memakai `var(--ark-*)`; boleh membaca `--ark-icon-size` dan `--ark-progress-value` yang diset pemakai lewat atribut `style`.
- Prefix `--ark-` dan `.ark-` dipilih agar tidak bertabrakan dengan `--color-*` dan kelas lama di `assets/css/style.css` — kedua sistem bisa dimuat bersamaan selama masa adopsi.
- Penamaan blok/elemen/modifier: `ark-blok`, `ark-blok__elemen`, `ark-blok--modifier`, `ark-blok__elemen--modifier`.

**Menambah komponen**: satu berkas baru di `components/`, contoh markup + seluruh state di `docs/design-system.html`, kontrak di §5 berkas ini. Tidak ada komponen yang didefinisikan di `base.css`.

---

## 3. Token

### 3.1 Warna

**Permukaan**

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--ark-surface-canvas` | `#FFFFFF` | card, panel, modal, input |
| `--ark-surface-app` | `#F8FAFC` | latar halaman |
| `--ark-surface-subtle` | `#F3F4F6` | head tabel, blok meta, field cari diam |
| `--ark-surface-hover` | `#F3F4F6` | hover baris/item/tombol sekunder |
| `--ark-surface-press` | `#E5E7EB` | `:active` |
| `--ark-surface-selected` | `#EEF2FF` | baris/item terpilih |
| `--ark-surface-accent` | `#4F46E5` | fill tombol primer |
| `--ark-surface-inverse` | `#111827` | tooltip |
| `--ark-surface-scrim` | `rgba(17,24,39,.55)` | latar modal |

**Teks** — rasio terhadap `--ark-surface-canvas`:

| Token | Nilai | Rasio | Batas pemakaian |
|---|---|---|---|
| `--ark-text-primary` | `#111827` | 16.1:1 | isi utama |
| `--ark-text-secondary` | `#6B7280` | 4.8:1 | meta, label seksi, teks bantu |
| `--ark-text-tertiary` | `#9CA3AF` | 2.9:1 | **dekoratif saja**: placeholder, ikon, counter `0` |
| `--ark-text-disabled` | `#9CA3AF` | 2.9:1 | kontrol nonaktif (tidak wajib dibaca) |
| `--ark-text-inverse` | `#FFFFFF` | — | di atas fill gelap |
| `--ark-text-accent` | `#4338CA` | 7.4:1 | tautan & teks aksi (lebih gelap dari fill) |
| `--ark-text-danger` | `#DC2626` | 4.9:1 | label bahaya |
| `--ark-text-success` | `#15803D` | 5.0:1 | status sukses |
| `--ark-text-warning` | `#B45309` | 5.5:1 | kuota > 80%, peringatan |

**Border**

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--ark-border-subtle` | `#F1F5F9` | pemisah internal card/tabel |
| `--ark-border-default` | `#E5E7EB` | outline card, tabel, modal |
| `--ark-border-strong` | `#D1D5DB` | border kontrol form saat diam |
| `--ark-border-accent` | `#C7D2FE` | border halus bernuansa aksen |
| `--ark-border-danger` | `#FCA5A5` | border pesan error |
| `--ark-border-focus` | `#4F46E5` | outline fokus |

**Aksen & semantik** — tiap keluarga punya `fill / hover / press / subtle / border`:

`--ark-accent` `#4F46E5` · `--ark-accent-hover` `#4338CA` · `--ark-accent-press` `#3730A3` · `--ark-accent-subtle` `#EEF2FF` · `--ark-accent-subtle-hover` `#E0E7FF` · `--ark-on-accent` `#FFFFFF`
`--ark-danger` `#DC2626` · `--ark-danger-hover` `#B91C1C` · `--ark-danger-press` `#991B1B` · `--ark-danger-subtle` `#FEF2F2` · `--ark-danger-border` `#FECACA`
`--ark-success` `#15803D` · `--ark-success-hover` `#166534` · `--ark-success-subtle` `#F0FDF4` · `--ark-success-border` `#BBF7D0`
`--ark-warning` `#B45309` · `--ark-warning-hover` `#92400E` · `--ark-warning-subtle` `#FFFBEB` · `--ark-warning-border` `#FDE68A`

**Hue kategori berkas** (`bg / fg / border`) — sengaja disamakan dengan palet yang dikirim `src/Helpers.php::getFileMeta()` (`rose / blue / emerald / amber / purple / slate`) supaya adopsi bertahap tidak mengubah makna warna yang sudah dikenal pengguna:

| Token | bg | fg | border | Padanan Helpers |
|---|---|---|---|---|
| `--ark-hue-pdf-*` | `#FFF1F2` | `#BE123C` | `#FECDD3` | PDF |
| `--ark-hue-doc-*` | `#EFF6FF` | `#1D4ED8` | `#BFDBFE` | Word |
| `--ark-hue-sheet-*` | `#ECFDF5` | `#047857` | `#A7F3D0` | Spreadsheet/CSV |
| `--ark-hue-image-*` | `#FFFBEB` | `#B45309` | `#FDE68A` | Gambar |
| `--ark-hue-zip-*` | `#FAF5FF` | `#7E22CE` | `#E9D5FF` | Arsip ZIP |
| `--ark-hue-plain-*` | `#F3F4F6` | `#4B5563` | `#E5E7EB` | teks/lainnya |

### 3.2 Tipografi

`--ark-font-sans` = Inter dengan fallback sistem; `--ark-font-mono` untuk token, angka teknis, dan pintasan. Baseline UI **14px** (bodi & isi tabel sama), lantai absolut 11px. Tidak ada ukuran hero: **24px adalah tipografi terbesar yang diizinkan**, dan hanya untuk judul halaman.

| Token | Nilai | Peran |
|---|---|---|
| `--ark-text-2xs` | 11px | micro label UPPERCASE, counter, caret |
| `--ark-text-xs` | 12px | meta sekunder: timestamp, ukuran, pemilik |
| `--ark-text-sm` | 13px | teks bantu, caption, item rapat |
| `--ark-text-md` | 14px | **BODI (default `body`), isi tabel, kontrol form, label nav** |
| `--ark-text-lg` | 15px | judul dalam panel |
| `--ark-text-xl` | 16px | heading seksi |
| `--ark-text-2xl` | 20px | judul panel hero (jarang) |
| `--ark-text-3xl` | 24px | **judul halaman (600)** · juga angka metrik, `tabular-nums` |

Hierarki yang dipakai view: judul halaman 24/600 → heading seksi 16/600 → judul panel 15/600 → bodi 14/400 → meta 12–13/400. Warna, bukan ukuran, adalah penanda sekunder; jangan menambah ukuran baru di view.

Leading: `tight 1.25` · `snug 1.4` · `normal 1.55` · `relaxed 1.7`.
Weight: `400 / 500 / 600 / 700` (700 hanya judul halaman + metrik).
Tracking: `tight -0.013em` (≥16px) · `normal 0` · `wide 0.03em` · `caps 0.06em`.

Resep per peran ada di `base.css` sebagai **kelas**, bukan elemen, agar view PHP bisa menempel tanpa mengganti tag: `.ark-title-page`, `.ark-title-sub`, `.ark-title-section`, `.ark-title-card`, `.ark-label-caps`, `.ark-label`, `.ark-helper`, `.ark-body`, `.ark-meta`, `.ark-metric`, plus `.ark-num` (tabular-nums), `.ark-mono` (slug/ID teknis), `.ark-truncate`, `.ark-kbd`, `.ark-sr-only`. `h1`–`h4` elemen ikut skala yang sama (h1=24, h2=16, h3=15, h4=14) supaya markup lama tidak perlu kelas tambahan.

### 3.3 Ruang, radius, elevasi, gerak, layout

Skala 4px: `0 · 2 · 4 · 6 · 8 · 10 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64` (`--ark-space-0` … `-16`, sisipan `0x5/1x5/2x5`). Alias semantik: `--ark-pad-control-sm/md/lg`, `--ark-pad-card` (16) / `--ark-pad-card-lg` (20), `--ark-pad-panel`, `--ark-gap-row`, `--ark-gap-stack-sm/md/lg` (`lg` = antar seksi pada halaman datar).

Radius `3 · 4 · 6 · 8 · 12 · 9999` (`xs/sm/md/lg/xl/full`); kontrol & panel = 6/8, `xl` khusus modal besar, `sm/xs` khusus chip/badge/kbd. Memilih radius lain = menambah token, bukan menulis angka di komponen.

Elevasi `xs · sm · md · lg` **hanya untuk lapisan mengambang** (menu, modal, toast, tooltip, search bar header). Panel, tabel, dan tombol tidak memakai bayangan: hierarki digerjakan border 1px + ruang. `--ark-shadow-ring` (3px `rgba(79,70,229,.20)`) dan `--ark-shadow-ring-danger` tetap untuk fokus/error.

Gerak: `80ms` state · `120ms` hover · `180ms` menu · `240ms` modal; easing `standard .2,0,0,1` · `entrance .16,1,.3,1` · `exit .4,0,1,1`. Semua dinetralkan oleh `prefers-reduced-motion`. Tekanan tombol digambarkan dengan `translateY(0.5px)` + perubahan warna, **bukan `scale()`** — `scale` hanya ada pada animasi masuk/keluar lapisan mengambang (menu, modal, toast).

Z-index: `raised 1 · sticky 10 · dropdown 20 · drawer 30 · scrim 40 · modal 50 · toast 60 · tooltip 70`. Angka telanjang dilarang.

Dimensi: kontrol `28/32/40` (28 hanya aksi ikon-only di baris; 32 = kontrol default, 40 = aksi primer), target sentuh minimum `44`, baris tabel `40` (±50 untuk sel nama berkas dua baris), sidebar `248` (`72` sebagai rail), header `56`, isi `1240`, menu `208–320`. Pada `@media (pointer: coarse)` kontrol naik ke `36/44/48` dan baris ke `52` **tanpa perubahan markup**.

---

## 4. Ikon

**Satu registry: `assets/js/icons.js`.** Bentuk SVG tidak pernah diduplikasi. Dua jalur masuk ke DOM:

| Jalur | Markup | Pengatur ukuran |
|---|---|---|
| Browser (`data-icon`) | `<span data-icon="nama"></span>` → `ArkIcons.hydrate()` | `--ark-icon-size` milik komponen |
| Server (halaman lama) | `renderSvgIcon('nama', 'w-4 h-4 …')` → `<svg class="ark-icon ark-icon--16 …">` | kelas `.ark-icon--NN` |

`src/Helpers.php::arkIconRegistry()` **membaca berkas JS yang sama** (regex atas `PATHS` / `ALIAS` / `FILLED`, dikache statis per request), jadi `arkIconSvg()` dan `ArkIcons.markup()` menghasilkan byte yang identik — ditegakkan `tests/run_tests.php`. Konsekuensinya: format `icons.js` dipatenkan (satu entri per baris, nilai dibungkus kutip tunggal, isi memakai kutip ganda); mengubah format itu berarti mengubah parser PHP.

Aturan bentuk: **outline**, viewBox 24, `stroke="currentColor"`, **stroke-width 1.75** seragam, tanpa dekorasi. Ikon solid hanya yang terdaftar di `FILLED` (saat ini `star-fill`). Nama lama tidak dibuang tapi masuk `ALIAS` (`more-horizontal`→`dots`, `star-solid`→`star-fill`, `refresh`→`restore`, `alert-triangle`→`alert`, `shield-check`→`shield`, `layout-grid`→`grid`, `file-stack`→`versions`, `more-vertical`→`dots-vertical`) supaya markup yang belum dimigrasi tetap ter-render.

Skala ukuran resmi (`base.css` §Ikon): **18** navigasi sidebar · **16** toolbar & aksi tabel (default) · **14** menu/densitas tinggi · **12** caret & micro · **24–32** empty state. Di luar angka itu pakai `--ark-icon-size`, bukan kelas. `renderSvgIcon()` memetakan kelas warisan `w-3 … w-8` ke `.ark-icon--NN` dan meneruskan kelas lain (mis. `text-slate-400`) apa adanya; setelah sebuah halaman dimigrasi, kelas warna itu ikut dihapus — ikon default **netral** (`color: inherit`), bukan berwarna per konteks.

Larangan (lihat `docs/design-refactor-audit.md` §temuan D): ikon di dalam kotak berwarna bersudut lengkung, ikon berlatar gradien, emoji sebagai ikon, ikon di samping setiap label, dan ikon yang dianimasikan tanpa fungsi.

Kontrak: ikon **selalu** berpasangan dengan teks atau `aria-label`. PHP jatuh ke ikon `file` untuk nama tak dikenal; `ArkIcons.markup()` mengembalikan string kosong — jangan pakai nama yang belum terdaftar.

---

## 5. Kontrak komponen

### 5.1 Tombol — `components/buttons.css`

```html
<button class="ark-btn ark-btn--primary" type="button">
  <span data-icon="upload"></span><span class="ark-btn__label">Unggah berkas</span>
</button>
```

| Kelas | Kontrak |
|---|---|
| `.ark-btn` | sekunder (default), tinggi `--ark-control-h-md` (32), radius `--ark-radius-lg`, **tanpa bayangan** |
| `--primary` `--secondary` `--ghost` `--danger` `--danger-ghost` `--link` | varian; **satu primer per tampilan**, sisanya sekunder/ghost |
| `--sm` `--lg` `--block` | ukuran 28/40 dan lebar penuh |
| `.ark-icon-btn` (`--sm` `--danger`) | tombol ikon saja; `[aria-pressed="true"]` = aktif |
| `.ark-btn-group` | kontrol menyatu, border ditumpuk `-1px`, tanpa bayangan |

State: `:hover` `:active` `:focus-visible` (ring global), `[disabled]`/`[aria-disabled="true"]` (teks `--ark-text-disabled`, border `--ark-border-default`), `[aria-busy="true"]` (spinner `::before` 14px, ikon & label diredupkan, lebar tidak berubah, `cursor: progress`).

### 5.2 Form & pencarian — `components/forms.css`

```html
<div class="ark-field">
  <label class="ark-label" for="x">Nama</label>
  <input class="ark-input" id="x" type="text" aria-describedby="x-help" aria-invalid="true">
  <p class="ark-field__message ark-field__message--error" id="x-help">…</p>
</div>
```

- `.ark-input` `.ark-select` `.ark-textarea` — tinggi `--ark-control-h-md`, border `--ark-border-strong`, ring lembut saat fokus (`--ark-shadow-ring`), `[aria-invalid="true"]` → border danger + ring danger.
- `.ark-select` memakai chevron data-URI; `.ark-select--sm` untuk filter baris; `.ark-select--fit` agar lebarnya mengikuti isi (dipakai di toolbar penyaring dan sel tabel).
- `.ark-form` (+ `.ark-form--tight`) — pembungkus field yang memegang jarak antar field (16 / 12px). View **tidak** menulis `gap` inline pada form.
- `.ark-field-grid` — dua kolom rata untuk pasangan field singkat, satu kolom di ≤640px.
- `.ark-form-actions` — baris aksi form, rata kanan; isi `.ark-btn--primary` dan, kalau perlu, `.ark-btn--ghost` / `.ark-btn--danger`.
- `.ark-search` + `__icon` `__field` `__hint` `__clear` — field cari dengan pintasan `Ctrl K`; `__clear` menyembunyikan `__hint` saat ada.
- `.ark-check` `__input` `__box` — input asli disembunyikan di atas kotak (tetap fokusabel), centang lewat `mask-image`, indeterminate lewat `[aria-checked="mixed"]` pada `__box`.
- `.ark-dropzone` (+ `.is-dragover`) — area unggah; state drag mengubah border jadi solid + `--ark-accent-subtle`.

Pesan validasi **wajib** terikat `aria-describedby` dan tidak hanya berubah warna.

### 5.3 Menu dropdown — `components/menu.css`

```html
<div class="ark-menu-wrap">
  <button class="ark-btn ark-menu__trigger" data-menu="#m1" aria-haspopup="menu" aria-expanded="false">Aksi baris</button>
  <div class="ark-menu" id="m1" role="menu" hidden>
    <p class="ark-menu__label" id="g1">Berkas</p>
    <button class="ark-menu__item" role="menuitem" aria-selected="true"><span data-icon="eye"></span><span class="ark-menu__text">Pratinjau</span><span class="ark-menu__shortcut">Enter</span></button>
    <div class="ark-menu__separator" role="separator"></div>
    <button class="ark-menu__item ark-menu__item--danger" role="menuitem"><span data-icon="trash"></span><span class="ark-menu__text">Pindahkan ke Sampah</span><span class="ark-menu__shortcut">Del</span></button>
  </div>
</div>
```

`.ark-menu--right` menempel ke kanan (baris tabel). Panel memakai `width: max-content` dengan `--ark-menu-w-min`/`-max` — lebarnya tidak boleh dipotong oleh lebar pemicu. Item terpilih: `aria-selected` (menuitemradio) atau `.is-selected`. Fokus item = inset ring 2px, bukan outline. Tanggung jawab JS: `hidden`, `aria-expanded`, panah atas/bawah, `Home`/`End`, `Escape` kembali ke pemicu, tutup saat klik di luar, dan `closeAllMenus()` setiap modal dibuka.

### 5.4 Tabel arsip — `components/table.css`

`.ark-table-wrap` > `table.ark-table` + `.ark-table__head`/`.ark-table__body`; kolom khusus: `.ark-th--check`/`.ark-td--check`, `--num`, `--actions`, `--center`, `.ark-td--nowrap`. Kolom sekunder disembunyikan bertahap lewat `.ark-table__col--hide-md` (≤1024px) lalu `--hide-sm` (≤768px) — **bukan** scroll horizontal. Pembungkusnya datar: border 1px, **tanpa bayangan**. Baris: tinggi `--ark-row-h` (48px; sel nama dua baris ≈55px), padding vertikal `--ark-space-2`, `tr:hover` subtle, `tr.is-selected` = `--ark-surface-selected` + marker `inset 2px 0 0 --ark-accent`, `tr[aria-busy="true"]` meredupkan aksi tanpa mengubah tinggi baris. Isi sel `--ark-text-md` (14), meta `--ark-text-xs` (12).

`.ark-table-wrap` wajib `min-width:0`: ia flex item di dalam `.ark-section`, dan min-content tabel lebih lebar daripada layar ponsel. Tanpa aturan itu halaman yang melebar (luber horizontal); dengan itu hanya tabelnya yang menggulir di dalam wadahnya (terasa di ≤390px, sisanya sudah dipotong lewat `--hide-md/-sm`).

Urutan kolom tetap: Berkas · Tipe · Ukuran · Pemilik · Perbarui · Aksi. Sel nama = `.ark-file` (`__glyph` + `__main` + `__name` + `__meta`). `__glyph` kini **ikon garis netral** (`--ark-text-secondary`, 16px) — modifier hue `--pdf|doc|sheet|image|zip` sudah dihapus; tipe berkas ditandai lewat badge, bukan kotak bewarna di belakang ikon. Sortable: `.ark-sort[aria-sort="ascending|descending"]` dengan caret 12px. `.ark-table--compact` untuk panel samping, `--dividers` untuk grid padat.

Aksi baris **selalu terlihat** (bukan hover-only).

### 5.5 Badge — `components/badge.css`

`.ark-badge` + varian semantik (`--neutral --accent --success --warning --danger`) + hue berkas (`--pdf --doc --sheet --image --zip --plain`) + `--pill` `--lg`. `.ark-badge__dot` untuk penanda bulat, `.ark-badge--count` untuk angka tabular. Tinggi 20px, `--ark-text-xs`/500, latar lembut + border senada. Warna tidak pernah sendirian membawa makna — selalu ada teks.

### 5.6 Tab & segmented — `components/tabs.css`

`.ark-tabs` dengan `role="tablist"`, item `.ark-tab[aria-selected="true"]` (garis bawah 2px aksen, teks primer), `.ark-tab__count` untuk angka. `.ark-segmented` (+ `--sm`) dengan `.ark-segmented__item[aria-pressed="true"]` untuk sakelar tunggal (Daftar/Kisi, rentang waktu). Panel: `role="tabpanel"` + `aria-labelledby`.

### 5.7 Modal — `components/modal.css`

```html
<div class="ark-scrim" id="m1" hidden>
  <div class="ark-modal ark-modal--sm" role="dialog" aria-modal="true" aria-labelledby="m1-t" aria-describedby="m1-d">
    <div class="ark-modal__header">…</div>
    <div class="ark-modal__body">…</div>
    <div class="ark-modal__footer">…</div>
  </div>
</div>
```

Ukuran `--sm` (400) · default (520) · `--lg` (720) · `--xl` (920); tinggi maksimum `100vh - 48px`. Body bisa `--flush` (tabel penuh); footer `--split` memisahkan aksi destruktif ke kiri, isi aksi kanan dibungkus `.ark-modal__footer-actions`; `.ark-modal__note` untuk keterangan non-dioleng dan `.ark-modal__section` untuk pemisah blok. `.ark-modal__alert-icon` untuk dialog konfirmasi. Pada ≤640px modal jadi sheet bawah.

JS: `openModal()` menutup semua menu, menyimpan pemicu, `body` dikunci, fokus ke `[data-autofocus]` atau kontrol pertama; `closeModal()` mengembalikan fokus ke pemicu; `Tab` dikunci di dalam dialog; `Escape` dan klik scrim menutup; `.is-open` menggerakkan transisi 240ms.

### 5.8 Toast — `components/toast.css`

Region tunggal `#toast-container` (id sama dengan aplikasi lama) dengan `role="status" aria-live="polite"`; kegagalan memakai `aria-live="assertive"` pada itemnya. Item `.ark-toast--success|danger|error|warning|info` = card putih + garis 3px penanda jenis, ikon 16px, judul + deskripsi, `.ark-toast__action` opsional, tombol tutup ber-`aria-label`, dan `.ark-toast__timer` yang menyusut selama `--ark-toast-ttl` (JS memakai durasi yang sama, hover/`focus-within` menjeda keduanya). Maks 4 item. Keluar dengan `.is-leaving`.

### 5.9 Tooltip — `components/tooltip.css`

Murni CSS: `data-ark-tooltip="teks"` pada elemen, tampil pada `:hover` **dan** `:focus-visible` dengan jeda 120ms; posisi `data-ark-tooltip-position="top|right|bottom|left"`. Varian `.ark-tooltip` (dengan `__title` dan `__meta`) untuk kebutuhan JS/tooltip kaya. Isi tooltip tidak boleh memuat instruksi wajib — hanya memperpendek label.

### 5.10 Empty state — `components/empty-state.css`

`.ark-empty` (`__icon` `__title` `__desc` `__actions`) dalam panel datar; `--inline` untuk di dalam tabel; `--flush` untuk tanpa padding panel; `__hint` untuk hasil filter kosong. `__icon` = ikon garis **netral** (`--ark-text-tertiary`) yang ukurannya dari `--ark-icon-size`: 28px untuk empty state halaman, 20px untuk varian inline — **tidak ada kotak/border bewarna di belakangnya**. Selalu menawarkan satu aksi konkret, bukan sekadar "tidak ada data".

Kontrak view `views/components/empty-state.php` memakai variabel, bukan HTML mentah: `$emptyIcon`, `$emptyTitle`, `$emptyDesc`, `$emptyHint`, dan `$emptyAction = ['label' => ..., 'icon' => ..., 'onclick' => ...]` **atau** `['label' => ..., 'icon' => ..., 'href' => ...]`. Tidak ada aksi = tidak ada baris aksi. `document-grid.php` dan `document-table.php` merakit kondisi kosongnya sendiri (`--flush` dan `--inline`) karena pesan yang benar di sana adalah "hasil penyaring nol", bukan "arsip kosong".

### 5.11 Indikator muat — `components/loader.css`

`.ark-spinner` (`--sm` `--lg` `--accent`) untuk aksi singkat; `.ark-skeleton` (`--text --title --icon --circle --row`, `.ark-skeleton-line`) untuk muat awal dengan tinggi sama seperti konten jadi; `.ark-progress` + `.ark-progress__bar` (elemen `<span>` — `display:block` sudah disertakan) dengan lebar dari `--ark-progress-value` dan varian `--success|--warning|--danger|--indeterminate`; `.ark-progress--thin` 4px; `.ark-progress-label` untuk "43,1 GB dari 50 GB"; `.ark-loading-block` untuk placeholder blok berisi spinner + teks. Elemen yang menunggu **wajib** `[aria-busy="true"]`.

### 5.12 Breadcrumb — `components/breadcrumb.css`

`<nav class="ark-crumbs-nav" aria-label="Breadcrumb">` > `ol.ark-crumbs` > `li.ark-crumbs__item` dengan `__link`, `__sep` (chevron), dan `__current[aria-current="page"]`. Akar selalu tampil; pada layar sempit item tengah **ditutup** lewat `.ark-crumbs__item--hide-sm` / `.ark-crumbs__sep--hide-sm`, bukan dipotong. Varian `--bar` (di bawah header halaman) dan `--inline` (di dalam sel tabel).

### 5.13 Kerangka aplikasi — `components/shell.css`

```html
<div class="ark-shell" data-shell-page="dashboard">
  <aside class="ark-sidebar" id="app-nav">…</aside>
  <div class="ark-drawer-scrim" data-drawer-scrim></div>
  <div class="ark-main">
    <header class="ark-header">…</header>
    <main class="ark-content">
      <div class="ark-content__body">
        <div class="ark-page-head">…</div>   <!-- lihat §5.16 -->
        <section class="ark-section">…</section>
      </div>
    </main>
  </div>
</div>
```

Lebar sidebar dikendalikan satu properti lokal `--ark-side-w` pada `.ark-shell`; `.ark-sidebar` (fixed) dan `.ark-main` (margin) membacanya, jadi mode rail dan mode drawer tidak saling menimpa.

| Kondisi | Sidebar | Catatan |
|---|---|---|
| `≥ 1280px` | inline 248 | `.is-collapsed` → rail 72 |
| `1024–1279px` | inline 248 / rail 72 | padding header turun ke 16, cari 240px |
| `< 1024px` | drawer 248 (maks 86vw) | off-canvas + scrim, `body.ark-drawer-lock`, rail tidak berlaku |
| `< 768px` | drawer | padding konten 16, judul header pendek, aksi jadi ikon |

`.ark-content` membatasi isi pada `--ark-content-max` dan memberi ritme ruang yang sama untuk semua halaman; `.ark-panel` (permukaan + border + `--ark-pad-card`) adalah permukaan isi, `--flush` untuk tabel yang menempel tepi, dan `.ark-content__body` satu-satunya sumber jarak vertikal antar blok (gap `--ark-gap-stack-lg` = 32px, tanpa margin per blok). Kepala halaman tidak lagi dimiliki `shell.css`: pakai `.ark-page-head` (§5.16).

**Aturan tumpukan (`shell.css`)**: `.ark-content { position: relative; z-index: 0 }`. Satu baris ini membuat seluruh halaman jadi stacking context sendiri, sehingga `z-index` apa pun yang dipakai view — termasuk kelas Tailwind lama seperti `.dropdown-menu` (`z-40`) — tidak bisa lagi menyalip chrome kerangka (header 11, sidebar 31). Konsekuensinya yang harus diingat saat memindahkan view: **overlay tingkat halaman tidak boleh dirender di dalam view**. `#toast-container`, `#multi-select-bar`, dan seluruh `modals.php` memang berada di luar `.ark-content`, jadi tetap di atas chrome; `#modal-invite-member` tadinya satu-satunya pengecualian (dipasang di `views/pages/members.php`) dan sudah dipindah ke `views/layouts/modals.php` sebagai modal #13.

Header memakai `z-index: calc(var(--ark-z-sticky) + 1)`: satu selapis di atas kepala tabel yang di-stick. Tanpa itu, `<th>` yang sticky (z 10 juga, tetapi lebih akhir di DOM) menimpa dropdown workspace/akun — bug yang benar-benar terjadi sebelum naik satu lapis.

### 5.14 Sidebar — `components/sidebar.css`

```html
<div class="ark-sidebar__head">
  <div class="ark-menu-wrap ark-menu-wrap--block ark-ws">
    <button class="ark-ws__trigger" type="button" data-menu="#ws-menu"
            aria-haspopup="menu" aria-expanded="false" aria-controls="ws-menu">
      <span class="ark-ws__mark">AD</span>
      <span class="ark-ws__meta">
        <span class="ark-ws__name">Arsip Dinas</span>
        <span class="ark-ws__plan">Tim · 21,3 / 50 GB</span>
      </span>
      <span class="ark-menu__chevron" data-icon="chevron-down"></span>
    </button>
    <div class="ark-menu" id="ws-menu" role="menu" hidden>…</div>
  </div>
  <button class="ark-icon-btn ark-icon-btn--sm ark-sidebar__collapse" type="button"
          data-drawer="collapse" data-ark-tooltip="Ciutkan navigasi" aria-label="Ciutkan navigasi">…</button>
  <button class="ark-icon-btn ark-icon-btn--sm ark-sidebar__close" type="button"
          data-drawer="close" aria-label="Tutup navigasi">…</button>
</div>

<nav class="ark-sidebar__nav">
  <div class="ark-nav__group">
    <p class="ark-nav__group-label ark-label-caps">Arsip</p>
    <div class="ark-nav__list">
      <a class="ark-nav__item" data-nav="documents" href="?page=documents">
        <span class="ark-nav__icon" data-icon="archive"></span>
        <span class="ark-nav__text">Semua Dokumen</span>
        <span class="ark-badge ark-badge--count">1.284</span>
      </a>
    </div>
  </div>
</nav>

<div class="ark-sidebar__foot">
  <div class="ark-usage">
    <div class="ark-usage__row"><span>Penyimpanan</span><span class="ark-usage__value">43%</span></div>
    <div class="ark-progress ark-progress--thin" role="progressbar"
         aria-valuenow="43" aria-valuemin="0" aria-valuemax="100"
         style="--ark-progress-value: 43%"><span class="ark-progress__bar"></span></div>
    <a class="ark-usage__link" href="?page=billing"><span data-icon="credit-card"></span>Tambah kapasitas</a>
  </div>
</div>
```

Matriks state item navigasi:

| State | Latar | Teks | Bobot |
|---|---|---|---|
| diam | transparan | `--ark-text-secondary` | 400 |
| hover | `--ark-surface-hover` | `--ark-text-primary` | 400 |
| aktif `[aria-current="page"]` | `--ark-surface-selected` | `--ark-text-primary` | 500 |

Tidak ada latar berwarna pada navigasi: penanda aktif hanya permukaan terpilih yang nyaris netral plus bobot teks. Status aktif **ditentukan server** lewat `?page=`; `app-shell.js` hanya menyelaraskannya untuk halaman statis dan tidak pernah menjadikannya keputusan akses.

Rail (`.ark-shell.is-collapsed`, desktop saja): label grup, teks item, counter, dan kaki penyimpanan hilang; item diratakan ke tengah; ikon tombol rail dicerminkan (`scaleX(-1)`) sehingga satu ikon menyatakan dua arah. `.ark-sidebar__nav` beralih ke `overflow: visible` di mode ini supaya tooltip rail tidak terpotong — daftarnya sengaja pendek (12 item muat pada tinggi 720px).

Karena itu item nav membawa `data-ark-tooltip` dengan `data-ark-tooltip-position="right"`: aturannya ada di sini, bukan di `tooltip.css`, karena hanya sidebar yang tahu bahwa label sedang tersembunyi.

```css
/* sidebar.css — tooltip label hanya aktif di rail */
.ark-shell:not(.is-collapsed) .ark-nav__item[data-ark-tooltip]::before,
.ark-shell:not(.is-collapsed) .ark-nav__item[data-ark-tooltip]::after { display: none; }
@media (max-width: 1023px) {
  /* di bawah 1024px rail tidak pernah aktif (lihat shell.css) */
  .ark-nav__item[data-ark-tooltip]::before,
  .ark-nav__item[data-ark-tooltip]::after { display: none; }
}
```

### 5.15 Header — `components/header.css`

```html
<header class="ark-header">
  <div class="ark-header__trail">
    <button class="ark-icon-btn" type="button" data-drawer="menu" aria-label="Buka navigasi"
            aria-expanded="false" aria-controls="app-nav">…</button>
    <nav class="ark-crumbs-nav ark-header__crumbs" aria-label="Breadcrumb">…</nav>
    <span class="ark-header__title">Beranda</span>
  </div>

  <form class="ark-search ark-header__search" action="index.php" method="get" role="search">
    <input type="hidden" name="page" value="documents">
    <input type="hidden" name="ws" value="workspace-slug">
    <button class="ark-header__search-submit" type="submit" aria-label="Cari">
      <span class="ark-search__icon" data-icon="search"></span>
    </button>
    <input class="ark-search__field" type="search" id="global-search" name="q" placeholder="Cari dokumen, folder, atau pemilik..." aria-label="Cari di workspace ini">
    <kbd class="ark-kbd ark-search__hint">Ctrl K</kbd>
  </form>

  <div class="ark-header__divider" aria-hidden="true"></div>

  <div class="ark-header__actions">
    <button class="ark-icon-btn ark-header__search-toggle" type="button"
            data-search-toggle aria-label="Cari" aria-expanded="false">…</button>
    <button class="ark-btn ark-btn--primary ark-btn--sm" type="button">
      <span data-icon="upload-cloud"></span><span class="ark-btn__label">Unggah</span>
    </button>
    <div class="ark-menu-wrap">
      <button class="ark-account__trigger" type="button" data-menu="#account-menu"
              aria-haspopup="menu" aria-expanded="false" aria-controls="account-menu">
        <span class="ark-avatar">RN</span><span class="ark-account__name">Rani N.</span>
        <span class="ark-menu__chevron" data-icon="chevron-down"></span>
      </button>
      <div class="ark-menu ark-menu--right" id="account-menu" role="menu" hidden>
        <div class="ark-account__id">…</div>
        <button class="ark-menu__item" type="button" role="menuitem">…</button>
      </div>
    </div>
  </div>
</header>
```

Tinggi 56px, empat area dengan urutan tetap. `.ark-header__search` ikut memakai `.ark-search` (satu elemen, bukan pembungkus ganda) dan `flex: 0 1 320px` — 240px di ≤1279px, lalu **disembunyikan** (bukan di-opacity, supaya tidak ikut tab order) di < 1024px; pemicu `data-search-toggle` membukanya sebagai baris selebar header tepat di bawahnya. Di ≤ 767px breadcrumb digantikan `.ark-header__title`, divider hilang, dan tombol aksi kehilangan labelnya — teksnya pindah ke `aria-label`.

Inisial (`AD`, `RN`) adalah teks, bukan gambar: tanpa permintaan jaringan dan tidak pernah tampil kosong. `.ark-avatar` dan `.ark-ws__mark` sengaja netral; satu-satunya warna di kerangka ini adalah tombol primer dan baris penyimpanan.

**Perilaku** (`assets/js/app-shell.js`) — lima interaksi: status navigasi, dropdown workspace, dropdown akun, drawer, rail + baris cari. Semuanya menutup diri pada `Escape` (termasuk ketika fokus tidak berada di dalam menu), klik di luar, dan saat jendela melebar kembali ke desktop; fokus dikembalikan ke pemicu. Rail dipertahankan pada `localStorage['arsipku_sidebar_collapsed']` — kunci yang sama pernah dipakai `assets/js/app.js` lama; di layout aplikasi keduanya aman berjalan bersamaan karena cabang sidebar/drawer skrip lama sudah tidak menemukan elemen penjaganya (lihat jembatan di bawah).

**Jembatan saat `views/layouts/app.php` server-render.** Kerangka ini sekarang dipakai layout aplikasi, sementara isi halaman masih Tailwind, jadi empat kontrak lama sengaja dipertahankan di dalam markup Ark:

| Kontrak | Kenapa tetap ada |
|---|---|
| `#global-search` di dalam form GET | Enter mengirim `q` ke `?page=documents`; dokumen dicocokkan menurut nama, pemilik, dan nama folder, sementara folder cocok ditampilkan sebagai tautan langsung. `app.js`/`app-shell.js` menyediakan pintasan fokus `Ctrl+K`/`/`. |
| `.ws-switch-item` + `data-ws-slug` pada item menu workspace | `app.js` yang memanggil `api/workspaces.php` lalu redirect; `app-shell.js` tidak memuat data bisnis. |
| `onclick="openModal('modal-upload' \| 'modal-create-workspace')"` | modal masih punya runtime dan styling lama (`.modal-backdrop`). |
| `#toast-container` + `showToast(...)` | toast masih dirender `app.js` dengan kelas Tailwind. |

`app.js` tidak lagi menemukan `#btn-toggle-sidebar`, `#btn-open-mobile-drawer`, `#mobile-drawer`, atau `#mobile-drawer-overlay` di dalam kerangka, jadi seluruh cabang sidebar/drawer pada skrip lama otomatis tidak aktif (semuanya dijaga `if (!el) return`). Kelas `.dropdown-menu`/`[data-dropdown-toggle]` masih dipakai dropdown per-baris di dalam halaman, jadi controller itu tetap hidup — tetapi z-index-nya tidak lagi relevan terhadap chrome: `.ark-content` adalah stacking context (`z-index: 0`, lihat §5.13) sehingga menu baris tidak pernah bisa menutupi header atau sidebar, seberapa besar pun nilai z di dalam view.

### 5.16 Komposisi halaman datar — `components/page.css`

Primitif pembentuk isi halaman. Alasannya: halaman arsip sebelumnya dibangun dari tumpukan card (dan card di dalam card), sehingga struktur visual compet dengan konten. Berkas ini adalah pengganti yang tidak punya bayangan sama sekali — hierarki dikerjakan lewat tipografi, jarak, dan hairline.

```html
<div class="ark-page-head">
  <div class="ark-page-head__text">
    <h1 class="ark-title-page">Beranda</h1>
    <p class="ark-title-sub">148 arsip · 21,3 GB dari 50 GB</p>
  </div>
  <div class="ark-page-head__actions">
    <button class="ark-btn" type="button">…</button>
    <button class="ark-btn ark-btn--primary ark-btn--lg" type="button">…</button>
  </div>
</div>

<div class="ark-section ark-section--ruled">
  <div class="ark-section__head">
    <h2 class="ark-section__title">Aktivitas terbaru</h2>
    <a class="ark-section__more" href="…">Lihat semua</a>
  </div>
  <div class="ark-list ark-list--inset">
    <a class="ark-list__row ark-list__row--link" href="…">
      <span class="ark-list__icon" data-icon="upload"></span>
      <span class="ark-list__main">
        <span class="ark-list__title">Faktur_Pelanggan_3391.pdf diunggah</span>
        <span class="ark-list__meta">Ahmad Fauzi · Legal</span>
      </span>
      <span class="ark-list__end">09:14</span>
    </a>
  </div>
</div>

<span class="ark-meter" style="--ark-meter-value:43%" role="progressbar"
      aria-valuenow="43" aria-valuemin="0" aria-valuemax="100"
      aria-valuetext="43,1 GB dari 50 GB terpakai">
  <span class="ark-meter__label">Workspace</span>
  <span class="ark-meter__track"><span class="ark-meter__fill"></span></span>
  <span class="ark-meter__value">43,1 / 50 GB</span>
</span>

<div class="ark-tile-grid">
  <article class="ark-tile">
    <div class="ark-tile__head">
      <span class="ark-tile__icon" data-icon="folder"></span>
      <div class="ark-tile__main"><h3 class="ark-tile__name">Keuangan</h3><span class="ark-tile__meta">32 arsip · 6,4 GB</span></div>
    </div>
    <div class="ark-tile__actions">…</div>
  </article>
</div>
```

| Kelas | Kontrak |
|---|---|
| `.ark-page-head` | `<h1>` + `.ark-title-sub` di kiri, `__actions` di kanan. Tanpa border, tanpa card. Aksi primer satu dan `--lg` (40) — kecuali head yang juga memuat kolom cari: semua kontrol 32 agar baris tidak berlapis. |
| `.ark-section` | `__head` = `.ark-section__title` (16/600) + `__more`/`__hint`. Seksi TIDAK memberi margin sendiri: jarak antar blok dimiliki `.ark-content__body` (gap 32px), jarak kepala-ke-isi dimiliki `gap` internal seksi (16px). `--ruled` memberi hairline **hanya** bila seksi langsung berisi list/tabel. |
| `.ark-list` | `__row` min-height `--ark-row-h` dengan padding `8px 8px` — blok 8px sama dengan padding sel tabel, jadi list di samping tabel punya irama yang identik (satu baris teks = 48px, dua baris = ~55px). `__icon` 16px `currentColor` netral (tanpa kotak), `__title` 14/500, `__meta` 12 sekunder, `__end` rata kanan `tabular-nums`. Pemisah = `border-top --ark-border-subtle` pada `+ .ark-list__row`, bukan zebra. `--dense` → min-height 32 & padding 4px; `--inset` menarik baris 8px ke tepi kolom supaya area hover/sentuh melebar tanpa menggeser teks. |
| `.ark-list__row--link` | Dipakai sebagai `<a>` atau `<button>`; hover = `--ark-surface-hover` saja. Dilarang translate/scale/bayangan. Baris non-interaktif (mis. membawa tombol) tetap `.ark-list__row` tanpa modifier. |
| `.ark-meter` | Track 4px (`--ark-space-1`), lebar terisi dari `--ark-meter-value`, state `.is-warning` (>80%) dan `.is-critical` (penuh) mengganti warna isian. Wajib `role="progressbar"` + `aria-valuenow/min/max` + `aria-valuetext`; angka manusia ada di `__value`, bukan hanya di warna. Bukan kartu, bukan donat, bukan angka 24px yang berdiri sendiri. |
| `.ark-tile-grid` / `.ark-tile` | `repeat(auto-fill, minmax(--ark-tile-min, 1fr))` gap `--ark-space-3`. Tile = satu border `--ark-border-default`, radius `lg`, padding `--ark-pad-card`, **tanpa bayangan**; `:hover` dan `.is-selected` hanya mengubah border/latar. `__actions` menempel di bawah (`margin-top:auto`) agar tile setinggi apa pun tetap sejajar. |
| `.ark-toolbar` | Deretan kontrol di atas daftar (cari + penyaring + pemilih tampilan). `flex` `space-between` dengan `__group` (wrap, gap 8) dan `__search` (260px, menyusut); **tanpa pembungkus card dan tanpa border**. Kontrol di dalamnya memakai `ark-select--sm` (28) dan `ark-segmented--sm` supaya satu baris tetap tinggi 32. |
| `.ark-chip` | Penyaring aktif: `.ark-badge.ark-badge--neutral` + `<a>`/`<button>` pembuang berisi ikon `x` 12px. Kelas ini hanya mengatur warna kontrol pembuang (tertiary → primary saat hover); bentuk & tinggi tetap milik badge. |
| `.ark-notice` | Catatan yang mengubah tindakan (retensi sampah, kuota, percobaan login). Hairline + `--ark-surface-subtle`, radius `md`, ikon garis 16px `__icon`, teks `__text`. `--warning` / `--danger` / `--success` mengganti latar/border/ikon dengan token `*-subtle` + `*-border`. Bukan banner besar, bukan ikon di dalam kotak. |
| `.ark-split` | Grid `1.6fr / 1fr` gap 16 untuk pasangan blok pendek (daftar aktivitas + indikator). Satu kolom di bawah 1024px. Ini ritme, bukan permukaan: garis tiap kolom tetap milik seksinya. |
| `.ark-measure` | Pembatas lebar baca (56rem) untuk halaman formulir/penjelasan (`settings`, `billing`). Ia flex column dengan gap `--ark-gap-stack-lg` yang sama, jadi memindahkan blok ke dalamnya tidak mengubah ritme vertikal. |

Tombol yang berdiri langsung di dalam `.ark-section` (`> .ark-btn`) di-`align-self: flex-start` supaya tidak melebar jadi baris penuh.

Yang **tidak** disediakan berkas ini dan memang tidak boleh ada: stat card bewarna, ikon di dalam kotak ber-hue, card di dalam card, dan pembungkus panel untuk list atau tabel. Contoh perakitannya ada di `docs/design-system.html` seksi "Komposisi halaman datar" dan di `views/pages/dashboard.php`.

---

## 6. Helper layout

Lima helper saja — bukan utility framework: `.ark-stack` `.ark-row` `.ark-cluster` `.ark-between` `.ark-grow`, semuanya `display:flex` + `gap` dari token. Untuk susunan halaman yang lebih dari sekadar baris flex, jangan merangkai `style="display:grid"` di view: tambahkan primitif ke `page.css` (lihat `.ark-tile-grid`, `.ark-split`). Grid inline hanya boleh di halaman pratinjau.

---

## 7. Aksesibilitas

- **Kontras** (WCAG 2.2 AA, teks biasa 4.5:1, teks besar 3:1, batas komponen 3:1) tercantum penuh di tabel "Kontras" pada halaman pratinjau. Dua deviasi **disengaja dan didokumentasikan**: `--ark-text-tertiary` 2.9:1 (hanya placeholder/ikon dekoratif/counter nol — dilarang untuk instruksi) dan `--ark-border-default` 1.3:1 (batas komponen tidak pernah satu-satunya penanda; label terlihat + ring fokus 6.3:1).
- **Fokus**: satu gaya global `outline: 2px solid --ark-border-focus; offset 2px` lewat `:focus-visible`; radius tidak dipaksakan agar mengikuti komponen. Kontrol form beralih ke ring lembut. Tidak ada `outline: none` tanpa pengganti.
- **Fokus keyboard** diuji per komponen: menu (panah/Home/End/Escape), modal (trap + restore), tab, checkbox (input asli tetap fokusabel), dropzone.
- **Target sentuh**: `--ark-target-min` 44px dan `@media (pointer: coarse)` menaikkan tinggi kontrol/baris tanpa mengubah markup.
- **Perangkat bantu**: ikon `aria-hidden`, teks `ark-sr-only` untuk label ikon, `aria-current`, `aria-sort`, `aria-selected`, `aria-expanded`, `aria-invalid`, `aria-describedby`, live region untuk toast.
- **Gerak**: `prefers-reduced-motion` mematikan animasi skeleton, spinner tetap tapi melambat, toast/menu/modal kehilangan transisi.
- **Tipografi**: lantai 11px hanya untuk label UPPERCASE/counter; isi terkecil 12px; tidak ada teks yang hanya mengandalkan warna.

---

## 8. Peta adopsi (Tailwind → Ark)

Status hari ini: **`views/layouts/app.php` dan seluruh `views/pages/*.php` sudah pindah ke Ark** (sidebar, drawer, header, kontainer, kepala halaman, tabel, form). Yang masih memakai Tailwind + `assets/css/style.css`: `views/layouts/modals.php`, markup yang dibangun JS, dan badge yang **di-emit backend** (`src/Helpers.php::getFileMeta()`) — yang terakhir tidak terlihat oleh grep di `views/` tetapi wajib tetap berfungsi bila CSS utilitas lama dicabut bertahap.

| Kelas hari ini | Pengganti Ark |
|---|---|
| `text-xs` (12px Tailwind) | `.ark-helper` / `.ark-meta` |
| `text-sm` (14px) | `.ark-body` (13px) atau `.ark-label` |
| `text-lg`/`text-xl`/`text-2xl` judul halaman | `.ark-title-page` (24/600 — satu-satunya 24px di halaman) |
| `font-semibold`/`font-medium` | sudah tercakup resep peran |
| `text-gray-400/500` | `.ark-meta` (`--ark-text-secondary`) |
| `text-gray-900` | `.ark-title-*` / `--ark-text-primary` |
| `rounded-lg`/`rounded-xl` card | `--ark-radius-lg` (8) |
| `shadow-sm`/`shadow-md` | `--ark-shadow-sm` hanya untuk lapis melayang |
| `bg-indigo-600 text-white` tombol | `.ark-btn--primary` |
| `border-gray-200/300` | `--ark-border-default` / `--ark-border-strong` |
| `px-4 py-2` kontrol | token `--ark-pad-control-*` |
| `hover:bg-gray-50` baris | `tr:hover` komponen tabel |
| `bg-rose-50 text-rose-600 border-rose-200` (Helpers) | `.ark-badge--pdf` (`--ark-hue-pdf-*`) |
| `bg-blue-50 text-blue-600 border-blue-200` | `.ark-badge--doc` |
| `bg-emerald-50 …` | `.ark-badge--sheet` |
| `bg-amber-50 …` | `.ark-badge--image` |
| `bg-purple-50 …` | `.ark-badge--zip` |
| `z-[9999]`, `h-[90vh]`, `max-w-[220px]` | token z-index / `max-height` modal / `--ark-menu-w-max` |
| `id="toast-container"`, `.dropzone`, `.modal-backdrop` | tetap: `#toast-container`, `.ark-dropzone`, `.ark-scrim` |

Kelas JS lama yang di-hardcode (`bg-white`, `text-indigo-600`, `border-indigo-600`, `text-amber-500`, `text-slate-300/400/500` pada `assets/js/documents.js`) harus diganti toggle atribut/kelas `.is-*` saat halaman tersebut diadopsi.

---

## 9. Menjalankan pratinjau

```powershell
php -S localhost:8000          # dari root repo
```

Buka `http://localhost:8000/docs/design-system.html`. Halaman ini statis: hanya memuat `tokens.css`, `base.css`, 16 berkas komponen, `assets/js/icons.js`, dan `assets/js/design-system.js`. Tidak ada Tailwind, tidak ada CDN, tidak ada langkah build, dan tidak menyentuh backend atau `assets/css/style.css`.

Kerangka aplikasinya punya halaman pratinjau sendiri: `http://localhost:8000/docs/app-shell.html` (memuat `icons.js` + `app-shell.js`, **tanpa** `design-system.js`). Tambahkan `?page=favorites` untuk melihat status navigasi aktif berpindah; kecilkan jendela di bawah 1024px untuk melihat drawer.

Versi hidup sudah menjadi kerangka aplikasi: buka `http://localhost:8000/` — halaman ini memakai workspace, statistik penyimpanan, dan menu asli lewat `views/layouts/app.php`. Status nav di aplikasi dimiliki server: PHP langsung menandai `aria-current="page"` dari `$page`, dan `app-shell.js` hanya mencocokkan `?page=` dari query string (fallback `data-shell-page` dipakai pratinjau statis yang tidak punya routing), jadi tidak ada penanda aktif yang bisa diubah dari klien tanpa mengubah URL.

---

## 10. Batasan yang diketahui

- Belum ada tema gelap. Token sudah memisah peran (`surface/text/border`) sehingga tema gelap cukup menimpa `:root`, tapi skalanya belum dirancang.
- Kerangka aplikasi sudah dipakai `views/layouts/app.php`, tetapi **isinya belum**: `views/pages/*.php`, `views/partials/*.php`, `views/layouts/modals.php` masih Tailwind + `style.css`. Kartu statistik dan baris unggah belum punya komponen Ark — keduanya muncul di halaman aplikasi dan harus dibuat sebelum adopsi penuh, kalau tidak tampilannya akan juxtaposisi dengan `.ark-*` di sekelilingnya.
- `assets/js/design-system.js` adalah **peraga** (interaksi pratinjau), bukan runtime aplikasi; ia tidak boleh dimuat oleh view. Yang sudah dipakai produksi hanyalah `icons.js` (registry bentuk ikon) dan `app-shell.js` (perilaku kerangka).
- `frontend/ui/meters.ts` memasang storage meter pada `.sidebar-storage-box`; footer Ark tidak lagi memakai kelas itu, jadi meter TS tersebut inert (guard-nya no-op, `?ui=ts` tetap jalan). Belum dipindahkan ke `.ark-usage` / `.ark-progress`.
- Tidak ada font yang di-*preload*; Inter diambil dari stack sistem, jadi tampilan bisa sedikit berbeda saat Inter tidak terpasang.
