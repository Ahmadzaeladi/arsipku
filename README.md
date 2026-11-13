# ArsipKu — SaaS Digital Archive Management System

ArsipKu adalah aplikasi **SaaS Digital Archive Management System** berbasis **PHP Native** (Strictly Zero Framework) yang dirancang untuk menyimpan, mengelola, mencari, melihat pratinjau, dan membagikan dokumen digital secara aman dengan arsitektur multi-tenant terisolasi.

---

## 🚀 Fitur Utama

- **Workspace**: Data workspace dan keanggotaannya dikelola di MySQL dan hanya dapat diakses setelah login.
- **Modern & Clean UI/UX**: Tampilan terinspirasi Linear & Notion dengan palet warna Indigo, tipografi Inter, dan layout yang responsif untuk desktop, tablet, dan smartphone.
- **Tampilan Fleksibel**: Toggle tampilan List (Tabel data lengkap) dan Grid (Kartu thumbnail/ikon berkas) dengan memori preferensi pengguna.
- **Pencarian Cerdas & Filter Multi-Kriteria**: Pencarian real-time (debounced), filter berdasarkan tipe berkas (PDF, Word, Excel, Gambar, ZIP, Teks), filter rentang waktu, dan sorting dinamis.
- **Hierarki Folder & Subfolder**: Navigasi struktur direktori tanpa batas dengan breadcrumbs interaktif.
- **Multi-File Upload Drag & Drop**: Dropzone interaktif dengan progress bar live per berkas, antrean unggah, dan retensi nama berkas otomatis.
- **Viewer Pratinjau Dokumen**: Dukungan pratinjau langsung untuk file PDF, Gambar (PNG, JPG, WEBP), Kode/Teks, serta kartu fallback informatif untuk file Excel/Word/ZIP lengkap dengan panel metadata berkas.
- **Ubah Nama (Rename) Aman**: Dialog rename dengan proteksi otomatis terhadap ekstensi berkas agar tidak terhapus.
- **Manajemen Keranjang Sampah**: Soft delete dengan fitur Pulihkan (Restore) dan Hapus Permanen.
- **Kolaborasi Workspace**: Undang email akun terdaftar dengan pilihan role, notifikasi dalam aplikasi, dan konfirmasi Terima/Tolak oleh penerima.
- **Share Link Anggota**: Atur role, persetujuan Owner/Admin atau gabung langsung, masa berlaku, batas penggunaan, dan pencabutan link.
- **Registrasi Email**: Buat akun memakai nama, email, dan kata sandi; workspace Personal dibuat otomatis.
- **Pengaturan Profil**: Ubah nama/foto akun, ganti kata sandi, dan atur logo workspace (Owner).
- **Floating Multi-Select Toolbar**: Memilih banyak berkas sekaligus untuk pemindahan atau penghapusan massal.
- **Database Otomatis**: Dilengkapi migrasi otomatis dan database seeder ke MySQL dengan data realistis Bahasa Indonesia.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.x Native (Object-Oriented Services, PDO MySQL, Fileinfo, Session).
- **Database**: MySQL / MariaDB (Database: `arsipku`, Engine: InnoDB, Charset: utf8mb4).
- **Styling**: Tailwind CSS + Custom CSS (`assets/css/style.css`).
- **Frontend Interactivity**: Pure Modular Vanilla JavaScript (`assets/js/`).
- **Dependencies**: 0 External Frameworks (Tanpa Laravel, Tanpa Composer).

---

## ⚙️ Konfigurasi Database (MySQL)

Konfigurasi database dapat disesuaikan melalui berkas **`.env`** atau langsung di **`src/Config.php`**:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=arsipku
DB_USER=root
DB_PASS=
```

> **Catatan**: Jika menggunakan XAMPP atau Laragon standar, `DB_USER=root` dan `DB_PASS=` (dikosongkan). Database `arsipku` dan seluruh tabelnya akan dibuat serta di-seed secara otomatis saat aplikasi pertama kali dibuka.
> Anda juga dapat mengimpor file `database.sql` secara manual melalui phpMyAdmin jika diinginkan.

### Sumber data aplikasi

Data bisnis aplikasi (workspace, dokumen, folder, anggota, statistik, dan aktivitas)
dibaca atau diubah melalui backend PHP dan PDO MySQL/MariaDB. Jika koneksi MySQL atau
endpoint gagal, aplikasi menampilkan kegagalan tersebut; tidak ada fallback ke SQLite,
fixture, mock, atau data bisnis di browser. Preferensi antarmuka seperti tampilan tabel/grid
dan status sidebar boleh disimpan di `localStorage`, tetapi bukan sumber data bisnis.

Isi berkas tetap disimpan di direktori `uploads/`; baris metadata dan relasi berkas disimpan
di MySQL. Seed awal juga dimasukkan ke MySQL, bukan dibaca dari file database lokal.
Akun login dan profil aktif dibaca dari tabel `users` di MySQL; password hanya disimpan sebagai
hash. Registrasi email tersedia dan membuat workspace Personal. Verifikasi email dan login tanpa
kata sandi belum tersedia karena layanan pengiriman email belum dikonfigurasi.

Jika database sudah ada sebelum fitur undangan anggota, terapkan migrasi
[`migrations/20261113_member_invitations.sql`](migrations/20261113_member_invitations.sql)
satu kali. Profil akun dan logo workspace tersedia melalui migrasi
[`migrations/20261113_account_workspace_profiles.sql`](migrations/20261113_account_workspace_profiles.sql);
aplikasi juga memastikan kolom tersebut tersedia saat koneksi database dibuat. Instalasi baru
mendapat struktur tabel dari `database.sql`. Panduan pemakaian ada di
[`docs/member-invitations.md`](docs/member-invitations.md), sedangkan kontrak endpoint ada di
[`docs/api-contract.md`](docs/api-contract.md).

---

## ⚡ Cara Menjalankan

1. Pastikan PHP 8.x telah terpasang di sistem Anda.
2. Buka terminal pada folder proyek `c:\Arsip digital`.
3. Jalankan server bawaan PHP:
   ```bash
   php -S localhost:8000
   ```
4. Buka peramban web dan kunjungi:
   ```
   http://localhost:8000
   ```
5. Untuk sengaja mengosongkan database dan membuat satu akun Owner, siapkan environment
   `ARSIPKU_RESET_PASSWORD`, lalu jalankan `php reset_data.php --name="Nama" --username=username`
   dari PowerShell dan konfirmasi dengan `Y`. Perintah ini destruktif: data MySQL dan berkas
   langsung di `uploads/` akan dihapus. Password tidak boleh diberikan sebagai argumen CLI.

---

## 🧪 Menjalankan Automated Tests

Jalankan suite utama serta pengujian alur undangan/registrasi:
```bash
php tests/run_tests.php
php tests/member_invitation_tests.php
```

---

## 📖 Dokumentasi Lengkap

Untuk arsitektur mendalam, skema database lengkap, panduan API endpoint, dan roadmap pengembangan lanjutan, silakan baca [project.md](project.md).
