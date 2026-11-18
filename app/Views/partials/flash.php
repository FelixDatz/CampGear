<?php
/** Pesan flash dari request sebelumnya (setelah redirect). */
$types = [
    'success' => ['success', 'bi-check-circle-fill'],
    'error'   => ['danger', 'bi-x-octagon-fill'],
    'warning' => ['warning', 'bi-exclamation-triangle-fill'],
    'info'    => ['info', 'bi-info-circle-fill'],
];
$messages = [];
foreach ($types as $key => [$class, $icon]) {
    $message = App\Core\Session::getFlash($key);
    if (is_string($message) && $message !== '') {
        $messages[] = [$class, $icon, $message];
    }
}
if ($messages === []) {
    return;
}
?>
<div class="<?= empty($inline) ? 'container pt-3' : 'mb-3' ?>">
    <?php foreach ($messages as [$class, $icon, $message]): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show d-flex align-items-start gap-2 mb-2" role="alert">
            <i class="bi <?= $icon ?> flex-shrink-0 mt-1"></i>
            <div><?= e($message) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endforeach ?>
</div>
