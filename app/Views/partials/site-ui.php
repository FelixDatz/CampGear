<?php
/**
 * Elemen antarmuka global halaman publik: popover (langit, cek booking, akun,
 * kalender), palet pencarian, ransel, menu HP, dan toast.
 * Perilakunya ada di assets/js/site.js dan assets/js/trip.js.
 */
$user = auth_user();
$rental = config('rental');
$stations = [
    ['fajar', 0.06, 'bi-sunrise', 'Fajar', 'terang'],
    ['siang', 0.5, 'bi-sun', 'Siang', 'terang'],
    ['senja', 0.8, 'bi-sunset', 'Senja', 'terang'],
    ['malam', 1, 'bi-moon-stars', 'Malam', 'gelap'],
];
?>
<!-- Langit & tema -->
<div popover data-lenis-prevent id="pop-langit" class="pop" data-align="end">
    <div class="pop-body">
        <p class="pop-title">Langit</p>
        <div class="sky-stations">
            <?php foreach ($stations as [$key, $u, $icon, $label, $mode]): ?>
                <button class="sky-station" type="button" data-sky-set="<?= $u ?>" data-sky-phase="<?= $key ?>" aria-pressed="false">
                    <i class="bi <?= $icon ?>" aria-hidden="true"></i><span><?= $label ?><small>tema <?= $mode ?></small></span>
                </button>
            <?php endforeach ?>
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-sky-auto>Ikuti jam perangkat</button>
        <p class="pop-note">Di beranda, matahari di atas nama CampGear juga bisa digeser.</p>
    </div>
</div>

<!-- Cek booking tanpa pindah halaman -->
<div popover data-lenis-prevent id="pop-cek" class="pop" data-align="end">
    <form class="pop-body" method="post" action="<?= url('/cek-booking') ?>">
        <?= csrf_field() ?>
        <p class="pop-title">Cek status booking</p>
        <div>
            <label class="form-label small fw-semibold" for="pop-cek-code">Kode booking</label>
            <input class="form-control font-monospace text-uppercase" id="pop-cek-code" name="code" placeholder="CG261118-AB12" required maxlength="20" autocomplete="off" autocapitalize="characters" data-autofocus>
        </div>
        <div>
            <label class="form-label small fw-semibold" for="pop-cek-contact">Email atau nomor HP</label>
            <input class="form-control" id="pop-cek-contact" name="contact" required maxlength="150" autocomplete="email">
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Cek status</button>
        <p class="pop-note">Pakai email atau nomor HP yang diisi saat memesan.</p>
    </form>
</div>

<!-- Akun -->
<div popover data-lenis-prevent id="pop-akun" class="pop" data-align="end">
    <div class="pop-body">
        <?php if ($user === null): ?>
            <p class="pop-title">Belum masuk</p>
            <p class="pop-note">Kamu tetap bisa booking sebagai tamu. Masuk agar riwayat sewa tersimpan.</p>
            <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('/login') ?>">Masuk</a>
                <a class="btn btn-primary btn-sm" href="<?= url('/register') ?>">Daftar</a>
            </div>
        <?php else: $role = App\Enums\Role::from($user['role']); ?>
            <div class="pop-user">
                <span class="frame-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                <div><b><?= e($user['name']) ?></b><span><?= e($user['email']) ?></span></div>
            </div>
            <div class="pop-links">
                <?php if (App\Core\Auth::isStaff()): ?>
                    <a href="<?= url('/staff') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i>Panel <?= e($role->label()) ?></a>
                <?php else: ?>
                    <a href="<?= url('/akun') ?>"><i class="bi bi-person-circle" aria-hidden="true"></i>Akun Saya</a>
                <?php endif ?>
                <a href="<?= url('/akun/profil') ?>"><i class="bi bi-person-gear" aria-hidden="true"></i>Profil &amp; Password</a>
                <form action="<?= url('/logout') ?>" method="post">
                    <?= csrf_field() ?>
                    <button class="text-danger" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Keluar</button>
                </form>
            </div>
        <?php endif ?>
    </div>
</div>

<!-- Kalender tanggal sewa (Trip) -->
<div popover data-lenis-prevent id="pop-kalender" class="pop cal" data-align="start"
     data-today="<?= e(date('Y-m-d')) ?>"
     data-max-days="<?= (int) $rental['max_rental_days'] ?>"
     data-max-advance="<?= (int) $rental['max_advance_days'] ?>">
    <div class="pop-body">
        <div class="cal-head">
            <b data-cal-title></b>
            <div class="cal-nav">
                <button type="button" data-cal-step="-1" aria-label="Bulan sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <button type="button" data-cal-step="1" aria-label="Bulan berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="cal-grid" data-cal-grid></div>
        <p class="cal-sum" data-cal-sum></p>
        <p class="cal-hint" data-cal-hint aria-live="polite"></p>
        <div class="cal-foot">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-cal-reset>Atur ulang</button>
            <button class="btn btn-sm btn-primary" type="button" data-cal-apply disabled>Pakai tanggal ini</button>
        </div>
    </div>
</div>

<!-- Palet pencarian (Ctrl+K atau /) -->
<dialog data-lenis-prevent class="palette" id="palette" aria-label="Cari">
    <div class="palette-head">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="text" id="palette-input" placeholder="Cari alat, paket, halaman, atau kode booking" autocomplete="off"
               role="combobox" aria-controls="palette-list" aria-expanded="true" aria-autocomplete="list">
        <span class="kbd">Esc</span>
    </div>
    <div class="palette-list" id="palette-list" role="listbox" aria-label="Hasil pencarian"></div>
    <p class="palette-foot">↑ ↓ pilih · Enter buka · Esc tutup</p>
</dialog>

<!-- Ransel -->
<dialog data-lenis-prevent class="drawer" id="ransel" aria-labelledby="ransel-title">
    <div class="drawer-head">
        <div>
            <h2 id="ransel-title">Ransel</h2>
            <p class="drawer-trip" data-ransel-trip></p>
        </div>
        <button class="icon-close" type="button" data-dialog-close aria-label="Tutup ransel"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <ul class="drawer-list" data-ransel-list></ul>
    <div class="drawer-foot">
        <div class="row-line"><span>Per hari</span><b data-ransel-perday>Rp0</b></div>
        <div class="row-line muted"><span data-ransel-days>Pilih tanggal untuk menghitung total</span><b data-ransel-total></b></div>
        <a class="btn btn-cta" href="<?= url('/sewa') ?>" data-ransel-go>Lanjut ke booking <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-ransel-clear>Kosongkan ransel</button>
    </div>
</dialog>

<!-- Menu Jelajahi di HP -->
<dialog data-lenis-prevent class="sheet" id="sheet-jelajahi" aria-labelledby="sheet-title">
    <div class="sheet-body">
        <span class="sheet-grip" aria-hidden="true"></span>
        <div class="sheet-head">
            <h2 id="sheet-title">Jelajahi</h2>
            <button class="icon-close" type="button" data-dialog-close aria-label="Tutup"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <div class="sheet-list">
            <a class="drop-item" href="<?= url('/alat') ?>"><b>Katalog alat</b><span>Semua alat yang bisa disewa harian</span></a>
            <a class="drop-item" href="<?= url('/paket') ?>"><b>Paket sewa</b><span>Lebih hemat daripada sewa satuan</span></a>
        </div>
        <p class="eyebrow">Panduan</p>
        <div class="sheet-list">
            <a class="drop-item" href="<?= url('/cara-reservasi') ?>"><b>Cara Reservasi</b><span>Alur sewa dan ketentuannya</span></a>
            <a class="drop-item" href="<?= url('/artikel') ?>"><b>Artikel</b><span>Checklist dan tips perjalanan</span></a>
            <a class="drop-item" href="<?= url('/statistik') ?>"><b>Statistik</b><span>Penyewaan dan pemakaian stok</span></a>
            <a class="drop-item" href="<?= url('/kontak') ?>"><b>Kontak &amp; FAQ</b><span>WhatsApp, alamat, jam buka</span></a>
        </div>
    </div>
</dialog>

<nav class="tabbar" aria-label="Navigasi HP">
    <button class="tab-btn" type="button" data-sheet-open="sheet-jelajahi"><i class="bi bi-grid" aria-hidden="true"></i>Jelajahi</button>
    <a class="tab-btn<?= nav_active('/sewa') ? ' is-active' : '' ?>" href="<?= url('/sewa') ?>" data-sewa-link><i class="bi bi-calendar-check" aria-hidden="true"></i>Sewa</a>
    <button class="tab-btn" type="button" popovertarget="pop-cek"><i class="bi bi-ticket-perforated" aria-hidden="true"></i>Booking</button>
    <button class="tab-btn" type="button" data-ransel-open><i class="bi bi-backpack" aria-hidden="true"></i>Ransel<span class="frame-badge" data-ransel-count hidden>0</span></button>
    <button class="tab-btn" type="button" popovertarget="pop-akun"><i class="bi bi-person" aria-hidden="true"></i>Akun</button>
</nav>

<div class="site-toast" id="site-toast" role="status" aria-live="polite"></div>
