<?php
/**
 * @var string $status
 * @var array $damages
 * @var App\Core\Paginator $pager
 */
$tabs = ['unsettled' => 'Belum diselesaikan', 'settled' => 'Selesai', 'all' => 'Semua'];
?>
<?php App\Core\View::partial('status-tabs', ['tabs' => $tabs, 'current' => $status, 'path' => '/staff/kerusakan']) ?>

<div class="card">
    <div class="card-body">
        <p class="small text-muted">Laporan dibuat otomatis saat staf mencatat pengembalian alat yang rusak atau hilang. Penyewa wajib melakukan penggantian; booking baru bisa diselesaikan setelah semua laporannya tuntas.</p>
        <?php if ($damages === []): ?>
            <div class="empty-state py-4">
                <i class="bi bi-shield-check"></i>
                <p class="text-muted mb-0">Tidak ada laporan<?= $status === 'unsettled' ? ' yang belum diselesaikan' : '' ?>.</p>
            </div>
        <?php else: ?>
            <?php App\Core\View::partial('damage-table', ['damages' => $damages, 'showCode' => true, 'staffLinks' => true]) ?>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
