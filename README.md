# CampGear

Aplikasi web manajemen reservasi persewaan alat camping dengan tiga peran pengguna: **User**, **Staff**, dan **Admin**.
Proyek UAS Pemrograman Web — Kelas A PTIK 2024.

| | |
|---|---|
| Nama | Felix Trinidat |
| NIM | K3524075 |
| Kelas / Kelompok | A PTIK 2024 / 1 |
| URL live | https://campgear.freehosting.dev/ |

## Fitur

**Pengunjung & User**
- Katalog alat (chip kategori yang menempel saat di-scroll, pencarian, quick view), detail alat, paket sewa, artikel, cara reservasi, kontak + FAQ.
- **Beranda interaktif:** matahari yang bisa digeser mengatur tema terang/gelap, nama brand di antara lapisan gunung, kartu rencana trip (tanggal + rombongan), grid kategori yang membesar saat disentuh (isi, stok di tanggal trip, tombol ransel), papan isi paket, jalur sewa yang tergambar mengikuti scroll, katalog, contoh tiket ekspedisi, dan altimeter (mdpl) sebagai navigasi section.
- **Navigasi ringkas:** menu Jelajahi (mega panel) dan Panduan, cek booking lewat popover, pencarian Ctrl+K, tab bar di HP.
- **Trip & ransel:** tanggal sewa dipilih sekali lalu dipakai di seluruh situs (stok di katalog mengikuti tanggal); alat dan paket bisa dikumpulkan di ransel lalu dibawa ke form booking.
- Booking alat satuan dan paket dengan **validasi stok per hari** dalam rentang tanggal (cek langsung via AJAX), dalam form empat langkah pada **mode fokus** (navbar diganti bar progres, total selalu terlihat).
- Pembayaran transfer/e-wallet dengan upload bukti bayar privat dan verifikasi staf (atau tunai oleh staf di lokasi), kode booking unik, batas bayar 3 jam dengan kedaluwarsa otomatis.
- Cek status booking dengan kode + email/nomor HP. Halaman booking berupa **tiket ekspedisi**: stempel dan jejak status, hitung mundur batas bayar 3 jam, formulir upload bukti, penanganan penolakan & unggah ulang, QR setelah diverifikasi/dibayar, dan checklist serah terima (bisa dicetak).
- Pengajuan pembatalan dengan alasan; status *menunggu / disetujui / ditolak* beserta tanggapan dan catatan refund.
- **Grafik** penyewaan per periode, pembatalan, dan tingkat pemakaian stok (bulanan/mingguan, dengan tampilan tabel).
- Registrasi, login, "Ingat saya", riwayat penyewaan, status pembatalan, profil, dan ganti password.

**Staff**
- Panel memakai sistem desain yang sama dengan situs publik (tema terang/gelap yang tersimpan bersama, radius 6px, font yang sama).
- Dashboard: sapaan dan ringkasan hari ini, angka utama (alat siap diambil, sedang disewa, pembatalan menunggu, penggantian belum tuntas, pendapatan lunas), agenda serah terima, grafik, tabel transaksi.
- Detail booking menampilkan tiket ekspedisi yang sama dengan yang dilihat penyewa, jejak status, dan kotak **Langkah berikutnya** sesuai status.
- Operasional booking: verifikasi bukti pembayaran (preview privat, setujui/tolak dengan catatan wajib), terima tunai, checklist pengambilan + catat deposit, checklist pengembalian + laporan **kerusakan/kehilangan**, penyelesaian (diblokir sampai penggantian tuntas).
- Tinjau pembatalan: setujui (booking dibatalkan, stok dilepas, catat refund/deposit) atau tolak.
- CRUD alat (gambar, stok total, tarif harian), kategori, paket, dan artikel.

**Admin**
- Semua fitur Staff, ditambah manajemen akun Admin/Staff/User dan **log aktivitas** (catatan tindakan staf).

## Teknologi

| Lapisan | Teknologi | Alasan |
|---|---|---|
| Backend | PHP native (minimum PHP 8.1, diuji pada PHP 8.2.12), arsitektur front controller + MVC ringan | Sesuai materi kuliah; seluruh alur dapat ditelusuri dan dipertanggungjawabkan |
| Database | MySQL / MariaDB via **MySQLi** (prepared statement, transaksi) | Sesuai materi; mencegah SQL injection dan double booking |
| Tampilan | Bootstrap 5.3 + Bootstrap Icons, sistem desain sendiri (`site.css`) dengan tema terang/gelap | Responsif untuk desktop dan mobile; satu radius kartu, kartu kaca, cahaya hover |
| Interaksi | Vanilla JavaScript (`fetch`, `async/await`, Canvas 2D, Popover API, `<dialog>`, View Transitions, `localStorage`), Chart.js 4.4, qrcode.js, Lenis | AJAX untuk stok, katalog & grafik; ilustrasi langit/gunung; QR kartu pengambilan; scroll halus di beranda |
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
| Batas pembayaran | **3 jam**; lewat batas tanpa bukti pending → kedaluwarsa, stok dilepas |
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
| GET | `/api/katalog` | Daftar ringkas alat, paket, dan artikel untuk pencarian (Ctrl+K) dan ransel |

Form HTML memakai `POST` dengan method spoofing (`_method=PUT/DELETE`) untuk operasi ubah dan hapus.

## Keamanan

- Password `password_hash` (bcrypt); semua query memakai prepared statement MySQLi.
- Token CSRF di setiap form dan request `fetch` non-GET; output di-escape (`htmlspecialchars`).
- Session `HttpOnly` + `SameSite=Lax`, ID session diganti saat login/logout; "Ingat saya" memakai token ber-hash yang dirotasi.
- **Invalidasi sesi:** saat pengguna mengganti password sendiri atau di-reset oleh Admin, seluruh token "Ingat saya" milik akun tersebut dicabut (`RememberToken::deleteForUser()`), dan sesi aktif di perangkat lain otomatis tertolak pada request berikutnya saat evaluasi tanda tangan hash password (`$_SESSION['password_sig']`).
- **Rate limiting:** brute-force login & cek booking dibatasi via `login_attempts`; pembuatan booking dibatasi per IP per jam via tabel `rate_limits` atomik (`RateLimiter`).
- Middleware role (`auth`, `guest`, `role:staff,admin`, `role:admin`).
- **Upload aman & privat:** gambar publik (alat/artikel) divalidasi MIME (`finfo`), `getimagesize`, ukuran ≤ 2 MB, nama acak; folder `public/uploads/` memblokir eksekusi skrip. Bukti transfer/e-wallet disimpan di **`storage/payments/`** di luar folder `public/` (akses HTTP langsung diblokir aturan `.htaccess`), hanya dapat diunduh/dilihat melalui endpoint privat terotentikasi dengan verifikasi relasi booking dan header `X-Content-Type-Options: nosniff` serta `Cache-Control: private`.
- Transaksi + `SELECT ... FOR UPDATE` mencegah double booking dan aksi ganda.
- Semua tindakan Staff/Admin tercatat di log aktivitas (`audit_logs`).

## Pengujian

```
bash tests/run-all.sh
```

Hasil terakhir: **340 pemeriksaan otomatis lulus, 0 gagal**, ditambah 40 pemeriksaan aturan `.htaccess` di Apache dan uji responsif 31 halaman. Pengujian memakai database terpisah `campgear_test`. Detail: [docs/pengujian.md](docs/pengujian.md) · [tests/README.md](tests/README.md).

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
