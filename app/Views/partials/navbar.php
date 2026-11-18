<?php
$user = auth_user();
$links = [
    ['/', 'Beranda'],
    ['/alat', 'Katalog'],
    ['/paket', 'Paket'],
    ['/artikel', 'Artikel'],
    ['/statistik', 'Statistik'],
    ['/cara-reservasi', 'Cara Reservasi'],
    ['/cek-booking', 'Cek Booking'],
    ['/kontak', 'Kontak'],
];
?>
<nav class="navbar navbar-expand-xl site-navbar sticky-top" aria-label="Navigasi utama">
    <div class="container">
        <a class="navbar-brand brand" href="<?= url('/') ?>">
            <i class="bi bi-tree-fill"></i> <?= e(config('app.name')) ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto mb-3 mb-xl-0">
                <?php foreach ($links as [$path, $label]): $active = nav_active($path); ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active ?>" href="<?= url($path) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    </li>
                <?php endforeach ?>
            </ul>

            <?php if ($user === null): ?>
                <div class="d-flex gap-2 pb-3 pb-xl-0">
                    <a class="btn btn-outline-primary flex-fill" href="<?= url('/login') ?>">Masuk</a>
                    <a class="btn btn-primary flex-fill" href="<?= url('/register') ?>">Daftar</a>
                </div>
            <?php else: $role = App\Enums\Role::from($user['role']); ?>
                <div class="dropdown pb-3 pb-xl-0">
                    <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2 w-100" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-sm"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <span class="text-truncate" style="max-width: 10rem"><?= e($user['name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (App\Core\Auth::isStaff()): ?>
                            <li><a class="dropdown-item" href="<?= url('/staff') ?>"><i class="bi bi-speedometer2 me-2"></i>Panel <?= e($role->label()) ?></a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="<?= url('/akun') ?>"><i class="bi bi-person-circle me-2"></i>Akun Saya</a></li>
                        <?php endif ?>
                        <li><a class="dropdown-item" href="<?= url('/akun/profil') ?>"><i class="bi bi-person-gear me-2"></i>Profil &amp; Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="<?= url('/logout') ?>" method="post">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php endif ?>
        </div>
    </div>
</nav>
