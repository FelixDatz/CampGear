# Panduan Instalasi & Deploy CampGear

## Kebutuhan

| Komponen | Versi |
|---|---|
| PHP | **8.1 atau lebih baru** (diuji pada PHP 8.2.12). Ekstensi: `mysqli` (mysqlnd), `mbstring`, `fileinfo`, `json`, `session` |
| Database | MariaDB 10.4+ atau MySQL 8 (InnoDB) |
| Web server | Apache dengan `mod_rewrite` (XAMPP / hosting), atau server bawaan PHP untuk pengembangan |
| Browser | Browser modern dengan JavaScript aktif (Chrome, Edge, Firefox, Safari) |

Tidak ada dependensi Composer maupun npm. Bootstrap 5.3, Bootstrap Icons, Chart.js 4.4, dan qrcode.js dimuat dari CDN.

## A. Instalasi Lokal (XAMPP)

1. **Jalankan MySQL** dari XAMPP Control Panel.
2. **Siapkan konfigurasi.** Salin `config/config.example.php` menjadi `config/config.php`, lalu sesuaikan:
   ```php
   'debug' => true,            // hanya untuk lokal
   'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'campgear', 'user' => 'root', 'pass' => ''],
   ```
3. **Buat database.** Pilih salah satu cara berikut.
   - **CLI** (dari folder project):
     ```
     D:\xampp\php\php.exe database\setup.php --fresh          (data awal saja)
     D:\xampp\php\php.exe database\setup.php --fresh --demo   (data awal + transaksi contoh)
     ```
     > `--fresh` **menghapus** database `campgear` lalu membuatnya ulang. Jangan dipakai bila datanya ingin dipertahankan.
   - **phpMyAdmin:** buat database `campgear` (collation `utf8mb4_unicode_ci`), lalu impor berurutan: `schema.sql` → `seed.sql` → `seed_articles.sql` → (opsional) `demo_data.sql`.
4. **Jalankan aplikasi.** Pilih salah satu cara berikut.
   - **Server bawaan PHP** (paling mudah):
     ```
     D:\xampp\php\php.exe -S localhost:8000 -t public public/index.php
     ```
     Buka <http://localhost:8000>.
   - **Apache XAMPP:** salin/arahkan folder project ke `htdocs`. File `.htaccess` di root project meneruskan request ke `public/`. Bila diakses lewat subfolder (mis. `http://localhost/CampGear`), isi `'base_path' => '/CampGear'` di `config.php`.

### Menambahkan artikel ke database yang sudah ada

Bila database dibuat sebelum artikel awal tersedia, impor **hanya** `database/seed_articles.sql`. File ini tidak menghapus data dan tidak menggandakan artikel.

## B. Deploy ke InfinityFree

1. **Buat akun hosting** di InfinityFree dan pilih subdomain gratis (mis. `campgear.infinityfreeapp.com`).
2. **Pilih versi PHP** 8.1 atau lebih baru di *Control Panel → PHP Version* (bila tersedia).
3. **Buat database MySQL** di *Control Panel → MySQL Databases*. Catat:
   - Host, mis. `sql123.infinityfree.com`
   - Nama database, mis. `if0_12345678_campgear`
   - Username, mis. `if0_12345678`
   - Password akun hosting (*vPanel password*)
4. **Impor SQL** lewat phpMyAdmin hosting (pilih database tadi → *Import*), berurutan:
   `schema.sql` → `seed.sql` → `seed_articles.sql` → (opsional, untuk demo) `demo_data.sql`.
   Jangan menyertakan `CREATE DATABASE`. File SQL CampGear memang tidak berisi perintah itu karena nama database di InfinityFree sudah ditentukan.
5. **Unggah file** ke folder `htdocs` lewat FTP (FileZilla) atau *Online File Manager*:
   ```
   htdocs/
   ├── .htaccess        ← wajib (meneruskan ke public/, memblokir folder internal)
   ├── app/
   ├── config/          ← berisi config.php buatan langkah 6
   ├── public/
   └── storage/
   ```
   Folder `database/`, `docs/`, dan `.git/` tidak perlu diunggah. Bila tetap terunggah, akses web ke folder tersebut diblokir `.htaccess`.
6. **Buat `htdocs/config/config.php`** dari `config.example.php`:
   ```php
   'debug' => false,
   'base_path' => '',
   'db' => [
       'host' => 'sql123.infinityfree.com',
       'port' => 3306,
       'name' => 'if0_12345678_campgear',
       'user' => 'if0_12345678',
       'pass' => 'PASSWORD_VPANEL',
   ],
   ```
7. **Aktifkan SSL gratis** di *Client Area → SSL Certificates*, lalu akses lewat `https://`. Cookie session otomatis memakai flag `Secure` bila diakses lewat HTTPS.
8. **Verifikasi:**
   - Halaman beranda, katalog, artikel, dan statistik terbuka.
   - `https://domain/app/bootstrap.php` dan `https://domain/config/config.php` menghasilkan **403 Forbidden**.
   - Login dengan ketiga akun demo berhasil.
   - Unggah gambar alat dari panel staff berhasil dan gambar tampil.

### Catatan khusus InfinityFree

- InfinityFree memasang pemeriksaan keamanan berbasis cookie JavaScript pada kunjungan pertama. Browser biasa lolos otomatis. Akses API dari luar browser (mis. curl) bisa tertahan, tapi ini tidak memengaruhi aplikasi.
- Tidak ada cron. Kedaluwarsa booking 24 jam berjalan otomatis setiap kali halaman booking/stok dibuka (*lazy expiration*).
- Fungsi `mail()` tidak dipakai, sehingga tidak ada fitur yang bergantung pada pengiriman email.
- Bila muncul error 500, ubah sementara `'debug' => true` untuk melihat detailnya, lalu kembalikan ke `false`. Log error tersimpan di `storage/logs/app.log`.

## C. Akun Demo

| Peran | Email | Password |
|---|---|---|
| Admin | admin@demo.test | Admin#2026 |
| Staff | staff@demo.test | Staff#2026 |
| User | user@demo.test | User#2026 |

Tabel akun demo tampil di halaman login selama `'show_demo_accounts' => true`. Matikan opsi ini bila aplikasi dipakai di luar keperluan penilaian.
