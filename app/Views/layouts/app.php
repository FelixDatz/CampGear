<?php
/**
 * Layout utama halaman publik dan area pelanggan.
 * @var string $content
 * @var string|null $title
 */
$appName = (string) config('app.name', 'CampGear');
$pageTitle = isset($title) ? $title . ' · ' . $appName : $appName;
$isHome = App\Core\Request::path() === '/';
?>
<!doctype html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= e($metaDescription ?? config('business.tagline')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-path" content="<?= e(rtrim((string) config('app.base_path', ''), '/')) ?>">
    <title><?= e($pageTitle) ?></title>
    <script>
        /* Tema dari posisi matahari, dijalankan sebelum CSS agar halaman tidak berkedip.
           u: 0 = fajar, 0,5 = siang, 1 = malam. Aturan lengkap ada di assets/js/site.js. */
        (function () {
            var d = document.documentElement, saved = null, u;
            try { saved = JSON.parse(localStorage.getItem('cg-langit') || 'null'); } catch (e) { saved = null; }
            if (saved && typeof saved.u === 'number') {
                u = saved.u;
            } else if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) {
                u = 1;
            } else {
                var now = new Date(), m = now.getHours() * 60 + now.getMinutes();
                u = Math.min(0.86, Math.max(0, (m - 330) / 780));
            }
            var theme = u >= 0.88 ? 'dark' : 'light';
            d.setAttribute('data-theme', theme);
            d.setAttribute('data-bs-theme', theme);
            d.setAttribute('data-langit', u < 0.2 ? 'fajar' : u < 0.66 ? 'siang' : u < 0.88 ? 'senja' : 'malam');
            window.__langit = { u: u, manual: !!(saved && saved.manual) };
        })();
    </script>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=IBM+Plex+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/site.css') ?>" rel="stylesheet">
</head>
<body class="site<?= $isHome ? ' is-home' : '' ?>">
    <a class="visually-hidden-focusable btn btn-primary position-fixed m-2" style="z-index: 1100" href="#konten">Lewati ke konten</a>

    <?php App\Core\View::partial('navbar', ['isHome' => $isHome]) ?>

    <main id="konten" class="site-main">
        <?php App\Core\View::partial('flash') ?>
        <?= $content ?>
    </main>

    <?php App\Core\View::partial('footer') ?>
    <?php App\Core\View::partial('site-ui') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/site.js') ?>"></script>
    <script src="<?= asset('js/trip.js') ?>"></script>
    <?php App\Core\View::partial('page-scripts', ['pageScripts' => $pageScripts ?? []]) ?>
</body>
</html>
