<?php
declare(strict_types=1);

namespace Cros\Core;

/** Autenticació dels usuaris del panell d'administració. */
class Auth
{
    private static ?array $user = null;
    private const MAX_ATTEMPTS = 8;
    private const LOCK_MINUTES = 15;

    /** Usuari autenticat o null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = (int) ($_SESSION['admin_user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $user = Db::one('SELECT * FROM users WHERE id = :id AND active = 1', ['id' => $id]);
        if (!$user) {
            unset($_SESSION['admin_user_id']);
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

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /** Intenta iniciar sessió. Retorna un missatge d'error o null si tot va bé. */
    public static function attempt(string $email, string $password): ?string
    {
        $ip = client_ip();
        if (self::tooManyAttempts($ip)) {
            return 'Massa intents fallits. Espereu ' . self::LOCK_MINUTES . ' minuts i torneu-ho a provar.';
        }
        $user = Db::one('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => mb_strtolower($email)]);
        $valid = $user && password_verify($password, $user['password_hash']);
        self::logAttempt($ip, $email, $valid);

        if (!$valid) {
            return 'Les credencials no són correctes.';
        }
        if ((int) $user['active'] !== 1) {
            return 'Aquest compte està desactivat.';
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }
        self::login($user);
        return null;
    }

    /** Inicia la sessió d'un usuari. */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int) $user['id'];
        self::$user = $user;
        Db::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        self::logActivity('login', 'user', (int) $user['id']);
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::logActivity('logout', 'user', self::id());
        }
        unset($_SESSION['admin_user_id']);
        self::$user = null;
        session_regenerate_id(true);
    }

    /** Exigeix sessió iniciada (redirigeix al formulari d'accés). */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect('/admin/acces');
        }
    }

    /** Exigeix rol d'administrador. */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            throw new HttpException(403, 'Aquesta secció només és accessible per a administradors.');
        }
    }

    private static function tooManyAttempts(string $ip): bool
    {
        try {
            $count = (int) Db::val(
                'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND attempted_at > :since',
                ['ip' => $ip, 'since' => date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60)],
                0
            );
            return $count >= self::MAX_ATTEMPTS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function logAttempt(string $ip, string $email, bool $success): void
    {
        try {
            Db::insert('login_attempts', [
                'ip' => $ip,
                'email' => mb_substr($email, 0, 190),
                'success' => $success ? 1 : 0,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
            if (random_int(1, 20) === 1) {
                Db::q('DELETE FROM login_attempts WHERE attempted_at < :old', ['old' => date('Y-m-d H:i:s', time() - 86400 * 7)]);
            }
        } catch (\Throwable $e) {
            log_line('auth', 'No s\'ha pogut registrar l\'intent d\'accés', ['error' => $e->getMessage()]);
        }
    }

    /** Registra una acció al registre d'activitat. */
    public static function logActivity(string $action, string $entity = '', int $entityId = 0, array $details = []): void
    {
        try {
            Db::insert('activity_log', [
                'user_id' => self::$user['id'] ?? null,
                'action' => mb_substr($action, 0, 60),
                'entity' => mb_substr($entity, 0, 60),
                'entity_id' => $entityId ?: null,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip' => client_ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // El registre d'activitat mai no ha de trencar una acció.
        }
    }
}
