<?php /** @var array $business */ ?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Kontak</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Kontak</h1>
        <p class="text-muted mb-0">Ada pertanyaan soal alat, ketersediaan, atau booking? Hubungi kami.</p>
    </div>
</section>

<div class="container py-4">
    <div class="row g-3 g-lg-4">
        <?php
        $cards = [
            ['bi-whatsapp', 'WhatsApp', $business['phone'], 'https://wa.me/' . $business['whatsapp'], 'Chat sekarang'],
            ['bi-envelope', 'Email', $business['email'], 'mailto:' . $business['email'], 'Kirim email'],
            ['bi-geo-alt', 'Alamat', $business['address'], null, null],
            ['bi-clock', 'Jam Operasional', $business['hours'], null, null],
        ];
        foreach ($cards as [$icon, $label, $value, $href, $cta]): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 contact-card">
                    <div class="card-body">
                        <i class="bi <?= e($icon) ?>"></i>
                        <h2 class="h6 mt-3 mb-1"><?= e($label) ?></h2>
                        <p class="text-muted small mb-2"><?= e($value) ?></p>
                        <?php if ($href !== null): ?>
                            <a class="small fw-semibold" href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($cta) ?> <i class="bi bi-arrow-up-right"></i></a>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h2 class="h5">Pertanyaan Umum</h2>
            <div class="accordion accordion-flush" id="faq">
                <?php
                $faqs = [
                    ['Apakah harus punya akun untuk menyewa?', 'Tidak. Kamu bisa memesan sebagai tamu dan mengecek status booking dengan kode booking serta email atau nomor HP. Dengan akun, riwayat sewa tersimpan otomatis.'],
                    ['Apakah bisa mengambil alat lebih awal?', 'Alat diambil pada tanggal mulai sewa sesuai jam operasional. Untuk kebutuhan khusus, hubungi kami lewat WhatsApp.'],
                    ['Bagaimana jika alat rusak saat dipakai?', 'Laporkan saat pengembalian. Staf mencatat kerusakan atau kehilangan pada booking-mu, dan penyewa wajib melakukan penggantian. Ketentuan penggantian akan dikonfirmasi oleh staf.'],
                    ['Apakah gas kaleng dan baterai disediakan?', 'Barang habis pakai seperti gas kaleng dan baterai tidak termasuk dalam sewa.'],
                ];
                foreach ($faqs as $i => [$question, $answer]): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-<?= $i ?>" aria-expanded="false" aria-controls="faq-<?= $i ?>">
                                <?= e($question) ?>
                            </button>
                        </h3>
                        <div id="faq-<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faq">
                            <div class="accordion-body text-muted"><?= e($answer) ?></div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>
