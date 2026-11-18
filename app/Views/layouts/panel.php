<?php
/**
 * Layout panel Staff & Admin: sidebar di desktop, offcanvas di mobile.
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
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-path" content="<?= e(rtrim((string) config('app.base_path', ''), '/')) ?>">
    <meta name="robots" content="noindex">
    <title><?= e(($title ?? 'Panel') . ' · Panel ' . $appName) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="panel-body">
    <div class="panel-wrapper">
        <aside class="offcanvas-lg offcanvas-start panel-sidebar" tabindex="-1" id="panelSidebar" aria-labelledby="panelSidebarLabel">
            <div class="offcanvas-header border-bottom border-light border-opacity-10">
                <a class="brand text-white text-decoration-none" id="panelSidebarLabel" href="<?= url('/staff') ?>">
                    <i class="bi bi-tree-fill"></i> <?= e($appName) ?>
                </a>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#panelSidebar" aria-label="Tutup menu"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-3">
                <a class="brand text-white text-decoration-none fs-5 px-2 pb-3 mb-2 border-bottom border-light border-opacity-10 d-none d-lg-block" href="<?= url('/staff') ?>">
                    <i class="bi bi-tree-fill"></i> <?= e($appName) ?>
                </a>
                <?php $currentPath = App\Core\Request::path(); ?>
                <?php foreach ($menu as $group => $items): ?>
                    <div class="small text-uppercase text-white-50 fw-semibold mb-2 mt-2 px-2"><?= e($group) ?></div>
                    <nav class="nav flex-column gap-1 mb-2" aria-label="<?= e($group) ?>">
                        <?php foreach ($items as $item):
                            $active = !empty($item['exact'])
                                ? $currentPath === $item['path']
                                : ($currentPath === $item['path'] || str_starts_with($currentPath, $item['path'] . '/'));
                        ?>
                            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= url($item['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i class="bi <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                                <?php if (!empty($item['badge'])): ?><span class="badge rounded-pill text-bg-warning ms-auto"><?= (int) $item['badge'] ?></span><?php endif ?>
                            </a>
                        <?php endforeach ?>
                    </nav>
                <?php endforeach ?>
                <div class="mt-auto pt-3 border-top border-light border-opacity-10">
                    <a class="nav-link" href="<?= url('/') ?>"><i class="bi bi-box-arrow-up-right"></i> Lihat situs</a>
                </div>
            </div>
        </aside>

        <div class="panel-main">
            <header class="panel-topbar">
                <button class="btn btn-link text-body d-lg-none p-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#panelSidebar" aria-controls="panelSidebar" aria-label="Buka menu">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <h1 class="h5 mb-0 text-truncate"><?= e($title ?? 'Panel') ?></h1>
                <div class="dropdown ms-auto">
                    <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-sm"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span class="d-none d-sm-inline"><?= e($user['name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                        <li><span class="dropdown-item-text"><span class="badge <?= e($role->badgeClass()) ?>"><?= e($role->label()) ?></span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('/akun/profil') ?>"><i class="bi bi-person-gear me-2"></i>Profil Saya</a></li>
                        <li>
                            <form action="<?= url('/logout') ?>" method="post">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="panel-content">
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
