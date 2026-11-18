<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Upload;
use App\Core\Validator;
use App\Exceptions\BusinessRuleException;
use App\Models\Article;
use App\Models\AuditLog;

/** CRUD artikel. */
final class ArticleController
{
    private const LABELS = ['title' => 'Judul', 'slug' => 'Slug', 'excerpt' => 'Ringkasan', 'body' => 'Isi artikel', 'status' => 'Status'];

    public function index(): void
    {
        $status = Request::query('status');
        $pager = new Paginator(Article::adminCount($status), 20);
        view('staff/articles/index', [
            'title'    => 'Artikel',
            'status'   => array_key_exists($status, Article::STATUS_LABELS) ? $status : '',
            'articles' => Article::adminList($status, $pager->perPage, $pager->offset),
            'pager'    => $pager,
        ], 'layouts/panel');
    }

    public function create(): void
    {
        view('staff/articles/form', ['title' => 'Tulis Artikel', 'article' => null], 'layouts/panel');
    }

    public function store(): void
    {
        $data = self::validate('/staff/artikel/baru');
        $data['cover_image'] = self::uploadCover('/staff/artikel/baru');
        $id = Article::create($data, (int) Auth::id());
        AuditLog::record('article.create', 'article', $id, "Menulis artikel \"{$data['title']}\" ({$data['status']})");
        flash('success', $data['status'] === 'published' ? 'Artikel diterbitkan.' : 'Artikel disimpan sebagai draf.');
        redirect('/staff/artikel');
    }

    public function edit(string $id): void
    {
        view('staff/articles/form', ['title' => 'Ubah Artikel', 'article' => self::findOrFail($id)], 'layouts/panel');
    }

    public function update(string $id): void
    {
        $article = self::findOrFail($id);
        $backTo = '/staff/artikel/' . $article['id'] . '/edit';
        $data = self::validate($backTo);
        $newCover = self::uploadCover($backTo);
        $data['cover_image'] = $newCover ?? (isset($_POST['remove_image']) ? null : $article['cover_image']);

        Article::update((int) $article['id'], $data, $article);
        if ($data['cover_image'] !== $article['cover_image']) {
            Upload::delete($article['cover_image']);
        }
        AuditLog::record('article.update', 'article', (int) $article['id'], "Mengubah artikel \"{$data['title']}\"",
            ['status_before' => $article['status'], 'status_after' => $data['status']]);
        flash('success', 'Artikel diperbarui.');
        redirect('/staff/artikel');
    }

    public function destroy(string $id): void
    {
        $article = self::findOrFail($id);
        Article::delete((int) $article['id']);
        Upload::delete($article['cover_image']);
        AuditLog::record('article.delete', 'article', (int) $article['id'], "Menghapus artikel \"{$article['title']}\"");
        flash('success', 'Artikel dihapus.');
        redirect('/staff/artikel');
    }

    private static function validate(string $backTo): array
    {
        $validator = new Validator($_POST, [
            'title'   => 'required|min:5|max:200',
            'slug'    => 'slug|max:220',
            'excerpt' => 'max:300',
            'body'    => 'required|min:50|max:60000',
            'status'  => 'required|in:draft,published',
        ], self::LABELS);
        if ($validator->fails()) {
            back_with_errors($backTo, $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $data['excerpt'] ??= excerpt($data['body'], 200);
        return $data;
    }

    private static function uploadCover(string $backTo): ?string
    {
        try {
            return Upload::image('image', 'articles');
        } catch (BusinessRuleException $e) {
            back_with_errors($backTo, ['image' => $e->getMessage()], $_POST);
        }
    }

    private static function findOrFail(string $id): array
    {
        return Article::find(int_param($id)) ?? throw new HttpException(404, 'Artikel tidak ditemukan.');
    }
}
