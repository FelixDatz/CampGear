<?php /** @var array $packages */ ?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Paket Sewa</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Paket Sewa</h1>
        <p class="text-muted mb-0">Kumpulan alat yang sudah dipilihkan untuk kebutuhan umum, dengan harga lebih hemat daripada sewa satuan.</p>
    </div>
</section>

<div class="container py-4">
    <?php if ($packages === []): ?>
        <div class="empty-state">
            <i class="bi bi-box2"></i>
            <h2 class="h5">Belum ada paket</h2>
            <p class="text-muted">Kamu tetap bisa menyewa alat satuan dari katalog.</p>
            <a class="btn btn-primary" href="<?= url('/alat') ?>">Buka katalog</a>
        </div>
    <?php endif ?>

    <div class="row g-4">
        <?php foreach ($packages as $package): ?>
            <div class="col-lg-4" id="<?= e($package['slug']) ?>">
                <div class="card package-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                            <h2 class="h5 mb-0"><?= e($package['name']) ?></h2>
                            <?php if ((float) $package['discount_percent'] > 0): ?>
                                <span class="badge text-bg-warning flex-shrink-0">Hemat <?= (float) $package['discount_percent'] ?>%</span>
                            <?php endif ?>
                        </div>
                        <p class="text-muted small"><?= e($package['description']) ?></p>

                        <h3 class="h6 small text-uppercase text-muted mt-2">Isi paket</h3>
                        <ul class="package-items">
                            <?php foreach ($package['items'] as $line): ?>
                                <li>
                                    <a href="<?= url('/alat/' . $line['slug']) ?>"><?= e($line['name']) ?></a>
                                    <span>&times;<?= (int) $line['quantity'] ?></span>
                                </li>
                            <?php endforeach ?>
                        </ul>

                        <div class="mt-auto pt-3 border-top">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Harga normal</span><s><?= rupiah($package['normal_daily']) ?>/hari</s>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="fw-semibold">Harga paket</span>
                                <span><span class="price fs-5"><?= rupiah($package['daily_price']) ?></span><span class="small text-muted">/hari</span></span>
                            </div>
                            <?php if (!$package['is_available']): ?>
                                <div class="alert alert-warning small py-2 mt-3 mb-0">Sebagian alat di paket ini sedang tidak disewakan.</div>
                            <?php else: ?>
                                <a class="btn btn-primary w-100 mt-3" href="<?= url('/sewa', ['paket' => $package['slug']]) ?>"><i class="bi bi-calendar-check me-1"></i> Sewa paket ini</a>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
