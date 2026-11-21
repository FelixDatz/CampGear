<?php
/**
 * Panel visual di samping form masuk, daftar, dan cek booking: langit malam,
 * punggungan gunung, dan beberapa poin singkat.
 *
 * @var string $title
 * @var array  $points [[ikon Bootstrap Icons, teks], ...]
 */
?>
<div class="auth-aside">
    <a class="auth-brand" href="<?= url('/') ?>" aria-label="<?= e(config('app.name')) ?>, ke beranda">
        <svg class="brand-mark" viewBox="0 0 28 24" aria-hidden="true"><path class="m" d="M1 21 10 6l4.5 7.2L18 8l9 13z"/><circle class="s" cx="22.5" cy="4.5" r="2.6"/></svg>
        <span><?= e(config('app.name')) ?></span>
    </a>
    <div class="auth-aside-body">
        <p class="eyebrow">Basecamp</p>
        <p class="auth-aside-title"><?= e($title) ?></p>
        <ul class="auth-points">
            <?php foreach ($points as [$icon, $text]): ?>
                <li><i class="bi <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($text) ?></span></li>
            <?php endforeach ?>
        </ul>
    </div>
    <svg class="auth-ridges" viewBox="0 0 400 160" preserveAspectRatio="none" aria-hidden="true">
        <path class="r1" d="M0 118 L58 72 L104 104 L168 38 L226 92 L288 58 L346 98 L400 70 V160 H0Z"/>
        <path class="r2" d="M0 142 L76 104 L146 132 L218 96 L296 128 L356 108 L400 124 V160 H0Z"/>
    </svg>
</div>
