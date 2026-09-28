<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Core\Tenancy;

/**
 * Vigilància del certificat del servidor.
 *
 * El panell no renova res —per renovar un certificat cal ser root, i el web no
 * ho és ni ho ha de ser mai— però sí que pot mirar com està: s'obre una
 * connexió TLS als seus propis dominis, com faria qualsevol visitant, i se'n
 * llegeix la data de caducitat i els noms que cobreix. Amb això el tauler diu
 * quants dies queden, la superadministració rep un avís abans que es faci
 * tard, i es veu de seguida si un cros nou s'ha quedat fora del certificat.
 */
class Certificate
{
    /** Dies que falten per caducar en què s'envia un avís. */
    public const WARN_DAYS = [21, 7, 3, 1];

    /** Segons que s'espera el servidor abans de donar-ho per perdut. */
    private const TIMEOUT = 6;

    /**
     * El certificat que serveix un amfitrió.
     *
     * @return array{host:string,ok:bool,error:string,expires_at:string,days:int,issuer:string,names:array<int,string>}
     */
    public static function read(string $host, int $port = 443): array
    {
        $buit = ['host' => $host, 'ok' => false, 'error' => '', 'expires_at' => '',
            'days' => 0, 'issuer' => '', 'names' => []];
        $host = strtolower(trim($host));
        if ($host === '') {
            return ['error' => 'No hi ha cap amfitrió a mirar.'] + $buit;
        }

        // No es verifica la cadena a propòsit: un certificat caducat o que no
        // correspon també s'ha de poder llegir, que és justament el que
        // s'intenta descobrir.
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);
        $socket = @stream_socket_client(
            'ssl://' . $host . ':' . $port,
            $errno,
            $error,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket === false) {
            return ['error' => trim((string) $error) ?: 'No s\'hi ha pogut connectar.'] + $buit;
        }
        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return ['error' => 'El servidor no ha donat cap certificat.'] + $buit;
        }
        $data = @openssl_x509_parse($cert);
        if (!is_array($data)) {
            return ['error' => 'El certificat no es deixa llegir.'] + $buit;
        }

        $until = (int) ($data['validTo_time_t'] ?? 0);

        return [
            'host' => $host,
            'ok' => $until > time(),
            'error' => '',
            'expires_at' => $until > 0 ? date('Y-m-d H:i:s', $until) : '',
            // Cap a baix: 0,9 dies són 0 dies, no 1.
            'days' => $until > 0 ? (int) floor(($until - time()) / 86400) : 0,
            'issuer' => (string) ($data['issuer']['O'] ?? $data['issuer']['CN'] ?? ''),
            'names' => self::names($data),
        ];
    }

    /**
     * Els noms que cobreix un certificat, del camp «subjectAltName».
     * @return array<int,string>
     */
    private static function names(array $data): array
    {
        $raw = (string) ($data['extensions']['subjectAltName'] ?? '');
        $names = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'DNS:')) {
                $names[] = strtolower(substr($part, 4));
            }
        }
        if ($names === [] && ($data['subject']['CN'] ?? '') !== '') {
            $names[] = strtolower((string) $data['subject']['CN']);
        }

        return array_values(array_unique($names));
    }

    /**
     * Un certificat que cobreix aquests noms, val per a aquest amfitrió?
     *
     * Un comodí («*.crosescolar.cat») val per a un nivell i només un:
     * «lagranada.crosescolar.cat» sí, «crosescolar.cat» no, i
     * «a.b.crosescolar.cat» tampoc.
     *
     * @param array<int,string> $names
     */
    public static function covers(array $names, string $host): bool
    {
        $host = strtolower(trim($host));
        foreach ($names as $name) {
            $name = strtolower(trim($name));
            if ($name === $host) {
                return true;
            }
            if (str_starts_with($name, '*.')) {
                $arrel = substr($name, 2);
                if (str_ends_with($host, '.' . $arrel)
                    && !str_contains(substr($host, 0, -strlen($arrel) - 1), '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Com està el certificat de tota la plataforma.
     *
     * Es mira el domini principal i, amb el que en surt, quins amfitrions
     * queden coberts: el panell, la portada i el web de cada cros en marxa.
     *
     * @return array{checked_at:string,host:string,ok:bool,error:string,expires_at:string,days:int,issuer:string,names:array<int,string>,uncovered:array<int,string>}
     */
    public static function status(?string $root = null, bool $fresh = false): array
    {
        $root = $root ?? CROS_ROOT;
        if (!$fresh) {
            // Sense «fresh» no es connecta enlloc: es torna l'últim repàs que
            // va fer la vigilància. Així el tauler no s'espera mai per un
            // servidor que no respon, i si encara no se n'ha fet cap, no
            // s'ensenya res.
            $desat = json_decode((string) Settings::get('platform_cert_status', ''), true);

            return is_array($desat) && isset($desat['checked_at'])
                ? $desat
                : ['checked_at' => '', 'host' => '', 'ok' => false, 'error' => '',
                    'expires_at' => '', 'days' => 0, 'issuer' => '', 'names' => [], 'uncovered' => []];
        }

        $principal = Platform::domain($root);
        $estat = self::read($principal);
        $estat['checked_at'] = date('Y-m-d H:i:s');
        $estat['uncovered'] = [];
        if ($estat['names'] !== []) {
            foreach (self::hosts($root) as $host) {
                if (!self::covers($estat['names'], $host)) {
                    $estat['uncovered'][] = $host;
                }
            }
        }
        Settings::set('platform_cert_status', json_encode($estat, JSON_UNESCAPED_UNICODE));

        return $estat;
    }

    /**
     * Els amfitrions que ha de cobrir el certificat: els dominis de la
     * plataforma, el panell i el web de cada cros que està en marxa.
     *
     * @return array<int,string>
     */
    public static function hosts(?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $hosts = [];
        // Els noms del panell surten del fitxer de la plataforma («console»),
        // que és el mateix lloc d'on els treu l'encaminador.
        $consoles = (array) (Tenancy::settings($root)['console'] ?? ['admin']);
        foreach (Tenancy::domains($root) as $domain) {
            $hosts[] = $domain;
            foreach ($consoles as $console) {
                $console = trim((string) $console);
                if ($console !== '') {
                    $hosts[] = $console . '.' . $domain;
                }
            }
        }
        foreach (Instance::all() as $instance) {
            if (in_array((string) $instance['status'], ['new', 'active'], true)) {
                $hosts[] = Instance::host($instance, $root);
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * L'ordre que cal executar al servidor per renovar o ampliar el
     * certificat, a punt de copiar.
     */
    public static function command(?string $root = null, bool $wildcard = false): string
    {
        $root = $root ?? CROS_ROOT;
        $principal = Platform::domain($root);
        if ($wildcard) {
            return 'certbot certonly --manual --preferred-challenges dns --agree-tos'
                . ' -d ' . $principal . " -d '*." . $principal . "'";
        }
        $noms = '';
        foreach (self::hosts($root) as $host) {
            $noms .= ' -d ' . $host;
        }

        return 'certbot --nginx --redirect --agree-tos' . $noms;
    }

    /**
     * Avisa la superadministració quan queden pocs dies.
     *
     * S'envia un sol avís per llindar: als 21 dies, als 7, als 3 i a l'últim.
     * Si es renova, els llindars es tornen a armar tots sols.
     */
    public static function warn(?string $root = null, ?array $estat = null): string
    {
        $root = $root ?? CROS_ROOT;
        // Qui ja acaba de mirar-lo (la vigilància) ens passa el resultat, que
        // així no s'obre una segona connexió per no res.
        $estat = $estat ?? self::status($root, true);
        if ($estat['error'] !== '' || $estat['expires_at'] === '') {
            return '';
        }
        $avisat = (int) Settings::get('platform_cert_warned', 0);
        // En renovar-se, el que s'havia avisat ja no compta.
        if ($estat['days'] > max(self::WARN_DAYS)) {
            if ($avisat !== 0) {
                Settings::set('platform_cert_warned', 0);
            }
            return '';
        }
        $llindar = 0;
        foreach (self::WARN_DAYS as $dies) {
            if ($estat['days'] <= $dies) {
                $llindar = $dies;
            }
        }
        if ($llindar === 0 || ($avisat !== 0 && $avisat <= $llindar)) {
            return '';
        }

        $to = Platform::notifyEmail($root);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        $assumpte = $estat['days'] <= 0
            ? 'El certificat del servidor ha caducat'
            : 'El certificat del servidor caduca d\'aquí a ' . $estat['days']
                . ($estat['days'] === 1 ? ' dia' : ' dies');
        Mailer::sendTemplate($to, $assumpte, 'platform-certificate', [
            'days' => $estat['days'],
            'expires' => $estat['expires_at'],
            'host' => $estat['host'],
            'command' => self::command($root, self::looksWildcard($estat['names'])),
        ]);
        Settings::set('platform_cert_warned', $llindar);
        Console::log('cert_warning', '', null, ['dies' => $estat['days'], 'caduca' => $estat['expires_at']]);

        return $assumpte;
    }

    /**
     * Com va anar l'última renovació automàtica.
     *
     * Ho escriu tools/renovar-certificat.sh des del cron de root; aquí només
     * es llegeix. Si no hi ha el fitxer, és que no s'ha engegat mai.
     *
     * @return array{quan:string,resultat:string,detall:string}
     */
    public static function renewal(?string $root = null): array
    {
        $file = ($root ?? CROS_ROOT) . '/storage/certificat.json';
        if (!is_file($file) || !is_readable($file)) {
            return ['quan' => '', 'resultat' => '', 'detall' => ''];
        }
        $dades = json_decode((string) @file_get_contents($file), true);

        return [
            'quan' => (string) ($dades['quan'] ?? ''),
            'resultat' => (string) ($dades['resultat'] ?? ''),
            'detall' => (string) ($dades['detall'] ?? ''),
        ];
    }

    /** El certificat que hi ha ara és de comodí? */
    public static function looksWildcard(array $names): bool
    {
        foreach ($names as $name) {
            if (str_starts_with(trim($name), '*.')) {
                return true;
            }
        }

        return false;
    }
}
