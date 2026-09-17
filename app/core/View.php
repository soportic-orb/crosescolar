<?php
declare(strict_types=1);

namespace Cros\Core;

/** Renderitzador de vistes PHP amb plantilla base. */
class View
{
    private static array $shared = [];

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /** Renderitza una vista dins d'una plantilla i l'envia al navegador. */
    public static function render(string $template, array $data = [], string $layout = 'layouts/public'): void
    {
        echo self::make($template, $data, $layout);
        clear_old();
    }

    /** Renderitza i retorna el resultat com a cadena. */
    public static function make(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /** Inclou una vista parcial. */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data);
    }

    private static function capture(string $template, array $data): string
    {
        $file = CROS_APP . '/views/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('No s\'ha trobat la vista: ' . $template);
        }
        extract(self::$shared, EXTR_SKIP);
        extract($data, EXTR_OVERWRITE);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
