<?php $business = config('business'); ?>
<footer class="sitefoot" id="puncak" data-mdpl="3265" data-label="Puncak">
    <div class="container">
        <div class="sitefoot-top">
            <div class="sitefoot-brand">
                <a class="sitebar-brand" href="<?= url('/') ?>">
                    <svg class="brand-mark" viewBox="0 0 28 24" aria-hidden="true"><path class="m" d="M1 21 10 6l4.5 7.2L18 8l9 13z"/><circle class="s" cx="22.5" cy="4.5" r="2.6"/></svg>
                    <span><?= e(config('app.name')) ?></span>
                </a>
                <p><?= e($business['tagline']) ?> Tenda, carrier, alat tidur, dan alat masak dalam kondisi terawat, siap menemani perjalananmu.</p>
            </div>
            <nav aria-labelledby="foot-jelajahi">
                <h2 id="foot-jelajahi">Jelajahi</h2>
                <ul>
                    <li><a href="<?= url('/alat') ?>">Katalog Alat</a></li>
                    <li><a href="<?= url('/paket') ?>">Paket Sewa</a></li>
                    <li><a href="<?= url('/sewa') ?>">Sewa Sekarang</a></li>
                    <li><a href="<?= url('/cek-booking') ?>">Cek Booking</a></li>
                </ul>
            </nav>
            <nav aria-labelledby="foot-panduan">
                <h2 id="foot-panduan">Panduan</h2>
                <ul>
                    <li><a href="<?= url('/cara-reservasi') ?>">Cara Reservasi</a></li>
                    <li><a href="<?= url('/artikel') ?>">Artikel</a></li>
                    <li><a href="<?= url('/statistik') ?>">Statistik Penyewaan</a></li>
                    <li><a href="<?= url('/kontak') ?>">Kontak &amp; FAQ</a></li>
                </ul>
            </nav>
            <div>
                <h2>Hubungi Kami</h2>
                <address>
                    <span><i class="bi bi-geo-alt" aria-hidden="true"></i><?= e($business['address']) ?></span>
                    <span><i class="bi bi-telephone" aria-hidden="true"></i><?= e($business['phone']) ?></span>
                    <span><i class="bi bi-envelope" aria-hidden="true"></i><?= e($business['email']) ?></span>
                    <span><i class="bi bi-clock" aria-hidden="true"></i><?= e($business['hours']) ?></span>
                </address>
            </div>
        </div>
        <div class="sitefoot-word" aria-hidden="true"><?= e(config('app.name')) ?></div>
        <div class="sitefoot-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(config('app.name')) ?>. Proyek UAS Pemrograman Web.</span>
            <span class="mono">Puncak Lawu · 3.265 mdpl</span>
            <span>Pembayaran pada aplikasi ini adalah simulasi.</span>
        </div>
    </div>
</footer>
