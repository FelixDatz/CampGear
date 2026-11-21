<?php
/**
 * Daftar artikel: artikel terbaru ditonjolkan di atas, sisanya berupa baris bersekat
 * tanpa kartu (sejalan dengan kolom artikel di beranda).
 *
 * @var array $articles
 * @var App\Core\Paginator $pager
 */
$featured = $pager->page === 1 ? array_shift($articles) : null;
$summary = static fn (array $a): string => $a['excerpt'] ?: excerpt($a['body'], 170);
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
    <?php if ($featured === null && $articles === []): ?>
        <div class="empty-state"><i class="bi bi-journal"></i><p class="text-muted">Belum ada artikel yang diterbitkan.</p></div>
    <?php else: ?>
        <?php if ($featured !== null): ?>
            <a class="art-feature lamp" href="<?= url('/artikel/' . $featured['slug']) ?>">
                <span class="art-feature-cover" aria-hidden="true">
                    <?php if ($cover = upload_url($featured['cover_image'])): ?><img src="<?= e($cover) ?>" alt=""><?php else: ?><i class="bi bi-journal-richtext"></i><?php endif ?>
                </span>
                <span class="art-feature-body">
                    <span class="eyebrow">Terbaru · <?= App\Models\Article::readingMinutes($featured['body']) ?> menit baca</span>
                    <span class="art-feature-title"><?= e($featured['title']) ?></span>
                    <span class="art-feature-text"><?= e($summary($featured)) ?></span>
                    <span class="art-meta"><?= e(tanggal($featured['published_at'], true)) ?><?= $featured['author_name'] ? ' · oleh ' . e($featured['author_name']) : '' ?></span>
                    <span class="art-go">Baca artikel <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </span>
            </a>
        <?php endif ?>

        <?php if ($articles !== []): ?>
            <ol class="art-list">
                <?php foreach ($articles as $article): ?>
                    <li>
                        <a class="art-row" href="<?= url('/artikel/' . $article['slug']) ?>">
                            <time datetime="<?= e(substr((string) $article['published_at'], 0, 10)) ?>"><?= e(tanggal($article['published_at'])) ?></time>
                            <span class="art-row-main">
                                <span class="art-row-title"><?= e($article['title']) ?></span>
                                <span class="art-row-text"><?= e($summary($article)) ?></span>
                            </span>
                            <span class="art-row-meta"><?= App\Models\Article::readingMinutes($article['body']) ?> menit <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    <?php endif ?>
</div>
