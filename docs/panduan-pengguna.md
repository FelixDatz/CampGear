# Panduan Penggunaan CampGear

## 1. Pengunjung & Pelanggan

### Menyewa alat
1. Buka **Katalog** atau **Paket**, lalu tekan **Sewa alat ini** / **Sewa paket ini**. Bisa juga langsung lewat **Sewa Sekarang** di beranda.
2. Pilih **tanggal mulai** (hari ambil) dan **tanggal selesai** (hari kembali). Durasi dihitung inklusif, maksimal 14 hari.
3. Setiap alat menampilkan **"Tersedia N unit"** untuk seluruh tanggal yang dipilih. Atur jumlah dengan tombol − / +. Baris yang melebihi stok ditandai merah dan tombol **Buat Booking** nonaktif.
4. Isi nama, email, dan nomor HP. Bila sudah login, data ini terisi otomatis.
5. Tekan **Buat Booking**. Catat **kode booking** yang muncul.

### Membayar (simulasi)
Di halaman booking, pilih **Transfer bank** atau **E-wallet**, lalu tekan **Bayar Sekarang**. Batas bayar 24 jam. Setelah itu booking kedaluwarsa dan stok dilepas. Pembayaran tunai juga bisa dilakukan di lokasi dan dicatat oleh staf.

### Cek status & kartu pengambilan
- Menu **Cek Booking** → masukkan kode booking dan email **atau** nomor HP.
- Setelah dibayar, halaman booking menampilkan **Kartu Pengambilan Alat** berisi QR kode booking dan checklist serah terima. Tekan **Cetak kartu** untuk mencetak atau menyimpannya sebagai PDF.

### Mengajukan pembatalan
Di halaman booking (status Menunggu Pembayaran atau Terkonfirmasi, sebelum tanggal mulai), isi **Alasan pembatalan** lalu tekan **Ajukan Pembatalan**. Status pengajuan (*Menunggu → Disetujui/Ditolak*) beserta tanggapan staf dan catatan refund tampil di halaman yang sama.

### Akun pelanggan
- **Daftar** dengan nama, email, nomor HP, dan password (min. 8 karakter, berisi huruf dan angka).
- **Akun Saya** menampilkan riwayat penyewaan, status pembatalan, dan penggantian yang belum tuntas. Booking tamu yang memakai email akun ikut tampil.
- **Profil & Password** untuk mengubah data pribadi dan mengganti password.

### Informasi lain
- **Artikel:** panduan dan tips camping.
- **Statistik:** grafik penyewaan per periode, pembatalan, dan tingkat pemakaian stok. Bisa dipilih bulanan atau mingguan, dan setiap grafik punya tampilan tabel.

## 2. Staff

Login lalu buka **Panel Staff** (menu kiri; di HP lewat tombol ☰).

| Menu | Kegunaan |
|---|---|
| **Dashboard** | Ringkasan: alat siap diambil hari ini, sedang disewa, pembatalan menunggu, penggantian belum tuntas, pendapatan bulan ini. Ada juga agenda pengambilan/pengembalian, grafik, dan transaksi terbaru |
| **Booking** | Cari/filter booking (Ambil hari ini, Sedang disewa, Jatuh tempo kembali, Menunggu diselesaikan, Menunggu bayar) |
| **Pembatalan** | Tinjau pengajuan: setujui (catat refund/deposit) atau tolak (wajib alasan) |
| **Kerusakan** | Kelola laporan kerusakan/kehilangan: isi nominal penggantian, tandai tuntas |
| **Alat / Kategori / Paket / Artikel** | CRUD inventaris dan konten |

### Alur di halaman detail booking
1. **Menunggu bayar:** tombol **Terima tunai** bila penyewa membayar di lokasi.
2. **Terkonfirmasi** (pada rentang tanggal sewa): isi checklist pengambilan (jumlah baik/cacat), catat deposit bila ada, lalu **Serahkan alat**.
3. **Sedang disewa:** isi checklist pengembalian (baik/rusak/hilang). Bila ada unit rusak/hilang, isi keterangan dan nominal penggantian (opsional). Centang **Kurangi stok total** untuk unit hilang. Tekan **Terima pengembalian**.
4. **Sudah dikembalikan:** selesaikan semua laporan di menu **Kerusakan**, perbarui **Deposit** (mis. Dikembalikan), lalu **Selesaikan booking**.

## 3. Admin

Admin memiliki seluruh menu Staff, ditambah:

| Menu | Kegunaan |
|---|---|
| **Pengguna** | Tambah akun (Admin/Staff/User), ubah data/role/status aktif, atur ulang password, hapus akun tanpa riwayat |
| **Log Aktivitas** | Catatan semua tindakan Staff/Admin; filter pelaku, jenis tindakan, dan tanggal; detail perubahan dalam JSON |

## 4. Skenario Demonstrasi (± 10 menit)

Gunakan database dengan data demo (`setup.php --fresh --demo` atau impor `demo_data.sql`).

| # | Peran | Langkah | Yang ditunjukkan |
|---|---|---|---|
| 1 | Pengunjung | Beranda → Katalog → filter kategori → detail alat | Halaman informasi, responsif |
| 2 | Pengunjung | **Sewa alat ini** → pilih tanggal → tambah paket + alat → coba melebihi stok | Cek stok per tanggal via AJAX, penanda kekurangan stok |
| 3 | Pengunjung | Buat booking → bayar simulasi | Kode booking, status Terkonfirmasi, kartu QR + checklist |
| 4 | Pengunjung | Jendela private → **Cek Booking** dengan kode + nomor HP | Cek status tanpa login |
| 5 | User | Login `user@demo.test` → Akun Saya | Riwayat, status pembatalan (ada yang Menunggu), penggantian belum tuntas |
| 6 | User | Buka booking yang akan datang → ajukan pembatalan | Pengajuan pembatalan dengan alasan |
| 7 | Staff | Login `staff@demo.test` → Dashboard | Ringkasan hari ini, agenda, grafik, transaksi |
| 8 | Staff | Pembatalan → setujui satu pengajuan (isi catatan refund) | Booking → Dibatalkan, stok dilepas |
| 9 | Staff | Booking → *Ambil hari ini* → isi checklist + deposit → Serahkan alat | Serah terima pengambilan |
| 10 | Staff | Booking → *Sedang disewa* → tandai 1 unit rusak → Terima pengembalian | Laporan kerusakan otomatis, booking tidak bisa diselesaikan |
| 11 | Staff | Kerusakan → isi nominal → Tandai tuntas → kembali ke booking → Selesaikan | Kewajiban penggantian ditegakkan |
| 12 | Staff | Alat → ubah tarif/stok/gambar; Artikel → tulis & terbitkan | CRUD + upload gambar |
| 13 | Admin | Login `admin@demo.test` → Pengguna → tambah Staff baru | Manajemen akun |
| 14 | Admin | Log Aktivitas → filter pelaku Staff | Catatan tindakan staf dari langkah 8–12 |
| 15 | Semua | Buka `/statistik`, ganti Bulanan ↔ Mingguan, buka tabel data | Grafik penyewaan, pembatalan, pemakaian stok |
