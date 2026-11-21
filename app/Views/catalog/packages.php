<?php
/**
 * Paket sewa: tiap paket sebagai papan isi (ilustrasi alat) + harga, lalu tabel
 * perbandingan isi semua paket di bagian bawah.
 *
 * @var array $packages paket aktif beserta items, normal_daily, daily_price, is_available, sleepers
 */
// Tabel perbandingan: semua alat yang muncul di paket mana pun.
$compareRows = [];
foreach ($packages as $package) {
    foreach ($package['items'] as $line) {
        $compareRows[(int) $line['equipment_id']] ??= ['name' => $line['name'], 'slug' => $line['slug'], 'qty' => []];
        $compareRows[(int) $line['equipment_id']]['qty'][(int) $package['id']] = (int) $line['quantity'];
    }
}
?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Paket Sewa</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Paket Sewa</h1>
        <p class="text-muted mb-0">Alat yang sudah dipilihkan untuk satu perjalanan, dengan harga lebih hemat daripada sewa satuan.</p>
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

    <div class="pkg-list">
        <?php foreach ($packages as $package): $saving = (float) $package['normal_daily'] - (float) $package['daily_price']; ?>
            <article class="pkg-row lamp" id="<?= e($package['slug']) ?>" aria-labelledby="pkg-<?= (int) $package['id'] ?>">
                <div class="pkg-board" aria-label="Isi <?= e($package['name']) ?>">
                    <?php foreach ($package['items'] as $line): ?>
                        <a class="pkg-obj" href="<?= url('/alat/' . $line['slug']) ?>" title="<?= e($line['name']) ?> ×<?= (int) $line['quantity'] ?>">
                            <span class="pkg-obj-art" aria-hidden="true"><?php App\Core\View::partial('gear-glyph', ['item' => $line]) ?></span>
                            <span class="pkg-obj-name"><?= e($line['name']) ?></span>
                            <?php if ((int) $line['quantity'] > 1): ?><span class="obj-qty">×<?= (int) $line['quantity'] ?></span><?php endif ?>
                        </a>
                    <?php endforeach ?>
                </div>

                <div class="pkg-info">
                    <p class="eyebrow">Paket<?= (int) $package['sleepers'] > 0 ? ' · untuk sekitar ' . (int) $package['sleepers'] . ' orang' : '' ?></p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h2 class="pkg-name" id="pkg-<?= (int) $package['id'] ?>"><?= e($package['name']) ?></h2>
                        <?php if ((float) $package['discount_percent'] > 0): ?><span class="tag tag-cta">Hemat <?= (float) $package['discount_percent'] ?>%</span><?php endif ?>
                    </div>
                    <p class="pkg-desc"><?= e($package['description']) ?></p>
                    <dl class="pkg-price">
                        <div><dt>Harga satuan</dt><dd><s class="mono"><?= rupiah($package['normal_daily']) ?></s></dd></div>
                        <div><dt>Harga paket</dt><dd class="pkg-big"><?= rupiah($package['daily_price']) ?><small>/hari</small></dd></div>
                        <?php if ($saving > 0): ?><div><dt>Hemat</dt><dd class="pkg-save"><?= rupiah($saving) ?>/hari</dd></div><?php endif ?>
                    </dl>
                    <?php if (!$package['is_available']): ?>
                        <div class="alert alert-warning small py-2 mb-0">Sebagian alat di paket ini sedang tidak disewakan.</div>
                    <?php else: ?>
                        <button type="button" class="avail-line" data-trip-open data-avail-pkg="<?= (int) $package['id'] ?>" data-avail-empty="Pilih tanggal untuk cek ketersediaan paket">Pilih tanggal untuk cek ketersediaan paket</button>
                        <div class="pkg-actions">
                            <button type="button" class="btn btn-cta btn-ransel" data-ransel-add="package:<?= (int) $package['id'] ?>" data-name="<?= e($package['name']) ?>"><i class="bi bi-backpack" aria-hidden="true"></i> Masukkan ke ransel</button>
                            <a class="btn btn-outline-primary" href="<?= url('/sewa', ['paket' => $package['slug']]) ?>" data-sewa-link><i class="bi bi-calendar-check me-1" aria-hidden="true"></i> Sewa paket ini</a>
                        </div>
                    <?php endif ?>
                </div>
            </article>
        <?php endforeach ?>
    </div>

    <?php if (count($packages) > 1): ?>
        <section class="pkg-compare" aria-labelledby="compare-title">
            <div class="scene-head mb-3">
                <p class="eyebrow">Bandingkan</p>
                <h2 id="compare-title">Isi setiap paket</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle compare-table">
                    <thead>
                        <tr>
                            <th scope="col">Alat</th>
                            <?php foreach ($packages as $package): ?><th scope="col" class="text-center"><?= e(preg_replace('/^Paket\s+/i', '', $package['name'])) ?></th><?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compareRows as $row): ?>
                            <tr>
                                <th scope="row"><a href="<?= url('/alat/' . $row['slug']) ?>"><?= e($row['name']) ?></a></th>
                                <?php foreach ($packages as $package): $n = $row['qty'][(int) $package['id']] ?? 0; ?>
                                    <td class="text-center<?= $n === 0 ? ' text-muted' : ' fw-semibold' ?>"><?= $n === 0 ? '–' : '×' . $n ?></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Harga per hari</th>
                            <?php foreach ($packages as $package): ?><td class="text-center mono fw-semibold"><?= rupiah($package['daily_price']) ?></td><?php endforeach ?>
                        </tr>
                        <tr>
                            <th scope="row">Untuk sekitar</th>
                            <?php foreach ($packages as $package): ?><td class="text-center"><?= (int) $package['sleepers'] > 0 ? (int) $package['sleepers'] . ' orang' : '–' ?></td><?php endforeach ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    <?php endif ?>
</div>
