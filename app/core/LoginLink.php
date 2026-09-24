<?php
declare(strict_types=1);

namespace Cros\Core;

use RuntimeException;

/**
 * Enllaços d'accés d'un sol ús al panell.
 *
 * Serveixen per a tres coses: donar les claus a qui estrenarà un cros, tornar
 * a entrar si s'han perdut, i que qui manté la plataforma hi pugui entrar a
 * donar un cop de mà. Sempre d'un sol ús, sempre amb data de caducitat i
 * sempre deixant-ne constància al registre del web.
 */
class LoginLink
{
    public const PURPOSES = [
        'welcome' => 'Estrena del web',
        'reset' => 'Recuperació de l\'accés',
        'support' => 'Suport de la plataforma',
    ];

    /** Quanta estona val cada mena d'enllaç, en minuts. */
    private const MINUTES = [
        'welcome' => 60 * 24 * 7,
        'reset' => 60 * 2,
        'support' => 20,
    ];

    /**
     * Crea un enllaç per a un usuari i en torna la clau.
     * El que es desa és una empremta: si algú es mira la base de dades, no en
     * treu cap enllaç que funcioni.
     */
    public static function issue(int $userId, string $purpose = 'reset', string $note = ''): string
    {
        if (!isset(self::PURPOSES[$purpose])) {
            throw new RuntimeException('Aquesta mena d\'enllaç no existeix.');
        }
        $token = bin2hex(random_bytes(24));
        Db::insert('login_links', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'purpose' => $purpose,
            'note' => $note !== '' ? mb_substr($note, 0, 190) : null,
            'expires_at' => date('Y-m-d H:i:s', time() + self::MINUTES[$purpose] * 60),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        self::purge();

        return $token;
    }

    /** L'adreça d'un enllaç, per al web on som ara. */
    public static function url(string $token): string
    {
        return base_url() . '/admin/clau/' . $token;
    }

    /** L'adreça d'un enllaç d'un altre web (l'usa la plataforma). */
    public static function urlFor(string $base, string $token): string
    {
        return rtrim($base, '/') . '/admin/clau/' . $token;
    }

    /**
     * Gasta un enllaç i torna l'usuari a qui pertoca, o null si no val.
     * Un enllaç només es pot fer servir una vegada.
     */
    public static function consume(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }
        $link = Db::one(
            'SELECT * FROM login_links WHERE token_hash = :hash LIMIT 1',
            ['hash' => hash('sha256', $token)]
        );
        if (!$link || $link['used_at'] !== null || strtotime((string) $link['expires_at']) < time()) {
            return null;
        }
        $user = Db::one('SELECT * FROM users WHERE id = :id AND active = 1', ['id' => $link['user_id']]);
        if (!$user) {
            return null;
        }
        Db::update('login_links', [
            'used_at' => date('Y-m-d H:i:s'),
            'used_ip' => client_ip(),
        ], 'id = :id', ['id' => $link['id']]);

        return ['user' => $user, 'link' => $link];
    }

    /** Treu els que ja no valen, de tant en tant. */
    public static function purge(): void
    {
        try {
            if (random_int(1, 10) === 1) {
                Db::q('DELETE FROM login_links WHERE expires_at < :old', [
                    'old' => date('Y-m-d H:i:s', time() - 86400 * 30),
                ]);
            }
        } catch (\Throwable $e) {
            // Netejar mai no ha de trencar res.
        }
    }
}
