<?php
/**
 * Input jumlah dengan tombol −/+.
 * @var string $name   nama field, mis. items[3]
 * @var int    $value
 * @var string $label  untuk aria-label
 * @var string $attr   atribut tambahan (sudah aman, dibuat oleh kode)
 */
?>
<div class="qty-stepper input-group input-group-sm flex-nowrap">
    <button class="btn btn-outline-secondary" type="button" data-step="-1" aria-label="Kurangi <?= e($label) ?>"><i class="bi bi-dash"></i></button>
    <input class="form-control text-center" type="number" name="<?= e($name) ?>" value="<?= (int) $value ?>"
           min="0" max="999" step="1" inputmode="numeric" aria-label="Jumlah <?= e($label) ?>" <?= $attr ?>>
    <button class="btn btn-outline-secondary" type="button" data-step="1" aria-label="Tambah <?= e($label) ?>"><i class="bi bi-plus"></i></button>
</div>
