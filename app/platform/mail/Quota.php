<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Platform\Platform;
use Cros\Platform\Site;

/**
 * El límit de correus per hora de cada domini, comú a tothom qui hi envia.
 *
 * Els enviaments del panell i els que fan els webs dels clients a les seves
 * persones inscrites surten pel mateix servidor de correu del domini (Mailjet,
 * Nominalia…), i el límit que admet el proveïdor és de tots plegats. Cada tanda
 * apunta aquí quants correus enviarà abans d'enviar-los, i la següent mira
 * quants en queden aquesta hora.
 *
 * Es fa servir des de la plataforma, o des del web d'un client a través de
 * Bridge::run(). Si la taula encara no hi és, no hi ha límit.
 */
final class Quota
{
    /** Correus per hora del domini (0: sense límit). Es llegeix de la configuració de la plataforma. */
    public static function limit(string $domain): int
    {
        return Site::during(Platform::validDomain($domain), static fn (): int => max(0, (int) Settings::get('mail_hourly_limit', '0')));
    }

    /** Els que s'han enviat l'última hora pel domini. */
    public static function used(string $domain): int
    {
        try {
            return (int) Db::val(
                'SELECT COALESCE(SUM(count), 0) FROM platform_mail_quota WHERE domain = :d AND created_at >= :since',
                ['d' => Platform::validDomain($domain), 'since' => date('Y-m-d H:i:s', time() - 3600)],
                0
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Quants se'n poden enviar ara mateix (PHP_INT_MAX si no hi ha límit). */
    public static function allowance(string $domain): int
    {
        $limit = self::limit($domain);

        return $limit > 0 ? max(0, $limit - self::used($domain)) : PHP_INT_MAX;
    }

    /**
     * Agafa lloc per a una tanda: en torna quants se'n poden enviar (com a
     * molt $want) i els apunta de seguida, perquè una altra tanda que vagi
     * alhora ja els compti.
     */
    public static function take(string $domain, string $source, int $want): int
    {
        $n = min(max(0, $want), self::allowance($domain));
        if ($n > 0) {
            self::record($domain, $source, $n);
        }

        return $n;
    }

    /** Torna el que s'havia agafat i no s'ha fet servir. */
    public static function giveBack(string $domain, string $source, int $n): void
    {
        if ($n > 0) {
            self::record($domain, $source, -$n);
        }
    }

    public static function record(string $domain, string $source, int $n): void
    {
        if ($n === 0) {
            return;
        }
        try {
            if ($n < 0) {
                // Es resta de l'última anotació d'aquesta font.
                $row = Db::one('SELECT id, count FROM platform_mail_quota WHERE domain = :d AND source = :s ORDER BY id DESC LIMIT 1',
                    ['d' => Platform::validDomain($domain), 's' => mb_substr($source, 0, 60)]);
                if ($row) {
                    Db::update('platform_mail_quota', ['count' => max(0, (int) $row['count'] + $n)], 'id = :id', ['id' => (int) $row['id']]);
                }

                return;
            }
            Db::insert('platform_mail_quota', [
                'domain' => Platform::validDomain($domain),
                'source' => mb_substr($source, 0, 60),
                'count' => $n,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            // Les anotacions velles no serveixen de res.
            if (random_int(1, 50) === 1) {
                Db::delete('platform_mail_quota', 'created_at < :old', ['old' => date('Y-m-d H:i:s', time() - 86400 * 2)]);
            }
        } catch (\Throwable $e) {
            // Sense la taula (encara sense la migració) no es compta res.
        }
    }

    /** Quants minuts falten perquè es torni a poder enviar (aproximat). */
    public static function minutesToWait(string $domain): int
    {
        try {
            $oldest = (string) Db::val(
                'SELECT MIN(created_at) FROM platform_mail_quota WHERE domain = :d AND created_at >= :since AND count > 0',
                ['d' => Platform::validDomain($domain), 'since' => date('Y-m-d H:i:s', time() - 3600)],
                ''
            );
        } catch (\Throwable $e) {
            return 5;
        }
        $at = strtotime($oldest) ?: time();

        return max(1, (int) ceil(($at + 3600 - time()) / 60));
    }
}
