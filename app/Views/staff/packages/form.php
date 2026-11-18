<?php
/**
 * @var array|null $package
 * @var array $equipment
 */
$isEdit = $package !== null;
$value = static fn (string $key, string $default = '') => old($key, isset($package[$key]) ? (string) $package[$key] : $default);
$active = old('_submitted') !== '' ? old('is_active') !== '' : ($package === null || (int) $package['is_active'] === 1);
$image = upload_url($package['image'] ?? null);

// Baris isi paket: dari input lama, dari data paket, atau satu baris kosong.
$rows = old('items', null);
if (!is_array($rows)) {
    $rows = $isEdit
        ? array_map(static fn ($i) => ['equipment_id' => $i['equipment_id'], 'quantity' => $i['quantity']], $package['items'])
        : [['equipment_id' => '', 'quantity' => 1]];
}
$rows = array_values($rows) ?: [['equipment_id' => '', 'quantity' => 1]];

$options = static function (mixed $selectedId) use ($equipment): string {
    $html = '<option value="">Pilih alat…</option>';
    foreach ($equipment as $e) {
        $label = $e['name'] . ' — ' . rupiah($e['daily_rate']) . '/hari' . ((int) $e['is_active'] === 1 ? '' : ' (nonaktif)');
        $html .= '<option value="' . (int) $e['id'] . '" data-rate="' . (float) $e['daily_rate'] . '"' . selected($selectedId, $e['id']) . '>' . e($label) . '</option>';
    }
    return $html;
};
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/staff/paket') ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
<form method="post" action="<?= url($isEdit ? '/staff/paket/' . $package['id'] : '/staff/paket') ?>" enctype="multipart/form-data" novalidate id="package-form">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>
    <input type="hidden" name="_submitted" value="1">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Nama paket</label>
                            <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="120">
                            <?php if ($msg = error('name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="discount_percent">Diskon (%)</label>
                            <input class="form-control<?= invalid('discount_percent') ?>" type="number" id="discount_percent" name="discount_percent" value="<?= e($value('discount_percent', '0')) ?>" min="0" max="100" step="0.5" required>
                            <?php if ($msg = error('discount_percent')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Deskripsi</label>
                            <textarea class="form-control<?= invalid('description') ?>" id="description" name="description" rows="3" maxlength="2000"><?= e($value('description')) ?></textarea>
                            <?php if ($msg = error('description')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="slug">Slug URL <span class="text-muted small">(kosongkan untuk otomatis)</span></label>
                            <input class="form-control font-monospace<?= invalid('slug') ?>" id="slug" name="slug" value="<?= e($value('slug')) ?>" maxlength="140">
                            <?php if ($msg = error('slug')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= checked($active) ?>>
                                <label class="form-check-label" for="is_active">Paket aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" data-repeater>
                <div class="card-body">
                    <h2 class="h6">Isi paket</h2>
                    <?php if ($msg = error('items')): ?><div class="alert alert-danger small py-2"><?= e($msg) ?></div><?php endif ?>
                    <div data-repeater-list>
                        <?php foreach ($rows as $i => $row): ?>
                            <div class="row g-2 mb-2 align-items-center" data-repeater-item>
                                <div class="col-7 col-md-8">
                                    <select class="form-select form-select-sm" name="items[<?= $i ?>][equipment_id]" aria-label="Alat"><?= $options($row['equipment_id'] ?? '') ?></select>
                                </div>
                                <div class="col-3 col-md-2">
                                    <input class="form-control form-control-sm" type="number" name="items[<?= $i ?>][quantity]" value="<?= (int) ($row['quantity'] ?? 1) ?>" min="1" max="99" aria-label="Jumlah">
                                </div>
                                <div class="col-2 text-end">
                                    <button class="btn btn-sm btn-outline-danger" type="button" data-repeater-remove aria-label="Hapus baris"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <template>
                        <div class="row g-2 mb-2 align-items-center" data-repeater-item>
                            <div class="col-7 col-md-8"><select class="form-select form-select-sm" name="items[__INDEX__][equipment_id]" aria-label="Alat"><?= $options('') ?></select></div>
                            <div class="col-3 col-md-2"><input class="form-control form-control-sm" type="number" name="items[__INDEX__][quantity]" value="1" min="1" max="99" aria-label="Jumlah"></div>
                            <div class="col-2 text-end"><button class="btn btn-sm btn-outline-danger" type="button" data-repeater-remove aria-label="Hapus baris"><i class="bi bi-trash"></i></button></div>
                        </div>
                    </template>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-repeater-add><i class="bi bi-plus-lg"></i> Tambah alat</button>
                    <div class="small mt-3" id="package-price" aria-live="polite"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="image">Gambar (opsional)</label>
                    <img id="image-preview" class="img-fluid rounded mb-2<?= $image ? '' : ' d-none' ?>" src="<?= e($image ?? '') ?>" alt="Pratinjau gambar">
                    <input class="form-control<?= invalid('image') ?>" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="#image-preview">
                    <?php if ($msg = error('image')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB.</div>
                    <?php if ($image): ?>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1"><label class="form-check-label small" for="remove_image">Hapus gambar</label></div>
                    <?php endif ?>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Tambah paket' ?></button>
        </div>
    </div>
</form>
<script>
// Pratinjau harga paket per hari: jumlah (tarif x qty) dikurangi diskon.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('package-form');
    const output = document.getElementById('package-price');
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const update = () => {
        let normal = 0;
        form.querySelectorAll('[data-repeater-item]').forEach((row) => {
            const option = row.querySelector('select')?.selectedOptions[0];
            const qty = parseInt(row.querySelector('input')?.value, 10) || 0;
            normal += (parseFloat(option?.dataset.rate) || 0) * qty;
        });
        const discount = Math.min(100, Math.max(0, parseFloat(form.discount_percent.value) || 0));
        output.textContent = `Harga normal ${rupiah(normal)}/hari → harga paket ${rupiah(normal * (1 - discount / 100))}/hari`;
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('click', () => setTimeout(update, 0));
    update();
});
</script>
