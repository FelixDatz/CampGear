<?php
/**
 * Skrip khusus halaman, dikirim controller lewat 'pageScripts'.
 * Item berupa path di public/assets (mis. 'js/booking.js') atau URL CDN https://.
 * @var string[] $pageScripts
 */
foreach ($pageScripts as $script):
    $src = str_starts_with($script, 'https://') ? $script : asset($script);
?>
    <script src="<?= e($src) ?>"<?= str_starts_with($script, 'https://') ? ' crossorigin="anonymous" referrerpolicy="no-referrer"' : '' ?>></script>
<?php endforeach ?>
