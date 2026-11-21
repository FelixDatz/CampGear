<?php
/**
 * Layout panel Staff & Admin: sidebar di desktop, offcanvas di mobile.
 * Memakai sistem desain yang sama dengan halaman publik (site.css): token warna,
 * font, radius 6px, dan tema terang/gelap yang tersimpan bersama (localStorage cg-langit).
 * Tampilan khusus panel ada di panel.css.
 *
 * @var string $content
 * @var string|null $title
 */
$appName = (string) config('app.name', 'CampGear');
$user = auth_user();
$role = App\Enums\Role::from($user['role']);

// Menu panel, dikelompokkan. Grup 'Admin' hanya untuk role admin.
$pendingCancellations = App\Models\CancellationRequest::pendingCount();
$unsettledDamages = App\Models\DamageReport::unsettledCount();
$menu = [
    'Operasional' => [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'path' => '/staff', 'exact' => true],
        ['label' => 'Booking', 'icon' => 'bi-journal-text', 'path' => '/staff/booking'],
        ['label' => 'Pembatalan', 'icon' => 'bi-x-circle', 'path' => '/staff/pembatalan', 'badge' => $pendingCancellations],
        ['label' => 'Kerusakan', 'icon' => 'bi-shield-exclamation', 'path' => '/staff/kerusakan', 'badge' => $unsettledDamages],
    ],
    'Inventaris & Konten' => [
        ['label' => 'Alat', 'icon' => 'bi-backpack', 'path' => '/staff/alat'],
        ['label' => 'Kategori', 'icon' => 'bi-tags', 'path' => '/staff/kategori'],
        ['label' => 'Paket', 'icon' => 'bi-box2-heart', 'path' => '/staff/paket'],
        ['label' => 'Artikel', 'icon' => 'bi-newspaper', 'path' => '/staff/artikel'],
    ],
];
if ($role === App\Enums\Role::Admin) {
    $menu['Admin'] = [
        ['label' => 'Pengguna', 'icon' => 'bi-people', 'path' => '/admin/pengguna'],
        ['label' => 'Log Aktivitas', 'icon' => 'bi-clock-history', 'path' => '/admin/log'],
    ];
}
$currentPath = App\Core\Request::path();
$days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
?>
<!doctype html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-path" content="<?= e(rtrim((string) config('app.base_path', ''), '/')) ?>">
    <meta name="robots" content="noindex">
    <title><?= e(($title ?? 'Panel') . ' · Panel ' . $appName) ?></title>
    <script>
        /* Tema panel mengikuti pilihan di situs publik (u >= 0,88 = malam/gelap), dijalankan sebelum CSS. */
        (function () {
            var d = document.documentElement, saved = null, dark;
            try { saved = JSON.parse(localStorage.getItem('cg-langit') || 'null'); } catch (e) { saved = null; }
            if (saved && typeof saved.u === 'number') dark = saved.u >= 0.88;
            else dark = !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
            d.setAttribute('data-theme', dark ? 'dark' : 'light');
            d.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            d.setAttribute('data-langit', dark ? 'malam' : 'siang');
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
    <link href="<?= asset('css/panel.css') ?>" rel="stylesheet">
</head>
<body class="site panel-body">
    <a class="visually-hidden-focusable btn btn-primary position-fixed m-2" style="z-index: 1100" href="#konten">Lewati ke konten</a>
    <div class="panel-wrapper">
        <aside class="offcanvas-lg offcanvas-start panel-sidebar" tabindex="-1" id="panelSidebar" aria-labelledby="panelSidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <span class="panel-brand mb-0 pb-0" id="panelSidebarLabel"><?= e($appName) ?> <span class="panel-brand-tag">Panel</span></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#panelSidebar" aria-label="Tutup menu"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column">
                <a class="panel-brand d-none d-lg-flex" href="<?= url('/staff') ?>" aria-label="<?= e($appName) ?>, dashboard panel">
                    <svg class="brand-mark" viewBox="0 0 28 24" aria-hidden="true"><path class="m" d="M1 21 10 6l4.5 7.2L18 8l9 13z"/><circle class="s" cx="22.5" cy="4.5" r="2.6"/></svg>
                    <span><?= e($appName) ?></span>
                    <span class="panel-brand-tag"><?= e($role->label()) ?></span>
                </a>
                <?php foreach ($menu as $group => $items): ?>
                    <p class="panel-group"><?= e($group) ?></p>
                    <nav class="nav flex-column panel-nav" aria-label="<?= e($group) ?>">
                        <?php foreach ($items as $item):
                            $active = !empty($item['exact'])
                                ? $currentPath === $item['path']
                                : ($currentPath === $item['path'] || str_starts_with($currentPath, $item['path'] . '/'));
                        ?>
                            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= url($item['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i> <?= e($item['label']) ?>
                                <?php if (!empty($item['badge'])): ?><span class="badge rounded-pill text-bg-warning ms-auto"><?= (int) $item['badge'] ?></span><?php endif ?>
                            </a>
                        <?php endforeach ?>
                    </nav>
                <?php endforeach ?>
                <div class="panel-side-foot">
                    <a class="nav-link" href="<?= url('/') ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Lihat situs</a>
                </div>
            </div>
        </aside>

        <div class="panel-main">
            <header class="panel-topbar">
                <button class="panel-tool d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#panelSidebar" aria-controls="panelSidebar" aria-label="Buka menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="min-w-0">
                    <p class="panel-date"><?= e($days[(int) date('w')] . ', ' . tanggal(date('Y-m-d'), true)) ?></p>
                    <h1 class="panel-title text-truncate"><?= e($title ?? 'Panel') ?></h1>
                </div>
                <div class="panel-tools">
                    <button class="panel-tool" type="button" data-panel-theme aria-label="Ganti tema terang/gelap" title="Ganti tema">
                        <i class="bi bi-moon-stars" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown">
                        <button class="panel-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-sm"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                            <span class="d-none d-sm-inline text-truncate"><?= e($user['name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                            <li><span class="dropdown-item-text"><span class="badge <?= e($role->badgeClass()) ?>"><?= e($role->label()) ?></span></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url('/akun/profil') ?>"><i class="bi bi-person-gear me-2" aria-hidden="true"></i>Profil Saya</a></li>
                            <li>
                                <form action="<?= url('/logout') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="panel-content" id="konten">
                <?php App\Core\View::partial('flash', ['inline' => true]) ?>
                <?= $content ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/staff.js') ?>"></script>
    <?php App\Core\View::partial('page-scripts', ['pageScripts' => $pageScripts ?? []]) ?>
</body>
</html>
