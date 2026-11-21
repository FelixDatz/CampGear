<?php
/**
 * Kontak: papan informasi basecamp (status buka, kanal kontak) dan pertanyaan umum.
 * Jawaban FAQ memakai aturan sewa dari konfigurasi agar selalu sama dengan sistem.
 *
 * @var array $business
 * @var array $rental
 * @var array|null $open ['is_open' => bool, 'from' => '08.00', 'until' => '20.00']
 */
$channels = [
    ['bi-whatsapp', 'WhatsApp', $business['phone'], 'https://wa.me/' . $business['whatsapp'], 'Chat sekarang'],
    ['bi-envelope', 'Email', $business['email'], 'mailto:' . $business['email'], 'Kirim email'],
    ['bi-geo-alt', 'Alamat', $business['address'], null, null],
    ['bi-clock', 'Jam operasional', $business['hours'], null, null],
];
$faqs = [
    ['Apakah harus punya akun untuk menyewa?', 'Tidak. Kamu bisa memesan sebagai tamu dan mengecek status booking dengan kode booking serta email atau nomor HP. Dengan akun, riwayat sewa tersimpan otomatis.'],
    ['Berapa lama maksimal menyewa?', 'Maksimal ' . (int) $rental['max_rental_days'] . ' hari per booking, dihitung termasuk tanggal ambil dan tanggal kembali. Booking bisa dibuat paling jauh ' . (int) $rental['max_advance_days'] . ' hari ke depan.'],
    ['Berapa lama batas pembayaran?', 'Pembayaran (simulasi) diselesaikan dalam ' . (int) $rental['payment_window_hours'] . ' jam setelah booking dibuat. Lewat dari itu booking kedaluwarsa dan stoknya dilepas untuk penyewa lain.'],
    ['Apakah booking bisa dibatalkan?', 'Bisa diajukan dari halaman booking sebelum tanggal mulai sewa, dengan alasan. Pengajuan ditinjau staf; bila disetujui, booking dibatalkan dan catatan refund dicatat staf.'],
    ['Apakah bisa mengambil alat lebih awal?', 'Alat diambil pada tanggal mulai sewa sesuai jam operasional. Untuk kebutuhan khusus, hubungi kami lewat WhatsApp.'],
    ['Bagaimana jika alat rusak saat dipakai?', 'Laporkan saat pengembalian. Staf mencatat kerusakan atau kehilangan pada booking-mu, dan penyewa wajib melakukan penggantian. Ketentuan penggantian akan dikonfirmasi oleh staf.'],
    ['Apakah gas kaleng dan baterai disediakan?', 'Barang habis pakai seperti gas kaleng dan baterai tidak termasuk dalam sewa.'],
];
?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Kontak</li>
            </ol>
        </nav>
        <h1 class="h2 mb-1">Kontak &amp; FAQ</h1>
        <p class="text-muted mb-0">Ada pertanyaan soal alat, ketersediaan, atau booking? Hubungi kami.</p>
    </div>
</section>

<div class="container py-4">
    <div class="kontak-grid">
        <section class="kontak-board" aria-labelledby="board-title">
            <p class="eyebrow">Basecamp</p>
            <h2 class="kontak-title" id="board-title"><?= e($business['name']) ?></h2>
            <?php if ($open !== null): ?>
                <p class="kontak-open" data-open="<?= $open['is_open'] ? '1' : '0' ?>">
                    <span class="dot" aria-hidden="true"></span>
                    <?= $open['is_open'] ? 'Sedang buka · tutup pukul ' . e($open['until']) : 'Sedang tutup · buka pukul ' . e($open['from']) ?>
                </p>
            <?php endif ?>
            <ul class="kontak-list">
                <?php foreach ($channels as [$icon, $label, $value, $href, $cta]): ?>
                    <li>
                        <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                        <div><span class="kontak-label"><?= e($label) ?></span><span class="kontak-value"><?= e($value) ?></span></div>
                        <?php if ($href !== null): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($cta) ?> <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
            <a class="btn btn-cta w-100" href="https://wa.me/<?= e($business['whatsapp']) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i> Tanya lewat WhatsApp</a>
        </section>

        <section aria-labelledby="faq-title">
            <p class="eyebrow">Pertanyaan umum</p>
            <h2 class="h3 mb-3" id="faq-title">Sebelum kamu bertanya</h2>
            <div class="accordion faq" id="faq">
                <?php foreach ($faqs as $i => [$question, $answer]): ?>
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
            <p class="small text-muted mt-3 mb-0">Aturan lengkap ada di <a href="<?= url('/cara-reservasi') ?>">Cara Reservasi</a>.</p>
        </section>
    </div>
</div>
