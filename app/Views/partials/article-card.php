<?php /** @var array $article */ ?>
<article class="card article-card h-100">
    <a class="article-cover" href="<?= url('/artikel/' . $article['slug']) ?>" tabindex="-1" aria-hidden="true">
        <?php if ($cover = upload_url($article['cover_image'])): ?>
            <img src="<?= e($cover) ?>" alt="" loading="lazy">
        <?php else: ?>
            <i class="bi bi-journal-richtext"></i>
        <?php endif ?>
    </a>
    <div class="card-body d-flex flex-column">
        <div class="small text-muted mb-1"><?= e(tanggal($article['published_at'], true)) ?> · <?= App\Models\Article::readingMinutes($article['body']) ?> menit baca</div>
        <h3 class="h5 card-title"><a class="stretched-link text-reset text-decoration-none" href="<?= url('/artikel/' . $article['slug']) ?>"><?= e($article['title']) ?></a></h3>
        <p class="text-muted small mb-0"><?= e($article['excerpt'] ?: excerpt($article['body'], 150)) ?></p>
    </div>
</article>
