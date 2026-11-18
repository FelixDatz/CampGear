<?php
/**
 * @var array|null $item
 * @var array $categories
 * @var int|null $futurePeak
 */
$isEdit = $item !== null;
$value = static fn (string $key, string $default = '') => old($key, isset($item[$key]) ? (string) $item[$key] : $default);
$active = old('_submitted') !== '' ? old('is_active') !== '' : ($item === null || (int) $item['is_active'] === 1);
$image = upload_url($item['image'] ?? null);
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/staff/alat') ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
<form method="post" action="<?= url($isEdit ? '/staff/alat/' . $item['id'] : '/staff/alat') ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>
    <input type="hidden" name="_submitted" value="1">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Nama alat</label>
                            <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="120">
                            <?php if ($msg = error('name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category_id">Kategori</label>
                            <select class="form-select<?= invalid('category_id') ?>" id="category_id" name="category_id" required>
                                <option value="">Pilih…</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"<?= selected($value('category_id'), $category['id']) ?>><?= e($category['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                            <?php if ($msg = error('category_id')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="stock_total">Stok total (unit dimiliki)</label>
                            <input class="form-control<?= invalid('stock_total') ?>" type="number" id="stock_total" name="stock_total" value="<?= e($value('stock_total', '0')) ?>" min="<?= (int) ($futurePeak ?? 0) ?>" max="9999" required>
                            <?php if ($msg = error('stock_total')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            <?php if (!empty($futurePeak)): ?><div class="form-text">Booking mendatang memakai hingga <?= (int) $futurePeak ?> unit pada hari tersibuk.</div><?php endif ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="daily_rate">Tarif harian per unit (Rp)</label>
                            <input class="form-control<?= invalid('daily_rate') ?>" type="number" id="daily_rate" name="daily_rate" value="<?= e($value('daily_rate') !== '' ? (string) (int) (float) $value('daily_rate') : '') ?>" min="0" step="500" required>
                            <?php if ($msg = error('daily_rate')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            <div class="form-text">Perubahan tarif hanya berlaku untuk booking baru.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Deskripsi</label>
                            <textarea class="form-control<?= invalid('description') ?>" id="description" name="description" rows="5" maxlength="5000"><?= e($value('description')) ?></textarea>
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
                                <label class="form-check-label" for="is_active">Tampil di katalog</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="image">Gambar</label>
                    <img id="image-preview" class="img-fluid rounded mb-2<?= $image ? '' : ' d-none' ?>" src="<?= e($image ?? '') ?>" alt="Pratinjau gambar">
                    <input class="form-control<?= invalid('image') ?>" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="#image-preview">
                    <?php if ($msg = error('image')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB.</div>
                    <?php if ($image): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                            <label class="form-check-label small" for="remove_image">Hapus gambar</label>
                        </div>
                    <?php endif ?>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Tambah alat' ?></button>
        </div>
    </div>
</form>
