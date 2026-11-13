# KONTRAK API ARSIPKU

Dokumen ini adalah **kontrak frontend**, bukan klaim bahwa semua endpoint sudah tersedia.
Frontend aktif mengambil data bisnis dari halaman PHP dan API yang membaca/menulis MySQL.
Endpoint yang belum tersedia tidak memiliki data pengganti: pemanggilannya harus gagal dan
ditampilkan sebagai error, bukan diisi fixture, mock, SQLite, atau state bisnis browser.

Penomoran section dipakai sebagai rujukan di dalam kode. Jangan mengubah nomor yang sudah dikutip:

| # | Domain | rujukan kode |
|---|---|---|
| [1](#1-authentication) | Authentication | `frontend/services/auth.ts`, `frontend/types/auth.ts` |
| [2](#2-workspace) | Workspace | `frontend/services/workspaces.ts` |
| [3](#3-workspace-members) | Workspace members | `frontend/services/members.ts` |
| [4](#4-documents) | Documents | `frontend/services/documents.ts` |
| [5](#5-folders) | Folders | `frontend/services/folders.ts` |
| [6](#6-upload-dan-download) | Upload dan download | `frontend/services/uploads.ts` |
| [7](#7-document-versions) | Document versions | `frontend/services/versions.ts` |
| [8](#8-permissions) | Permissions | `frontend/services/permissions.ts` |
| [9](#9-favorites) | Favorites | `frontend/services/favorites.ts` |
| [10](#10-search) | Search | `frontend/services/search.ts` |
| [11](#11-trash-dan-restore) | Trash dan restore | `frontend/services/trash.ts` |
| [12](#12-subscription-dan-storage-usage) | Subscription dan storage usage | `frontend/services/subscription.ts` |
| [13](#13-activities) | Activities | `frontend/services/activities.ts` |

Status tiap file endpoint tercatat di [`frontend/lib/endpoints.ts`](../frontend/lib/endpoints.ts):
`live` = script PHP-nya sudah ada hari ini, `planned` = belum tersedia dan tidak boleh diperlakukan
seolah-olah data berhasil dimuat.

### Sumber data dan penyimpanan

- Metadata dan data bisnis (workspace, dokumen, folder, anggota, statistik, dan aktivitas) bersumber
  dari MySQL/MariaDB melalui PHP/PDO; bila MySQL/API gagal, tampilkan kegagalan tersebut.
- Berkas fisik tersimpan di `uploads/`, sedangkan metadata/lokasi berkas disimpan di MySQL.
- `localStorage` hanya boleh menyimpan preferensi UI atau slug workspace aktif; bukan cache atau
  sumber cadangan untuk data bisnis.
- Isi katalog paket pada halaman billing adalah konfigurasi tampilan; nilai paket aktif dan kuota
  workspace tetap dibaca dari MySQL.

---

## 0. Konvensi umum

### 0.1 Bentuk URL dan routing

Backend hari ini **action-based**, bukan RESTful. Kontrak mempertahankan bentuk itu karena
frontend membangun URL dari satu fungsi (`lib/endpoints.ts`), tetapi seluruh aksi juga dapat
dipetakan ke router Laravel tanpa mengubah isi dokumen ini.

```
GET  {apiBase}/{file}.php?action={action}&{params}&ws={slug}
POST {apiBase}/{file}.php?action={action}&ws={slug}     body: JSON
```

- `apiBase` default `api`, dapat diubah lewat `window.__ARSIPKU__.apiBase` atau
  `<body data-arsipku-api-base="...">`.
- Pada request POST, `action` dikirim **dua kali**: di query string dan di dalam body JSON
  (`lib/http.ts:withAction`). Router boleh membaca salah satunya.
- Upload memakai `multipart/form-data`; field `Content-Type` sengaja tidak diset agar browser
  menambah boundary.

### 0.2 Header

| Header | Kapan | Catatan |
|---|---|---|
| `Accept: application/json` | selalu | respons HTML = gagal (`MALFORMED_RESPONSE`) |
| `X-Requested-With: XMLHttpRequest` | selalu | aktif selama `config.identifyAjax` true; dipakai `api/*.php` untuk membedakan AJAX dari page load |
| `X-CSRF-TOKEN: <token>` | semua non-GET | dibaca dari cookie `XSRF-TOKEN` yang **harus tidak httpOnly**; nama cookie/header dapat dikonfigurasi |
| `Content-Type: application/json; charset=utf-8` | POST JSON | |
| Cookie sesi | selalu | `credentials: 'same-origin'`; sesi PHP adalah sumber identitas |

### 0.3 Workspace context

Setiap request yang berscope workspace membawa `ws=<slug>`. Slug dibaca frontend dari
`window.__ARSIPKU__.workspaceSlug`, `body[data-arsipku-workspace]`, `?ws=` di URL, lalu
`localStorage.arsipku_active_workspace` (lihat `frontend/lib/workspaceContext.ts`). Nilai ini
hanya memilih konteks request; hasil workspace dan datanya tetap harus divalidasi/dimuat server.

**Aturan keras:** `ws` adalah *transport context*, bukan mekanisme autorisasi.

- Server wajib memetakan slug → `workspace_id`, lalu memfilter **setiap** query dengan
  `WHERE workspace_id = ?` dan memeriksa keanggotaan pemanggil.
- Jika `ws` kosong, server fallback ke workspace aktif di sesi.
- Jika `ws` menunjuk workspace yang tidak boleh diakses pemanggil → `403 FORBIDDEN`
  (bukan `404`, agar pesan error jelas), atau `422 WORKSPACE_CONTEXT_MISSING` bila slug tidak dikenal.
- Domain yang sengaja **tidak** berscope: `auth.php`, `billing.php?action=plans`
  (frontend mengirim `ws: null` di sana).

### 0.4 Encoding parameter

Aturan serialisasi ada di `lib/endpoints.ts:toQuery()`, dan backend harus memperlakukan
nilainya seperti ini:

| Nilai | Dikirim sebagai | Catatan |
|---|---|---|
| `undefined`, `null`, `''` | tidak dikirim | jangan andalkan "parameter kosong" |
| `true` | `key=1` | |
| `false` | tidak dikirim | PHP memakai `!empty($_GET['favorite'])`, jadi absen = false |
| array | `key=a&key=b` | berulang, tanpa `[]` |
| `Date` | ISO-8601 | |

Untuk body JSON, `false` dan `null` **tetap dikirim** sebagai nilai JSON.

### 0.5 Amplop respons

Semua endpoint diharapkan mengembalikan:

```json
{
  "success": true,
  "message": "Dokumen berhasil dipindahkan.",
  "data": { }
}
```

| Kunci | Wajib | Keterangan |
|---|---|---|
| `success` | ya | `false` selalu dianggap error meskipun HTTP 200. Jika kunci ini **absen**, frontend menganggap HTTP 2xx sebagai sukses (`normalize.ts:readSuccess`) |
| `message` | ya untuk write | teks Bahasa Indonesia yang langsung ditampilkan sebagai toast; jika absen frontend memakai fallback copy sendiri |
| `data` | bila ada payload | |
| `meta` | untuk list terpaginasi | lihat 0.6 |
| `links` | opsional | `first/last/prev/next` |
| `code` | saat gagal | lihat 0.7 |
| `errors` | saat gagal validasi | `{ field: ["pesan", ...] }`; string tunggal juga diterima |

Kunci payload alternatif yang diterima client (warisan endpoint lama):
`workspace`, `stats`, `uploaded`, `workspaces`, `documents`, `folders`, `members`, `is_favorite`, `count`.
Endpoint baru sebaiknya **hanya** memakai `data` + `meta`; daftar di atas dijaga agar PHP lama tetap
jalan tanpa perubahan.

### 0.6 Pagination

Kontrak list:

```json
{
  "success": true,
  "data": [ /* item */ ],
  "meta": { "page": 2, "per_page": 20, "total": 87, "total_pages": 5, "has_more": true },
  "links": { "first": "...", "last": "...", "prev": "...", "next": "..." }
}
```

- Param masuk: `page` (1-based, default 1) dan `per_page` (20 untuk daftar di UI). Beberapa
  agregasi client meminta hingga 1000 baris dalam satu request (mis. statistik Sampah, karena
  belum ada `action=stats`) — server boleh membatasi `per_page`, tapi **lebih baik sediakan
  endpoint statistik** daripada memotong permintaan itu diam-diam.
  `limit` dipakai sebagian endpoint lama sebagai alias jumlah item.
- Jika `meta` tidak dikirim, frontend mensintesis `total = jumlah item` dan halaman tunggal
  (`normalize.ts:paginate`). Ini membuat respons "array polos" masih jalan, tetapi `has_more`
  menjadi tidak akurat — jadi **sertakan `meta`** pada semua endpoint list baru.
- `has_more` dihitung server (`page < total_pages`); jangan digantikan dengan tebakan client.
- Bentuk paginator Laravel (`{ data, from, to, total, per_page, current_page, last_page, next_page }`)
  dapat dipakai asalkan pemetaan `meta` ditambahkan; frontend hari ini hanya membaca `meta`/`links`.

### 0.7 Format error

```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "Nama dokumen tidak boleh kosong.",
  "errors": { "name": ["Nama dokumen tidak boleh kosong."] }
}
```

| `code` | HTTP | Kapan | Perilaku client |
|---|---|---|---|
| `UNAUTHENTICATED` | 401 | sesi habis / belum login | event `auth:expired` → toast "Sesi berakhir"; `auth.session()` menganggapnya belum login |
| `FORBIDDEN` | 403 | bukan anggota / hak kurang | toast error, tidak di-retry |
| `NOT_FOUND` | 404 | id di luar workspace aktif atau benar-benar hilang | toast error |
| `VALIDATION_FAILED` | 422 (atau 400) | input ditolak | `errors` dipetakan ke field |
| `CONFLICT` | 409 | nama ganda, versi ganda, double-submit | toast error |
| `WORKSPACE_NOT_FOUND` | 404 | slug tidak dikenal | toast error |
| `WORKSPACE_CONTEXT_MISSING` | 422 | `ws` wajib tapi tidak dikirim | toast error |
| `STORAGE_QUOTA_EXCEEDED` | 422 | kuota penuh | toast + meter merah |
| `FILE_TOO_LARGE` | 413 | melebihi `max_bytes` | baris antrean gagal |
| `UNSUPPORTED_FILE_TYPE` | 422 | ekstensi/MIME ditolak | baris antrean gagal |
| `PARTIAL_UPLOAD` | 502 | koneksi putus, file tidak terkonfirmasi | baris antrean gagal |
| `LAST_WORKSPACE` | 409 | mencoba menghapus workspace terakhir | toast |
| `RATE_LIMITED` | 429 | throttle | **di-retry** oleh client |
| `SERVER_ERROR` | 5xx | kegagalan tak terduga | **di-retry** (GET saja) |
| `MALFORMED_RESPONSE` | - | body bukan JSON | error lokal, bukan dari server |

`code` boleh dibatasi dari HTTP status saja bila server tidak mengirimnya
(`errors.ts:codeFromStatus`: 400/422 → `VALIDATION_FAILED`, 408 → `TIMEOUT`, 415 →
`UNSUPPORTED_FILE_TYPE`, 409 → `CONFLICT`, 413 → `FILE_TOO_LARGE`, dst.). Sebagai pelindung
terakhir, client juga menebak kode dari isi `message` (`errors.ts:MESSAGE_HINTS`, mis. "kuota"
→ `STORAGE_QUOTA_EXCEEDED`), jadi pesan Bahasa Indonesia yang konsisten membantu diagnostik —
tetapi jangan mengandalkannya: kirim `code`. Retry policy: hanya GET, `maxRetries` 2, backoff 400ms eksponensial;
status 0/429/5xx layak retry, 4xx (kecuali 429) langsung dilempar. Timeout: GET 15s, write 30s,
upload 120s.

### 0.8 Tipe nilai dan tanggal

Baris PDO MySQL hari ini dapat mengirim `0/1` dan angka sebagai string. Normalizer frontend
(`lib/normalize.ts`) meng-coerce-nya, jadi kedua bentuk diterima; **yang direkomendasikan** adalah
JSON bertipe benar: `boolean` untuk flag, `number` untuk byte/ukuran, `null` eksplisit (bukan `""`)
untuk kolom kosong.

Semua timestamp: ISO-8601 dengan zona waktu (`2026-03-24T14:30:00+07:00`). Format lama
`YYYY-MM-DD HH:MM:SS` masih dapat dibaca (`lib/format.ts:parseDate`), tetapi jangan dipakai untuk
endpoint baru. Jangan kirim label hasil render (`formatted_size`, `time_ago`) sebagai satu-satunya
sumber; frontend menghitung sendiri tampilannya.

### 0.9 Aturan autorisasi yang diuji dari sisi client

1. Tidak ada satu pun flag client yang membuka akses. UI boleh menyembunyikan tombol dari
   `capabilities`, tetapi server **wajib** memeriksa ulang setiap aksi.
2. Aksi terhadap id milik workspace lain harus gagal, bukan mengembalikan data (`404`/`403`).
3. Aksi destruktif yang butuh konfirmasi nama (`delete workspace`, `empty trash`) membandingkan
   string di server, bukan di browser.
4. `role` pada respons hanya untuk tampilan.

---

## 1. Authentication

**File:** `api/auth.php` — status **live** untuk `csrf`, `me`, `login`, `register`, dan `logout`.
Akun tersimpan di tabel MySQL `users`; password disimpan sebagai hash `password_hash()`, bukan
teks biasa. Registrasi email membuat workspace Personal dan Owner pertama secara otomatis.
Profil dan kata sandi dikelola lewat `api/account.php`.

| Action | Method | Body/params | Respons `data` |
|---|---|---|---|
| `csrf` | GET | unscoped | `csrf_token` |
| `me` | GET | unscoped | `AuthSession` |
| `login` | POST | `username` atau `email`, `password` | `AuthSession` |
| `register` | POST | `name`, `email`, `password`, `password_confirmation` | `AuthSession` |
| `logout` | POST | `_csrf` atau header `X-CSRF-TOKEN` | hanya `message` |
| `forgot_password`, `reset_password` | POST | Belum tersedia | 501 |

`POST api/account.php` menerima `multipart/form-data` dan token `_csrf`:

| Action | Field | Ketentuan |
|---|---|---|
| `update_profile` | `name`, `avatar?`, `remove_avatar?` | Nama 1-150 karakter; avatar JPG/PNG/WebP maksimal 2 MB |
| `change_password` | `current_password`, `new_password`, `password_confirmation` | Password lama wajib benar; password baru minimal 8 karakter |

`POST api/workspaces.php` action `update_profile` memperbarui nama, kategori, dan logo workspace.
Hanya Owner yang dapat mengubahnya; logo menerima JPG/PNG/WebP maksimal 2 MB.
Gambar disimpan dengan nama acak pada `uploads/`, dan database hanya menyimpan nama file.

Session menggunakan cookie `HttpOnly`, `SameSite=Lax`; login merotasi session id. Login form dan
endpoint login/logout/register memvalidasi CSRF token. Registrasi saat ini menggunakan email dan
kata sandi; verifikasi email dan login tanpa kata sandi memerlukan konfigurasi pengiriman email
yang belum tersedia.

`GET /api/auth.php?action=me`

```json
{
  "success": true,
  "message": "",
  "data": {
    "authenticated": true,
    "user": {
      "id": "usr_owner_01", "username": "username", "name": "Nama", "email": "username@arsipku.local",
      "role": "Owner", "avatar": "uploads/avatar_<random>.png"
    },
    "active_workspace": "workspace-utama",
    "workspaces": [
      { "id": "ws_main", "name": "Workspace Nama", "slug": "workspace-utama",
        "type": "company", "logo_path": "workspace_<random>.png", "user_role": "Owner" }
    ]
  }
}
```

Aturan:

- `action=me` mengembalikan `authenticated: false` untuk sesi anonim, tidak mengembalikan 401.
- `workspaces` dijawab **dari sisi server**: hanya workspace yang benar-benar keanggotaannya aktif.
- Transport sesi: cookie httpOnly `SameSite=Lax`; tidak ada bearer token atau penyimpanan token
  autentikasi di `localStorage`.
- `csrf` mengembalikan token CSRF dan cookie `XSRF-TOKEN`; operasi POST wajib menyertakan token.
  Login dan penggantian kata sandi merotasi session id; logout mengakhiri sesi.
- Login gagal dengan HTTP 401 dan pesan generik tanpa membedakan username/email dari password salah.
- `register` membuat user baru, workspace Personal, dan keanggotaan Owner secara atomik.
  Registrasi tidak mengirim verifikasi email karena SMTP/mailer belum dikonfigurasi.

---

## 2. Workspace

**File:** `api/workspaces.php` — status **live**.

| Action | Method | Body/params | Respons |
|---|---|---|---|
| `list` | GET | `ws` tetap dikirim | `data: Workspace[]` |
| `current` | GET | | `workspace: Workspace`, `stats: WorkspaceStats` |
| `switch` | POST | `slug` | `workspace`, `message` |
| `create` | POST | `name`, `type` | `workspace`, `message` |
| `update` | POST | `name`, `type` | `message` |
| `update_profile` | POST multipart | `name`, `type`, `logo?`, `remove_logo?`, `_csrf` | `message` |
| `change_plan` | POST | lihat §12 | `message` |
| `delete` | POST | `confirm_name` | `message` |

`Workspace` (respons):

```json
{
  "id": "ws_abc", "name": "PT ABC Solusindo", "slug": "pt-abc-solusindo",
  "type": "company", "logo_path": "workspace_<random>.png", "storage_limit_bytes": 53687091200,
  "plan_name": "Enterprise Business", "plan_key": "enterprise_business",
  "user_role": "Owner", "member_count": 5,
  "created_at": "2026-01-10T09:00:00+07:00"
}
```

`WorkspaceStats` (blok `stats` pada `action=current`):

```json
{
  "total_documents": 8, "total_folders": 7, "total_size_bytes": 19293184,
  "storage_limit_bytes": 53687091200, "usage_percentage": 0.04,
  "is_warning": false, "plan_name": "Enterprise Business"
}
```

Aturan akses yang diterapkan:

- Daftar dan perpindahan workspace hanya menampilkan workspace yang pengguna aktif masuki sebagai
  anggota.
- `update` dan `update_profile` untuk nama, kategori, dan logo hanya dapat dipanggil oleh **Owner**.
  Peran lain dapat melihat informasi workspace, tetapi tidak dapat mengubahnya; otorisasi diperiksa
  server-side.
- `delete` hanya dapat dipanggil oleh **Owner** workspace. Pemeriksaan dilakukan server-side,
  sehingga menyembunyikan tombol dari anggota bukan satu-satunya proteksi.
- Workspace terakhir yang masih bisa diakses akun tidak dapat dihapus. Kegagalan ini menghasilkan
  `409 CONFLICT`; pengguna yang bukan Owner mendapat `403 FORBIDDEN`.
- Penghapusan tetap meminta `confirm_name` yang sama persis dengan nama workspace. Setelah berhasil,
  sesi berpindah ke workspace aktif lain milik pengguna.
- `usage_percentage` dihitung server dengan pembulatan 1 desimal; `is_warning` bernilai true pada
  pemakaian `>= 80%`.

---

## 3. Workspace members

**File:** `api/members.php` — status **live**. Sebelum memakai endpoint baru, jalankan
[`migrations/20261113_member_invitations.sql`](../migrations/20261113_member_invitations.sql)
di database yang sudah ada; instalasi baru juga mendapat tabel dari `database.sql`.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `list` | GET | — | `data: Member[]` |
| `invite` | POST | `email`, `role` | `data: invitation`, `message` |
| `my_invitations` | GET | — | Undangan email untuk pengguna aktif |
| `respond_invitation` | POST | `id`, `decision: accept\|decline` | Status undangan |
| `workspace_invitations` | GET | — | Undangan dan permintaan yang menunggu (Owner/Admin) |
| `resolve_invitation` | POST | `id`, `decision: accept\|reject` | Status permintaan (Owner/Admin) |
| `cancel_invitation` | POST | `id` | Membatalkan undangan email (Owner/Admin) |
| `create_link` | POST | `role`, `approval_required`, `expires_in_days`, `max_uses?` | URL tautan ditampilkan satu kali |
| `link_info` | GET | `token` | Nama workspace, role, dan kebijakan persetujuan |
| `join_link` | POST | `token` | Langsung bergabung atau meminta persetujuan |
| `invite_links` | GET | — | Metadata tautan, tanpa token rahasia (Owner/Admin) |
| `revoke_link` | POST | `id` | Menonaktifkan tautan (Owner/Admin) |
| `update_role` | POST | `id`, `role` | `message` |
| `remove` | POST | `id` | `message` |

Undangan email hanya dapat dibuat untuk email pengguna yang sudah terdaftar. Undangan masuk ke
notifikasi akun tersebut dan akses baru diberikan sesudah penerima memilih Terima. Share link
memiliki role tetap, masa berlaku 1–30 hari, batas penggunaan opsional, dan pilihan persetujuan
Owner/Admin. Token acak disimpan sebagai hash dan tidak bisa dibaca kembali setelah link dibuat.
Semua aksi `POST` memvalidasi CSRF; pengelolaan anggota/link hanya untuk anggota aktif Owner/Admin.
`respond_invitation` hanya menerima undangan email milik pengguna aktif. Owner/Admin hanya dapat
menyetujui atau menolak permintaan `source=link`; mereka tidak dapat menerima undangan email atas
nama penerima.

Contoh struktur `Member` di bawah ini juga mencakup baris undangan lama (`members.status=invited`).
Undangan email baru disimpan di `member_invitations` dan tidak memberi akses sebelum diterima.

```json
{
  "id": "mbr_abc_02", "workspace_id": "ws_abc", "user_id": "usr_siti_02",
  "name": "Siti Rahmawati", "email": "siti@abc.co.id", "avatar_url": "https://...",
  "role": "Admin", "status": "invited",
  "joined_at": "2026-02-01T09:15:00+07:00", "invited_at": "2026-02-01T09:15:00+07:00",
  "last_active_at": null,
  "capabilities": { "can_change_role": true, "can_remove": true, "is_owner": false }
}
```

Aturan:

- `role` untuk undangan/link/update_role hanya `Admin | Editor | Viewer`. **`Owner` tidak pernah
  dapat diset lewat API** → `422`.
- Hanya `Owner`/`Admin` yang boleh memanggil `invite`, `update_role`, `remove`; sisanya `403`.
- Menurunkan `Owner` atau menghapus diri sendiri/Owner → `409 CONFLICT` dengan
  `message` yang menjelaskan alasannya.
- Email yang tidak terdaftar atau role tidak valid → `422`; undangan ganda/anggota yang sudah
  aktif → `409`.
- Link yang kadaluarsa, dicabut, atau habis kuota tidak dapat digunakan. Penerima link harus
  masuk atau mendaftar dahulu dan mengonfirmasi langkah bergabung di halaman undangan.
- Batas kursi paket belum diterapkan di endpoint anggota saat ini.
- Daftar anggota saat ini belum mendukung pencarian atau pagination; endpoint mengembalikan array
  anggota untuk workspace aktif.

---

## 4. Documents

**File:** `api/documents.php` — status **live**.

### 4.1 `action=list` (GET)

| Param | Tipe | Keterangan |
|---|---|---|
| `q` | string | free-text; hari ini `LIKE %…%` atas `name`, `original_name`, `owner_name` |
| `type` | `all,pdf,word,spreadsheet,excel,image,archive,zip,txt` | bucket ekstensi, bukan MIME |
| `date_range` | `all,today,7days,30days,this_year` | filter `updated_at` |
| `folder_id` | id atau `root` | `root` = dokumen tanpa folder; absen = semua folder |
| `sort` | `updated_desc,updated_asc,name_asc,name_desc,size_asc,size_desc,created_desc` | default `updated_desc`; `created_desc` hari ini jatuh ke `updated_desc` — implementasikan yang sebenarnya |
| `favorite` | boolean flag | dikirim sebagai `favorite=1`, absen bila false |
| `shared` | boolean flag | sama seperti `favorite` |
| `trash` | boolean flag | `1` = isi Sampah (dipakai §11 sampai `trash.php` ada) |
| `owner` | string | nama atau id pengunggah |
| `limit` | number | alias jumlah item, dipakai widget "recent" |
| `page`, `per_page` | number | lihat §0.6 |

Respons: `data: Document[]` + `meta`. `Document` row:

```json
{
  "id": "doc_abc_01", "workspace_id": "ws_abc", "folder_id": "fld_abc_leg",
  "name": "Surat_Perjanjian_Kerjasama_2026.pdf", "original_name": "Surat_Perjanjian_Kerjasama_2026.pdf",
  "mime_type": "application/pdf", "extension": "pdf", "size_bytes": 2457600,
  "uploaded_by": "usr_ahmad_01", "owner_name": "Ahmad Suryanto",
  "is_favorite": false, "is_shared": true, "shared_permission": "view",
  "created_at": "2026-03-24T14:30:00+07:00", "updated_at": "2026-03-24T14:30:00+07:00",
  "deleted_at": null,
  "download_url": "api/download.php?ticket=eyJ1IjoiZG9jX2FiY18wMSJ9",
  "folder_name": "Legalitas & Perjanjian", "folder_color": "indigo"
}
```

Catatan:

- `folder_name`/`folder_color` adalah hasil JOIN dan **wajib** ikut diperbarui saat dokumen
  dipindahkan atau folder diganti nama. Frontend menurunkan label dari `folder_id` setiap baca,
  dan backend harus melakukan hal yang sama.
- `download_url` sebaiknya berupa ticket bertanda tangan + kedaluwarsa (§6.3), bukan path publik
  `uploads/...`. Bila belum ada, kirim `null` — UI menampilkan "Tautan unduh belum dikirim server"
  alih-alih tautan yang bocor ke tenant lain.
- Jangan mengandalkan filter di client: hasil `list` harus sudah terscope `workspace_id` dan
  (bila diminta) `folder_id`.

### 4.2 `action=detail` (GET, `id`)

Mengembalikan `Document` ditambah:

```json
{
  "capabilities": {
    "can_view": true, "can_download": true, "can_rename": true, "can_move": true,
    "can_delete": true, "can_share": true, "can_restore": false,
    "can_permanent_delete": false, "can_upload_version": true
  },
  "preview_url": "api/download.php?ticket=…&disposition=inline",
  "versions_count": 3, "latest_version_number": 3,
  "breadcrumbs": [ { "id": "fld_abc_leg", "name": "Legalitas & Perjanjian" } ]
}
```

`capabilities` dihitung server (lihat §8) dan hanya untuk kenyamanan tampilan.

### 4.3 Aksi write

| Action | Method | Body | `data` / catatan |
|---|---|---|---|
| `rename` | POST | `id`, `name` | `Document` baru. Jika `name` tanpa ekstensi, server menempelkan ekstensi asli; jangan pernah mengubah `extension`/`mime_type` |
| `move` | POST | `id`, `folder_id` (`root` = tanpa folder) | `message`; validasi folder tujuan milik workspace yang sama → `404 NOT_FOUND` |
| `toggle_favorite` | POST | `id` | `is_favorite` (boolean) diamping `data`; lihat §9 untuk kontrak baru |
| `delete` | POST | `id`, `kind?` | soft delete, `message` |
| `restore` | POST | `id`, `kind?`, `folder_id?` | `message`; `folder_id` diabaikan bila tujuannya sudah terhapus |
| `permanent_delete` | POST | `id`, `kind?` | `message`; hanya untuk baris yang `deleted_at`-nya terisi |
| `empty_trash` | POST | `confirm_name?`, `older_than_days?`, `kind?` | `count` **atau** `data.affected_count` (client menerima keduanya, lihat `services/trash.ts:empty()`); `data.freed_bytes` disarankan |
| `multi_action` | POST | `sub_action` (`delete,restore,permanent_delete,move`), `ids: string[]`, `target_folder_id?` | `data: { requested_count, affected_count, failed_ids: string[] }` |

Permintaan multi harus **atomic per id, bukan per batch**: satu id gagal (mis. bukan pemilik) tidak
boleh membatalkan sisanya, dan `failed_ids` wajib diisi. Frontend menampilkan
`${affected_count} dokumen ...` dan menandai baris gagal.

Konsistensi yang diminta: `rename`, `move`, `delete`, `restore`, `permanent_delete` dan
`multi_action` semuanya meng-update `updated_at`, menulis satu baris `activities`
(§13), dan menyegarkan `storage`/`trash stats` secara tepat.

---

## 5. Folders

**File:** `api/folders.php` — status **live**.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `list` | GET | `parent_id` (default `root`), `with_trashed?`, `page?`, `per_page?` | `data: Folder[]` |
| `all_flat` | GET | | `data: [{ id, name, parent_id }]` untuk seluruh workspace |
| `breadcrumbs` | GET | `folder_id?` | `data: [{ id, name, parent_id }]` dari root ke folder |
| `create` | POST | `name`, `parent_id?`, `color?` (default `indigo`) | `data: Folder`, `message` |
| `rename` | POST | `id`, `name` | `message` |
| `move` | POST | `id`, `parent_id` (`root` = top-level) | `message` |
| `delete` | POST | `id`, `cascade?` (default `true`) | `message` |

`Folder`:

```json
{
  "id": "fld_abc_adm", "workspace_id": "ws_abc", "parent_id": null,
  "name": "Administrasi Umum", "color": "indigo", "icon": "folder",
  "doc_count": 3, "subfolder_count": 1,
  "created_at": "2026-01-10T09:05:00+07:00", "updated_at": "2026-03-01T08:00:00+07:00",
  "deleted_at": null,
  "capabilities": { "can_view": true, "can_rename": true, "can_delete": true, "can_upload_into": true, "can_create_child": true }
}
```

Aturan:

- `color` hanya `indigo | emerald | blue | amber | purple`; nilai lain → `422`.
- Nama folder harus unik di dalam parent yang sama (`409 CONFLICT`).
- `move` wajib menolak siklus (folder menjadi turunannya sendiri) → `422 VALIDATION_FAILED`.
- `delete` non-cascade menolak folder yang masih berisi (`409 CONFLICT`, `message` menjelaskan
  jumlah isi); cascade memindahkan subfolder **dan** dokumen ke Sampah dengan `deleted_at` sama.
- `all_flat` adalah sumber pilihan folder di dialog Move/Upload, jadi harus mengembalikan
  seluruh hierarki non-trashed dalam satu request (tanpa paginasi) dan tetap terscope.
- `doc_count` tidak menghitung dokumen yang sudah berada di Sampah.

---

## 6. Upload dan download

**File upload:** `api/upload.php` — status **live** (single-shot).
**File download:** `api/download.php` — status **planned**.

### 6.1 `action=limits` (POST, body `{}`)

Frontend menanyakan batas sebelum menerima file. Endpoint ini belum ada; `services/uploads.ts`
jatuh ke default (`50 MB`, 15 ekstensi, `max_files_per_request: 10`) bila request gagal.

```json
{
  "success": true, "message": "",
  "data": {
    "max_bytes": 52428800, "max_files_per_request": 10,
    "allowed_extensions": ["pdf","doc","docx","xls","xlsx","ppt","pptx","png","jpg","jpeg","webp","svg","txt","csv","zip"],
    "allowed_mime_types": [],
    "remaining_bytes": 53667858024,
    "chunking_supported": false
  }
}
```

`allowed_mime_types` kosong berarti "ekstensi saja". `remaining_bytes` dari kuota workspace aktif;
`null` berarti server tidak membatasi di sisi ini.

### 6.2 Upload satu file per request

```
POST api/upload.php?ws=<slug>
Content-Type: multipart/form-data

file           <binary>            (alias lama: files[])
folder_id      fld_abc_adm         (omitted bila 'root' / Arsip Utama)
on_name_conflict  rename | new_version | reject   (opsional)
idempotency_key   opsional, untuk retry aman (kontrak; klien saat ini belum mengirimnya)
```

Sukses:

```json
{
  "success": true,
  "message": "1 file berhasil diunggah",
  "data": {
    "uploaded": [ /* Document row, sama seperti §4.1 */ ],
    "failed":   [ { "file_name": "laporan.exe", "reason": "Ekstensi .exe tidak didukung.", "code": "UNSUPPORTED_FILE_TYPE" } ]
  }
}
```

`normalizeUploadResult()` menerima baik `data.uploaded`/`data.failed` maupun `uploaded`/`errors`
di root (bentuk `api/upload.php` hari ini: `errors: string[]`). Endpoint baru cukup pakai yang pertama.

Ketentuan:

- **Satu request = satu file** dari sisi UI, supaya progress, cancel, dan error per baris antrean
  tetap akurat. Batch adalah loop, bukan multipart berisi banyak file.
- Quota diperiksa **sebelum** menulis ke disk → `422 STORAGE_QUOTA_EXCEEDED`.
- Ukuran melebihi `max_bytes` (atau `post_max_size` PHP) → `413 FILE_TOO_LARGE`.
- Ekstensi/MIME ditolak → `422 UNSUPPORTED_FILE_TYPE`.
- `on_name_conflict=reject` → `409 CONFLICT`; `new_version` membuat versi (§7) dan
  mengembalikan baris dokumen **induk**; `rename` menghasilkan nama unik (`file (2).pdf`).
- Respons **wajib** memuat baris dokumen final (`id`, `size_bytes`, `folder_id`, `folder_name`)
  karena antrean menampilkan hasil ini dan daftar ikut menyegarkan diri.
- Putus koneksi setelah byte terkirim: frontend menandai `PARTIAL_UPLOAD`; server sebaiknya
  menyimpan `idempotency_key` agar retry tidak menghasilkan duplikat.
- Simpan berkas di luar web-root (atau dengan proteksi server-side); path fisik tidak boleh
  bisa ditebak dari nama dokumen.

### 6.3 Download dan pratinjau

Kontrak target (`api/download.php`, **planned**):

| Action | Method | Body/params | Respons |
|---|---|---|---|
| `ticket` | POST | `id`, `version_id?`, `disposition?` (`attachment`\|`inline`) | `data: DownloadTicket` |
| `file` | GET | `ticket=<token>` | aliran biner (`Content-Disposition`, `Content-Length`, `Accept-Ranges`) |

```json
{
  "success": true, "message": "",
  "data": {
    "url": "https://arsipku.test/api/download.php?action=file&ticket=eyJ1IjoiZG9jX2FiY18wMSJ9",
    "expires_at": "2026-10-27T12:05:00+07:00",
    "filename": "Surat_Perjanjian_Kerjasama_2026.pdf",
    "size_bytes": 2457600,
    "mime_type": "application/pdf"
  }
}
```

- Frontend **tidak pernah** merakit path `uploads/...` sendiri; tanpa ticket, UI menampilkan
  pesan "Tautan unduhan belum dikirim server" (sudah begitu di `documentList.ts`).
- Ticket: opaque, ditandatangani server, berlaku ≤ 5 menit, sekali pakai atau terikat IP/sesi,
  dan menyimpan `document_id` + `version_id` + `workspace_id`. Permintaan lintas tenant →
  `403 FORBIDDEN`, ticket kedaluwarsa → `410 GONE` dengan `code: "UNAUTHENTICATED"` atau
  `NOT_FOUND` (frontend menampilkan pesan server apa adanya).
- `preview_url` pada §4.2 memakai ticket yang sama dengan `disposition=inline`.
- Dukung `Range` untuk pemutakan pratinjau PDF berukuran besar, dan `Cache-Control: private, no-store`
  agar dokumen tidak tersimpan di cache bersama.
- Migrasi bertahap: selama `uploads/` masih publik, `list`/`detail` boleh mengirim
  `download_url` lama, tetapi **`is_shared=false` tidak boleh menjadi satu-satunya gerbang** —
  pemeriksaan anggota tetap wajib di `download.php`.

### 6.4 Chunked upload (opsional, kontrak sudah didefinisikan tipe-typenya)

Aktif hanya bila `limits.chunking_supported = true`:

| Action | Method | Body | Respons `data` |
|---|---|---|---|
| `init` | POST | `file_name`, `size_bytes`, `mime_type`, `checksum_sha256?`, `folder_id?` | `{ upload_id, chunk_size_bytes, required_parts: number[], expires_at }` |
| `part` | POST (multipart) | `upload_id`, `part_number` (0-based), `data` | `{ part_number, received_bytes }` |
| `complete` | POST | `upload_id` | `Document` row |
| `abort` | POST | `upload_id` | `message` |

`required_parts` membuat klien bisa melanjutkan upload yang putus tanpa mengirim ulang semua byte.

---

## 7. Document versions

**File:** `api/versions.php` — status **planned**. Tabel `document_versions` belum ada, jadi aksi
versi belum tersedia. Pemanggilan endpoint yang belum ada harus gagal; jangan menampilkan riwayat,
restore, atau perbandingan versi buatan.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `list` | GET | `document_id`, `page?`, `per_page?` | `data: DocumentVersion[]`, `meta` |
| `create` | POST (multipart) | `file`, `document_id`, `label?` | `data: DocumentVersion`, `document` |
| `restore` | POST | `document_id`, `version_id` | `data: DocumentVersion` (versi baru), `document` |
| `delete` | POST | `document_id`, `version_id` | `message` |
| `compare` | GET | `document_id`, `from_version_number`, `to_version_number` | `VersionDiff` |

`DocumentVersion`:

```json
{
  "id": "ver_abc_01_v3", "document_id": "doc_abc_01", "version_number": 3,
  "label": "Revisi tanda tangan", "size_bytes": 2511000,
  "mime_type": "application/pdf", "extension": "pdf",
  "checksum_sha256": "9f2c…", "created_by": "usr_ahmad_01", "created_by_name": "Ahmad Suryanto",
  "created_at": "2026-03-24T14:30:00+07:00", "is_current": true,
  "download_url": "api/download.php?action=file&ticket=…"
}
```

Ketentuan:

- `version_number` mulai dari 1 dan **monoton per dokumen**; tepat satu versi `is_current = true`.
- `restore` **tidak membalik** riwayat: ia menyalin versi lama menjadi versi baru
  (`version_number` terbesar) sehingga audit trail utuh. Respons mengembalikan versi baru dan
  baris dokumen yang sudah disegarkan.
- Unggah versi baru tidak boleh mengubah `name`/`extension` dokumen kecuali server secara
  sengaja menyamakan ekstensi; kembalikan baris dokumen final agar UI konsisten.
- `checksum_sha256` disarankan (dipakai UI untuk menandai duplikat); `null` diterima.
- `compare` hanya boleh mengembalikan metadata + delta, **bukan** isi berkas:
  `{ from: {…}, to: {…}, size_delta_bytes: 53400, summary: "Ukuran naik 52 KB" }`.
- `delete` versi: tolak menghapus versi current (`409 CONFLICT`), dan jangan hapus berkas fisik
  bila versi lain masih menunjuk path yang sama.
- Satu dokumen = satu rantaian versi; akses mengikuti izin dokumen induk (§8), bukan terpisah.

---

## 8. Permissions

**File:** `api/permissions.php` — status **planned**.
Fakta permission hari ini hanya `documents.is_shared` + `documents.shared_permission`;
`services/permissions.ts` memetakannya secara konservatif (`edit` tidak pernah tersirat) dan
sisa kapabilitas seperti grant dan tautan berbagi belum tersedia melalui JSON API.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `effective` | GET | `resource_type` (`workspace\|folder\|document`), `resource_id` | `EffectivePermissions` |
| `grants` | GET | `resource_type`, `resource_id` | `data: AccessGrant[]` |
| `grant` | POST | `resource_type`, `resource_id`, `grantee_type`, `grantee_id?`, `permission`, `expires_at?`, `cascade?` | `data: AccessGrant` |
| `revoke` | POST | `grant_id` | `message` |
| `link_create` | POST | `resource_type`, `resource_id`, `permission?` (default `view`), `expires_in_days?`, `password?` | `data: ShareLink` |
| `link_update` | POST | `token`, `permission?`, `expires_at?`, `revoke?` | `message` — `revoke: true` adalah satu-satunya cara mencabut tautan |
| `matrix` | GET | | `PermissionMatrix` |

`EffectivePermissions`:

```json
{
  "resource_type": "document", "resource_id": "doc_abc_01",
  "role": "Editor", "permission": "edit", "source": "inherited",
  "capabilities": {
    "view": true, "download": true, "rename": true, "move": true, "delete": false,
    "restore": false, "permanent_delete": false, "share": true, "upload": true,
    "create_folder": false, "manage_members": false, "manage_workspace": false,
    "change_plan": false, "override_lock": false
  },
  "denied_actions": ["delete", "permanent_delete", "manage_workspace", "change_plan"]
}
```

`source` = `owner | workspace_role | inherited | explicit | link | none`.
`denied_actions` **selalu** dihitung server dan menang atas allow manapun.

Matriks peran yang diharapkan (dasar untuk `action=matrix`, dikirim sebagai
`{ roles: [...], actions: [...], grants: { Owner: { view: true, … } } }`):

| Aksi | Owner | Admin | Editor | Viewer |
|---|:-:|:-:|:-:|:-:|
| view / download | ✓ | ✓ | ✓ | ✓ |
| upload / create_folder | ✓ | ✓ | ✓ | – |
| rename / move | ✓ | ✓ | ✓ (milik sendiri atau semua, sesuai kebijakan) | – |
| delete (ke Sampah) / restore | ✓ | ✓ | ✓ | – |
| permanent_delete | ✓ | ✓ | – | – |
| share / manage grants | ✓ | ✓ | ✓ | – |
| manage_members | ✓ | ✓ | – | – |
| manage_workspace / change_plan / delete workspace | ✓ | – | – | – |
| override_lock | ✓ | ✓ | – | – |

`AccessGrant` dan `ShareLink`:

```json
{
  "id": "grnt_01", "workspace_id": "ws_abc", "resource_type": "folder", "resource_id": "fld_abc_adm",
  "grantee_type": "user", "grantee_id": "usr_siti_02", "grantee_label": "Siti Rahmawati",
  "permission": "view", "granted_by": "usr_ahmad_01", "granted_by_name": "Ahmad Suryanto",
  "granted_at": "2026-10-20T10:00:00+07:00", "expires_at": null, "inherited_from": null
}
```

```json
{
  "token": "sl_9f2c41", "url": "https://arsipku.test/s/sl_9f2c41",
  "resource_type": "document", "resource_id": "doc_abc_01", "permission": "view",
  "is_public": true, "password_protected": false,
  "expires_at": "2026-11-26T00:00:00+07:00", "access_count": 4, "created_at": "2026-10-27T09:00:00+07:00"
}
```

Ketentuan:

- `grantee_type` = `user | member | role | public_link`; `grantee_id` wajib kecuali
  `public_link`. Grant ke folder bersifat cascade ke descendant **kecuali** server menolak.
- Frontend tetap memeriksa ulang per-request: menyembunyikan tombol bukan pengganti `403`.
- Selama `permissions.php` belum ada, UI memakai dialog "Bagikan tautan" sebagai *deep link*
  halaman preview, bukan sebagai record sharing nyata (lihat `ui/forms.ts:openShareModal`).
- `password` pada share link tidak pernah dikembalikan; hanya `password_protected: boolean`.

---

## 9. Favorites

**File:** `api/favorites.php` — status **planned**.
`documents.is_favorite` adalah satu kolom bersama untuk seluruh anggota workspace. Kontrak
memindahkannya ke tabel per-user `document_favorites (workspace_id, user_id, document_id, created_at)`;
hingga skema per-user tersedia, API dokumen hanya dapat mengembalikan nilai bersama tersebut.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `list` | GET | `q?`, `folder_id?`, `sort?`, `page?`, `per_page?` | `data: FavoriteItem[]`, `meta` |
| `toggle` | POST | `document_id`, `favorite?` (boolean absolut) | `data: { document_id, is_favorite }` |
| `set_many` | POST | `document_ids: string[]`, `favorite: boolean` | `data: { requested_count, affected_count, failed_ids }` |
| `summary` | GET | | `data: { count, total_size_bytes }` |

Yang dilakukan client hari ini, dan apa yang harus diganti oleh `favorites.php`:

| Kebutuhan UI | Sekarang | Setelah `favorites.php` |
|---|---|---|
| daftar berbintang | `documents.php?action=list&favorite=1`; `favorited_at`/`added_by` dikirim string kosong karena belum ada histori bintang per-user | `favorites.php?action=list` mengembalikan kedua field itu terisi |
| satu bintang | `documents.php?action=toggle_favorite` dengan body `{ id }`; nilai absolut `favorite` dari hook **diabaikan adapter HTTP** | `action=toggle` dengan `{ document_id, favorite }` |
| bintang massal | loop `toggle` setelah membandingkan daftar (`per_page: 1000`) | satu panggilan `action=set_many` |
| ringkasan | dihitung dari `list` `per_page: 1000` | `action=summary` |

`FavoriteItem`: `{ document: Document, favorited_at: string, added_by: string }`.

- Semantik toggle: jika `favorite` dikirim, nilainya **absolut** (bukan di-inversi). Ini sengaja,
  karena antrean klik cepat di UI (`useDocuments.toggleFavorite`) harus bisa mengirim keadaan
  terakhir yang dilihat user; bila `favorite` absen, server boleh men-inversi.
  `is_favorite` pada respons adalah keadaan akhir, bukan "apakah berubah".
- Aksi bintang tidak mengubah `updated_at` dokumen dan tidak memicu entri aktivitas "upload";
  cukup satu `activities` dengan `action_type = favorite` (opsional).
- Kompatibilitas lama: `documents.php?action=toggle_favorite` (mengembalikan `is_favorite` di
 amping `data`) tetap dikenali frontend, tetapi implementasi baru harus memakai `favorites.php`
  agar bintang tidak lagi saling menimpa antar anggota.

---

## 10. Search

**File:** `api/search.php` — status **planned**.
Implementasi HTTP hari ini **belum memanggil `search.php` sama sekali**: `services/search.ts`
menumpang `documents.php?action=list&q=…`, lalu memetakan barisnya menjadi `SearchHit`
(`degraded: true`, scope folder/member diambil dari `folders.php?action=all_flat` dan
`members.php`). Total dibaca dari kunci `count` diamping `data` — bila `count` absen, UI hanya
tahu jumlah item yang kembali. Kontrak di bawah adalah bentuk yang cukup untuk mengganti
satu baris target action di service tersebut.

| Action | Method | Params | Respons |
|---|---|---|---|
| `query` | GET | lihat tabel di bawah | `data: SearchResult` |
| `suggest` | GET | `q`, `scope?` | `data: SearchSuggestion[]` (maks 10) |
| `saved_list` | GET | | `data: SavedSearch[]` |
| `saved_create` | POST | `name`, `params` (objek SearchParams) | `data: SavedSearch` |
| `saved_delete` | POST | `id` | `message` |

Client hari ini hanya memakai `query` (dan menurunkannya menjadi `saved_*` belum dipakai:
`suggestions()` di `services/search.ts` memanggil `query` dengan `scope=documents`, dan riwayat
pencarian disimpan di `localStorage`). Tiga action terakhir adalah bagian kontrak yang boleh
diimplementasikan belakangan tanpa mengubah UI.

Param `query`:

| Param | Nilai |
|---|---|
| `q` | teks bebas; string kosong = "browse dengan filter" |
| `scope` | `documents \| folders \| members \| all` (default `all`) |
| `area` | `this_workspace \| current_folder \| shared_with_me \| trash \| favorites` |
| `type`, `date_range`, `folder_id`, `owner`, `favorite_only`, `sort` | sama seperti §4.1 |
| `highlight` | `1` untuk snippet `<mark>` |
| `page`, `per_page` | lihat §0.6 |

`SearchResult`:

```json
{
  "hits": [
    { "kind": "document", "id": "doc_abc_01", "name": "Surat_Perjanjian_Kerjasama_2026.pdf",
      "container_name": "Legalitas & Perjanjian", "container_id": "fld_abc_leg",
      "score": 0.92, "highlight": "Surat <mark>Perjanjian</mark> Kerjasama",
      "matched_fields": ["name"], "document": { /* Document row */ } },
    { "kind": "folder", "id": "fld_abc_adm", "name": "Administrasi Umum", "container_id": null,
      "folder": { /* Folder row */ } }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 12, "total_pages": 1, "has_more": false },
  "facets": {
    "by_type": [ { "value": "pdf", "label": "PDF", "count": 7 } ],
    "by_owner": [ { "value": "usr_ahmad_01", "label": "Ahmad Suryanto", "count": 5 } ],
    "by_folder": [ { "value": "fld_abc_leg", "label": "Legalitas & Perjanjian", "count": 4 } ],
    "by_date_range": [ { "value": "30days", "label": "30 hari terakhir", "count": 9 } ]
  },
  "normalized_query": "perjanjian",
  "took_ms": 18,
  "degraded": false
}
```

Ketentuan:

- Hasil **selalu** terfilter oleh workspace aktif dan keanggotaan pemanggil; jangan pernah
  mengembalikan dokumen dari tenant lain meskipun id-nya cocok.
- PostgreSQL: `ILIKE` + `pg_trgm` (atau `tsvector` dengan konfigurasi Bahasa Indonesia) sudah
  cukup untuk memenuhi kontrak ini tanpa mesin pencarian terpisah.
- `highlight` harus sudah di-escape sisi server; frontend menyisipkannya sebagai HTML pada panel
  hasil, jadi snippet mentah dari user adalah vektor XSS.
- `normalized_query` di-echo agar UI dapat menampilkan "menampilkan hasil untuk …".
- Bila server tidak sanggup menjawab facets, kirim tanpa `facets` dan set `degraded: true`;
  frontend tetap memakai filter daftar sebagai fallback. Riwayat pencarian **terakhir** disimpan
  di `localStorage` (`arsipku_recent_searches`, maks 8), jadi tidak butuh endpoint.

---

## 11. Trash dan restore

**File:** `api/trash.php` — status **planned**.
Hari ini seluruh aksi Sampah diarahkan ke `documents.php` (`action=list&trash=1`, `delete`,
`restore`, `permanent_delete`, `empty_trash`, `multi_action`) dan itu tetap berfungsi;
kontrak di bawah adalah pemisahan yang diminta begitu router dipecah.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `list` | GET | `kind?` (`document\|folder\|all`), `q?`, `sort?`, `page?`, `per_page?` | `data: TrashItem[]`, `meta` |
| `stats` | GET | | `data: TrashStats` |
| `delete` | POST | `id`, `kind?` | `message` (saat ini `documents.php?action=delete`) |
| `restore` | POST | `id`, `kind?`, `folder_id?` | `message` |
| `purge` | POST | `id`, `kind?` | `message` |
| `batch` | POST | `action` (`delete\|restore\|permanent_delete`), `ids[]` | `TrashOperationResult` |
| `empty` | POST | `kind?`, `older_than_days?`, `confirm_name?` | `TrashOperationResult` |

`TrashItem` = dokumen/folder yang terhapus + meta pemulihan:

```json
{
  "kind": "document", "id": "doc_abc_trash_01",
  "name": "Draft_Kontrak_Kerja_Magang_Lama.docx",
  "deleted_at": "2026-09-29T08:30:00+07:00",
  "deleted_by": "usr_budi_03", "deleted_by_name": "Budi Santoso",
  "original_folder_id": "fld_abc_hr", "original_folder_name": "Sumber Daya Manusia (SDM)",
  "restore_target_available": true,
  "size_bytes": 524288, "extension": "docx",
  "purge_after": "2026-10-29T08:30:00+07:00",
  "document": { /* Document row lengkap */ }
}
```

```json
{ "document_count": 2, "folder_count": 0, "total_size_bytes": 1992294,
  "retention_days": 30, "oldest_deleted_at": "2026-09-29T08:30:00+07:00" }
```

Saat ini client men-simulasikannya dengan memanggil `list` ber-`per_page: 1000` lalu menjumlah
sendiri (`services/trash.ts:stats()`). `action=stats` yang asli menghilangkan permintaan
berukuran besar itu, jadi prioritaskan sebelum workspace tumbuh.

Ketentuan:

- `purge_after` dihitung **server** dari kebijakan retensi (default 30 hari,
  `TRASH_RETENTION_DAYS`). UI hanya menampilkan "sisa N hari", dan menulis
  "segera dihapus" bila tanggalnya sudah lewat — jangan kirim tanggal mati yang absurd
  untuk item yang di-purge manual.
- `restore`: tanpa `folder_id` → kembalikan ke folder asal; bila folder asal sudah terhapus,
  kembalikan ke Arsip Utama (`folder_id = null`) dan set `restore_target_available: false`.
  Mengembalikan ke folder milik workspace lain → `404 NOT_FOUND`.
- `purge` hanya berlaku untuk baris `deleted_at` terisi; baris aktif → `404 NOT_FOUND`.
  Purge menghapus berkas fisik **hanya** bila tidak ada versi lain yang menunjuk path sama.
- `batch` mengembalikan `requested_count`, `affected_count`, `failed_ids`, `freed_bytes?`
  dan tetap per-id atomic (§4.3).
- Apakah byte Sampah dihitung dalam kuota adalah keputusan server, tapi harus konsisten dengan
  `usage.trash_included` di §12 — meter di sidebar menampilkan keterangan itu apa adanya.
- `empty` membutuhkan `confirm_name` sama persis dengan nama workspace bila dipanggil dari UI
  (sama seperti `workspaces.php?action=delete`).

---

## 12. Subscription dan storage usage

**File:** `api/billing.php` — status **planned**.
Hari ini penggantian paket lewat `workspaces.php?action=change_plan` dan — **cacat yang harus
dihapus** — menerima `limit_bytes` dari browser. Kuota harus diturunkan server dari paket.

| Action | Method | Params/body | Respons |
|---|---|---|---|
| `overview` | GET | | `data: { subscription, usage, plans, invoices }` |
| `subscription` | GET | | `data: Subscription` |
| `usage` | GET | | `data: StorageUsage` |
| `plans` | GET | **unscoped** (`ws` tidak dikirim) | `data: Plan[]` |
| `change_plan` | POST | `plan_key`, `billing_cycle?` (`monthly\|yearly`), `idempotency_key?` | `data: { subscription, usage, over_quota_warning? }` |
| `invoices` | GET | `page?`, `per_page?` | `data: Invoice[]`, `meta` |

Unduhan invoice tidak punya action sendiri: baris `Invoice` membawa `download_url` (lihat format
ticket pada §6.3), dan UI membuka tautan itu langsung.

`Plan` (katalog global, tidak membawa `ws`):

```json
{
  "key": "enterprise_business", "name": "Enterprise Business",
  "description": "Untuk tim dengan kebutuhan arsip lintas divisi",
  "storage_limit_bytes": 53687091200, "max_members": 25, "max_upload_bytes": 52428800,
  "price_monthly_idr": 1500000, "price_yearly_idr": 15000000, "currency": "IDR",
  "features": ["Version history", "Share link"], "is_current": true,
  "available_for_workspace_type": ["company", "school"]
}
```

`Subscription`:

```json
{
  "workspace_id": "ws_abc", "plan_key": "enterprise_business",
  "plan_name": "Enterprise Business", "status": "active", "billing_cycle": "monthly",
  "current_period_start": "2026-10-01T00:00:00+07:00",
  "current_period_end": "2026-10-31T23:59:59+07:00",
  "cancel_at_period_end": false, "seat_count": 5, "seat_limit": 25,
  "payment_method_label": "Visa •••• 4242", "amount_due_idr": 1500000
}
```

`StorageUsage` (superset kapasitas dari `WorkspaceStats`):

```json
{
  "used_bytes": 19293184, "limit_bytes": 53687091200, "remaining_bytes": 53667798016,
  "usage_percentage": 0.04, "is_warning": false, "is_over_quota": false,
  "trash_included": true, "trash_bytes": 1992294,
  "breakdown": [
    { "kind": "pdf", "label": "PDF", "bytes": 12582912, "document_count": 5, "share": 0.65 }
  ],
  "largest_documents": [ { "id": "doc_abc_02", "name": "Laporan_Keuangan_Kuartal_I_2026.xlsx", "size_bytes": 1887436 } ]
}
```

`Invoice`: `{ id, number, period_start, period_end, amount_idr, status(paid|open|void|refunded), issued_at, download_url? }`

Ketentuan:

- `plan_key` wajib dikenali server; nilai tidak dikenal → `422 VALIDATION_FAILED`.
  `limit_bytes` yang datang dari klien **ditolak** (400/422) — satu-satunya sumber kuota adalah
  katalog paket.
- Turunkan paket di bawah pemakaian berjalan: boleh, tapi kembalikan `over_quota_warning`
  (teks) dan set `is_over_quota: true`; setelah itu `upload.php` menolak dengan
  `STORAGE_QUOTA_EXCEEDED`.
- `change_plan` wajib idempotent terhadap `idempotency_key` agar retry UI tidak membuat dua
  invoice.
- Hanya `Owner` (dan `Admin` bila kebijakan workspace mengizinkan) yang boleh mengubah paket;
  sisanya `403 FORBIDDEN`.
- `usage.breakdown[].share` adalah pecahan `used_bytes` (0–1) dan hanya boleh berisikan
  tipe yang benar-benar ada; frontend mengurutkan tampilan tanpa menghitung ulang.
- Nilai rupiah adalah integer biasa (bukan minor units) — sama seperti `formatIdr()` di
  `lib/format.ts`.

---

## 13. Activities

**File:** `api/activities.php` — status **planned**.
Tabel `activities` sudah ditulis oleh `DocumentService`/`FolderService::recordActivity()`, tapi
belum pernah diekspos sebagai JSON; feed yang tersedia dirender server-side. Endpoint JSON aktivitas
belum tersedia.

| Action | Method | Params | Respons |
|---|---|---|---|
| `list` | GET | `actions?` (csv), `user_id?`, `since?` (ISO), `page?`, `per_page?`, `limit?` | `data: Activity[]`, `meta` |

`Activity`:

```json
{
  "id": "act_01", "workspace_id": "ws_abc", "user_id": "usr_ahmad_01",
  "user_name": "Ahmad Suryanto", "user_avatar_url": "https://...",
  "action_type": "upload", "description": "Mengunggah dokumen \"Invoice_Vendor.pdf\"",
  "created_at": "2026-10-27T09:12:00+07:00",
  "resource_type": "document", "resource_id": "doc_abc_09"
}
```

`action_type` yang sudah diproduksikan PHP: `upload, rename, folder_create, delete, favorite,
share, restore, move`. Kontrak menambahkan: `version_upload, version_restore, permission_change,
member_invite, member_role_change, member_remove, plan_change, workspace_create, workspace_delete,
permanent_delete`.

Ketentuan:

- `description` ditulis server dalam Bahasa Indonesia dan **sudah disanitasi**; frontend
  menampilkan teks ini apa adanya, jadi nama dokumen yang dibawa user harus di-escape sebelum
  masuk ke string deskripsi. Jangan juga mengirim HTML yang sudah di-render.
- `resource_type`/`resource_id` opsional; kalau ada, UI memakai deep link
  `?page=preview&id=<resource_id>`.
- Entri tidak pernah menghapus dirinya sendiri: activity untuk dokumen yang di-purge permanen
  tetap tersimpan (audit), dengan `resource_id` yang mungkin sudah tidak ada — frontend menangani
  `404` saat membuka tautannya.
- Tanpa `meta.total` yang akurat frontend menyamakan total dengan jumlah item yang kembali, jadi
  sertakan `meta` bila feed bisa lebih panjang dari satu halaman.

---

## 14. Cara frontend bereaksi (rujukan implementasi)

Perilaku ini sudah dibangun; backend cukup menjaga kontrak di atas.

| Situasi | Reaksi frontend |
|---|---|
| `401` dari endpoint mana pun | event `auth:expired` → satu toast "Sesi berakhir. Muat ulang halaman…", tanpa redirect otomatis |
| `success:false` / 4xx / 5xx | event `api:error` + toast dengan `message` server (`useDocuments.mutate`, `useTrash.mutate`) |
| Write berhasil | resource dengan `keys` yang cocok men-refetch otomatis (`data:invalidated`), lalu toast dari `message` server bila tersedia |
| `ws` berubah | semua resource dengan `scoped !== false` di-refocus; resource non-scoped (auth, plans) tidak |
| Endpoint domain `planned` | request gagal secara eksplisit; tidak ada data pengganti |
| `download_url` / ticket absen | baris dokumen menampilkan "Tautan unduh belum dikirim server" dan tombol Unduh tidak ditautkan ke path kosong |

Dev tools yang tersedia saat integrasi:

- `?ui=ts` (atau `data-arsipku-ui="ts"`) mengaktifkan lapisan TypeScript; tanpa itu halaman memakai
  `assets/js/documents.js` lama. Keduanya tidak boleh aktif bersamaan karena mengikat id DOM yang sama.
- Di konsol: `window.ArsipKu.hooks` (state), `window.ArsipKu.takeover`.
- Tidak ada flag atau sakelar sumber data. Lapisan TypeScript hanya memakai service HTTP PHP.

---

## 15. Urutan implementasi yang disarankan

1. **`download.php` (ticket)** — menutup kebocoran lintas tenant paling parah; `uploads/` hari ini
   dunia-readable.
2. **`favorites.php`** — kecil, langsung terasa: bintang tidak lagi saling menimpa antar anggota.
3. **`document_versions` + `versions.php`** — domain versi belum tersedia.
4. **`billing.php`** — termasuk menghapus dukungan `limit_bytes` dari klien (§12).
5. **`permissions.php`** (effective + grants + share link) dan **`trash.php`** — pemisahan router
   setelah data pendukungnya ada.
6. **`search.php`** dan **`activities.php`** — peningkatan kualitas; implementasikan API MySQL
   sebelum UI TypeScript meminta domain ini.

Setiap langkah menambah endpoint PHP dan akses MySQL sesuai tabel di atas. Jangan menambah fallback
data buatan; integrasikan UI hanya setelah endpoint tersedia.
