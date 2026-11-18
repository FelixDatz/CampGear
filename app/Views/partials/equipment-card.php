<?php /** @var array $item */ ?>
<article class="card gear-card h-100">
    <a class="gear-thumb" href="<?= url('/alat/' . $item['slug']) ?>" tabindex="-1" aria-hidden="true">
        <?php if ($image = upload_url($item['image'])): ?>
            <img src="<?= e($image) ?>" alt="" loading="lazy">
        <?php else: ?>
            <i class="bi <?= e($item['category_icon'] ?: 'bi-box') ?>"></i>
        <?php endif ?>
    </a>
    <div class="card-body d-flex flex-column">
        <span class="badge text-bg-light border align-self-start mb-2"><?= e($item['category_name']) ?></span>
        <h3 class="h6 card-title mb-1">
            <a class="stretched-link text-reset text-decoration-none" href="<?= url('/alat/' . $item['slug']) ?>"><?= e($item['name']) ?></a>
        </h3>
        <p class="small text-muted mb-3"><?= e(excerpt($item['description'], 80)) ?></p>
        <div class="mt-auto d-flex align-items-end justify-content-between">
            <div>
                <span class="price"><?= rupiah($item['daily_rate']) ?></span><span class="small text-muted">/hari</span>
            </div>
            <span class="small text-muted"><i class="bi bi-box-seam"></i> <?= (int) $item['stock_total'] ?> unit</span>
        </div>
    </div>
</article>
