# Panduan Undangan Anggota dan Registrasi

Fitur kolaborasi tersedia di **Workspace → Anggota**. Undangan dikelola di server; pengguna
baru hanya memperoleh akses setelah alur persetujuan yang berlaku selesai.

Penghapusan workspace hanya tersedia untuk Owner. Akun tidak dapat menghapus workspace terakhir
yang masih bisa diaksesnya. Anggota lain tidak dapat menghapus workspace, sekalipun mengirim
permintaan langsung ke API.

## Undang akun melalui email

1. Owner atau Admin membuka halaman Anggota dan memilih **Undang lewat Email**.
2. Masukkan email yang sudah memiliki akun ArsipKu dan pilih role **Admin**, **Editor**, atau
   **Viewer**. Email yang belum terdaftar harus mendaftar terlebih dahulu.
3. Pengguna yang diundang melihat notifikasi di menu Notifikasi setelah masuk ke akun tersebut.
4. Pengguna memilih **Terima** atau **Tolak**. Role ditetapkan oleh pengundang; pengguna tidak
   dapat mengubah role lewat tindakan menerima undangan.
5. Workspace hanya muncul di daftar workspace pengguna setelah undangan diterima.

Undangan email disampaikan melalui notifikasi dalam aplikasi. Sistem ini belum mengirim email
eksternal. Undangan yang masih menunggu dapat dibatalkan oleh Owner/Admin.

## Buat dan bagikan link

1. Di halaman Anggota, pilih role, masa berlaku (1–30 hari), dan batas penggunaan opsional.
2. Centang **Owner/Admin harus menyetujui** untuk menahan akses sampai permintaan disetujui.
   Jika tidak dicentang, pengguna dapat langsung bergabung setelah mengonfirmasi di halaman link.
3. Pilih **Buat share link**, lalu salin atau buka link yang baru dibuat. Link membuka dialog
   undangan ArsipKu dengan nama workspace, role, dan aturan persetujuan.
4. Penerima harus masuk atau membuat akun, lalu kembali ke dialog undangan untuk mengonfirmasi.
   Hasil bergabung atau permintaan persetujuan tampil di dialog; akses langsung baru membuka
   workspace setelah penerima memilih tombol **Buka workspace**.
5. Owner/Admin dapat meninjau permintaan link, menyetujui atau menolak, dan menonaktifkan link.

Link menyimpan role dan aturan yang dipilih saat dibuat. Token dibuat acak dan disimpan sebagai hash;
nilai token tidak ditampilkan kembali setelah pembuatan. Link yang kedaluwarsa, dicabut, atau
melewati batas penggunaan tidak dapat dipakai. Penggunaan dihitung saat bergabung atau meminta
persetujuan, bukan saat sekadar membuka halaman link.

## Registrasi

Pilih **Daftar dengan Email**, lalu isi nama, email, dan kata sandi minimal 8 karakter. Sistem
membuat akun dan workspace Personal dengan role Owner. Saat mendaftar dari share link, pengguna
akan kembali ke halaman undangan setelah akun dibuat.

Registrasi saat ini menggunakan email dan kata sandi tanpa verifikasi email. Verifikasi email,
email undangan eksternal, dan login tanpa kata sandi memerlukan konfigurasi layanan pengiriman
email dan belum diaktifkan.

## Database dan endpoint

- Database baru: jalankan [`database.sql`](../database.sql).
- Database yang sudah ada: jalankan [`../migrations/20261113_member_invitations.sql`](../migrations/20261113_member_invitations.sql)
  satu kali sebelum menggunakan undangan.
- Detail action, request, dan response ada di [Kontrak API](./api-contract.md), bagian 1 dan 3.
- Test terarah: `php tests/member_invitation_tests.php`; regresi umum: `php tests/run_tests.php`.
