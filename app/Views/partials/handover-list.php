<?php
/** @var array $handovers */
use App\Models\Handover;
?>
<?php foreach ($handovers as $handover): ?>
    <div class="handover-block">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
            <strong><?= e(Handover::TYPE_LABELS[$handover['type']]) ?></strong>
            <span class="small text-muted"><?= e(tanggal($handover['created_at'], false, true)) ?> · <?= e($handover['staff_name'] ?? 'Staf') ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-1 small">
                <thead><tr class="text-muted"><th>Alat</th><th class="text-end">Baik</th><th class="text-end">Rusak</th><?php if ($handover['type'] === 'return'): ?><th class="text-end">Hilang</th><?php endif ?><th>Catatan</th></tr></thead>
                <tbody>
                <?php foreach ($handover['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['equipment_name']) ?></td>
                        <td class="text-end"><?= (int) $item['qty_good'] ?></td>
                        <td class="text-end<?= $item['qty_damaged'] > 0 ? ' text-warning-emphasis fw-semibold' : '' ?>"><?= (int) $item['qty_damaged'] ?></td>
                        <?php if ($handover['type'] === 'return'): ?>
                            <td class="text-end<?= $item['qty_lost'] > 0 ? ' text-danger fw-semibold' : '' ?>"><?= (int) $item['qty_lost'] ?></td>
                        <?php endif ?>
                        <td><?= e($item['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if ($handover['notes']): ?><p class="small text-muted mb-0"><?= e($handover['notes']) ?></p><?php endif ?>
    </div>
<?php endforeach ?>
