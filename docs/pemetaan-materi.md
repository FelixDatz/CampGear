# Pemetaan Materi Perkuliahan ke Implementasi CampGear

Tabel ini menunjukkan di mana setiap materi Pemrograman Web diterapkan dalam kode. Seluruh backend ditulis dengan PHP native (tanpa framework), sehingga setiap konsep terlihat langsung.

| # | Materi | Penerapan | File utama |
|---|---|---|---|
| 1 | Fondasi web, client-server, PHP, Apache, MySQL, XAMPP | Front controller menerima semua request HTTP; Apache + `.htaccess` di XAMPP/hosting; MySQL/MariaDB | `public/index.php`, `.htaccess`, `public/.htaccess` |
| 2 | Sintaks & fitur modern PHP 8 | `declare(strict_types=1)`, *typed properties*, *constructor promotion*, `readonly`, **enum** (`BookingStatus`, `Role`), `match`, `throw` sebagai ekspresi (`?? throw`), tipe `never` dan *union type*, *spread* `...`, `??=`, `str_starts_with`/`str_contains` | `app/Enums/*`, `app/Core/*`, `app/Models/Booking.php` |
| 3 | Percabangan & perulangan | Perhitungan stok per hari (perulangan tanggal × booking), validasi checklist, template view (`foreach`, `if`) | `app/Models/Availability.php`, `app/Models/Handover.php`, `app/Views/**` |
| 4 | Function, built-in function, include & require | Helper global (`e()`, `url()`, `rupiah()`, `tanggal()`); autoloader `spl_autoload_register` + `require`; view memakai `include`; `password_hash`, `filter_var`, `htmlspecialchars`, `json_encode`, `random_bytes` | `app/helpers.php`, `app/bootstrap.php`, `app/Core/View.php` |
| 5 | Variable scope, superglobal, GET & POST | `$_GET` (filter/pencarian), `$_POST` (form), `$_SERVER` (path, IP, method), `$_SESSION`, `$_COOKIE`, `$_FILES`; method spoofing `_method` untuk PUT/DELETE | `app/Core/Request.php`, `app/Core/Router.php`, controller |
| 6 | Database relasional, MySQL, phpMyAdmin, relasi, JOIN, MySQLi | 16 tabel berelasi (FK, UNIQUE, CHECK); query `JOIN`, `LEFT JOIN`, `GROUP BY`, subquery; **MySQLi prepared statement** (`prepare` + `bind_param`); transaksi `begin_transaction`/`commit`/`rollback`; `SELECT ... FOR UPDATE`; impor via phpMyAdmin | `app/Core/Database.php`, `app/Models/*`, `database/*.sql` |
| 7 | Session & Cookie | Login berbasis session (`session_regenerate_id`), flash message, cookie session `HttpOnly`/`SameSite`; fitur **"Ingat saya"** berbasis cookie dengan token selector/validator yang di-hash dan dirotasi | `app/Core/Session.php`, `app/Core/Auth.php`, `app/Models/RememberToken.php` |
| 8 | CRUD PHP + MySQL + Bootstrap 5 | CRUD alat, kategori, paket, artikel, pengguna; tampilan Bootstrap 5 responsif (grid, card, table, form, offcanvas, accordion) | `app/Controllers/Staff/*`, `app/Controllers/Admin/*`, `app/Views/staff/*` |
| 9 | RESTful API, JSON, HTTP methods, status code, URL rewriting | `GET /api/availability`, `GET /api/stats` (JSON); route GET/POST/PUT/DELETE; status 200/302/403/404/405/419/422/500; URL bersih via `mod_rewrite` | `app/Controllers/Api/*`, `app/routes.php`, `app/Core/ErrorHandler.php` |
| 10 | File handling, direktori, keamanan upload | Upload gambar alat/paket/artikel: cek ukuran, MIME dari isi file (`finfo`), `getimagesize`, nama acak, `move_uploaded_file`, `mkdir`, `unlink` file lama; folder uploads tidak mengeksekusi skrip; log error dengan `file_put_contents` | `app/Core/Upload.php`, `public/uploads/.htaccess`, `app/Core/ErrorHandler.php` |
| 11 | Asynchronous JS, AJAX, Promise, async/await, DOM | Form booking: `fetch` + `async/await` ke API stok, pembatalan request usang, ringkasan biaya dihitung di DOM; grafik memuat data via AJAX; tabel data dibuat dengan DOM API; checklist serah terima interaktif | `public/assets/js/booking.js`, `public/assets/js/stats.js`, `public/assets/js/staff.js`, `public/assets/js/app.js` |

## Pustaka Tambahan dan Alasannya

| Pustaka | Alasan teknis | Alasan akademis |
|---|---|---|
| Bootstrap 5.3 + Bootstrap Icons | Tampilan responsif konsisten tanpa menulis ulang grid & komponen | Materi CRUD + Bootstrap 5 |
| Chart.js 4.4 | Grafik interaktif (tooltip, responsif) yang dibutuhkan brief | Data diambil lewat AJAX dari API JSON buatan sendiri |
| qrcode.js | QR dummy pada kartu pengambilan, dibuat di browser tanpa layanan pihak ketiga | Manipulasi DOM sisi klien |

Tidak ada framework PHP maupun dependensi Composer. Seluruh logika backend (routing, autentikasi, validasi, akses database) ditulis sendiri agar dapat dijelaskan baris per baris.
