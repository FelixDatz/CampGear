<?php $business = config('business'); ?>
<footer class="site-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="brand text-white text-decoration-none fs-4" href="<?= url('/') ?>"><i class="bi bi-tree-fill"></i> <?= e(config('app.name')) ?></a>
                <p class="mt-3 mb-0 text-white-50"><?= e($business['tagline']) ?> Tenda, carrier, alat tidur, dan alat masak dalam kondisi terawat, siap menemani perjalananmu.</p>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="h6 text-white">Jelajahi</h2>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= url('/alat') ?>">Katalog Alat</a></li>
                    <li><a href="<?= url('/paket') ?>">Paket Sewa</a></li>
                    <li><a href="<?= url('/artikel') ?>">Artikel</a></li>
                    <li><a href="<?= url('/statistik') ?>">Statistik Penyewaan</a></li>
                    <li><a href="<?= url('/cara-reservasi') ?>">Cara Reservasi</a></li>
                    <li><a href="<?= url('/cek-booking') ?>">Cek Booking</a></li>
                    <li><a href="<?= url('/kontak') ?>">Kontak</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h2 class="h6 text-white">Hubungi Kami</h2>
                <ul class="list-unstyled footer-links text-white-50">
                    <li><i class="bi bi-geo-alt me-2"></i><?= e($business['address']) ?></li>
                    <li><i class="bi bi-telephone me-2"></i><?= e($business['phone']) ?></li>
                    <li><i class="bi bi-envelope me-2"></i><?= e($business['email']) ?></li>
                    <li><i class="bi bi-clock me-2"></i><?= e($business['hours']) ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="border-top border-light border-opacity-10">
        <div class="container py-3 small text-white-50 d-flex flex-column flex-md-row justify-content-between gap-1">
            <span>&copy; <?= date('Y') ?> <?= e(config('app.name')) ?>. Proyek UAS Pemrograman Web.</span>
            <span>Pembayaran pada aplikasi ini adalah simulasi.</span>
        </div>
    </div>
</footer>
