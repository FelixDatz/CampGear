<?php
/** @var array $damage */
use App\Models\DamageReport;

$base = '/staff/kerusakan/' . $damage['id'];
$open = $damage['status'] === 'unsettled';
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/staff/kerusakan') ?>"><i class="bi bi-arrow-left"></i> Daftar laporan</a>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <h2 class="h6 mb-0"><?= e($damage['equipment_name']) ?></h2>
                    <span class="badge <?= $damage['type'] === 'lost' ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= e(DamageReport::TYPE_LABELS[$damage['type']]) ?></span>
                </div>
                <dl class="spec-list small mb-0">
                    <div><dt>Booking</dt><dd><a class="font-monospace" href="<?= url('/staff/booking/' . $damage['code']) ?>"><?= e($damage['code']) ?></a></dd></div>
                    <div><dt>Penyewa</dt><dd><?= e($damage['customer_name']) ?> · <?= e($damage['customer_phone']) ?></dd></div>
                    <div><dt>Jumlah unit</dt><dd><?= (int) $damage['quantity'] ?></dd></div>
                    <div><dt>Dilaporkan</dt><dd><?= e(tanggal($damage['created_at'], false, true)) ?> · <?= e($damage['reporter_name'] ?? '-') ?></dd></div>
                    <div><dt>Status</dt><dd><?= e(DamageReport::STATUS_LABELS[$damage['status']]) ?></dd></div>
                    <?php if (!$open): ?>
                        <div><dt>Diselesaikan</dt><dd><?= e(tanggal($damage['settled_at'], false, true)) ?> · <?= e($damage['settler_name'] ?? '-') ?></dd></div>
                        <div><dt>Catatan penyelesaian</dt><dd><?= e($damage['settlement_note'] ?? '-') ?></dd></div>
                        <div><dt>Keterangan</dt><dd><?= e($damage['description'] ?: '-') ?></dd></div>
                        <div><dt>Nominal penggantian</dt><dd><?= $damage['charge_amount'] !== null ? rupiah($damage['charge_amount']) : '-' ?></dd></div>
                    <?php endif ?>
                </dl>
            </div>
        </div>
    </div>

    <?php if ($open): ?>
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Detail penggantian</h2>
                    <p class="small text-muted">Nominal dan bentuk penggantian dicatat per kasus. Kosongkan nominal bila belum ditentukan.</p>
                    <form method="post" action="<?= url($base) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="description">Keterangan</label>
                            <textarea class="form-control<?= invalid('description') ?>" id="description" name="description" rows="2" maxlength="500"><?= e(old('description', $damage['description'] ?? '')) ?></textarea>
                            <?php if ($msg = error('description')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="charge_amount">Nominal penggantian (Rp)</label>
                            <input class="form-control<?= invalid('charge_amount') ?>" type="number" id="charge_amount" name="charge_amount" min="0" step="1"
                                   value="<?= e(old('charge_amount', $damage['charge_amount'] !== null ? (string) (int) $damage['charge_amount'] : '')) ?>">
                            <?php if ($msg = error('charge_amount')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                    </form>
                </div>
            </div>

            <div class="card border-success">
                <div class="card-body">
                    <h2 class="h6 text-success">Nyatakan penggantian tuntas</h2>
                    <form method="post" action="<?= url($base . '/selesai') ?>" data-confirm="Nyatakan penggantian ini tuntas?">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="settlement_note">Catatan penyelesaian</label>
                            <textarea class="form-control<?= invalid('settlement_note') ?>" id="settlement_note" name="settlement_note" rows="2" minlength="5" maxlength="500" required
                                      placeholder="mis. diganti unit baru / dibayar tunai / diperbaiki penyewa"><?= e(old('settlement_note')) ?></textarea>
                            <?php if ($msg = error('settlement_note')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="payment_method">Catat pembayaran penggantian (opsional)</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="">Tidak ada pembayaran uang</option>
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer (simulasi)</option>
                                <option value="ewallet">E-wallet (simulasi)</option>
                            </select>
                            <div class="form-text">Bila dipilih, pembayaran sebesar nominal penggantian dicatat. Nominal harus sudah diisi.</div>
                        </div>
                        <button class="btn btn-success" type="submit">Tandai tuntas</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif ?>
</div>
