<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Paginator;
use App\Models\Article;

/** Halaman artikel publik. */
final class ArticleController
{
    /** GET /artikel */
    public function index(): void
    {
        $pager = new Paginator(Article::publishedCount(), 9);
        view('articles/index', [
            'title'    => 'Artikel',
            'articles' => Article::published($pager->perPage, $pager->offset),
            'pager'    => $pager,
        ]);
    }

    /** GET /artikel/{slug} */
    public function show(string $slug): void
    {
        $article = Article::findPublishedBySlug($slug) ?? throw new HttpException(404, 'Artikel tidak ditemukan.');
        view('articles/show', [
            'title'           => $article['title'],
            'metaDescription' => $article['excerpt'],
            'article'         => $article,
            'related'         => Article::related((int) $article['id']),
        ]);
    }
}
