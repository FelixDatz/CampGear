<?php /** @var array $rental */ ?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cara Reservasi</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Cara Reservasi</h1>
        <p class="text-muted mb-0">Alur sewa dari memilih alat sampai mengembalikannya, beserta ketentuan yang berlaku.</p>
    </div>
</section>

<div class="container py-4">
    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <h2 class="h4 mb-3">Alur Penyewaan</h2>
            <ol class="timeline">
                <li>
                    <h3 class="h6">1. Pilih alat dan tanggal sewa</h3>
                    <p>Pilih alat satuan atau paket, tentukan tanggal mulai dan selesai, lalu isi jumlah unit. Sistem langsung mengecek apakah stok tersedia untuk <em>setiap hari</em> dalam rentang tanggal tersebut.</p>
                </li>
                <li>
                    <h3 class="h6">2. Isi data kontak</h3>
                    <p>Isi nama, email, dan nomor HP. Kamu bisa memesan tanpa akun. Jika mendaftar dengan email yang sama, riwayat sewamu akan tersimpan di akun.</p>
                </li>
                <li>
                    <h3 class="h6">3. Simpan kode booking dan bayar</h3>
                    <p>Setelah booking dibuat, kamu mendapat <strong>kode booking</strong>. Selesaikan pembayaran (simulasi) dalam <strong><?= (int) $rental['payment_window_hours'] ?> jam</strong>. Booking yang tidak dibayar akan kedaluwarsa dan stoknya dilepas kembali.</p>
                </li>
                <li>
                    <h3 class="h6">4. Cek status booking</h3>
                    <p>Status booking bisa dicek kapan saja menggunakan kode booking dan email atau nomor HP. Setelah terkonfirmasi, kartu pengambilan alat beserta QR akan tampil.</p>
                </li>
                <li>
                    <h3 class="h6">5. Ambil alat</h3>
                    <p>Datang pada tanggal mulai sewa dan tunjukkan kartu pengambilan. Staf bersama kamu akan mengecek kondisi setiap alat menggunakan checklist serah terima.</p>
                </li>
                <li>
                    <h3 class="h6">6. Kembalikan alat</h3>
                    <p>Kembalikan alat paling lambat pada tanggal selesai sewa. Staf mengecek dan mencatat kondisi setiap alat menggunakan checklist pengembalian.</p>
                </li>
            </ol>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5"><i class="bi bi-calendar-range text-primary me-1"></i> Ketentuan Sewa</h2>
                    <ul class="mb-0 small">
                        <li>Durasi sewa dihitung per hari, <strong>termasuk</strong> tanggal mulai dan tanggal selesai. Sewa tanggal 1–3 dihitung 3 hari.</li>
                        <li>Maksimal <?= (int) $rental['max_rental_days'] ?> hari per booking.</li>
                        <li>Booking dapat dibuat paling cepat untuk hari ini dan paling lambat <?= (int) $rental['max_advance_days'] ?> hari ke depan.</li>
                        <li>Total yang dibayar saat booking adalah biaya sewa setelah diskon paket.</li>
                        <li>Deposit (jika ada) dicatat oleh staf saat pengambilan alat.</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5"><i class="bi bi-x-circle text-primary me-1"></i> Pembatalan</h2>
                    <ul class="mb-0 small">
                        <li>Pembatalan diajukan dengan menyertakan alasan, sebelum tanggal mulai sewa.</li>
                        <li>Staf meninjau pengajuan dengan status <em>menunggu</em>, <em>disetujui</em>, atau <em>ditolak</em>.</li>
                        <li>Jika disetujui, booking menjadi <em>dibatalkan</em> dan stok dilepas kembali.</li>
                        <li>Catatan refund dan deposit dicatat oleh staf pada pengajuan tersebut. Pembayaran di aplikasi ini adalah simulasi, sehingga tidak ada pengembalian dana sungguhan.</li>
                    </ul>
                </div>
            </div>

            <div class="card border-warning">
                <div class="card-body">
                    <h2 class="h5"><i class="bi bi-shield-exclamation text-warning me-1"></i> Kerusakan &amp; Kehilangan</h2>
                    <ul class="mb-0 small">
                        <li>Kondisi alat dicatat saat diambil dan saat dikembalikan.</li>
                        <li>Jika ditemukan kerusakan atau kehilangan, staf mencatatnya pada booking dan penyewa <strong>wajib melakukan penggantian</strong>.</li>
                        <li>Bentuk dan nominal penggantian dicatat oleh staf untuk setiap kasus.</li>
                        <li>Booking belum dianggap selesai sampai penggantian dinyatakan tuntas oleh staf.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
