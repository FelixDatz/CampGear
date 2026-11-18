<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Upload;
use App\Core\Validator;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Availability;
use App\Models\Category;
use App\Models\Equipment;

/** CRUD alat camping: kategori, stok total, tarif harian, gambar, status aktif. */
final class EquipmentController
{
    private const LABELS = [
        'category_id' => 'Kategori', 'name' => 'Nama alat', 'slug' => 'Slug', 'description' => 'Deskripsi',
        'stock_total' => 'Stok total', 'daily_rate' => 'Tarif harian',
    ];

    public function index(): void
    {
        $filters = [
            'q'        => mb_substr(Request::query('q'), 0, 100),
            'category' => (int) Request::query('category', '0'),
            'active'   => Request::query('active'),
        ];
        $pager = new Paginator(Equipment::adminCount($filters), 20);

        view('staff/equipment/index', [
            'title'      => 'Alat Camping',
            'filters'    => $filters,
            'categories' => Category::options(),
            'items'      => Equipment::adminList($filters, $pager->perPage, $pager->offset),
            'pager'      => $pager,
        ], 'layouts/panel');
    }

    public function create(): void
    {
        view('staff/equipment/form', [
            'title'      => 'Tambah Alat',
            'item'       => null,
            'categories' => Category::options(),
        ], 'layouts/panel');
    }

    public function store(): void
    {
        $data = self::validate(null, '/staff/alat/baru');
        $data['image'] = self::uploadImage('/staff/alat/baru');
        try {
            $id = Equipment::create($data);
        } catch (\Throwable $e) {
            Upload::delete($data['image']);
            throw $e;
        }
        AuditLog::record('equipment.create', 'equipment', $id, "Menambah alat {$data['name']}",
            ['stock_total' => $data['stock_total'], 'daily_rate' => $data['daily_rate']]);
        flash('success', 'Alat ditambahkan.');
        redirect('/staff/alat');
    }

    public function edit(string $id): void
    {
        $item = self::findOrFail($id);
        view('staff/equipment/form', [
            'title'      => 'Ubah Alat',
            'item'       => $item,
            'categories' => Category::options(),
            'futurePeak' => Availability::futurePeak((int) $item['id']),
        ], 'layouts/panel');
    }

    public function update(string $id): void
    {
        $item = self::findOrFail($id);
        $backTo = '/staff/alat/' . $item['id'] . '/edit';
        $data = self::validate((int) $item['id'], $backTo);

        // Stok total tidak boleh di bawah unit yang sudah dipesan untuk tanggal mendatang.
        $peak = Availability::futurePeak((int) $item['id']);
        if ($data['stock_total'] < $peak) {
            back_with_errors($backTo, ['stock_total' => "Stok total tidak boleh kurang dari {$peak} unit yang sudah dipesan untuk tanggal mendatang."], $_POST);
        }

        $newImage = self::uploadImage($backTo);
        $data['image'] = $newImage ?? (isset($_POST['remove_image']) ? null : $item['image']);
        Equipment::update((int) $item['id'], $data);
        if ($data['image'] !== $item['image']) {
            Upload::delete($item['image']);
        }

        AuditLog::record('equipment.update', 'equipment', (int) $item['id'], "Mengubah alat {$data['name']}", [
            'before' => ['stock_total' => (int) $item['stock_total'], 'daily_rate' => (float) $item['daily_rate'], 'is_active' => (int) $item['is_active']],
            'after'  => ['stock_total' => $data['stock_total'], 'daily_rate' => $data['daily_rate'], 'is_active' => $data['is_active']],
        ]);
        flash('success', 'Alat diperbarui.');
        redirect('/staff/alat');
    }

    public function destroy(string $id): void
    {
        $item = self::findOrFail($id);
        $blocker = Equipment::deleteBlocker((int) $item['id']);
        if ($blocker !== null) {
            flash('error', $blocker);
            redirect('/staff/alat');
        }
        Equipment::delete((int) $item['id']);
        Upload::delete($item['image']);
        AuditLog::record('equipment.delete', 'equipment', (int) $item['id'], "Menghapus alat {$item['name']}");
        flash('success', 'Alat dihapus.');
        redirect('/staff/alat');
    }

    private static function validate(?int $id, string $backTo): array
    {
        $validator = new Validator($_POST, [
            'category_id' => 'required|integer|exists:categories,id',
            'name'        => 'required|min:3|max:120',
            'slug'        => 'slug|max:140',
            'description' => 'max:5000',
            'stock_total' => 'required|integer|min:0|max:9999',
            'daily_rate'  => 'required|integer|min:0|max:100000000',
        ], self::LABELS);
        if ($validator->fails()) {
            back_with_errors($backTo, $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $data['category_id'] = (int) $data['category_id'];
        $data['stock_total'] = (int) $data['stock_total'];
        $data['daily_rate'] = (float) $data['daily_rate'];
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        return $data;
    }

    private static function uploadImage(string $backTo): ?string
    {
        try {
            return Upload::image('image', 'equipment');
        } catch (BusinessRuleException $e) {
            back_with_errors($backTo, ['image' => $e->getMessage()], $_POST);
        }
    }

    private static function findOrFail(string $id): array
    {
        return Equipment::find(int_param($id)) ?? throw new HttpException(404, 'Alat tidak ditemukan.');
    }
}
