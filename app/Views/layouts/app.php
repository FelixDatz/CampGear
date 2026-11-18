<?php
/**
 * Layout utama halaman publik dan area pelanggan.
 * @var string $content
 * @var string|null $title
 */
$appName = (string) config('app.name', 'CampGear');
$pageTitle = isset($title) ? $title . ' · ' . $appName : $appName;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($metaDescription ?? config('business.tagline')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-path" content="<?= e(rtrim((string) config('app.base_path', ''), '/')) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <a class="visually-hidden-focusable btn btn-primary position-absolute m-2" href="#konten">Lewati ke konten</a>

    <?php App\Core\View::partial('navbar') ?>

    <main id="konten" class="flex-grow-1">
        <?php App\Core\View::partial('flash') ?>
        <?= $content ?>
    </main>

    <?php App\Core\View::partial('footer') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php App\Core\View::partial('page-scripts', ['pageScripts' => $pageScripts ?? []]) ?>
</body>
</html>
