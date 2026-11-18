<?php
/**
 * Tab filter berbasis query string.
 * @var array  $tabs    [nilai => label]
 * @var string $current nilai aktif
 * @var string $param   nama parameter query (default 'status')
 * @var string $path    path halaman
 */
$param ??= 'status';
?>
<ul class="nav nav-pills filter-tabs mb-3 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $value => $label): $active = (string) $value === (string) $current; ?>
        <li class="nav-item">
            <a class="nav-link text-nowrap<?= $active ? ' active' : '' ?>" href="<?= url($path, $value === '' ? [] : [$param => $value]) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        </li>
    <?php endforeach ?>
</ul>
