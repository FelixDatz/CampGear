<?php
/**
 * Beranda: tujuh adegan berurutan seperti pendakian (ketinggian di data-mdpl).
 *   1. Basecamp   langit + matahari pengatur tema, nama brand di antara lapisan gunung
 *   2. Pilih medan grid kategori yang "bernapas": kartu yang disentuh membesar dan
 *                 menampilkan isinya (kartu Trip menumpang di tepi atasnya)
 *   3. Loadout    isi paket di papan, info harga di kanan
 *   4. Jalur sewa profil ketinggian empat pos, ditahan saat di-scroll
 *   5. Katalog    semua alat; chip kategori menempel selama katalog di-scroll
 *   6. Tiket      contoh tiket ekspedisi dan perjalanan statusnya
 *   7. Artikel    kolom horizontal yang melebar saat disentuh
 *
 * @var array $categories
 * @var array $equipment
 * @var array $itemsByCategory
 * @var array $packages
 * @var array $packagesByItem
 * @var array $packagesByCategory
 * @var array|null $sample
 * @var array $articles
 * @var array $rental
 * @var array $business
 */
use App\Enums\BookingStatus;

$wide = ['tent', 'tent-l', 'carrier', 'mat', 'chair', 'table', 'pole'];
$shortName = static fn (string $name): string => preg_replace('/^Paket\s+/i', '', $name);
$hours = (int) $rental['payment_window_hours'];
// Langkah tiket contoh: label status, teks stempel, nada warna, penjelasan.
// {ambil} dan {kembali} diganti tanggal tiket oleh home.js.
$demoSteps = [
    [BookingStatus::PendingPayment->label(), 'Menunggu bayar', 'warn', "Selesaikan pembayaran sebelum waktunya habis. Lewat {$hours} jam, booking kedaluwarsa dan stoknya dilepas."],
    [BookingStatus::Confirmed->label(), 'Terkonfirmasi', 'accent', 'Tunjukkan tiket ini saat mengambil alat pada {ambil}. Kondisi setiap alat dicek bersama staf.'],
    [BookingStatus::PickedUp->label(), 'Sedang disewa', 'accent', 'Selamat menikmati perjalanan. Kembalikan alat paling lambat {kembali}.'],
    [BookingStatus::Returned->label(), 'Dikembalikan', 'muted', 'Staf mencatat kondisi setiap alat. Bila ada kerusakan atau kehilangan, booking selesai setelah penggantian tuntas.'],
    [BookingStatus::Completed->label(), 'Selesai', 'ok', 'Semua urusan tuntas. Sampai jumpa di perjalanan berikutnya.'],
];
$posts = [
    ['Pilih alat & tanggal', 'maks. ' . (int) $rental['max_rental_days'] . ' hari', 'Stok dicek untuk setiap hari dalam rentang sewamu.',
        'Durasi dihitung termasuk tanggal ambil dan kembali, maksimal ' . (int) $rental['max_rental_days'] . ' hari, paling jauh ' . (int) $rental['max_advance_days'] . ' hari ke depan.'],
    ['Bayar dalam ' . (int) $rental['payment_window_hours'] . ' jam', (int) $rental['payment_window_hours'] . ' jam', 'Lewat batas waktu, booking kedaluwarsa.',
        'Booking yang belum dibayar dalam ' . (int) $rental['payment_window_hours'] . ' jam kedaluwarsa, dan stoknya dilepas untuk penyewa lain.'],
    ['Ambil alat', 'QR + checklist', 'Tunjukkan tiket ber-QR.',
        'Tunjukkan tiket ekspedisi ber-QR. Kondisi setiap alat dicek bersama staf dengan checklist serah terima.'],
    ['Kembalikan', 'sesuai jadwal', 'Kondisi alat dicatat staf.',
        'Kembalikan paling lambat tanggal selesai sewa. Bila ada kerusakan atau kehilangan, booking selesai setelah penggantian tuntas.'],
];
// Titik pos pada jalur (koordinat viewBox SVG 1200 × 420). Puncak berada di antara Pos 3 dan Pos 4.
$marks = [[170, 350], [420, 260], [640, 170], [1060, 330]];
$trail = 'M40 388 C 100 388 120 356 170 350 C 260 340 340 290 420 260 C 500 230 570 200 640 170 C 710 140 770 64 820 60 C 880 56 900 170 960 240 C 1000 290 1030 326 1060 330 C 1100 336 1130 388 1160 388';
?>
<div class="stack" id="stack" data-stack>

    <!-- 1. Basecamp -->
    <section class="scene hero" id="basecamp" data-mdpl="95" data-label="Basecamp" aria-labelledby="hero-title">
        <div class="scene-inner" data-hero>
            <canvas class="sky-canvas" data-sky aria-hidden="true"></canvas>
            <div class="sun" role="slider" tabindex="0" data-sun
                 aria-label="Posisi matahari, mengatur tema terang atau gelap" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" aria-valuetext="Siang">
                <span class="sun-disc"></span>
            </div>
            <canvas class="ridge-canvas ridge-back" data-ridge="back" aria-hidden="true"></canvas>
            <canvas class="ridge-canvas ridge-front" data-ridge="front" aria-hidden="true"></canvas>
            <div class="hero-stage">
                <h1 class="hero-word" id="hero-title" data-hero-word><?= e(config('app.name')) ?><span class="visually-hidden">, persewaan alat camping di Surakarta</span></h1>
                <p class="hero-tagline"><?= e($business['tagline']) ?> Pilih tanggal dulu, kami tunjukkan alat yang tersedia.</p>
            </div>
        </div>
    </section>

    <!-- 2. Pilih medan (sepertiga atasnya mengintip di hero) -->
    <section class="scene medan" id="medan" data-mdpl="700" data-label="Pilih medan" aria-labelledby="medan-title">
        <div class="trip-dock">
            <form class="trip-card glass" data-trip-card novalidate aria-label="Rencana perjalanan">
                <div class="trip-row">
                    <button class="trip-field" type="button" data-trip-open>
                        <span class="trip-label">Tanggal ambil &amp; kembali</span>
                        <span class="trip-value" data-trip-dates data-trip-empty="Pilih tanggal">Pilih tanggal</span>
                    </button>
                    <div class="trip-field trip-people">
                        <span class="trip-label" id="people-label">Rombongan</span>
                        <div class="stepper" role="group" aria-labelledby="people-label">
                            <button type="button" data-people="-1" aria-label="Kurangi orang"><i class="bi bi-dash" aria-hidden="true"></i></button>
                            <output data-people-out aria-live="polite">2 orang</output>
                            <button type="button" data-people="1" aria-label="Tambah orang"><i class="bi bi-plus" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <button class="btn btn-cta trip-go" type="submit">Cek alat tersedia <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </div>
                <p class="trip-result" data-trip-result hidden aria-live="polite"></p>
            </form>
        </div>
        <div class="scene-inner">
            <div class="container medan-content">
                <header class="medan-head">
                    <div class="scene-head">
                        <p class="eyebrow">Pilih medan</p>
                        <h2 id="medan-title">Satu kategori, satu kebutuhan.</h2>
                    </div>
                    <p class="medan-hint"><?= count($categories) ?> kategori alat sewa harian. Sentuh kartu untuk melihat isinya dan stok di tanggalmu.</p>
                </header>
                <div class="medan-grid" data-medan>
                    <?php foreach ($categories as $category):
                        $items = $itemsByCategory[(int) $category['id']] ?? [];
                        $from = $items === [] ? null : min(array_map(static fn ($i) => (float) $i['daily_rate'], $items));
                        $base = $items === [] ? 'Segera tersedia' : count($items) . ' alat · mulai ' . rupiah($from) . '/hari';
                        $pkgs = $packagesByCategory[(int) $category['id']] ?? [];
                        $detailId = 'medan-' . $category['slug'];
                    ?>
                        <article class="medan-card lamp" data-medan-card>
                            <button class="medan-face" type="button" aria-expanded="false" aria-controls="<?= e($detailId) ?>">
                                <i class="bi <?= e($category['icon'] ?: 'bi-box') ?>" aria-hidden="true"></i>
                                <span class="medan-name"><?= e($category['name']) ?></span>
                                <span class="medan-meta" data-medan-meta data-base="<?= e($base) ?>" data-ids="<?= e(implode(',', array_column($items, 'id'))) ?>"><?= e($base) ?></span>
                            </button>
                            <div class="medan-art" aria-hidden="true">
                                <?php foreach (array_slice($items, 0, 4) as $item): ?>
                                    <?php App\Core\View::partial('gear-glyph', ['item' => $item]) ?>
                                <?php endforeach ?>
                            </div>
                            <div class="medan-detail" id="<?= e($detailId) ?>">
                                <div class="medan-side">
                                    <?php if ($category['description']): ?><p class="medan-desc"><?= e($category['description']) ?></p><?php endif ?>
                                    <?php if ($pkgs !== []): ?>
                                        <p class="medan-pkgs">Ada di paket
                                            <?php foreach ($pkgs as $k => $ref): ?><?= $k > 0 ? ' · ' : ' ' ?><a href="<?= url('/paket') ?>#<?= e($ref['slug']) ?>" data-go-pkg="<?= (int) $ref['id'] ?>"><?= e($shortName($ref['name'])) ?></a><?php endforeach ?>
                                        </p>
                                    <?php endif ?>
                                    <a class="medan-go" href="<?= url('/alat', ['kategori' => $category['slug']]) ?>" data-katalog-cat="<?= e($category['slug']) ?>">
                                        Lihat <?= count($items) ?> alat di katalog <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                                <?php if ($items !== []): ?>
                                    <ul class="medan-items">
                                        <?php foreach ($items as $item): ?>
                                            <li data-avail-row>
                                                <span class="medan-glyph" aria-hidden="true"><?php App\Core\View::partial('gear-glyph', ['item' => $item]) ?></span>
                                                <a class="medan-item" href="<?= url('/alat/' . $item['slug']) ?>"><?= e($item['name']) ?></a>
                                                <span class="medan-rate mono"><?= rupiah($item['daily_rate']) ?></span>
                                                <span class="avail-line medan-avail" data-avail-id="<?= (int) $item['id'] ?>" data-avail-short data-avail-empty="stok <?= (int) $item['stock_total'] ?>">stok <?= (int) $item['stock_total'] ?></span>
                                                <button class="medan-add" type="button" data-ransel-add="item:<?= (int) $item['id'] ?>" data-name="<?= e($item['name']) ?>" aria-label="Tambah <?= e($item['name']) ?> ke ransel"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                            </li>
                                        <?php endforeach ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="medan-desc">Belum ada alat aktif di kategori ini.</p>
                                <?php endif ?>
                            </div>
                        </article>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Loadout -->
    <section class="scene loadout" id="loadout" data-mdpl="1250" data-label="Loadout" aria-labelledby="loadout-title">
        <div class="scene-inner">
            <div class="container loadout-grid">
                <div>
                    <?php foreach ($packages as $i => $package): ?>
                        <div class="board" data-board="<?= (int) $package['id'] ?>"<?= $i === 0 ? '' : ' hidden' ?> aria-label="Isi <?= e($package['name']) ?>">
                            <?php foreach ($package['items'] as $item): $kind = gear_kind($item['name'] . ' ' . $item['slug']); ?>
                                <div class="obj<?= in_array($kind, $wide, true) ? ' span2' : '' ?>" data-obj>
                                    <div class="obj-art"><?php App\Core\View::partial('gear-glyph', ['item' => $item]) ?></div>
                                    <?php if ((int) $item['quantity'] > 1): ?><span class="obj-qty">×<?= (int) $item['quantity'] ?></span><?php endif ?>
                                    <button class="hot" type="button" aria-expanded="false" aria-label="<?= e($item['name']) ?>, <?= (int) $item['quantity'] ?> unit di paket"></button>
                                    <div class="obj-tip" role="group" aria-label="<?= e($item['name']) ?>">
                                        <b><?= e($item['name']) ?></b>
                                        <span class="mono"><?= rupiah($item['daily_rate']) ?>/hari · <?= (int) $item['quantity'] ?> di paket</span>
                                        <div class="obj-tip-actions">
                                            <button class="btn btn-sm btn-cta" type="button" data-ransel-add="item:<?= (int) $item['equipment_id'] ?>" data-name="<?= e($item['name']) ?>"><i class="bi bi-plus" aria-hidden="true"></i> Ransel</button>
                                            <a href="<?= url('/alat/' . $item['slug']) ?>">Detail alat</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endforeach ?>
                </div>

                <div class="loadout-info">
                    <p class="eyebrow">Loadout</p>
                    <h2 id="loadout-title">Lihat isi paketnya sebelum menyewa.</h2>
                    <?php if ($packages === []): ?>
                        <p class="pkg-desc">Belum ada paket aktif. Kamu tetap bisa menyewa alat satuan dari <a href="<?= url('/alat') ?>">katalog</a>.</p>
                    <?php else: ?>
                        <div class="pkg-tabs" role="tablist" aria-label="Paket sewa">
                            <?php foreach ($packages as $i => $package): ?>
                                <button class="pkg-tab" type="button" role="tab" id="tab-pkg-<?= (int) $package['id'] ?>" aria-controls="panel-pkg-<?= (int) $package['id'] ?>"
                                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"
                                        data-pkg-tab="<?= (int) $package['id'] ?>" data-sleepers="<?= (int) $package['sleepers'] ?>"><?= e(preg_replace('/^Paket\s+/i', '', $package['name'])) ?></button>
                            <?php endforeach ?>
                        </div>
                        <?php foreach ($packages as $i => $package): $saving = (float) $package['normal_daily'] - (float) $package['daily_price']; ?>
                            <div class="pkg-panel" role="tabpanel" id="panel-pkg-<?= (int) $package['id'] ?>" aria-labelledby="tab-pkg-<?= (int) $package['id'] ?>"
                                 data-pkg-panel="<?= (int) $package['id'] ?>" data-price="<?= (float) $package['daily_price'] ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                                <p class="pkg-desc"><?= e($package['description']) ?></p>
                                <?php if ((int) $package['sleepers'] > 0): ?>
                                    <p class="pkg-hint" data-pkg-hint>Untuk sekitar <?= (int) $package['sleepers'] ?> orang (<?= (int) $package['sleepers'] ?> sleeping bag).</p>
                                <?php endif ?>
                                <dl class="pkg-price">
                                    <div><dt>Harga satuan</dt><dd><s class="mono"><?= rupiah($package['normal_daily']) ?></s></dd></div>
                                    <div><dt>Harga paket</dt><dd class="pkg-big"><?= rupiah($package['daily_price']) ?><small>/hari</small></dd></div>
                                    <?php if ($saving > 0): ?><div><dt>Hemat</dt><dd class="pkg-save"><?= (float) $package['discount_percent'] ?>% · <?= rupiah($saving) ?>/hari</dd></div><?php endif ?>
                                    <div data-pkg-total hidden><dt data-pkg-total-label></dt><dd class="mono" data-pkg-total-value></dd></div>
                                </dl>
                                <p class="avail-line" data-avail-pkg="<?= (int) $package['id'] ?>" data-avail-empty="Pilih tanggal untuk cek ketersediaan paket"></p>
                                <div class="pkg-actions">
                                    <button class="btn btn-cta" type="button" data-ransel-add="package:<?= (int) $package['id'] ?>" data-name="<?= e($package['name']) ?>"><i class="bi bi-backpack me-1" aria-hidden="true"></i>Masukkan ke ransel</button>
                                    <a class="btn btn-outline-primary" href="<?= url('/sewa', ['paket' => $package['slug']]) ?>" data-sewa-link>Sewa paket ini</a>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Jalur sewa -->
    <section class="jalur" id="jalur" data-mdpl="1800" data-label="Jalur sewa" aria-labelledby="jalur-title" data-jalur>
        <div class="jalur-pin">
            <div class="container jalur-grid">
                <div class="jalur-head">
                    <div class="scene-head">
                        <p class="eyebrow">Jalur sewa</p>
                        <h2 id="jalur-title">Empat pos, dari memilih alat sampai pulang.</h2>
                    </div>
                    <a class="fw-semibold" href="<?= url('/cara-reservasi') ?>">Ketentuan lengkap <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="trail" data-trail>
                    <svg viewBox="0 0 1200 420" role="img" aria-labelledby="trail-desc">
                        <title id="trail-desc">Profil jalur sewa: Pos 1 sampai Pos 3 menanjak ke puncak (perjalananmu), lalu turun ke Pos 4.</title>
                        <path class="trail-ground" d="<?= $trail ?> L1160 420 L40 420 Z"/>
                        <path class="trail-base" d="<?= $trail ?>" data-trail-base/>
                        <path class="trail-prog" d="<?= $trail ?>" data-trail-prog/>
                        <g class="trail-summit" transform="translate(820 60)">
                            <path class="flag" d="M0 0V-34L22 -27L0 -20Z"/>
                            <text x="30" y="-12">Puncak: perjalananmu</text>
                        </g>
                        <?php foreach ($marks as $i => [$x, $y]): [$name, $key, $line] = $posts[$i]; ?>
                            <g class="trail-mark" transform="translate(<?= $x ?> <?= $y ?>)" data-pos="<?= $i ?>" tabindex="0" role="button"
                               aria-label="Pos <?= $i + 1 ?>: <?= e($name) ?>" data-title="Pos <?= $i + 1 ?> · <?= e($name) ?>" data-key="<?= e($key) ?>" data-line="<?= e($line) ?>">
                                <circle class="ring" r="12"/><circle class="dot" r="5"/>
                                <text y="-24" text-anchor="middle">Pos <?= $i + 1 ?></text>
                            </g>
                        <?php endforeach ?>
                        <circle class="trail-hiker" r="9" cx="40" cy="388" data-hiker/>
                    </svg>
                    <div class="trail-tip" data-trail-tip hidden></div>
                </div>
                <ol class="pos-list">
                    <?php foreach ($posts as $i => [$name, $key, , $detail]): ?>
                        <li><button class="pos-card lamp" type="button" data-pos="<?= $i ?>"><span class="mono">Pos <?= $i + 1 ?> · <?= e($key) ?></span><b><?= e($name) ?></b><span><?= e($detail) ?></span></button></li>
                    <?php endforeach ?>
                </ol>
            </div>
        </div>
    </section>
</div>

<!-- 5. Katalog: chip kategori menempel di bawah navbar selama katalog di-scroll -->
<section class="home-sec katalog-home" id="katalog" data-mdpl="2300" data-label="Katalog" aria-labelledby="katalog-title">
    <div class="container">
        <div class="sec-head">
            <div class="scene-head">
                <p class="eyebrow">Katalog</p>
                <h2 id="katalog-title">Semua alat dalam satu rak.</h2>
                <p>Saring per kategori, sentuh kartu untuk ringkasannya, lalu masukkan ke ransel.</p>
            </div>
            <a class="fw-semibold" href="<?= url('/alat') ?>">Buka halaman katalog <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        <?php App\Core\View::partial('catalog-browser', [
            'categories'     => $categories,
            'items'          => $equipment,
            'packagesByItem' => $packagesByItem,
            'activeSlug'     => '',
            'search'         => '',
            'isPage'         => false,
        ]) ?>
    </div>
</section>

<!-- 6. Tiket ekspedisi: contoh yang isinya mengikuti tanggal trip dan ransel pengunjung -->
<section class="home-sec tiket-home" id="tiket" data-mdpl="2700" data-label="Tiket" aria-labelledby="tiket-title"
         data-tiket-demo data-hours="<?= $hours ?>" data-sample-name="<?= e($sample['name'] ?? '') ?>" data-sample-price="<?= (float) ($sample['daily_price'] ?? 0) ?>">
    <div class="container">
        <div class="sec-head">
            <div class="scene-head">
                <p class="eyebrow">Tiket ekspedisi</p>
                <h2 id="tiket-title">Satu tiket untuk seluruh perjalanan.</h2>
                <p>Setelah booking, kamu mendapat tiket seperti ini. Isinya mengikuti tanggal dan ranselmu. Coba jalankan statusnya.</p>
            </div>
            <button class="btn btn-link fw-semibold p-0" type="button" popovertarget="pop-cek">Sudah booking? Cek tiketmu <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </div>
        <div class="tiket-grid">
            <?php App\Core\View::partial('ticket', ['t' => [
                'sample'   => true,
                'code'     => 'CG' . date('ymd') . '-DEMO',
                'start'    => '–',
                'startSub' => '',
                'end'      => '–',
                'endSub'   => '',
                'days'     => '–',
                'isi'      => $sample['name'] ?? '–',
                'total'    => '–',
                'stamp'    => $demoSteps[0][1],
                'tone'     => $demoSteps[0][2],
                'stub'     => 'demo',
            ]]) ?>
            <div class="status">
                <ol class="status-trail" style="--prog: 0" aria-label="Perjalanan status booking (contoh)" data-demo-trail>
                    <?php foreach ($demoSteps as $i => [$label, $stamp, $tone, $body]): ?>
                        <li class="<?= $i === 0 ? 'is-now' : '' ?>"<?= $i === 0 ? ' aria-current="step"' : '' ?> data-stamp="<?= e($stamp) ?>" data-tone="<?= e($tone) ?>" data-body="<?= e($body) ?>">
                            <span class="st-dot" aria-hidden="true"></span><span class="st-label"><?= e($label) ?></span>
                        </li>
                    <?php endforeach ?>
                </ol>
                <div class="status-now" data-tone="<?= e($demoSteps[0][2]) ?>" data-demo-now aria-live="polite">
                    <p class="eyebrow">Status sekarang</p>
                    <h3 data-demo-title><?= e($demoSteps[0][0]) ?></h3>
                    <p data-demo-body><?= e($demoSteps[0][3]) ?></p>
                    <div class="countdown" data-demo-countdown>
                        <span class="mono" data-demo-clock><?= sprintf('%02d:00:00', $hours) ?></span>
                        <span class="small text-muted">sisa waktu bayar (contoh)</span>
                    </div>
                </div>
                <p class="status-exit">Booking juga bisa berakhir sebagai <b>Kedaluwarsa</b> (tidak dibayar dalam <?= $hours ?> jam) atau <b>Dibatalkan</b> (pengajuan pembatalan disetujui staf).</p>
                <div class="status-actions">
                    <button class="btn btn-cta" type="button" data-demo-pay>Coba bayar (contoh)</button>
                    <button class="btn btn-outline-secondary" type="button" data-demo-next hidden>Lanjut ke status berikutnya <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    <button class="btn btn-outline-secondary" type="button" data-demo-reset hidden>Ulangi dari awal</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 7. Artikel -->
<?php if ($articles !== []): ?>
<section class="catatan" id="catatan" data-mdpl="3000" data-label="Artikel" aria-labelledby="catatan-title">
    <div class="container catatan-head">
        <div class="scene-head">
            <p class="eyebrow">Artikel Terbaru</p>
            <h2 id="catatan-title">Bacaan singkat sebelum berangkat.</h2>
        </div>
        <a class="fw-semibold" href="<?= url('/artikel') ?>">Semua artikel <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="catatan-list">
        <?php foreach ($articles as $article): $summary = $article['excerpt'] ?: excerpt($article['body'], 160); ?>
            <a class="catatan-item" href="<?= url('/artikel/' . $article['slug']) ?>">
                <div class="catatan-main">
                    <time datetime="<?= e(substr((string) $article['published_at'], 0, 10)) ?>"><?= e(tanggal($article['published_at'])) ?></time>
                    <h3><?= e($article['title']) ?></h3>
                    <p><?= e($summary) ?></p>
                </div>
                <div class="catatan-more" aria-hidden="true">
                    <div class="catatan-cover">
                        <?php if ($cover = upload_url($article['cover_image'])): ?>
                            <img src="<?= e($cover) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <i class="bi bi-journal-text"></i>
                        <?php endif ?>
                    </div>
                    <div>
                        <p><?= e($summary) ?></p>
                        <p class="meta"><?= App\Models\Article::readingMinutes($article['body']) ?> menit baca<?= $article['author_name'] ? ' · oleh ' . e($article['author_name']) : '' ?></p>
                        <span class="go">Baca artikel <i class="bi bi-arrow-right"></i></span>
                    </div>
                </div>
            </a>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<nav class="alti" aria-label="Ketinggian halaman" data-alti>
    <span class="alti-readout"><span data-alti-value>95</span> <span class="alti-unit">mdpl</span></span>
    <div class="alti-rail" data-alti-rail><span class="alti-marker" data-alti-marker></span></div>
    <span class="alti-unit">Solo → Lawu</span>
</nav>
