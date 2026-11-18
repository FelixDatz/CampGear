<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Statistik</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Statistik Penyewaan</h1>
        <p class="text-muted mb-0">Grafik penyewaan per periode, pembatalan, dan tingkat pemakaian stok. Data bersifat agregat tanpa informasi pribadi penyewa.</p>
    </div>
</section>

<div class="container py-4">
    <?php App\Core\View::partial('stats-panel', ['showRevenue' => false]) ?>
</div>
