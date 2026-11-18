<?php /** @var array $packages */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <p class="text-muted mb-0 small">Paket adalah kumpulan alat dengan diskon persentase. Paket yang pernah dipesan tidak dapat dihapus, cukup dinonaktifkan.</p>
    <a class="btn btn-primary flex-shrink-0" href="<?= url('/staff/paket/baru') ?>"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="row g-3">
    <?php foreach ($packages as $package): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100<?= (int) $package['is_active'] === 1 ? '' : ' opacity-75' ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h2 class="h6 mb-1"><?= e($package['name']) ?></h2>
                        <span class="badge <?= (int) $package['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $package['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
                    </div>
                    <div class="small text-muted mb-2">Diskon <?= (float) $package['discount_percent'] ?>% · <?= rupiah($package['daily_price']) ?>/hari</div>
                    <ul class="small mb-3">
                        <?php foreach ($package['items'] as $line): ?>
                            <li class="<?= (int) $line['is_active'] === 1 ? '' : 'text-danger' ?>"><?= e($line['name']) ?> ×<?= (int) $line['quantity'] ?><?= (int) $line['is_active'] === 1 ? '' : ' (nonaktif)' ?></li>
                        <?php endforeach ?>
                    </ul>
                    <div class="mt-auto d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="<?= url('/staff/paket/' . $package['id'] . '/edit') ?>">Ubah</a>
                        <form method="post" action="<?= url('/staff/paket/' . $package['id']) ?>" data-confirm="Hapus paket <?= e($package['name']) ?>?">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
    <?php if ($packages === []): ?>
        <div class="col-12"><div class="empty-state"><i class="bi bi-box2"></i><p class="text-muted">Belum ada paket.</p></div></div>
    <?php endif ?>
</div>
