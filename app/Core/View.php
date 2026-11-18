<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Perender template PHP. Template ada di app/Views/, layout di app/Views/layouts/.
 * Variabel $content berisi hasil render template dan dicetak oleh layout.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, ['content' => $content] + $data);
    }

    /** Render potongan template (partial) dari dalam template lain. */
    public static function partial(string $template, array $data = []): void
    {
        echo self::capture('partials/' . $template, $data);
    }

    private static function capture(string $__template, array $__data): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#i', $__template)) {
            throw new RuntimeException("Nama view tidak valid: {$__template}");
        }
        $__file = BASE_PATH . '/app/Views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new RuntimeException("View tidak ditemukan: {$__template}");
        }

        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
