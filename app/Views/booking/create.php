<?php
/**
 * Form booking dalam mode fokus: empat langkah (tanggal → isi ransel → data penyewa → periksa).
 * booking.js menampilkan satu langkah sekali waktu; tanpa JavaScript semua langkah tampil
 * sebagai satu form biasa, jadi pengiriman ke server tetap sama.
 *
 * @var array $equipment
 * @var array $packages
 * @var array $selectedItems
 * @var array $selectedPackages
 * @var array $contact
 * @var string $today
 * @var string $maxStart
 * @var array $prefill tanggal awal dari trip: ["start" => Y-m-d, "end" => Y-m-d]
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
$steps = ['Tanggal', 'Isi ransel', 'Data penyewa', 'Periksa'];
$hours = (int) config('rental.payment_window_hours');
?>
<div class="container wizard-page">
    <form id="booking-form" class="wizard" method="post" action="<?= url('/sewa') ?>" novalidate data-wizard>
        <?= csrf_field() ?>
        <?php if (!empty($formToken)): ?>
            <input type="hidden" name="_form_token" value="<?= e($formToken) ?>">
        <?php endif ?>
        <div style="display:none;" aria-hidden="true">
            <label for="website_url">Jangan isi bidang ini</label>
            <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
        </div>
        <?php if ($generalError = error('general')): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                <div><?= e($generalError) ?></div>
            </div>
        <?php endif ?>
        <header class="wiz-head">
            <h1 class="h2 mb-1">Buat Booking</h1>
            <p class="text-muted mb-0">Stok setiap alat dicek untuk setiap hari dalam rentang sewamu.</p>
            <ol class="wiz-steps" aria-label="Langkah booking" data-wiz-steps hidden>
                <?php foreach ($steps as $i => $label): ?>
                    <li><button class="wiz-step-btn" type="button" data-wiz-go="<?= $i + 1 ?>" data-wiz-nav aria-label="Langkah <?= $i + 1 ?>: <?= e($label) ?>"><span class="n" aria-hidden="true"><?= $i + 1 ?></span><span class="wiz-step-label"><?= e($label) ?></span></button></li>
                <?php endforeach ?>
            </ol>
        </header>

        <!-- Desktop: langkah + bar di kiri, ringkasan di kanan. HP: langkah, ringkasan (langkah terakhir), bar. -->
        <div class="wiz-layout">
            <div class="wiz-main">

                <!-- Langkah 1: tanggal -->
                <section class="wiz-panel" data-wiz-panel="1" aria-labelledby="wiz-title-1">
                    <div class="card">
                        <div class="card-body">
                            <h2 class="h5 wiz-title" id="wiz-title-1" tabindex="-1">Kapan alat diambil dan dikembalikan?</h2>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="start_date">Tanggal mulai (ambil alat)</label>
                                    <input class="form-control<?= invalid('start_date') ?><?= error('dates') ? ' is-invalid' : '' ?>" type="date"
                                           id="start_date" name="start_date" value="<?= e(old('start_date', $prefill['start'])) ?>"
                                           min="<?= e($today) ?>" max="<?= e($maxStart) ?>" required>
                                    <?php if ($msg = error('start_date')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="end_date">Tanggal selesai (kembalikan alat)</label>
                                    <input class="form-control<?= invalid('end_date') ?><?= error('dates') ? ' is-invalid' : '' ?>" type="date"
                                           id="end_date" name="end_date" value="<?= e(old('end_date', $prefill['end'])) ?>"
                                           min="<?= e($today) ?>" required>
                                    <?php if ($msg = error('end_date')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                                </div>
                            </div>
                            <div id="date-feedback" class="small mt-2 text-danger" role="alert"><?= e(error('dates') ?? '') ?></div>
                            <div class="form-text">Durasi dihitung termasuk tanggal mulai dan selesai, maksimal <?= (int) config('rental.max_rental_days') ?> hari.</div>
                        </div>
                    </div>
                </section>

                <!-- Langkah 2: isi ransel (paket + alat satuan) -->
                <section class="wiz-panel" data-wiz-panel="2" aria-labelledby="wiz-title-2">
                    <h2 class="h5 wiz-title wiz-title-bare" id="wiz-title-2" tabindex="-1">Apa saja yang dibawa?</h2>
                    <?php if ($msg = error('items')): ?>
                        <div class="alert alert-danger d-flex gap-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i><div><?= e($msg) ?></div>
                        </div>
                    <?php endif ?>

                    <?php if ($packages !== []): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h3 class="h6 pick-head">Paket <small class="text-muted fw-normal">(opsional, lebih hemat)</small></h3>
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

                    <div class="card">
                        <div class="card-body">
                            <h3 class="h6 pick-head">Alat satuan</h3>
                            <?php foreach ($byCategory as $categoryName => $items): ?>
                                <h4 class="h6 text-muted text-uppercase small mt-3 mb-2"><?= e($categoryName) ?></h4>
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
                </section>

                <!-- Langkah 3: data penyewa -->
                <section class="wiz-panel" data-wiz-panel="3" aria-labelledby="wiz-title-3">
                    <div class="card">
                        <div class="card-body">
                            <h2 class="h5 wiz-title" id="wiz-title-3" tabindex="-1">Data penyewa</h2>
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
                </section>

                <!-- Langkah 4: periksa sebelum mengirim (diisi booking.js) -->
                <section class="wiz-panel" data-wiz-panel="4" aria-labelledby="wiz-title-4" data-wiz-review hidden>
                    <div class="card">
                        <div class="card-body">
                            <h2 class="h5 wiz-title" id="wiz-title-4" tabindex="-1">Periksa sebelum mengirim</h2>
                            <dl class="review-list">
                                <div><dt>Tanggal</dt><dd data-review="dates">–</dd><button type="button" data-wiz-go="1">Ubah</button></div>
                                <div><dt>Isi</dt><dd data-review="items">–</dd><button type="button" data-wiz-go="2">Ubah</button></div>
                                <div><dt>Penyewa</dt><dd data-review="contact">–</dd><button type="button" data-wiz-go="3">Ubah</button></div>
                                <div><dt>Catatan</dt><dd data-review="notes">–</dd><button type="button" data-wiz-go="3">Ubah</button></div>
                            </dl>
                            <p class="small text-muted mt-3 mb-0">Setelah dikirim, kamu mendapat tiket ekspedisi dengan kode booking. Bayar (simulasi) dalam <?= $hours ?> jam.</p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Ringkasan biaya -->
            <aside class="wiz-side" aria-label="Ringkasan biaya">
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
                        <div id="summary-warning" class="alert alert-warning small py-2 mb-0 d-none" role="status"></div>
                        <p class="small text-muted mt-3 mb-0">Biaya akhir dihitung ulang oleh sistem saat booking disimpan.</p>
                    </div>
                </div>
            </aside>

            <!-- Bar aksi: di HP menempel di bawah layar bersama total -->
            <div class="wiz-bar" data-wiz-bar>
                <div class="wiz-total">
                    <span data-wiz-days>Total</span>
                    <b data-wiz-total>Rp0</b>
                </div>
                <div class="wiz-actions">
                    <button class="btn btn-outline-secondary" type="button" data-wiz-back aria-label="Kembali" hidden><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Kembali</span></button>
                    <button class="btn btn-cta" type="button" data-wiz-next hidden>Lanjut <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    <button class="btn btn-cta" type="submit" id="booking-submit">Buat Booking</button>
                </div>
                <p class="wiz-hint" data-wiz-hint role="status" aria-live="polite"></p>
            </div>
        </div>
    </form>
</div>

<script type="application/json" id="booking-data"><?= json_encode($jsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
