# Pengujian Otomatis CampGear

Pengujian *end-to-end* berbasis HTTP (curl) terhadap aplikasi yang benar-benar berjalan, ditambah uji konkurensi dua proses PHP paralel.

## Menjalankan

Dari folder project, lewat **Git Bash**, dengan MySQL XAMPP menyala:

```bash
bash tests/run-all.sh                    # semua suite
bash tests/run-all.sh booking crud       # suite tertentu
```

Skrip ini:
1. membuat salinan konfigurasi dengan database **`campgear_test`** (database utama `campgear` tidak disentuh);
2. menyalakan server bawaan PHP di port 8001 memakai konfigurasi uji;
3. membuat ulang `campgear_test` sebelum setiap suite;
4. mencetak kegagalan (bila ada) dan total di akhir, lalu mematikan server.

Path PHP/MySQL default mengikuti `D:\xampp`. Untuk lokasi lain:

```bash
PHP_BIN=/c/xampp/php/php.exe MYSQL_BIN=/c/xampp/mysql/bin/mysql.exe bash tests/run-all.sh
```

## Suite

| File | Cakupan |
|---|---|
| `smoke.sh` | Halaman publik, 404/405, escape XSS & wildcard LIKE, proteksi rute per role, CSRF, login 3 akun demo, redirect setelah login, logout, "Ingat saya" (rotasi token), validasi registrasi, role dipaksa user, pembatas brute-force |
| `booking.sh` | Validasi rentang tanggal di API, stok per hari, penolakan stok kurang (termasuk paket + alat satuan), diskon paket, validasi input, hak akses tamu, cek status dengan email/HP, pembayaran simulasi, pembayaran ganda, kedaluwarsa, booking pelanggan login, akses staff |
| `operations.sh` | Pengajuan pembatalan (validasi, ganda, tolak, ajukan ulang, setujui, refund), pembatalan pada hari H ditolak, pembayaran tunai, checklist pengambilan + deposit, checklist pengembalian + laporan rusak/hilang + pengurangan stok, penyelesaian diblokir sampai penggantian tuntas, pencatatan deposit, filter daftar booking, audit log |
| `crud.sh` | Artikel (draf/terbit, format aman, slug unik, PUT/DELETE), kategori, alat + upload gambar (file PHP berkedok JPG ditolak, skrip di uploads tidak dieksekusi), batas stok vs booking mendatang, snapshot tarif, paket, profil & ganti password, manajemen akun admin (nonaktif → sesi langsung berakhir, pengaman admin terakhir), log aktivitas, API statistik |
| `race.sh` + `race.php` | Dua proses paralel memperebutkan stok terakhir pada 40 tanggal: tidak boleh ada overbooking |

Hasil pengujian terakhir dicatat di [`docs/pengujian.md`](../docs/pengujian.md).
