<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Core\Tenancy;
use RuntimeException;

/**
 * El pont del web d'un client cap a la base de dades de la plataforma.
 *
 * Hi ha coses que no poden viure a la base de dades de cada client perquè són
 * de la relació entre el client i nosaltres: els tiquets de suport i el
 * pagament per tenir el web publicat. Viuen a la de la plataforma, i el panell
 * del client hi arriba obrint-la una estona i tornant-la a deixar on era. És
 * la mateixa via que el panell de superadministració ja feia servir per mirar
 * dins d'una instància, només que al revés.
 *
 * En sortir es descarta la configuració que hi hagués carregada: la del cros i
 * la de la plataforma no s'assemblen gens, i barrejar-les faria que un correu
 * del cros sortís amb el nom de la plataforma o a l'inrevés.
 */
class Bridge
{
    /** Evita obrir una connexió dins d'una altra. */
    private static bool $inside = false;

    /** Hi ha plataforma en aquesta instal·lació? */
    public static function available(?string $root = null): bool
    {
        return self::config($root ?? CROS_ROOT) !== [];
    }

    /**
     * Executa una feina contra la base de dades de la plataforma.
     *
     * @template T
     * @param callable():T $fn
     * @param bool $withSettings  també la configuració de la plataforma, per
     *                            enviar correu amb el seu remitent i no el del cros
     * @return T
     */
    public static function run(callable $fn, ?string $root = null, bool $withSettings = false)
    {
        if (self::$inside) {
            return $fn(); // ja hi som: qui ha obert la connexió la tancarà
        }
        $root = $root ?? CROS_ROOT;
        $db = self::config($root);
        if ($db === []) {
            throw new RuntimeException('Aquesta instal·lació no té plataforma configurada.');
        }
        $previous = Db::connection();
        $schema = Settings::currentSchema();
        self::$inside = true;
        try {
            Db::setConnection(Db::connect($db + ['charset' => 'utf8mb4', 'timeout' => 10]));
            if ($withSettings) {
                Settings::forget();
                Platform::prime($root);
            }

            return $fn();
        } finally {
            self::$inside = false;
            Db::setConnection($previous);
            Settings::forget();
            Settings::useSchema($schema);
        }
    }

    /**
     * Les dades de connexió de la plataforma, o [] si no n'hi ha.
     * @return array<string,mixed>
     */
    private static function config(string $root): array
    {
        $db = (array) (Tenancy::settings($root)['db'] ?? []);

        return trim((string) ($db['name'] ?? '')) === '' ? [] : $db;
    }
}
