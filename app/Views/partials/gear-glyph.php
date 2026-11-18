<?php
/**
 * Ilustrasi SVG alat tampak atas (gaya knolling: barang disusun rapi di alas).
 * Dipakai bila alat belum punya foto. Warna diambil dari token tema di site.css.
 * @var array $item  minimal berisi 'name' (dan opsional 'slug')
 */
$kind = gear_kind(($item['name'] ?? '') . ' ' . ($item['slug'] ?? ''));
$shapes = [
    'tent'         => '<rect x="22" y="38" width="156" height="44" rx="22" class="ga"/><rect x="64" y="34" width="10" height="52" rx="3" class="gd"/><rect x="126" y="34" width="10" height="52" rx="3" class="gd"/><circle cx="177" cy="60" r="7" class="gb"/><path d="M34 60h18" class="sl"/>',
    'tent-l'       => '<rect x="10" y="26" width="180" height="68" rx="34" class="ga"/><rect x="52" y="22" width="11" height="76" rx="3" class="gd"/><rect x="95" y="22" width="11" height="76" rx="3" class="gd"/><rect x="138" y="22" width="11" height="76" rx="3" class="gd"/><circle cx="188" cy="60" r="8" class="gb"/>',
    'carrier'      => '<path d="M66 44H52M134 44h14M66 82H52M134 82h14" class="sl"/><rect x="66" y="12" width="68" height="100" rx="20" class="ga"/><rect x="72" y="5" width="56" height="30" rx="12" class="gd"/><rect x="80" y="58" width="40" height="40" rx="10" class="gb"/>',
    'sleeping-bag' => '<rect x="46" y="34" width="108" height="52" rx="26" class="gb"/><path d="M80 36v48M120 36v48" class="sl2"/><circle cx="152" cy="60" r="6" class="gd"/>',
    'mat'          => '<rect x="12" y="44" width="176" height="32" rx="16" class="ge"/><path d="M46 46v28M80 46v28M114 46v28M148 46v28" class="sl3"/><rect x="12" y="44" width="30" height="32" rx="15" class="gc"/>',
    'stove'        => '<rect x="62" y="22" width="76" height="76" rx="14" class="gc"/><circle cx="100" cy="60" r="21" class="gd"/><circle cx="100" cy="60" r="8" class="gb"/><path d="M100 25v13M70 82l11-8M130 82l-11-8" class="sl"/><circle cx="146" cy="60" r="6" class="gd"/>',
    'cookset'      => '<circle cx="92" cy="60" r="42" class="gc"/><circle cx="92" cy="60" r="31" class="ge"/><circle cx="92" cy="60" r="19" class="gc"/><rect x="132" y="54" width="48" height="12" rx="6" class="gd"/>',
    'headlamp'     => '<ellipse cx="100" cy="66" rx="48" ry="30" class="sband"/><rect x="80" y="22" width="40" height="26" rx="9" class="gd"/><circle cx="100" cy="35" r="8" class="gb"/>',
    'lantern'      => '<circle cx="100" cy="64" r="36" class="gglow"/><circle cx="100" cy="64" r="24" class="gb"/><circle cx="100" cy="64" r="10" class="ge"/><path d="M100 40V24M90 26a10 10 0 0 1 20 0" class="sl"/>',
    'chair'        => '<rect x="18" y="38" width="10" height="44" rx="3" class="gd"/><rect x="172" y="38" width="10" height="44" rx="3" class="gd"/><rect x="26" y="44" width="148" height="32" rx="6" class="ga"/><path d="M30 54h140M30 66h140" class="sl2"/>',
    'table'        => '<rect x="36" y="26" width="128" height="68" rx="5" class="gc"/><path d="M40 43h120M40 60h120M40 77h120" class="sl3"/><rect x="92" y="20" width="16" height="10" rx="2" class="gd"/>',
    'hammock'      => '<path d="M62 60H24M138 60h38" class="srope"/><rect x="60" y="36" width="80" height="48" rx="24" class="gb"/><circle cx="22" cy="60" r="5" class="gd"/><circle cx="178" cy="60" r="5" class="gd"/>',
    'pole'         => '<rect x="14" y="55" width="172" height="10" rx="5" class="gc"/><rect x="14" y="50" width="46" height="20" rx="8" class="gd"/><path d="M100 51v18M134 51v18" class="sl"/><circle cx="188" cy="60" r="4" class="gd"/>',
    'crate'        => '<rect x="56" y="22" width="88" height="76" rx="8" class="gc"/><path d="M56 48h88M100 22v26" class="sl2"/><rect x="88" y="56" width="24" height="10" rx="3" class="gd"/>',
];
?>
<svg viewBox="0 0 200 120" aria-hidden="true" focusable="false"><?= $shapes[$kind] ?></svg>
