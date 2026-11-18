<?php /** @var App\Core\Paginator $pager */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <div class="small text-muted"><?= e($pager->summary()) ?></div>
    <?php if ($pager->lastPage > 1): ?>
        <nav aria-label="Navigasi halaman">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $pager->page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($pager->url(max(1, $pager->page - 1))) ?>" aria-label="Sebelumnya">&laquo;</a>
                </li>
                <?php foreach ($pager->pages() as $number): ?>
                    <li class="page-item<?= $number === $pager->page ? ' active' : '' ?>"<?= $number === $pager->page ? ' aria-current="page"' : '' ?>>
                        <a class="page-link" href="<?= e($pager->url($number)) ?>"><?= $number ?></a>
                    </li>
                <?php endforeach ?>
                <li class="page-item<?= $pager->page >= $pager->lastPage ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($pager->url(min($pager->lastPage, $pager->page + 1))) ?>" aria-label="Berikutnya">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif ?>
</div>
