<?php
/**
 * @var array $article
 * @var array $related
 */
?>
<article class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('/artikel') ?>">Artikel</a></li>
                    <li class="breadcrumb-item active text-truncate" aria-current="page"><?= e($article['title']) ?></li>
                </ol>
            </nav>
            <h1 class="display-6 fw-bold"><?= e($article['title']) ?></h1>
            <p class="text-muted">
                <?= e(tanggal($article['published_at'], true)) ?> · <?= App\Models\Article::readingMinutes($article['body']) ?> menit baca
                <?php if ($article['author_name']): ?> · oleh <?= e($article['author_name']) ?><?php endif ?>
            </p>
            <?php if ($cover = upload_url($article['cover_image'])): ?>
                <img class="img-fluid rounded-4 mb-4 w-100" src="<?= e($cover) ?>" alt="">
            <?php endif ?>
            <?php if ($article['excerpt']): ?>
                <p class="lead"><?= e($article['excerpt']) ?></p>
            <?php endif ?>
            <div class="article-body">
                <?= format_text($article['body']) ?>
            </div>

            <div class="cta-box mt-5">
                <div>
                    <h2 class="h5 mb-1">Siap berangkat?</h2>
                    <p class="mb-0 text-white-75">Cek ketersediaan alat untuk tanggal perjalananmu.</p>
                </div>
                <a class="btn btn-accent" href="<?= url('/sewa') ?>"><i class="bi bi-calendar-check me-1"></i> Sewa Sekarang</a>
            </div>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="mt-5">
            <h2 class="h4 mb-3">Baca juga</h2>
            <div class="row g-4">
                <?php foreach ($related as $item): ?>
                    <div class="col-md-4"><?php App\Core\View::partial('article-card', ['article' => $item]) ?></div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>
</article>
