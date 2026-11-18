<?php
/** @var array|null $article */
$isEdit = $article !== null;
$value = static fn (string $key, string $default = '') => old($key, isset($article[$key]) ? (string) $article[$key] : $default);
$cover = upload_url($article['cover_image'] ?? null);
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/staff/artikel') ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
<form method="post" action="<?= url($isEdit ? '/staff/artikel/' . $article['id'] : '/staff/artikel') ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="title">Judul</label>
                        <input class="form-control form-control-lg<?= invalid('title') ?>" id="title" name="title" value="<?= e($value('title')) ?>" required maxlength="200">
                        <?php if ($msg = error('title')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="excerpt">Ringkasan <span class="text-muted small">(opsional, maks. 300 karakter)</span></label>
                        <textarea class="form-control<?= invalid('excerpt') ?>" id="excerpt" name="excerpt" rows="2" maxlength="300"><?= e($value('excerpt')) ?></textarea>
                        <?php if ($msg = error('excerpt')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="body">Isi artikel</label>
                        <textarea class="form-control font-monospace small<?= invalid('body') ?>" id="body" name="body" rows="18" required><?= e($value('body')) ?></textarea>
                        <?php if ($msg = error('body')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    </div>
                    <div class="form-text">
                        Format sederhana: pisahkan paragraf dengan baris kosong · <code>## Subjudul</code> · <code>- butir daftar</code> · <code>1. daftar bernomor</code> · <code>**tebal**</code>. HTML tidak diproses.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select mb-3<?= invalid('status') ?>" id="status" name="status">
                        <option value="draft"<?= selected($value('status', 'draft'), 'draft') ?>>Draf (tidak tampil)</option>
                        <option value="published"<?= selected($value('status', 'draft'), 'published') ?>>Terbitkan</option>
                    </select>
                    <label class="form-label" for="slug">Slug URL <span class="text-muted small">(opsional)</span></label>
                    <input class="form-control font-monospace<?= invalid('slug') ?>" id="slug" name="slug" value="<?= e($value('slug')) ?>" maxlength="220">
                    <?php if ($msg = error('slug')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label" for="image">Gambar sampul</label>
                    <img id="image-preview" class="img-fluid rounded mb-2<?= $cover ? '' : ' d-none' ?>" src="<?= e($cover ?? '') ?>" alt="Pratinjau sampul">
                    <input class="form-control<?= invalid('image') ?>" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="#image-preview">
                    <?php if ($msg = error('image')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB.</div>
                    <?php if ($cover): ?>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1"><label class="form-check-label small" for="remove_image">Hapus sampul</label></div>
                    <?php endif ?>
                </div>
            </div>
            <button class="btn btn-primary w-100" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Simpan artikel' ?></button>
        </div>
    </div>
</form>
