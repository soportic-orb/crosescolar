<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Settings;
use Cros\Core\Tenancy;

/**
 * Repàs del DNS de la plataforma.
 *
 * La plataforma dona per fet que hi ha un **comodí** apuntant al servidor
 * (`*.crosescolar.cat`): és el que fa que un cros nou funcioni el mateix minut
 * que es dona d'alta, sense tocar el DNS. Si el comodí no hi és, tot sembla
 * que va bé —els cros que ja hi són tenen el seu registre— fins al dia que se'n
 * dona d'alta un de nou i resulta que, per a la resta del món, aquella adreça
 * no existeix.
 *
 * Això ho mira aquí, i el tauler ho diu abans que passi.
 */
class Dns
{
    /** @var callable|null qui resol els noms; a les proves se'n posa un de fals */
    private static $resolver = null;

    /** Canvia qui resol els noms (les proves no han de sortir a internet). */
    public static function using(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Les adreces IP d'un nom.
     *
     * Es pregunta al DNS de debò i no al sistema a propòsit: l'`/etc/hosts` del
     * servidor sol tenir el seu propi nom apuntat a 127.0.1.1, i això ha fet
     * perdre més d'una tarda.
     *
     * @return array<int,string>
     */
    public static function ips(string $host): array
    {
        $host = strtolower(trim($host, " \t\n\r\0\x0B."));
        if ($host === '') {
            return [];
        }
        if (self::$resolver !== null) {
            return array_values(array_unique(array_filter((array) (self::$resolver)($host))));
        }
        $records = @dns_get_record($host, DNS_A);
        $ips = [];
        foreach (is_array($records) ? $records : [] as $record) {
            if (($record['type'] ?? '') === 'A' && ($record['ip'] ?? '') !== '') {
                $ips[] = (string) $record['ip'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Una adreça que és el mateix servidor.
     *
     * No ve del DNS: és l'`/etc/hosts`, que sovint hi posa el nom del servidor
     * apuntat a 127.0.1.1, i el resolutor local (systemd-resolved) el contesta
     * abans de preguntar enlloc. No vol dir que apunti a un altre servidor.
     */
    public static function loopback(string $ip): bool
    {
        return str_starts_with($ip, '127.') || $ip === '::1';
    }

    /** @param array<int,string> $ips @return array<int,string> */
    private static function outside(array $ips): array
    {
        return array_values(array_filter($ips, static fn (string $ip): bool => !self::loopback($ip)));
    }

    /**
     * Com està el DNS de la plataforma.
     *
     * Els noms que el servidor es contesta a si mateix (local) no es poden
     * comprovar des d'aquí i no donen cap avís.
     *
     * @return array{checked_at:string,ips:array<int,string>,wildcard:bool,missing:array<int,string>,elsewhere:array<int,string>,local:array<int,string>,error:string}
     */
    public static function status(?string $root = null, bool $fresh = false): array
    {
        $root = $root ?? CROS_ROOT;
        $buit = ['checked_at' => '', 'ips' => [], 'wildcard' => true,
            'missing' => [], 'elsewhere' => [], 'local' => [], 'error' => ''];
        if (!$fresh) {
            // Com amb el certificat: el tauler ensenya l'últim repàs i no es
            // posa a preguntar noms mentre algú espera una pàgina.
            $desat = json_decode((string) Settings::get('platform_dns_status', ''), true);

            return is_array($desat) && isset($desat['checked_at']) ? $desat : $buit;
        }

        $principal = Platform::domain($root);
        $estat = $buit;
        $estat['checked_at'] = date('Y-m-d H:i:s');
        $propies = self::ips($principal);
        $estat['ips'] = self::outside($propies);
        if ($estat['ips'] === []) {
            $estat['error'] = $propies !== []
                ? 'Aquest servidor es contesta a si mateix el nom ' . $principal . ' (' . implode(', ', $propies) . ', de l\'/etc/hosts), '
                    . 'i així no es pot comprovar el DNS. Traieu ' . $principal . ' de la línia 127.0.1.1 de l\'/etc/hosts.'
                : 'El domini ' . $principal . ' no resol enlloc.';
            Settings::set('platform_dns_status', json_encode($estat, JSON_UNESCAPED_UNICODE));

            return $estat;
        }

        // Un nom que no existeix: si respon, és que hi ha comodí.
        $inventat = 'cros-' . bin2hex(random_bytes(4)) . '.' . $principal;
        $estat['wildcard'] = self::ips($inventat) !== [];

        foreach (self::hosts($root) as $host) {
            $totes = self::ips($host);
            $ips = self::outside($totes);
            if ($totes !== [] && $ips === []) {
                $estat['local'][] = $host;
            } elseif ($ips === []) {
                $estat['missing'][] = $host;
            } elseif (array_intersect($ips, $estat['ips']) === []) {
                // Resol, però cap a un altre lloc: sol ser un registre vell que
                // s'ha quedat d'un servidor anterior.
                $estat['elsewhere'][] = $host;
            }
        }
        Settings::set('platform_dns_status', json_encode($estat, JSON_UNESCAPED_UNICODE));

        return $estat;
    }

    /**
     * Els noms que han de resoldre: els dominis, el panell i el web de cada
     * cros en marxa.
     *
     * @return array<int,string>
     */
    public static function hosts(?string $root = null): array
    {
        return Certificate::hosts($root);
    }

    /** El registre que caldria afegir al DNS, a punt de copiar. */
    public static function record(?string $root = null): string
    {
        $estat = self::status($root);
        $ip = $estat['ips'][0] ?? '';

        return '*    A    ' . ($ip !== '' ? $ip : 'la IP del servidor');
    }
}
