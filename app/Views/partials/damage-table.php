<?php
/**
 * Tabel laporan kerusakan/kehilangan.
 * @var array $damages
 * @var bool  $showCode   tampilkan kolom kode booking (panel staff)
 * @var bool  $staffLinks tautan ke halaman kelola laporan
 */
use App\Models\DamageReport;

$staffLinks ??= false;
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
            <tr class="small text-muted">
                <?php if ($showCode): ?><th>Booking</th><?php endif ?>
                <th>Alat</th><th>Jenis</th><th class="text-end">Unit</th><th>Keterangan</th><th class="text-end">Penggantian</th><th>Status</th>
                <?php if ($staffLinks): ?><th></th><?php endif ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($damages as $damage): ?>
            <tr>
                <?php if ($showCode): ?>
                    <td><a class="font-monospace" href="<?= url('/staff/booking/' . $damage['code']) ?>"><?= e($damage['code']) ?></a>
                        <div class="small text-muted"><?= e($damage['customer_name']) ?></div></td>
                <?php endif ?>
                <td><?= e($damage['equipment_name']) ?></td>
                <td><span class="badge <?= $damage['type'] === 'lost' ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= e(DamageReport::TYPE_LABELS[$damage['type']]) ?></span></td>
                <td class="text-end"><?= (int) $damage['quantity'] ?></td>
                <td class="small"><?= e($damage['description'] ?: '-') ?></td>
                <td class="text-end text-nowrap"><?= $damage['charge_amount'] !== null ? rupiah($damage['charge_amount']) : '<span class="small text-muted">Belum ditentukan</span>' ?></td>
                <td>
                    <span class="badge <?= $damage['status'] === 'settled' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(DamageReport::STATUS_LABELS[$damage['status']]) ?></span>
                    <?php if ($damage['status'] === 'settled' && $damage['settlement_note']): ?>
                        <div class="small text-muted"><?= e($damage['settlement_note']) ?></div>
                    <?php endif ?>
                </td>
                <?php if ($staffLinks): ?>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('/staff/kerusakan/' . $damage['id']) ?>">Kelola</a></td>
                <?php endif ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
