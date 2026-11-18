<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\HttpException;
use App\Core\Upload;
use App\Core\Validator;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Package;

/** CRUD paket sewa: isi alat dan diskon persentase. */
final class PackageController
{
    private const LABELS = ['name' => 'Nama paket', 'slug' => 'Slug', 'description' => 'Deskripsi', 'discount_percent' => 'Diskon'];

    public function index(): void
    {
        view('staff/packages/index', ['title' => 'Paket Sewa', 'packages' => Package::allForAdmin()], 'layouts/panel');
    }

    public function create(): void
    {
        view('staff/packages/form', ['title' => 'Tambah Paket', 'package' => null, 'equipment' => Equipment::options()], 'layouts/panel');
    }

    public function store(): void
    {
        [$data, $items] = self::validate(null, '/staff/paket/baru');
        $data['image'] = self::uploadImage('/staff/paket/baru');
        $id = Package::save(null, $data, $items);
        AuditLog::record('package.create', 'package', $id, "Menambah paket {$data['name']}", ['items' => $items, 'discount' => $data['discount_percent']]);
        flash('success', 'Paket ditambahkan.');
        redirect('/staff/paket');
    }

    public function edit(string $id): void
    {
        view('staff/packages/form', [
            'title'     => 'Ubah Paket',
            'package'   => self::findOrFail($id),
            'equipment' => Equipment::options(),
        ], 'layouts/panel');
    }

    public function update(string $id): void
    {
        $package = self::findOrFail($id);
        $backTo = '/staff/paket/' . $package['id'] . '/edit';
        [$data, $items] = self::validate((int) $package['id'], $backTo);

        $newImage = self::uploadImage($backTo);
        $data['image'] = $newImage ?? (isset($_POST['remove_image']) ? null : $package['image']);
        Package::save((int) $package['id'], $data, $items);
        if ($data['image'] !== $package['image']) {
            Upload::delete($package['image']);
        }
        AuditLog::record('package.update', 'package', (int) $package['id'], "Mengubah paket {$data['name']}", ['items' => $items, 'discount' => $data['discount_percent']]);
        flash('success', 'Paket diperbarui. Perubahan hanya berlaku untuk booking baru.');
        redirect('/staff/paket');
    }

    public function destroy(string $id): void
    {
        $package = self::findOrFail($id);
        $blocker = Package::deleteBlocker((int) $package['id']);
        if ($blocker !== null) {
            flash('error', $blocker);
            redirect('/staff/paket');
        }
        Package::delete((int) $package['id']);
        Upload::delete($package['image']);
        AuditLog::record('package.delete', 'package', (int) $package['id'], "Menghapus paket {$package['name']}");
        flash('success', 'Paket dihapus.');
        redirect('/staff/paket');
    }

    /** @return array{0: array, 1: array} [data paket, isi paket] */
    private static function validate(?int $id, string $backTo): array
    {
        $validator = new Validator($_POST, [
            'name'             => 'required|min:3|max:120',
            'slug'             => 'slug|max:140',
            'description'      => 'max:2000',
            'discount_percent' => 'required|numeric|min:0|max:100',
        ], self::LABELS);

        // Isi paket: items[n][equipment_id], items[n][quantity]. Alat yang sama digabung.
        $items = [];
        $valid = array_column(Equipment::options(), 'id');
        foreach (is_array($_POST['items'] ?? null) ? $_POST['items'] : [] as $row) {
            if (!is_array($row) || ($row['equipment_id'] ?? '') === '') {
                continue;
            }
            $equipmentId = filter_var($row['equipment_id'], FILTER_VALIDATE_INT);
            $quantity = filter_var($row['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            if ($equipmentId === false || !in_array($equipmentId, array_map('intval', $valid), true) || $quantity === false) {
                $validator->addError('items', 'Setiap baris isi paket harus berisi alat yang valid dan jumlah 1–99.');
                break;
            }
            $items[$equipmentId] = ['equipment_id' => $equipmentId, 'quantity' => ($items[$equipmentId]['quantity'] ?? 0) + $quantity];
        }
        if ($items === [] && !isset($validator->errors()['items'])) {
            $validator->addError('items', 'Paket harus berisi minimal satu alat.');
        }

        if ($validator->fails()) {
            back_with_errors($backTo, $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $data['discount_percent'] = round((float) $data['discount_percent'], 2);
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        return [$data, array_values($items)];
    }

    private static function uploadImage(string $backTo): ?string
    {
        try {
            return Upload::image('image', 'packages');
        } catch (BusinessRuleException $e) {
            back_with_errors($backTo, ['image' => $e->getMessage()], $_POST);
        }
    }

    private static function findOrFail(string $id): array
    {
        return Package::find(int_param($id)) ?? throw new HttpException(404, 'Paket tidak ditemukan.');
    }
}
