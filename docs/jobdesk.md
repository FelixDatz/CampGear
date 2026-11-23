# Jobdesk Proyek CampGear

| | |
|---|---|
| Nama | Felix Trinidat |
| NIM | K3524075 |
| Kelas / Kelompok | A PTIK 2024 / Kelompok 1 |
| Jenis pengerjaan | Individu |

Proyek dikerjakan secara individu. Seluruh peran di bawah dipegang oleh Felix Trinidat. Asisten AI digunakan sebagai alat bantu pengembangan sistem. Penjelasannya ada di bagian 2.

## 1. Peran dan Tanggung Jawab

| Peran | Tanggung jawab | Keluaran |
|---|---|---|
| **Product owner & analis kebutuhan** | Memahami brief dosen; menetapkan visi, ruang lingkup, dan batasan; memutuskan aturan bisnis (durasi sewa, batas bayar, kebijakan refund/deposit/penggantian dicatat administratif) | Daftar kebutuhan, aturan bisnis (`docs/proses-bisnis.md`) |
| **Perancang sistem & database** | Menentukan teknologi (PHP native, MySQLi, Bootstrap 5), arsitektur front controller + MVC ringan, dan skema database | `docs/database.md`, `database/schema.sql` |
| **Backend developer** | Router, autentikasi, validasi, transaksi booking, pembatalan, operasional staff, CRUD, API JSON, upload file | `app/` |
| **Frontend developer** | Tampilan responsif Bootstrap 5, form booking interaktif (fetch/async), grafik Chart.js, panel staff | `app/Views/`, `public/assets/` |
| **Penguji (QA)** | Uji fungsional end-to-end, uji hak akses, uji keamanan dasar, uji konkurensi stok, uji responsif | `docs/pengujian.md` |
| **Penulis dokumentasi & konten** | README, dokumentasi proses bisnis, panduan pengguna, panduan instalasi/deploy, artikel camping | `README.md`, `docs/`, `database/seed_articles.sql` |
| **DevOps** | Pengelolaan repository Git (branch, commit, push), deploy ke hosting InfinityFree | Repository Git, URL live |

## 2. Pembagian Kerja dengan Asisten AI

| Pihak | Peran |
|---|---|
| **Mahasiswa** | Menentukan visi, kebutuhan, batasan, dan keputusan bisnis; memilih opsi teknologi (PHP native dipilih setelah membandingkan dengan Laravel); meninjau dan menyetujui hasil kode serta pengujian; mengelola Git dan deploy secara manual |
| **Asisten AI** | Membantu penulisan kerangka kode awal, alternatif solusi implementasi, pembuatan skrip pengujian otomatis, dan perapian dokumentasi sesuai spesifikasi dari mahasiswa |

Keputusan yang dibuat langsung oleh mahasiswa selama pengembangan, antara lain:

- Memilih **PHP native** (bukan Laravel) agar seluruh materi kuliah, termasuk MySQLi, terimplementasi langsung.
- Memilih **InfinityFree** sebagai hosting.
- Menghapus aturan refund otomatis H-3 (khusus kelas glamping), serta menetapkan bahwa deposit dan biaya kerusakan/kehilangan **dicatat secara administratif** tanpa nominal atau formula baku.
- Mempertahankan maksimal 14 hari sewa dan menetapkan batas waktu pembayaran 3 jam dengan upload bukti transfer/e-wallet serta verifikasi staf.
- Mengelola sendiri seluruh aktivitas Git dan GitHub.

## 3. Tahapan Pengerjaan

| Tahap | Kegiatan |
|---|---|
| 1. Analisis | Audit brief dan materi kuliah, pemilihan teknologi, penetapan aturan bisnis |
| 2. Fondasi | Kerangka aplikasi, router, database, autentikasi, halaman publik |
| 3. Booking & Pembayaran | Validasi stok per tanggal, transaksi berkunci, alur bukti bayar privat & verifikasi staf, cek status, kartu QR |
| 4. Operasional | Pembatalan, serah terima, deposit, kerusakan/kehilangan, dashboard |
| 5. Pengelolaan | CRUD alat/kategori/paket/artikel, manajemen akun, log aktivitas, grafik |
| 6. Finalisasi | Data demo, pengujian menyeluruh, dokumentasi, deploy |
