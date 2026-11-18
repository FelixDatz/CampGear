<?php
/**
 * @var int $status
 * @var string $title
 * @var string|null $message
 * @var Throwable|null $exception
 */
$defaults = [
    403 => 'Kamu tidak memiliki izin untuk membuka halaman ini.',
    404 => 'Halaman yang kamu cari tidak ada atau sudah dipindahkan.',
    405 => 'Metode request ini tidak didukung untuk alamat tersebut.',
    419 => 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    500 => 'Maaf, terjadi kesalahan di server. Silakan coba beberapa saat lagi.',
];
$icons = [403 => 'bi-shield-lock', 404 => 'bi-signpost-split', 419 => 'bi-hourglass-bottom', 500 => 'bi-exclamation-octagon'];
?>
<div class="container py-5">
    <div class="empty-state py-5">
        <i class="bi <?= $icons[$status] ?? 'bi-exclamation-circle' ?>"></i>
        <div class="display-6 fw-bold text-primary"><?= (int) $status ?></div>
        <h1 class="h4"><?= e($title) ?></h1>
        <p class="text-muted"><?= e($message ?? ($defaults[$status] ?? 'Terjadi kesalahan.')) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-primary" href="<?= url('/') ?>">Ke Beranda</a>
            <button class="btn btn-outline-primary" type="button" onclick="history.back()">Kembali</button>
        </div>
    </div>

    <?php if ($exception !== null): ?>
        <div class="alert alert-danger mt-4 small">
            <div class="fw-semibold mb-1">Detail error (mode debug)</div>
            <div><?= e(get_class($exception)) ?>: <?= e($exception->getMessage()) ?></div>
            <div class="text-muted"><?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?></div>
            <pre class="mt-2 mb-0 small" style="white-space: pre-wrap"><?= e($exception->getTraceAsString()) ?></pre>
        </div>
    <?php endif ?>
</div>
