<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\HttpException;
use App\Core\Validator;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Category;

/** CRUD kategori alat. */
final class CategoryController
{
    private const LABELS = ['name' => 'Nama kategori', 'slug' => 'Slug', 'description' => 'Deskripsi', 'icon' => 'Ikon'];

    public function index(): void
    {
        view('staff/categories/index', [
            'title'      => 'Kategori',
            'categories' => Category::allForAdmin(),
        ], 'layouts/panel');
    }

    public function create(): void
    {
        view('staff/categories/form', ['title' => 'Tambah Kategori', 'category' => null], 'layouts/panel');
    }

    public function store(): void
    {
        $data = self::validate('/staff/kategori/baru');
        $id = Category::create($data);
        AuditLog::record('category.create', 'category', $id, "Menambah kategori {$data['name']}");
        flash('success', 'Kategori ditambahkan.');
        redirect('/staff/kategori');
    }

    public function edit(string $id): void
    {
        view('staff/categories/form', ['title' => 'Ubah Kategori', 'category' => self::findOrFail($id)], 'layouts/panel');
    }

    public function update(string $id): void
    {
        $category = self::findOrFail($id);
        $data = self::validate('/staff/kategori/' . $category['id'] . '/edit');
        Category::update((int) $category['id'], $data);
        AuditLog::record('category.update', 'category', (int) $category['id'], "Mengubah kategori {$data['name']}",
            ['before' => ['name' => $category['name'], 'slug' => $category['slug']]]);
        flash('success', 'Kategori diperbarui.');
        redirect('/staff/kategori');
    }

    public function destroy(string $id): void
    {
        $category = self::findOrFail($id);
        try {
            Category::delete((int) $category['id']);
            AuditLog::record('category.delete', 'category', (int) $category['id'], "Menghapus kategori {$category['name']}");
            flash('success', 'Kategori dihapus.');
        } catch (BusinessRuleException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/staff/kategori');
    }

    private static function validate(string $backTo): array
    {
        $validator = new Validator($_POST, [
            'name'        => 'required|min:3|max:80',
            'slug'        => 'slug|max:100',
            'description' => 'max:255',
            'icon'        => 'required|in:' . implode(',', array_keys(Category::ICONS)),
        ], self::LABELS);
        if ($validator->fails()) {
            back_with_errors($backTo, $validator->errors(), $_POST);
        }
        return $validator->validated();
    }

    private static function findOrFail(string $id): array
    {
        return Category::find(int_param($id)) ?? throw new HttpException(404, 'Kategori tidak ditemukan.');
    }
}
