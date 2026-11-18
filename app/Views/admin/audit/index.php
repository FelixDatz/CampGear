<?php
/**
 * @var array $filters
 * @var array $actors
 * @var array $logs
 * @var App\Core\Paginator $pager
 */
use App\Enums\Role;
use App\Models\AuditLog;
?>
<div class="card">
    <div class="card-body">
        <p class="small text-muted">Setiap tindakan Staff dan Admin yang mengubah data tercatat otomatis beserta waktu, pelaku, dan alamat IP.</p>
        <form class="row g-2 mb-3" method="get" action="<?= url('/admin/log') ?>">
            <div class="col-md-3">
                <select class="form-select" name="user" aria-label="Pelaku">
                    <option value="0">Semua pelaku</option>
                    <?php foreach ($actors as $actor): ?>
                        <option value="<?= (int) $actor['id'] ?>"<?= selected($filters['user'], $actor['id']) ?>><?= e($actor['name']) ?> (<?= e(Role::from($actor['role'])->label()) ?>)</option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="group" aria-label="Jenis tindakan">
                    <option value="">Semua tindakan</option>
                    <?php foreach (AuditLog::GROUPS as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= selected($filters['group'], $value) ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="Dari tanggal"></div>
            <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="Sampai tanggal"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button></div>
        </form>

        <?php if ($logs === []): ?>
            <div class="empty-state py-4"><i class="bi bi-clock-history"></i><p class="text-muted mb-0">Belum ada catatan yang cocok.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr class="small text-muted"><th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Keterangan</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log): $link = AuditLog::entityLink($log); $meta = $log['meta'] ? json_decode($log['meta'], true) : null; ?>
                        <tr>
                            <td class="small text-nowrap"><?= e(tanggal($log['created_at'], false, true)) ?></td>
                            <td class="small"><?= e($log['user_name'] ?? 'Akun dihapus') ?>
                                <?php if ($log['user_role']): ?><div><span class="badge <?= e(Role::from($log['user_role'])->badgeClass()) ?>"><?= e(Role::from($log['user_role'])->label()) ?></span></div><?php endif ?></td>
                            <td><code class="small"><?= e($log['action']) ?></code></td>
                            <td class="small">
                                <?php if ($link): ?><a href="<?= url($link) ?>"><?= e($log['description']) ?></a><?php else: ?><?= e($log['description']) ?><?php endif ?>
                                <?php if (is_array($meta) && $meta !== []): ?>
                                    <details class="mt-1"><summary class="text-muted">Detail</summary>
                                        <pre class="small bg-light rounded p-2 mb-0 text-wrap"><?= e(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                    </details>
                                <?php endif ?>
                            </td>
                            <td class="small text-muted"><?= e($log['ip_address'] ?? '') ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
