<?php
/** @var array|null $category */
use App\Models\Category;

$isEdit = $category !== null;
$value = static fn (string $key, string $default = '') => old($key, $category[$key] ?? $default);
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/staff/kategori') ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
<div class="card" style="max-width: 40rem">
    <div class="card-body">
        <form method="post" action="<?= url($isEdit ? '/staff/kategori/' . $category['id'] : '/staff/kategori') ?>" novalidate>
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>
            <div class="mb-3">
                <label class="form-label" for="name">Nama kategori</label>
                <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="80">
                <?php if ($msg = error('name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="slug">Slug URL <span class="text-muted small">(kosongkan untuk dibuat otomatis)</span></label>
                <input class="form-control font-monospace<?= invalid('slug') ?>" id="slug" name="slug" value="<?= e($value('slug')) ?>" maxlength="100" pattern="[a-z0-9-]*">
                <?php if ($msg = error('slug')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Deskripsi singkat</label>
                <input class="form-control<?= invalid('description') ?>" id="description" name="description" value="<?= e($value('description')) ?>" maxlength="255">
                <?php if ($msg = error('description')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
            </div>
            <fieldset class="mb-4">
                <legend class="form-label fs-6">Ikon</legend>
                <div class="icon-picker">
                    <?php foreach (Category::ICONS as $icon => $label): ?>
                        <input class="btn-check" type="radio" name="icon" id="icon-<?= e($icon) ?>" value="<?= e($icon) ?>"<?= checked($value('icon', 'bi-box') === $icon) ?>>
                        <label class="btn btn-outline-primary" for="icon-<?= e($icon) ?>" title="<?= e($label) ?>"><i class="bi <?= e($icon) ?>"></i><span class="visually-hidden"><?= e($label) ?></span></label>
                    <?php endforeach ?>
                </div>
                <?php if ($msg = error('icon')): ?><div class="text-danger small mt-1"><?= e($msg) ?></div><?php endif ?>
            </fieldset>
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Tambah kategori' ?></button>
        </form>
    </div>
</div>
