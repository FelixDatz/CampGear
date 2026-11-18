<?php
/**
 * @var array $categories
 * @var array $featured
 * @var array $packages
 * @var array $articles
 * @var array $stats
 */
?>
<section class="hero">
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="hero-eyebrow"><i class="bi bi-geo-alt-fill"></i> Persewaan alat camping di Surakarta</span>
                <h1 class="display-5 fw-bold mt-3 mb-3">Sewa alat camping lengkap, tinggal berangkat.</h1>
                <p class="lead text-white-75 mb-4">Pilih alat, tentukan tanggal, dan cek ketersediaan stok secara langsung. Alat dicek setiap kali kembali, jadi kondisinya siap pakai di setiap perjalanan.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-accent btn-lg" href="<?= url('/sewa') ?>"><i class="bi bi-calendar-check me-1"></i> Sewa Sekarang</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= url('/alat') ?>">Lihat Katalog</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-stats">
                    <div><strong><?= (int) $stats['equipment'] ?></strong><span>jenis alat</span></div>
                    <div><strong><?= (int) $stats['categories'] ?></strong><span>kategori</span></div>
                    <div><strong><?= (int) $stats['packages'] ?></strong><span>paket hemat</span></div>
                </div>
            </div>
        </div>
    </div>
    <svg class="hero-mountains" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 120 L0 70 L180 20 L320 80 L470 30 L640 90 L820 10 L990 75 L1150 35 L1300 85 L1440 40 L1440 120 Z"></path>
    </svg>
</section>

<section class="container py-5">
    <div class="section-heading">
        <h2>Kategori Alat</h2>
        <a href="<?= url('/alat') ?>">Semua alat <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3">
        <?php foreach ($categories as $category): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a class="category-tile" href="<?= url('/alat', ['kategori' => $category['slug']]) ?>">
                    <i class="bi <?= e($category['icon'] ?: 'bi-box') ?>"></i>
                    <span class="fw-semibold"><?= e($category['name']) ?></span>
                    <span class="small text-muted"><?= (int) $category['equipment_count'] ?> alat</span>
                </a>
            </div>
        <?php endforeach ?>
    </div>
</section>

<section class="bg-sand py-5">
    <div class="container">
        <div class="section-heading">
            <h2>Alat Pilihan</h2>
            <a href="<?= url('/alat') ?>">Lihat katalog <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-3 g-lg-4">
            <?php foreach ($featured as $item): ?>
                <div class="col-sm-6 col-lg-4">
                    <?php App\Core\View::partial('equipment-card', ['item' => $item]) ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<?php if ($packages !== []): ?>
<section class="container py-5">
    <div class="section-heading">
        <h2>Paket Sewa Hemat</h2>
        <a href="<?= url('/paket') ?>">Detail paket <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3 g-lg-4">
        <?php foreach ($packages as $package): ?>
            <div class="col-md-4">
                <div class="card package-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h3 class="h5 mb-0"><?= e($package['name']) ?></h3>
                            <?php if ((float) $package['discount_percent'] > 0): ?>
                                <span class="badge text-bg-warning">Hemat <?= (float) $package['discount_percent'] ?>%</span>
                            <?php endif ?>
                        </div>
                        <p class="small text-muted"><?= e($package['description']) ?></p>
                        <p class="small mb-3"><i class="bi bi-box-seam me-1"></i><?= count($package['items']) ?> jenis alat</p>
                        <div class="mt-auto">
                            <?php if ($package['daily_price'] < $package['normal_daily']): ?>
                                <s class="small text-muted"><?= rupiah($package['normal_daily']) ?></s>
                            <?php endif ?>
                            <div><span class="price fs-5"><?= rupiah($package['daily_price']) ?></span><span class="small text-muted">/hari</span></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<section class="bg-sand py-5">
    <div class="container">
        <div class="section-heading">
            <h2>Cara Menyewa</h2>
            <a href="<?= url('/cara-reservasi') ?>">Selengkapnya <i class="bi bi-arrow-right"></i></a>
        </div>
        <ol class="steps">
            <li><i class="bi bi-cart-check"></i><h3 class="h6">Pilih alat &amp; tanggal</h3><p>Pilih alat atau paket, tentukan tanggal sewa, lalu sistem mengecek stok yang tersedia.</p></li>
            <li><i class="bi bi-credit-card"></i><h3 class="h6">Bayar</h3><p>Selesaikan pembayaran dalam <?= (int) config('rental.payment_window_hours') ?> jam supaya booking terkonfirmasi.</p></li>
            <li><i class="bi bi-qr-code"></i><h3 class="h6">Ambil alat</h3><p>Tunjukkan kartu pengambilan, lalu cek kondisi alat bersama staf.</p></li>
            <li><i class="bi bi-arrow-repeat"></i><h3 class="h6">Kembalikan</h3><p>Kembalikan alat sesuai jadwal. Staf mencatat kondisi setiap alat saat diterima kembali.</p></li>
        </ol>
    </div>
</section>

<?php if ($articles !== []): ?>
<section class="container pt-5">
    <div class="section-heading">
        <h2>Artikel Terbaru</h2>
        <a href="<?= url('/artikel') ?>">Semua artikel <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3 g-lg-4">
        <?php foreach ($articles as $article): ?>
            <div class="col-md-4"><?php App\Core\View::partial('article-card', ['article' => $article]) ?></div>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<section class="container py-5">
    <div class="cta-box">
        <div>
            <h2 class="h4 mb-1">Butuh rekomendasi alat?</h2>
            <p class="mb-0 text-white-75">Ceritakan rencana perjalananmu dan tim kami akan membantu memilih perlengkapan yang pas.</p>
        </div>
        <a class="btn btn-accent btn-lg" href="<?= url('/kontak') ?>"><i class="bi bi-chat-dots me-1"></i> Hubungi Kami</a>
    </div>
</section>
