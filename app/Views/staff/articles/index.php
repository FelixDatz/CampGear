<?php
/**
 * @var string $status
 * @var array $articles
 * @var App\Core\Paginator $pager
 */
use App\Models\Article;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <?php App\Core\View::partial('status-tabs', ['tabs' => ['' => 'Semua', 'published' => 'Terbit', 'draft' => 'Draf'], 'current' => $status, 'path' => '/staff/artikel']) ?>
    <a class="btn btn-primary mb-3" href="<?= url('/staff/artikel/baru') ?>"><i class="bi bi-pencil-square"></i> Tulis artikel</a>
</div>
<div class="card">
    <div class="card-body">
        <?php if ($articles === []): ?>
            <div class="empty-state py-4"><i class="bi bi-newspaper"></i><p class="text-muted mb-0">Belum ada artikel.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr class="small text-muted"><th>Judul</th><th>Penulis</th><th>Status</th><th>Diperbarui</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($articles as $article): ?>
                        <tr>
                            <td><strong><?= e($article['title']) ?></strong><div class="small text-muted font-monospace"><?= e($article['slug']) ?></div></td>
                            <td class="small"><?= e($article['author_name'] ?? '-') ?></td>
                            <td><span class="badge <?= $article['status'] === 'published' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(Article::STATUS_LABELS[$article['status']]) ?></span>
                                <?php if ($article['published_at']): ?><div class="small text-muted"><?= e(tanggal($article['published_at'])) ?></div><?php endif ?></td>
                            <td class="small text-nowrap"><?= e(tanggal($article['updated_at'], false, true)) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($article['status'] === 'published'): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/artikel/' . $article['slug']) ?>" target="_blank" rel="noopener" aria-label="Lihat"><i class="bi bi-eye"></i></a>
                                <?php endif ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('/staff/artikel/' . $article['id'] . '/edit') ?>">Ubah</a>
                                <form class="d-inline" method="post" action="<?= url('/staff/artikel/' . $article['id']) ?>" data-confirm="Hapus artikel ini?">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
