<?php
declare(strict_types=1);

namespace Cros\Core;

/** Protecció contra CSRF per a tots els formularis POST. */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(?string $token = null): bool
    {
        $token = $token ?? ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        return !empty($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Verifica el token o atura la petició. */
    public static function verify(): void
    {
        if (!self::check()) {
            throw new HttpException(419, 'La sessió ha caducat o el formulari no és vàlid. Torneu-ho a provar.');
        }
    }
}
