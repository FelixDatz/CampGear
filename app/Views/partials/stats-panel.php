<?php
/**
 * Panel grafik statistik (dipakai halaman /statistik dan dashboard staff).
 * Data dimuat oleh public/assets/js/stats.js dari GET /api/stats.
 * @var bool $showRevenue tampilkan grafik pendapatan (staff)
 */
$showRevenue ??= false;
$charts = [
    'rentals'       => ['Penyewaan per periode', 'Jumlah booking yang dibayar, menurut tanggal mulai sewa.'],
    'cancellations' => ['Pengajuan pembatalan', 'Menurut tanggal pengajuan dan status peninjauan.'],
    'utilization'   => ['Tingkat pemakaian stok', 'Unit-hari tersewa dibanding kapasitas (stok × hari), dalam persen.'],
    'categories'    => ['Pemakaian stok per kategori', 'Rata-rata untuk seluruh rentang yang dipilih.'],
];
if ($showRevenue) {
    $charts['revenue'] = ['Pendapatan (simulasi)', 'Pembayaran berstatus lunas, termasuk pembayaran penggantian.'];
}
?>
<div class="stats-root" data-stats-root data-show-revenue="<?= $showRevenue ? '1' : '0' ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group" role="group" aria-label="Periode">
            <?php foreach (App\Models\Stats::PERIODS as $value => $period): ?>
                <input class="btn-check" type="radio" name="stats-period" id="period-<?= e($value) ?>" value="<?= e($value) ?>" data-period<?= $value === 'month' ? ' checked' : '' ?>>
                <label class="btn btn-outline-primary btn-sm" for="period-<?= e($value) ?>"><?= e($period['label']) ?></label>
            <?php endforeach ?>
        </div>
        <label class="visually-hidden" for="stats-range">Rentang</label>
        <select class="form-select form-select-sm w-auto" id="stats-range" data-range></select>
        <span class="small text-muted ms-md-2" data-range-label aria-live="polite"></span>
    </div>

    <div class="row g-3 mb-3">
        <?php
        $tiles = [
            ['rentals', 'Penyewaan', 'bi-calendar-check'],
            ['cancellations', 'Pengajuan pembatalan', 'bi-x-circle'],
            ['utilization', 'Rata-rata pemakaian stok', 'bi-pie-chart'],
        ];
        if ($showRevenue) {
            $tiles[] = ['revenue', 'Pendapatan (simulasi)', 'bi-cash-stack'];
        }
        foreach ($tiles as [$key, $label, $icon]): ?>
            <div class="col-6 col-lg">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="small text-muted"><i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></div>
                        <div class="fs-4 fw-bold" data-total="<?= e($key) ?>">–</div>
                        <?php if ($key === 'cancellations'): ?><div class="small text-muted" data-total-note="cancellations"></div><?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="alert alert-danger d-none" data-stats-error role="alert"></div>

    <div class="row g-3">
        <?php foreach ($charts as $key => [$title, $subtitle]): ?>
            <div class="<?= $key === 'revenue' ? 'col-12' : 'col-lg-6' ?>">
                <div class="card h-100 chart-card">
                    <div class="card-body">
                        <h2 class="h6 mb-0"><?= e($title) ?></h2>
                        <p class="small text-muted mb-2"><?= e($subtitle) ?></p>
                        <div class="chart-box">
                            <canvas data-chart="<?= e($key) ?>" role="img" aria-label="Grafik <?= e(mb_strtolower($title)) ?>"></canvas>
                            <div class="chart-empty d-none" data-empty="<?= e($key) ?>">Belum ada data pada periode ini.</div>
                        </div>
                        <details class="mt-2 small">
                            <summary class="text-muted">Lihat tabel data</summary>
                            <div class="table-responsive mt-2" data-table-for="<?= e($key) ?>"></div>
                        </details>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
