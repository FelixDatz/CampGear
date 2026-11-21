<?php
/**
 * Bar ringkas pengganti navbar pada mode fokus (form booking): hanya tombol keluar,
 * nama brand, dan progres langkah. Teks langkah dan garis progres diisi booking.js.
 *
 * @var string $exitUrl
 */
?>
<header class="focusbar">
    <div class="focusbar-inner">
        <a class="focus-exit" href="<?= e($exitUrl) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Keluar</a>
        <a class="sitebar-brand focus-brand" href="<?= url('/') ?>" aria-label="<?= e(config('app.name')) ?>, ke beranda">
            <svg class="brand-mark" viewBox="0 0 28 24" aria-hidden="true"><path class="m" d="M1 21 10 6l4.5 7.2L18 8l9 13z"/><circle class="s" cx="22.5" cy="4.5" r="2.6"/></svg>
            <span><?= e(config('app.name')) ?></span>
        </a>
        <span class="focus-step" data-wiz-label>Buat booking</span>
    </div>
    <div class="focus-progress" aria-hidden="true"><span data-wiz-progress></span></div>
</header>
