# Laporan Pengujian CampGear

Tanggal pengujian: 18–19 November 2026. Lingkungan: Windows 11, PHP 8.2.12 (XAMPP), MariaDB 10.4.32, Apache 2.4.58, Microsoft Edge (headless) untuk uji tampilan.

## 1. Ringkasan Hasil

| Jenis pengujian | Alat | Jumlah pemeriksaan | Hasil |
|---|---|---|---|
| Uji dasar (smoke) | `tests/smoke.sh` | 85 | ✅ 85 lulus |
| Booking & stok | `tests/booking.sh` | 88 | ✅ 88 lulus |
| Operasional staff & pembatalan | `tests/operations.sh` | 73 | ✅ 73 lulus |
| CRUD, upload, profil, admin, statistik | `tests/crud.sh` | 91 | ✅ 91 lulus |
| Konkurensi (double booking) | `tests/race.sh` | 3 | ✅ 3 lulus |
| **Total otomatis** | `tests/run-all.sh` | **340** | ✅ **340 lulus, 0 gagal** |
| Aturan `.htaccess` di Apache (deploy) | instance Apache 2.4 terpisah | 40 | ✅ 40 lulus |
| Responsif 375 px | Edge headless, 31 halaman (diulang setelah redesain); beranda, /alat, dan 7 status halaman booking diulang 19 Nov | 31 | ✅ tanpa overflow horizontal |
| Interaksi tampilan publik | Edge headless lewat DevTools Protocol | 26 skenario | ✅ lihat bagian 2 |
| Navigasi keyboard | Edge headless (Tab, Enter, Esc) | 2 komponen | ✅ grid kategori & quick view |
| Cetak tiket | `Page.printToPDF` (A4) | 1 | ✅ tiket + kartu pengambilan muat satu halaman |
| Performa, CPU diperlambat 4× | Edge headless, frame diukur dengan `requestAnimationFrame` | 2 | ✅ scroll beranda 59,5 fps; animasi grid 53,7 fps; 0 frame > 50 ms |
| Logika JavaScript form booking | Edge headless | 18 | ✅ sesuai perhitungan server |
| Lint sintaks | `php -l` (semua file), `node --check` (semua JS) | — | ✅ tanpa error |

Pengujian dijalankan pada database terpisah (`campgear_test`) agar data di database utama tidak berubah.

## 2. Skenario Penting dan Hasilnya

### Validasi stok per rentang tanggal & alur pembayaran
| Skenario | Hasil yang diharapkan | Hasil |
|---|---|---|
| Stok 5; booking A tgl 10–12 (3 unit). Cek tgl 12–13 | Tersedia 2 | ✅ 2 |
| Cek tgl 13–14 (tidak beririsan) | Tersedia 5 | ✅ 5 |
| Tambah booking B tgl 14–15 (3 unit). Cek tgl 10–15 | Tersedia 2 (puncak per hari, bukan 5−6) | ✅ 2 |
| Minta 3 unit saat tersisa 2 | Ditolak dengan nama alat & jumlah tersedia | ✅ |
| Paket Family ×2 + Tenda 6P ×2 (stok 3) | Ditolak: "diminta 4, tersedia 3" | ✅ |
| Bayar transfer tanpa unggah berkas bukti | Ditolak (302) dengan pesan error validasi | ✅ |
| Unggah bukti transfer via multipart/form-data | Tersimpan privat di `storage/payments/`, payment `pending`, booking tetap `pending_payment` | ✅ |
| Booking lewat batas 3 jam dengan bukti pending | Stok tetap ditahan, booking tidak di-expire | ✅ |
| Verifikasi staf: setujui bukti pembayaran | Status booking `confirmed`, payment `paid`, tercatat nominal & staf | ✅ |
| Verifikasi staf: tolak bukti tanpa alasan | Ditolak dengan pesan validasi alasan wajib | ✅ |
| Verifikasi staf: tolak bukti sebelum deadline | Payment `failed`, alasan dicatat di note & audit log, booking tetap `pending_payment`, penyewa dapat re-upload bukti hingga deadline asli | ✅ |
| Verifikasi staf: tolak bukti setelah deadline terlewati | Payment `failed`, booking otomatis `expired`, stok dilepas, form upload terkunci | ✅ |
| Booking lewat batas 3 jam tanpa bukti pending | Stok dilepas, status kedaluwarsa, tidak bisa dibayar | ✅ |
| Rentang > 14 hari, tanggal lampau, > H+90, selesai < mulai | API 422 dengan pesan jelas | ✅ |

### Konkurensi
Dua proses PHP paralel memesan 2 unit dari stok 3 pada 40 tanggal yang sama secara bersamaan:

| Varian | Overbooking | Booking tersimpan |
|---|---|---|
| Tanpa `SELECT ... FOR UPDATE` (kontrol, dibuat khusus untuk pembuktian) | **40 tanggal** | 80 |
| `Booking::create()` (dengan kunci) | **0 tanggal** | 40 (tepat satu pemenang per tanggal) |

### Keamanan
| Pemeriksaan | Hasil |
|---|---|
| Form POST tanpa / dengan token CSRF palsu | 419 |
| Input `<script>` di pencarian dan isi artikel | Di-escape, tidak dieksekusi |
| `%` dan `_` di pencarian | Diperlakukan sebagai teks, bukan wildcard |
| Parameter array (`q[]=x`, `items=1`) | Ditangani tanpa error |
| Field `role=admin` disisipkan saat registrasi | Diabaikan; akun tetap User |
| Tamu/User membuka rute staff/admin | 302 ke login / 403 |
| Staff membuka rute admin | 403 |
| 5× login gagal | Diblokir 15 menit |
| Kode booking orang lain tanpa kontak yang cocok | Dialihkan ke form cek; pesan generik |
| File PHP berganti nama `.jpg` diunggah | Ditolak (dicek dari isi file) |
| File `.php` / `.php.jpg` di folder uploads | Tidak dieksekusi (Apache & server bawaan) |
| Akses `/app`, `/config`, `/database`, `/storage`, `/docs`, `/tests`, dotfile | 403/404 di Apache |
| Akses bukti pembayaran privat tanpa otorisasi | 403 / 404 (hanya pemilik booking & staf berwenang) |
| Servis berkas privat (`storage/payments/`) | Dilindungi header `X-Content-Type-Options: nosniff` dan `Cache-Control: private` |
| Akun dinonaktifkan admin | Sesi aktif langsung berakhir |
| Ganti password sendiri / reset password admin | Seluruh token "Ingat saya" akun dicabut; sesi aktif di perangkat lain tertolak pada request berikutnya saat evaluasi `password_sig` gagal cocok |
| Rate limiting booking per IP | Dibatasi per jam menggunakan tabel `rate_limits` atomik |
| Token "Ingat saya" lama dipakai ulang | Ditolak (token dirotasi) |
| Cookie session | `HttpOnly`, `SameSite=Lax` |
| Header | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |

### Aturan bisnis
| Pemeriksaan | Hasil |
|---|---|
| Pembatalan di hari H atau setelah diambil | Tidak bisa diajukan |
| Pengajuan pembatalan ganda | Dicegah |
| Refund melebihi jumlah dibayar | Ditolak |
| Booking dengan penggantian belum tuntas diselesaikan | Ditolak dengan pesan jumlah laporan |
| Laporan yang sudah tuntas diubah | Ditolak |
| Stok total diturunkan di bawah booking mendatang | Ditolak |
| Alat/paket yang pernah disewa dihapus | Ditolak (disarankan nonaktif) |
| Kategori berisi alat dihapus | Ditolak |
| Admin menurunkan role / menghapus dirinya | Ditolak |
| Akun dengan booking dihapus | Ditolak |
| Perubahan tarif setelah booking | Booking lama tetap memakai tarif lama (snapshot) |

### Tampilan publik (redesain)
| Skenario | Hasil |
|---|---|
| Matahari digeser ke puncak busur | Tema terang, fase "siang", pilihan tersimpan |
| Matahari digeser melewati cakrawala | Tema gelap (`data-theme` dan `data-bs-theme`), bulan tetap terlihat dan bisa digeser |
| Tombol Home pada slider matahari | Fase "fajar", tema terang |
| Pindah ke /alat setelah memilih tema | Tema yang dipilih tetap dipakai |
| Kalender trip: rentang 3 hari | Ringkasan "3 hari", tanggal lewat dan di atas batas tidak bisa dipilih |
| Cek alat tersedia | Ringkasan stok dari `/api/availability`, paket disarankan sesuai jumlah orang |
| Kartu trip saat di-scroll | Kartu parkir ke navbar sebagai chip tanggal |
| Tambah paket ke ransel | Badge ransel bertambah, total ransel = harga paket × jumlah hari |
| Klik Pos 4 lalu Pos 1 di jalur sewa | Halaman scroll ke pos tersebut, kartu pos aktif ikut berpindah |
| Ctrl+K lalu ketik "tenda" | Hasil alat dan artikel terkait muncul |
| Popover cek booking, akun, langit; mega panel Jelajahi | Terbuka di dekat tombolnya, bisa ditutup dengan Esc atau klik di luar |
| Grafik /statistik pada tema gelap | Warna sumbu dan grid mengikuti tema |
| Section 2: hover kartu Tenda, lalu Perlengkapan Lainnya | Kartu terbuka 725 × 305 px (±2/3 lebar, 3/4 tinggi grid), kartu lain menyusut; tinggi section tetap 775 px; isi 4 alat masih muat |
| Section 2 setelah tanggal trip dipilih | Ringkasan "3 dari 3 alat tersedia" per kategori, stok per alat ("3 unit", "Sisa 1") |
| Section 2: "Lihat 3 alat di katalog" | Halaman scroll ke katalog beranda dengan chip Tenda aktif |
| Katalog beranda di-scroll | Bar chip menempel di bawah navbar dan diberi bayangan |
| Chip Tenda | 3 kartu tampil, teks "Menampilkan 3 alat di Tenda"; di /alat URL berubah ke ?kategori=... dan judul halaman ikut berganti |
| Quick view: klik kartu, + dua kali, Masukkan ransel | Jumlah dibatasi stok (3), ransel berisi 3 unit, dialog tertutup |
| Tiket contoh di beranda | Tanggal, isi (dari ransel), dan total mengikuti trip; Coba bayar → QR terbuka; status bisa dijalankan sampai Selesai |
| Halaman booking untuk 7 status | Stempel, jejak status, dan bagian kanan tiket sesuai status; QR hanya saat Terkonfirmasi; hitung mundur saat menunggu bayar |
| /sewa: Lanjut tanpa alat | Tetap di langkah 2 dengan pesan "Pilih minimal satu alat atau paket." |
| /sewa: Lanjut dengan data penyewa kosong | Tetap di langkah 3, fokus ke kolom nama |
| /sewa: Enter di kolom nama (langkah 3) | Pindah ke langkah Periksa, form belum terkirim, isi ransel tetap |
| /sewa: Buat Booking di langkah 4 | Booking tersimpan, diarahkan ke tiket, ransel dikosongkan |
| /sewa di HP 375 px | Bar bawah menempel berisi total + Lanjut; ringkasan biaya tampil di langkah Periksa; tanpa overflow |
| /sewa dibuka dari halaman lain / dari situs lain | Tombol Keluar kembali ke halaman asal; Referer situs lain diabaikan |
| Halaman yang dirombak di 375 px (detail alat, paket, artikel, baca artikel, kontak, cara reservasi, statistik, masuk, daftar, cek booking, akun) dan 10 halaman panel | Tanpa overflow horizontal, tanpa error JavaScript |
| Panel: tombol tema gelap | Panel berganti gelap; pilihan tersimpan dan beranda publik ikut gelap |
| Panel: detail booking Terkonfirmasi sebelum tanggal ambil | Tiket sama dengan milik penyewa, jejak di Terkonfirmasi, Langkah berikutnya "Belum bisa diambil" |
| Akun pelanggan demo (32 booking) | Penggantian belum tuntas tampil di atas; 3 perjalanan aktif dan 29 riwayat sebagai tiket mini |

## 3. Bug yang Ditemukan dan Diperbaiki Selama Pengujian

| Temuan | Penyebab | Perbaikan |
|---|---|---|
| Sidebar panel tidak terlihat di desktop | Bootstrap 5.3 memaksa `.offcanvas-lg` transparan (`!important`) di layar ≥ lg | Latar sidebar diberi `!important` |
| Label kategori di grafik horizontal terpotong | Chart.js mengukur label sebelum font web dimuat | Grafik digambar setelah `document.fonts.ready` |
| `public/.htaccess` bisa diunduh di Apache | `RewriteRule` di `.htaccess` root tidak diwariskan ke `public/` | Blokir dotfile dengan `FilesMatch` di kedua `.htaccess` |
| Dotfile tersaji di server bawaan PHP | Server bawaan tidak membaca `.htaccess` | Router pengembangan menolak path tersembunyi |
| Status kedaluwarsa tidak tersimpan saat pembayaran terlambat | `UPDATE` di dalam transaksi ikut di-rollback | Penandaan diserahkan ke `expireOverdue()` di luar transaksi |
| Konfigurasi lokal menampilkan "08.00â€“20.00" | Salinan `config.php` dibuat dengan encoding ANSI | File diperbaiki (UTF-8) |
| Bulan tersembunyi di balik gunung pada tema gelap | Ujung busur matahari lebih rendah dari puncak punggungan | Ujung busur dihitung dari tinggi punggungan di titik itu |
| Daftar artikel di HP hanya menampilkan tanggal | Item flex `flex: 1 1 0` dengan `overflow: hidden` menyusut ke tinggi nol dalam kolom | Item di HP memakai `flex: 0 0 auto` |
| Kartu trip menutupi tagline setelah hasil cek muncul | Kartu diposisikan `translateY(-50%)` sehingga memanjang ke atas | Posisi memakai setengah tinggi baris utama, kartu memanjang ke bawah |
| Nama kategori terpotong ("Penerangar") di kartu section 2 yang menyusut | Ikon di samping nama dan huruf besar tidak muat di kolom 181 px | Ikon pindah ke pojok kanan atas dan memudar di kartu yang menyusut; ukuran nama tetap |
| Hanya 5 chip kategori yang muat di bar katalog /alat | Chip tanggal memakai format panjang dan kotak cari ikut di bar | Chip tanggal memakai format pendek, kotak cari pindah ke header, tepi kanan chip memudar bila masih bisa digeser |
| Tombol Lanjut di /sewa baru terlihat setelah scroll ke bawah daftar alat | Bar aksi berada di akhir kolom | Bar aksi menempel di bawah layar selama form di-scroll |
| Langkah aktif tidak terlihat di daftar langkah HP | Empat label langkah tidak muat di 375 px | Di HP, langkah yang tidak aktif hanya menampilkan nomor |
| Pendapatan bulan ini terpotong dua baris di dashboard | Nominal rupiah lebih panjang daripada lebar kartu angka | Nominal rupiah memakai ukuran huruf lebih kecil dan tidak dipotong |
| Badge pembatalan meluber keluar tiket mini di Akun Saya | Kolom stempel lebarnya tetap 150 px | Kolom stempel 200 px dan badge boleh turun baris |

## 4. Keterbatasan Pengujian

- Pengujian dilakukan di lingkungan lokal. Deploy ke InfinityFree belum diuji karena membutuhkan akun hosting. Perilaku `.htaccess` sudah diverifikasi dengan Apache 2.4 dalam susunan folder yang sama dengan `htdocs` hosting.
- Pengujian tampilan memakai Microsoft Edge (Chromium). Firefox tidak terpasang di laptop pengujian dan Safari tidak tersedia di Windows, jadi keduanya belum diuji langsung. Di browser yang belum mendukung View Transitions, quick view tetap berfungsi tanpa animasi perpindahan gambar.
- Uji performa memakai Edge headless tanpa GPU dengan CPU diperlambat 4×. Hasilnya perkiraan; laptop sungguhan dengan GPU lemah bisa berbeda.
- Tampilan HP diuji dengan emulasi lebar 375 px, belum di perangkat sungguhan.
- Pengujian tidak mencakup beban tinggi (load test).
