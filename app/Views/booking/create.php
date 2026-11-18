<?php
/**
 * @var array $equipment
 * @var array $packages
 * @var array $selectedItems
 * @var array $selectedPackages
 * @var array $contact
 * @var string $today
 * @var string $maxStart
 */
$byCategory = [];
foreach ($equipment as $item) {
    $byCategory[$item['category_name']][] = $item;
}

// Data untuk JavaScript (perhitungan ringkasan biaya & pengecekan stok di browser).
$jsData = [
    'equipment' => array_map(static fn ($e) => [
        'id' => (int) $e['id'], 'name' => $e['name'], 'rate' => (float) $e['daily_rate'],
    ], $equipment),
    'packages' => array_map(static fn ($p) => [
        'id' => (int) $p['id'], 'name' => $p['name'], 'discount' => (float) $p['discount_percent'],
        'items' => array_map(static fn ($i) => ['equipment_id' => (int) $i['equipment_id'], 'quantity' => (int) $i['quantity']], $p['items']),
    ], $packages),
    'maxDays' => (int) config('rental.max_rental_days', 14),
];

$qty = static fn (array $selected, int $id): int => max(0, (int) ($selected[$id] ?? 0));
?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Buat Booking</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Buat Booking</h1>
        <p class="text-muted mb-0">Tentukan tanggal sewa terlebih dahulu. Stok setiap alat langsung dicek untuk setiap hari dalam rentang tersebut.</p>
    </div>
</section>

<div class="container py-4">
    <form id="booking-form" method="post" action="<?= url('/sewa') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="row g-4">
            <div class="col-lg-8">

                <!-- 1. Tanggal -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h5 step-title"><span>1</span> Tanggal sewa</h2>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="start_date">Tanggal mulai (ambil alat)</label>
                                <input class="form-control<?= invalid('start_date') ?><?= error('dates') ? ' is-invalid' : '' ?>" type="date"
                                       id="start_date" name="start_date" value="<?= e(old('start_date')) ?>"
                                       min="<?= e($today) ?>" max="<?= e($maxStart) ?>" required>
                                <?php if ($msg = error('start_date')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="end_date">Tanggal selesai (kembalikan alat)</label>
                                <input class="form-control<?= invalid('end_date') ?><?= error('dates') ? ' is-invalid' : '' ?>" type="date"
                                       id="end_date" name="end_date" value="<?= e(old('end_date')) ?>"
                                       min="<?= e($today) ?>" required>
                                <?php if ($msg = error('end_date')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                        </div>
                        <div id="date-feedback" class="small mt-2 text-danger" role="alert"><?= e(error('dates') ?? '') ?></div>
                        <div class="form-text">Durasi dihitung termasuk tanggal mulai dan selesai, maksimal <?= (int) config('rental.max_rental_days') ?> hari.</div>
                    </div>
                </div>

                <?php if ($msg = error('items')): ?>
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i><div><?= e($msg) ?></div>
                    </div>
                <?php endif ?>

                <!-- 2. Paket -->
                <?php if ($packages !== []): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h5 step-title"><span>2</span> Pilih paket <small class="text-muted fw-normal fs-6">(opsional)</small></h2>
                        <div class="pick-list">
                            <?php foreach ($packages as $package): $id = (int) $package['id']; ?>
                                <div class="pick-row" data-row-package="<?= $id ?>">
                                    <div class="pick-info">
                                        <div class="fw-semibold"><?= e($package['name']) ?>
                                            <?php if ((float) $package['discount_percent'] > 0): ?>
                                                <span class="badge text-bg-warning ms-1">Hemat <?= (float) $package['discount_percent'] ?>%</span>
                                            <?php endif ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= e(implode(', ', array_map(static fn ($i) => $i['name'] . ' ×' . $i['quantity'], $package['items']))) ?>
                                        </div>
                                        <div class="small"><span class="price"><?= rupiah($package['daily_price']) ?></span>/hari per set
                                            · <span class="avail-badge" data-avail-package="<?= $id ?>">Pilih tanggal dulu</span></div>
                                    </div>
                                    <?php App\Core\View::partial('qty-stepper', [
                                        'name' => "packages[{$id}]", 'value' => $qty($selectedPackages, $id),
                                        'label' => $package['name'], 'attr' => 'data-package-id="' . $id . '"',
                                    ]) ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
                <?php endif ?>

                <!-- 3. Alat satuan -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h5 step-title"><span><?= $packages !== [] ? 3 : 2 ?></span> Pilih alat satuan</h2>
                        <?php foreach ($byCategory as $categoryName => $items): ?>
                            <h3 class="h6 text-muted text-uppercase small mt-3 mb-2"><?= e($categoryName) ?></h3>
                            <div class="pick-list">
                                <?php foreach ($items as $item): $id = (int) $item['id']; ?>
                                    <div class="pick-row" data-row-equipment="<?= $id ?>">
                                        <div class="pick-info">
                                            <a class="fw-semibold text-reset" href="<?= url('/alat/' . $item['slug']) ?>" target="_blank" rel="noopener"><?= e($item['name']) ?></a>
                                            <div class="small"><span class="price"><?= rupiah($item['daily_rate']) ?></span>/hari
                                                · <span class="avail-badge" data-avail-equipment="<?= $id ?>">Pilih tanggal dulu</span></div>
                                        </div>
                                        <?php App\Core\View::partial('qty-stepper', [
                                            'name' => "items[{$id}]", 'value' => $qty($selectedItems, $id),
                                            'label' => $item['name'], 'attr' => 'data-equipment-id="' . $id . '"',
                                        ]) ?>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>

                <!-- 4. Kontak -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h5 step-title"><span><?= $packages !== [] ? 4 : 3 ?></span> Data kontak</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="customer_name">Nama lengkap</label>
                                <input class="form-control<?= invalid('customer_name') ?>" id="customer_name" name="customer_name"
                                       value="<?= e(old('customer_name', $contact['customer_name'] ?? '')) ?>" autocomplete="name" required maxlength="100">
                                <?php if ($msg = error('customer_name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="customer_email">Email</label>
                                <input class="form-control<?= invalid('customer_email') ?>" type="email" id="customer_email" name="customer_email"
                                       value="<?= e(old('customer_email', $contact['customer_email'] ?? '')) ?>" autocomplete="email" required maxlength="150">
                                <?php if ($msg = error('customer_email')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="customer_phone">Nomor HP</label>
                                <input class="form-control<?= invalid('customer_phone') ?>" type="tel" id="customer_phone" name="customer_phone"
                                       value="<?= e(old('customer_phone', $contact['customer_phone'] ?? '')) ?>" autocomplete="tel" inputmode="tel"
                                       placeholder="08xxxxxxxxxx" required maxlength="20">
                                <?php if ($msg = error('customer_phone')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Catatan <span class="text-muted">(opsional)</span></label>
                                <textarea class="form-control<?= invalid('notes') ?>" id="notes" name="notes" rows="2" maxlength="500"
                                          placeholder="mis. rencana tujuan, jam pengambilan"><?= e(old('notes')) ?></textarea>
                                <?php if ($msg = error('notes')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                        </div>
                        <?php if (auth_user() === null): ?>
                            <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> Kamu memesan sebagai tamu. Status booking bisa dicek dengan kode booking dan email/nomor HP ini.
                                <a href="<?= url('/login') ?>">Masuk</a> agar riwayat tersimpan di akun.</p>
                        <?php endif ?>
                    </div>
                </div>
            </div>

            <!-- Ringkasan -->
            <div class="col-lg-4">
                <div class="card booking-summary">
                    <div class="card-body">
                        <h2 class="h5">Ringkasan</h2>
                        <div class="small text-muted mb-3" id="summary-dates">Tanggal belum dipilih</div>
                        <ul class="summary-lines" id="summary-lines">
                            <li class="text-muted">Belum ada alat dipilih</li>
                        </ul>
                        <dl class="summary-totals">
                            <div><dt>Subtotal</dt><dd id="summary-subtotal">Rp0</dd></div>
                            <div class="text-success"><dt>Diskon paket</dt><dd id="summary-discount">−Rp0</dd></div>
                            <div class="fs-5 fw-bold"><dt>Total bayar</dt><dd id="summary-total">Rp0</dd></div>
                        </dl>
                        <div id="summary-warning" class="alert alert-warning small py-2 d-none" role="status"></div>
                        <button class="btn btn-primary btn-lg w-100" type="submit" id="booking-submit">Buat Booking</button>
                        <p class="small text-muted mt-3 mb-0">
                            Bayar (simulasi) dalam <?= (int) config('rental.payment_window_hours') ?> jam setelah booking dibuat.
                            Biaya akhir dihitung ulang oleh sistem saat booking disimpan.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="application/json" id="booking-data"><?= json_encode($jsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
