# CampGear

Aplikasi web manajemen reservasi persewaan alat camping dengan tiga peran pengguna: **User**, **Staff**, dan **Admin**.
Proyek UAS Pemrograman Web — Kelas A PTIK 2024.

| | |
|---|---|
| Nama | Felix Trinidat |
| NIM | K3524075 |
| Kelas / Kelompok | A PTIK 2024 / 1 |
| URL live | _diisi setelah deploy ke InfinityFree_ |

## Fitur

**Pengunjung & User**
- Katalog alat (filter kategori, pencarian), detail alat, paket sewa, artikel, cara reservasi, kontak + FAQ.
- Booking alat satuan dan paket dengan **validasi stok per hari** dalam rentang tanggal (cek langsung via AJAX).
- Pembayaran simulasi, kode booking, batas bayar 24 jam dengan kedaluwarsa otomatis.
- Cek status booking dengan kode + email/nomor HP; **kartu pengambilan dengan QR** dan checklist serah terima (bisa dicetak).
- Pengajuan pembatalan dengan alasan; status *menunggu / disetujui / ditolak* beserta tanggapan dan catatan refund.
- **Grafik** penyewaan per periode, pembatalan, dan tingkat pemakaian stok (bulanan/mingguan, dengan tampilan tabel).
- Registrasi, login, "Ingat saya", riwayat penyewaan, status pembatalan, profil, dan ganti password.

**Staff**
- Dashboard: alat siap diambil hari ini, sedang disewa, pembatalan menunggu, penggantian belum tuntas, pendapatan (simulasi), agenda, grafik, tabel transaksi.
- Operasional booking: terima tunai, checklist pengambilan + catat deposit, checklist pengembalian + laporan **kerusakan/kehilangan**, penyelesaian (diblokir sampai penggantian tuntas).
- Tinjau pembatalan: setujui (booking dibatalkan, stok dilepas, catat refund/deposit) atau tolak.
- CRUD alat (gambar, stok total, tarif harian), kategori, paket, dan artikel.

**Admin**
- Semua fitur Staff, ditambah manajemen akun Admin/Staff/User dan **log aktivitas** (catatan tindakan staf).

## Teknologi

| Lapisan | Teknologi | Alasan |
|---|---|---|
| Backend | PHP 8.2 native (tanpa framework), arsitektur front controller + MVC ringan | Sesuai materi kuliah; seluruh alur dapat ditelusuri dan dipertanggungjawabkan |
| Database | MySQL / MariaDB via **MySQLi** (prepared statement, transaksi) | Sesuai materi; mencegah SQL injection dan double booking |
| Tampilan | Bootstrap 5.3 + Bootstrap Icons | Responsif untuk desktop dan mobile |
| Interaksi | Vanilla JavaScript (`fetch`, `async/await`), Chart.js 4.4, qrcode.js | AJAX untuk stok & grafik; QR kartu pengambilan |
| Server | Apache + `.htaccess` (URL rewriting) | XAMPP dan hosting InfinityFree |

Pemetaan lengkap materi kuliah ke kode ada di [docs/pemetaan-materi.md](docs/pemetaan-materi.md).

## Menjalankan Secara Lokal (XAMPP)

```
copy config\config.example.php config\config.php          (lalu sesuaikan bila perlu)
D:\xampp\php\php.exe database\setup.php --fresh --demo   (buat database + data demo)
D:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
```

Buka <http://localhost:8000>. Opsi `--fresh` **menghapus** database `campgear` lalu membuat ulang. Untuk instalasi lewat phpMyAdmin, Apache, dan deploy ke InfinityFree, lihat [docs/instalasi-deploy.md](docs/instalasi-deploy.md).

## Akun Demo

| Peran | Email | Password |
|---|---|---|
| Admin | admin@demo.test | Admin#2026 |
| Staff | staff@demo.test | Staff#2026 |
| User | user@demo.test | User#2026 |

## Ketentuan Bisnis

| Ketentuan | Nilai / perilaku |
|---|---|
| Durasi sewa | Per hari, inklusif; maksimal **14 hari** |
| Rentang pemesanan | Hari ini sampai H+90 |
| Batas pembayaran | **24 jam**; lewat batas → kedaluwarsa, stok dilepas |
| Ketersediaan stok | Stok total − pemakaian tertinggi per hari dari booking aktif |
| Pembatalan | Sebelum tanggal mulai; *pending → approved / rejected*; disetujui → booking dibatalkan, stok dilepas |
| Refund | Tidak dihitung otomatis; staf mencatat nominal (opsional) dan catatan |
| Deposit | Tidak dihitung otomatis dan tidak masuk total bayar; staf mencatat nominal, status, dan catatan |
| Kerusakan / kehilangan | Penyewa wajib mengganti; nominal & bentuk penggantian dicatat staf per kasus; booking selesai setelah semua tuntas |

Nominal deposit dan biaya penggantian sengaja belum ditetapkan dan menunggu keputusan lebih lanjut. Penjelasan lengkap ada di [docs/proses-bisnis.md](docs/proses-bisnis.md).

## Struktur Folder

```
app/
  Core/          Router, Database (MySQLi), Auth, Session, Csrf, Validator, View, Upload, Paginator, ErrorHandler
  Controllers/   Halaman publik & akun; Staff/ (operasional, inventaris, konten); Admin/ (akun, log); Api/ (JSON)
  Models/        Akses data & aturan bisnis (Booking, Availability, CancellationRequest, Handover, DamageReport, Stats, ...)
  Enums/         Enum PHP 8.1 (BookingStatus, Role)
  Exceptions/    Pelanggaran aturan bisnis
  Views/         Template PHP (layouts/, partials/, per fitur)
  routes.php     Daftar URL → controller + middleware
config/          config.example.php (salin menjadi config.php — tidak di-commit)
database/        schema.sql, seed.sql, seed_articles.sql, demo_data.sql, setup.php, tools/
docs/            Dokumentasi proses bisnis, database, panduan, pengujian, jobdesk
public/          Document root: index.php, assets/, uploads/
storage/logs/    Log error aplikasi
tests/           Pengujian end-to-end otomatis
```

Hanya `public/` yang dapat diakses dari web; folder lain diblokir `.htaccess`.

## API JSON

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/availability?start=YYYY-MM-DD&end=YYYY-MM-DD` | Sisa unit tiap alat dan jumlah set tiap paket untuk seluruh hari dalam rentang. `422` bila rentang melanggar aturan |
| GET | `/api/stats?period=month\|week&range=6\|8\|12` | Data grafik penyewaan, pembatalan, dan pemakaian stok. Pendapatan hanya untuk Staff/Admin |

Form HTML memakai `POST` dengan method spoofing (`_method=PUT/DELETE`) untuk operasi ubah dan hapus.

## Keamanan

- Password `password_hash` (bcrypt); semua query memakai prepared statement MySQLi.
- Token CSRF di setiap form dan request `fetch` non-GET; output di-escape (`htmlspecialchars`).
- Session `HttpOnly` + `SameSite=Lax`, ID session diganti saat login/logout; "Ingat saya" memakai token ber-hash yang dirotasi.
- Pembatas percobaan login dan cek booking; middleware role (`auth`, `guest`, `role:staff,admin`, `role:admin`).
- Upload: ukuran ≤ 2 MB, tipe dicek dari isi file, nama acak, folder uploads tidak mengeksekusi skrip.
- Transaksi + `SELECT ... FOR UPDATE` mencegah double booking dan aksi ganda.
- Semua tindakan Staff/Admin tercatat di log aktivitas.

## Pengujian

```
bash tests/run-all.sh
```

Hasil terakhir: **273 pemeriksaan otomatis lulus, 0 gagal**, ditambah 40 pemeriksaan aturan `.htaccess` di Apache dan uji responsif 31 halaman. Pengujian memakai database terpisah `campgear_test`. Detail: [docs/pengujian.md](docs/pengujian.md) · [tests/README.md](tests/README.md).

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/proses-bisnis.md](docs/proses-bisnis.md) | Aktor, pemetaan kebutuhan, status booking, alur proses (diagram), aturan bisnis, definisi statistik |
| [docs/database.md](docs/database.md) | ERD, kamus data, file SQL |
| [docs/panduan-pengguna.md](docs/panduan-pengguna.md) | Panduan per peran dan skenario demonstrasi |
| [docs/instalasi-deploy.md](docs/instalasi-deploy.md) | Instalasi lokal dan deploy InfinityFree |
| [docs/pengujian.md](docs/pengujian.md) | Laporan pengujian dan bug yang diperbaiki |
| [docs/pemetaan-materi.md](docs/pemetaan-materi.md) | Pemetaan materi kuliah ke kode |
| [docs/jobdesk.md](docs/jobdesk.md) | Jobdesk dan pembagian kerja |
