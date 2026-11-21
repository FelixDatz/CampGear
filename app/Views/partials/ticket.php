<?php
/**
 * Tiket ekspedisi. Dipakai halaman detail booking (data asli) dan beranda (contoh;
 * isinya diganti home.js mengikuti tanggal trip dan isi ransel lewat atribut data-t).
 *
 * @var array $t code, start, startSub, end, endSub, days, isi, total, stamp, tone,
 *               stub ('qr' | 'locked' | 'demo' | 'icon'), stubIcon, stubText, sample (bool),
 *               tag ('h1' di halaman penyewa, 'p' di panel staff yang sudah punya h1)
 */
$sample = !empty($t['sample']);
$locked = in_array($t['stub'], ['locked', 'demo'], true);
?>
<article class="ticket<?= $locked ? ' is-locked' : '' ?>" aria-label="Tiket booking<?= $sample ? ' contoh' : '' ?>"<?= $sample ? ' data-ticket' : '' ?>>
    <div class="ticket-main">
        <div class="ticket-top">
            <span class="brand-mini"><?= e(config('app.name')) ?></span>
            <span>Tiket ekspedisi<?= $sample ? ' · contoh' : '' ?></span>
        </div>
        <div class="ticket-code">
            <?php if ($sample): ?>
                <p class="code" data-t="code"><?= e($t['code']) ?></p>
            <?php else: $tag = in_array($t['tag'] ?? 'h1', ['h1', 'p'], true) ? ($t['tag'] ?? 'h1') : 'h1'; ?>
                <<?= $tag ?> class="code"><span class="visually-hidden">Booking </span><?= e($t['code']) ?></<?= $tag ?>>
                <?php if ($tag === 'h1'): ?>
                    <button class="btn btn-sm btn-outline-secondary d-print-none" type="button" data-copy="<?= e($t['code']) ?>" aria-label="Salin kode booking">
                        <i class="bi bi-clipboard" aria-hidden="true"></i> Salin
                    </button>
                <?php endif ?>
            <?php endif ?>
        </div>
        <div class="route">
            <div>
                <span class="lbl">Ambil</span>
                <strong data-t="start"><?= e($t['start']) ?></strong>
                <span class="sub" data-t="startSub"><?= e($t['startSub']) ?></span>
            </div>
            <svg viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden="true">
                <path class="route-line" d="M4 12 C 30 0, 70 24, 96 12"/>
                <circle class="route-dot" cx="4" cy="12" r="3.5"/><circle class="route-dot" cx="96" cy="12" r="3.5"/>
            </svg>
            <div class="route-end">
                <span class="lbl">Kembali</span>
                <strong data-t="end"><?= e($t['end']) ?></strong>
                <span class="sub" data-t="endSub"><?= e($t['endSub']) ?></span>
            </div>
            <span class="stamp" data-tone="<?= e($t['tone']) ?>" data-t="stamp"><?= e($t['stamp']) ?></span>
        </div>
        <dl class="ticket-meta">
            <div><dt>Durasi</dt><dd data-t="days"><?= e($t['days']) ?></dd></div>
            <div><dt>Isi</dt><dd data-t="isi"><?= e($t['isi']) ?></dd></div>
            <div><dt>Total</dt><dd class="mono" data-t="total"><?= e($t['total']) ?></dd></div>
        </dl>
    </div>
    <div class="ticket-stub">
        <?php if ($t['stub'] === 'qr'): ?>
            <div class="qr-box" data-qr="<?= e($t['code']) ?>" role="img" aria-label="QR kode booking <?= e($t['code']) ?>">
                <noscript><span class="small">QR memerlukan JavaScript</span></noscript>
            </div>
            <span class="stub-note">Tunjukkan saat ambil alat</span>
        <?php elseif ($t['stub'] === 'demo'): ?>
            <div class="qr-wrap">
                <canvas data-ticket-qr width="132" height="132" aria-hidden="true"></canvas>
                <span class="qr-lock">QR tampil setelah pembayaran</span>
            </div>
            <span class="stub-note mono">QR contoh</span>
        <?php elseif ($t['stub'] === 'locked'): ?>
            <div class="qr-wrap qr-placeholder" aria-hidden="true"><span class="qr-lock"><i class="bi bi-lock" aria-hidden="true"></i></span></div>
            <span class="stub-note">QR tampil setelah pembayaran</span>
        <?php else: ?>
            <i class="bi <?= e($t['stubIcon'] ?? 'bi-ticket-perforated') ?> stub-icon" aria-hidden="true"></i>
            <span class="stub-note"><?= e($t['stubText'] ?? '') ?></span>
        <?php endif ?>
    </div>
</article>
