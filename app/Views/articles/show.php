<?php
/**
 * Halaman baca artikel: bilah progres baca di atas, daftar isi dari subjudul
 * (dibuat artikel.js) di samping, lalu ajakan menyewa dan artikel lain.
 *
 * @var array $article
 * @var array $related
 */
$minutes = App\Models\Article::readingMinutes($article['body']);
?>
<div class="read-progress" aria-hidden="true"><span data-read-progress></span></div>
<article class="container read-page">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/artikel') ?>">Artikel</a></li>
            <li class="breadcrumb-item active text-truncate" aria-current="page"><?= e($article['title']) ?></li>
        </ol>
    </nav>

    <header class="read-head">
        <p class="eyebrow">Artikel · <?= $minutes ?> menit baca</p>
        <h1 class="read-title"><?= e($article['title']) ?></h1>
        <p class="read-meta">
            <?= e(tanggal($article['published_at'], true)) ?>
            <?php if ($article['author_name']): ?> · oleh <?= e($article['author_name']) ?><?php endif ?>
        </p>
    </header>

    <div class="read-grid">
        <aside class="read-toc" data-read-toc hidden>
            <p class="eyebrow">Daftar isi</p>
            <ol data-read-toc-list></ol>
        </aside>
        <div class="read-main">
            <?php if ($cover = upload_url($article['cover_image'])): ?>
                <img class="read-cover" src="<?= e($cover) ?>" alt="">
            <?php endif ?>
            <?php if ($article['excerpt']): ?>
                <p class="read-lead"><?= e($article['excerpt']) ?></p>
            <?php endif ?>
            <div class="article-body" data-read-body>
                <?= format_text($article['body']) ?>
            </div>

            <div class="cta-box mt-5">
                <div>
                    <h2 class="h5 mb-1">Siap berangkat?</h2>
                    <p class="mb-0">Cek ketersediaan alat untuk tanggal perjalananmu.</p>
                </div>
                <a class="btn btn-cta" href="<?= url('/sewa') ?>" data-sewa-link><i class="bi bi-calendar-check me-1" aria-hidden="true"></i> Sewa Sekarang</a>
            </div>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="read-related" aria-labelledby="related-title">
            <h2 class="h4 mb-3" id="related-title">Baca juga</h2>
            <ol class="art-list">
                <?php foreach ($related as $item): ?>
                    <li>
                        <a class="art-row" href="<?= url('/artikel/' . $item['slug']) ?>">
                            <time datetime="<?= e(substr((string) $item['published_at'], 0, 10)) ?>"><?= e(tanggal($item['published_at'])) ?></time>
                            <span class="art-row-main">
                                <span class="art-row-title"><?= e($item['title']) ?></span>
                                <span class="art-row-text"><?= e($item['excerpt'] ?: excerpt($item['body'], 150)) ?></span>
                            </span>
                            <span class="art-row-meta"><?= App\Models\Article::readingMinutes($item['body']) ?> menit <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ol>
        </section>
    <?php endif ?>
</article>
