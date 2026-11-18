<?php
/**
 * @var array $articles
 * @var App\Core\Paginator $pager
 */
?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Artikel</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Artikel Camping</h1>
        <p class="text-muted mb-0">Panduan, tips, dan checklist agar perjalanan camping lebih aman dan nyaman.</p>
    </div>
</section>

<div class="container py-4">
    <?php if ($articles === []): ?>
        <div class="empty-state"><i class="bi bi-journal"></i><p class="text-muted">Belum ada artikel yang diterbitkan.</p></div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($articles as $article): ?>
                <div class="col-md-6 col-lg-4"><?php App\Core\View::partial('article-card', ['article' => $article]) ?></div>
            <?php endforeach ?>
        </div>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    <?php endif ?>
</div>
