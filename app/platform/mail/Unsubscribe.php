<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

use Cros\Core\Crypto;
use Cros\Core\Db;

/**
 * Les baixes dels enviaments.
 *
 * Cada correu porta un enllaç propi per donar-se'n de baixa. L'enllaç porta
 * l'adreça i una signatura, de manera que no cal desar res abans d'enviar-lo i
 * ningú no pot donar de baixa una adreça que no és seva canviant l'enllaç.
 * També va a la capçalera List-Unsubscribe, amb la baixa d'un sol clic que
 * demanen Gmail i Yahoo als enviaments grans.
 *
 * Qui es dona de baixa no rep cap més enviament de la plataforma, sigui de
 * la llista o l'audiència que sigui. Els correus de servei (la benvinguda,
 * els enllaços d'accés, el suport) no hi tenen res a veure i continuen arribant.
 */
final class Unsubscribe
{
    /** L'adreça per donar-se de baixa, en un domini concret. */
    public static function url(string $email, string $domain, int $mailingId = 0): string
    {
        return 'https://' . $domain . '/baixa/' . self::token($email, $mailingId);
    }

    /** Codi de l'enllaç: l'adreça (i l'enviament) amb la seva signatura. */
    public static function token(string $email, int $mailingId = 0): string
    {
        $payload = rtrim(strtr(base64_encode(mb_strtolower(trim($email)) . '|' . $mailingId), '+/', '-_'), '=');

        // Sense punt entre les dues parts: un punt a l'adreça fa que alguns
        // servidors el prenguin per l'extensió d'un fitxer. La signatura fa
        // sempre 24 caràcters i va al final.
        return $payload . substr(Crypto::hmac('baixa|' . $payload), 0, 24);
    }

    /**
     * L'adreça i l'enviament d'un codi, o null si el codi no és bo.
     *
     * @return array{email:string,mailing:int}|null
     */
    public static function read(string $token): ?array
    {
        if (!preg_match('/^([A-Za-z0-9_-]{4,400})([0-9a-f]{24})$/', $token, $m)) {
            return null;
        }
        if (!hash_equals(substr(Crypto::hmac('baixa|' . $m[1]), 0, 24), $m[2])) {
            return null;
        }
        $raw = (string) base64_decode(strtr($m[1], '-_', '+/'), true);
        [$email, $mailing] = array_pad(explode('|', $raw, 2), 2, '0');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return ['email' => $email, 'mailing' => (int) $mailing];
    }

    /** Apunta la baixa. Tornar-ho a fer no canvia res. */
    public static function add(string $email, string $domain = '', int $mailingId = 0): void
    {
        $email = mb_strtolower(trim($email));
        if (self::has($email)) {
            return;
        }
        Db::insert('platform_unsubscribes', [
            'email' => mb_substr($email, 0, 190),
            'domain' => $domain !== '' ? mb_substr($domain, 0, 190) : null,
            'mailing_id' => $mailingId > 0 ? $mailingId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Torna a rebre els enviaments (ho fa el panell, si la persona ho demana). */
    public static function remove(string $email): void
    {
        Db::delete('platform_unsubscribes', 'email = :e', ['e' => mb_strtolower(trim($email))]);
    }

    public static function has(string $email): bool
    {
        try {
            return Db::one('SELECT id FROM platform_unsubscribes WHERE email = :e', ['e' => mb_strtolower(trim($email))]) !== null;
        } catch (\Throwable $e) {
            return false; // encara sense la migració
        }
    }

    /** Totes les adreces donades de baixa. @return array<string,bool> */
    public static function all(): array
    {
        try {
            return array_fill_keys(array_column(Db::all('SELECT email FROM platform_unsubscribes'), 'email'), true);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
