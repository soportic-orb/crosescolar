<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\HttpException;

/**
 * Qui entra al panell de superadministració.
 *
 * És a part de l'autenticació de les instàncies (Cros\Core\Auth): els usuaris
 * de la plataforma viuen a la seva base de dades i un administrador d'un cros
 * no té res a veure amb el panell de dalt.
 */
class Console
{
    private static ?array $user = null;
    private const MAX_ATTEMPTS = 8;
    private const LOCK_MINUTES = 15;

    /** Qui hi ha ara mateix, o null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = (int) ($_SESSION['platform_user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $user = Db::one('SELECT * FROM platform_users WHERE id = :id AND active = 1', ['id' => $id]);
        if (!$user) {
            unset($_SESSION['platform_user_id']);

            return null;
        }

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    /** Prova d'entrar. Retorna el missatge d'error, o null si tot ha anat bé. */
    public static function attempt(string $email, string $password): ?string
    {
        if (self::tooManyAttempts()) {
            return 'Massa intents fallits. Espereu ' . self::LOCK_MINUTES . ' minuts i torneu-ho a provar.';
        }
        $user = Db::one('SELECT * FROM platform_users WHERE email = :email LIMIT 1', ['email' => mb_strtolower(trim($email))]);
        $valid = $user && password_verify($password, (string) $user['password_hash']);
        if (!$valid) {
            Platform::log('login_failed', 'user', null, ['email' => mb_substr($email, 0, 120)]);

            return 'Les credencials no són correctes.';
        }
        if ((int) $user['active'] !== 1) {
            return 'Aquest compte està desactivat.';
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('platform_users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }
        self::login($user);

        return null;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['platform_user_id'] = (int) $user['id'];
        self::$user = $user;
        Db::update('platform_users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        Platform::log('login', 'user', (int) $user['id'], [], (int) $user['id']);
    }

    public static function logout(): void
    {
        if (self::check()) {
            Platform::log('logout', 'user', self::id(), [], self::id());
        }
        unset($_SESSION['platform_user_id']);
        self::$user = null;
        session_regenerate_id(true);
    }

    /** Sense sessió no es veu res: cap al formulari d'accés. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['console_redirect'] = (string) ($_SERVER['REQUEST_URI'] ?? '');
            redirect('/acces');
        }
    }

    /** Apunta una acció d'aquest usuari. */
    public static function log(string $action, string $subject = '', ?int $subjectId = null, array $context = []): void
    {
        Platform::log($action, $subject, $subjectId, $context, self::id() ?: null);
    }

    /** Crea o posa al dia un superadministrador (l'usa l'eina de consola). */
    public static function save(string $name, string $email, string $password, array $extra = []): int
    {
        $email = mb_strtolower(trim($email));
        $existing = Db::one('SELECT id FROM platform_users WHERE email = :email', ['email' => $email]);
        $fields = [
            'name' => trim($name),
            'email' => $email,
            'role' => (string) ($extra['role'] ?? 'superadmin'),
            'active' => !empty($extra['active']) || !isset($extra['active']) ? 1 : 0,
        ];
        if ($password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($existing) {
            Db::update('platform_users', $fields, 'id = :id', ['id' => $existing['id']]);

            return (int) $existing['id'];
        }
        if ($password === '') {
            throw new \RuntimeException('Cal una contrasenya per crear l\'usuari.');
        }
        $fields['created_at'] = date('Y-m-d H:i:s');

        return Db::insert('platform_users', $fields);
    }

    /** Hi ha algun superadministrador donat d'alta? */
    public static function any(): bool
    {
        return (int) Db::val('SELECT COUNT(*) FROM platform_users', [], 0) > 0;
    }

    private static function tooManyAttempts(): bool
    {
        try {
            $count = (int) Db::val(
                'SELECT COUNT(*) FROM platform_activity WHERE action = :action AND ip = :ip AND created_at > :since',
                ['action' => 'login_failed', 'ip' => client_ip(), 'since' => date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60)],
                0
            );

            return $count >= self::MAX_ATTEMPTS;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
