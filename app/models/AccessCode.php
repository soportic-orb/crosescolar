<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Crypto;
use Cros\Core\Db;

/**
 * Codis d'un sol ús que s'envien per correu per entrar a «Les meves inscripcions».
 *
 * El codi no es desa mai en clar: a la base de dades només hi ha el resum amb la
 * clau de l'aplicació, de manera que qui pugui llegir la taula no en pot fer res.
 */
class AccessCode
{
    /** Minuts que val un codi. */
    public const TTL_MINUTES = 15;

    /** Intents de comprovació per codi abans de descartar-lo. */
    private const MAX_ATTEMPTS = 5;

    /** Codis que es poden demanar per adreça en una hora. */
    private const MAX_PER_HOUR = 5;

    /**
     * Genera un codi per a una adreça.
     * @return string codi en clar, o '' si s'ha superat el límit de peticions
     */
    public static function create(string $email, string $ip = ''): string
    {
        $email = self::normalize($email);
        if ($email === '' || self::recentCount($email) >= self::MAX_PER_HOUR) {
            return '';
        }
        self::prune();
        // Els codis pendents d'aquesta adreça deixen de valer: només val el darrer.
        Db::update('access_codes', ['used_at' => date('Y-m-d H:i:s')], 'email = :email AND used_at IS NULL', ['email' => $email]);

        $code = self::digits();
        Db::insert('access_codes', [
            'email' => $email,
            'code_hash' => Crypto::hmac($code),
            'attempts' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
            'used_at' => null,
            'ip' => $ip !== '' ? mb_substr($ip, 0, 45) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $code;
    }

    /** Comprova un codi i el gasta si és correcte. */
    public static function verify(string $email, string $code): bool
    {
        $email = self::normalize($email);
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if ($email === '' || $code === '') {
            return false;
        }
        $row = Db::one(
            'SELECT * FROM access_codes WHERE email = :email AND used_at IS NULL AND expires_at > :now
             ORDER BY id DESC LIMIT 1',
            ['email' => $email, 'now' => date('Y-m-d H:i:s')]
        );
        if (!$row) {
            return false;
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            Db::update('access_codes', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
            return false;
        }
        Db::update('access_codes', ['attempts' => (int) $row['attempts'] + 1], 'id = :id', ['id' => $row['id']]);

        if (!hash_equals((string) $row['code_hash'], Crypto::hmac($code))) {
            return false;
        }
        Db::update('access_codes', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        return true;
    }

    /** Codis demanats per aquesta adreça la darrera hora. */
    public static function recentCount(string $email): int
    {
        return (int) Db::val(
            'SELECT COUNT(*) FROM access_codes WHERE email = :email AND created_at > :since',
            ['email' => self::normalize($email), 'since' => date('Y-m-d H:i:s', time() - 3600)],
            0
        );
    }

    /** Esborra els codis caducats de fa més d'un dia. */
    public static function prune(): void
    {
        Db::delete('access_codes', 'expires_at < :limit', ['limit' => date('Y-m-d H:i:s', time() - 86400)]);
    }

    public static function normalize(string $email): string
    {
        $email = mb_strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    /** Codi numèric de sis xifres, sense favoritismes pel primer dígit. */
    private static function digits(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
