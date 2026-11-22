<?php
$c = require 'd:/KULIAH/PEMWEB/UAS/CampGear/config/config.php';
$key    = $c['app']['key'];
$isDemo = ($key === 'base64:campgear_sec_key_2026_demo_secret_key_fixed');
$isEmpty = ($key === '');
$len    = strlen($key);
echo 'key_length='   . $len . PHP_EOL;
echo 'is_demo_key='  . ($isDemo  ? 'YES' : 'NO') . PHP_EOL;
echo 'is_empty='     . ($isEmpty ? 'YES' : 'NO') . PHP_EOL;
echo 'prefix_7char=' . substr($key, 0, 7) . PHP_EOL;
