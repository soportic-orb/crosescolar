<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Decideix quina instal·lació atén la petició.
 *
 * El mateix codi serveix el web de cada cros (un subdomini per client), la
 * pàgina pública de la plataforma i el panell de superadministració. Qui ho
 * tria és l'adreça per on entra la petició.
 *
 * Aquesta classe s'ha de poder fer servir abans d'arrencar l'aplicació —és qui
 * diu on són la configuració i les dades—, de manera que no depèn de res més.
 */
final class Tenancy
{
    /** Subdominis que no es poden donar a cap client. */
    public const RESERVED = [
        'www', 'admin', 'api', 'app', 'panel', 'panell', 'plataforma', 'platform',
        'mail', 'correu', 'webmail', 'smtp', 'imap', 'pop', 'mx', 'ns1', 'ns2', 'dns',
        'ftp', 'sftp', 'cdn', 'static', 'assets', 'media', 'files', 'fitxers',
        'blog', 'ajuda', 'suport', 'help', 'support', 'docs', 'estat', 'status',
        'dev', 'test', 'proves', 'staging', 'demo', 'localhost', 'autodiscover',
    ];

    /** Com es diu el fitxer que hi ha a la carpeta d'una instància aturada. */
    public const SUSPENDED = 'suspended';

    /** @var array{mode:string,slug:string,host:string,dir:string} */
    private static array $current = ['mode' => 'single', 'slug' => '', 'host' => '', 'dir' => ''];

    /**
     * Mira per on entra la petició i, si és el web d'un client, deixa
     * apuntades les seves carpetes perquè l'aplicació les trobi en arrencar.
     *
     * Modes possibles:
     *   single     una sola instal·lació, com sempre (no hi ha plataforma)
     *   tenant     el web d'un client
     *   platform   la pàgina pública de la plataforma
     *   console    el panell de superadministració
     *   suspended  el web d'un client aturat
     *   unknown    un subdomini que no és de ningú
     *
     * @return array{mode:string,slug:string,host:string,dir:string}
     */
    public static function boot(string $root, ?string $host = null): array
    {
        $settings = self::settings($root);
        $base = (string) ($settings['base_domain'] ?? '');
        if ($base === '') {
            return self::$current = ['mode' => 'single', 'slug' => '', 'host' => '', 'dir' => ''];
        }

        $host = self::host($host);
        $base = strtolower(trim(trim($base), '.'));

        // El domini de la plataforma (amb «www» o sense) és la pàgina pública.
        if ($host === $base || $host === 'www.' . $base) {
            return self::$current = ['mode' => 'platform', 'slug' => '', 'host' => $host, 'dir' => ''];
        }
        // Una adreça que no penja del domini de la plataforma no és de ningú:
        // val més dir-ho que no pas ensenyar-li el web d'algú altre.
        if (!str_ends_with($host, '.' . $base)) {
            return self::$current = ['mode' => 'unknown', 'slug' => '', 'host' => $host, 'dir' => ''];
        }
        $slug = self::slug($host, $base);
        if (in_array($slug, (array) ($settings['console'] ?? ['admin']), true)) {
            return self::$current = ['mode' => 'console', 'slug' => $slug, 'host' => $host, 'dir' => ''];
        }

        $dir = self::dir($root, $slug);
        if (!self::valid($slug) || $dir === '' || !is_file($dir . '/config.php')) {
            return self::$current = ['mode' => 'unknown', 'slug' => $slug, 'host' => $host, 'dir' => ''];
        }
        if (is_file($dir . '/' . self::SUSPENDED)) {
            return self::$current = ['mode' => 'suspended', 'slug' => $slug, 'host' => $host, 'dir' => $dir];
        }

        putenv('CROS_CONFIG=' . $dir . '/config.php');
        putenv('CROS_UPLOADS=' . $dir . '/uploads');
        putenv('CROS_STORAGE=' . $dir . '/storage');

        return self::$current = ['mode' => 'tenant', 'slug' => $slug, 'host' => $host, 'dir' => $dir];
    }

    /** El que ha decidit boot() en aquesta petició. */
    public static function current(): array
    {
        return self::$current;
    }

    public static function mode(): string
    {
        return (string) self::$current['mode'];
    }

    /** Subdomini que s'està atenent, o '' si no n'hi ha cap. */
    public static function slugOf(): string
    {
        return (string) self::$current['slug'];
    }

    /**
     * Configuració de la plataforma (tenants/platform.php), o [] si no n'hi ha.
     * Sense aquest fitxer, tot funciona com una instal·lació de tota la vida.
     *
     * @return array<string,mixed>
     */
    public static function settings(string $root): array
    {
        static $cache = [];
        if (array_key_exists($root, $cache)) {
            return $cache[$root];
        }
        $file = $root . '/tenants/platform.php';
        $settings = is_file($file) ? require $file : [];

        return $cache[$root] = is_array($settings) ? $settings : [];
    }

    /** Amfitrió de la petició, en minúscules i sense port. */
    public static function host(?string $host = null): string
    {
        $host = $host ?? (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(trim($host));
        // Una adreça pot arribar amb port, amb punt final o entre claudàtors (IPv6).
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return trim($host, '.[]');
    }

    /**
     * Subdomini d'un amfitrió respecte del domini de la plataforma.
     * Torna '' si l'amfitrió és el domini mateix o no hi té res a veure.
     */
    public static function slug(string $host, string $baseDomain): string
    {
        $host = self::host($host);
        $base = strtolower(trim(trim($baseDomain), '.'));
        if ($host === '' || $base === '' || $host === $base) {
            return '';
        }
        if (!str_ends_with($host, '.' . $base)) {
            return '';
        }

        return substr($host, 0, -strlen($base) - 1);
    }

    /** Un subdomini que es pugui donar a un client. */
    public static function valid(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{1,29}$/', $slug) === 1
            && !str_contains($slug, '--')
            && !str_ends_with($slug, '-')
            && !self::reserved($slug);
    }

    /** @param array<int,string> $extra subdominis reservats a més dels de sempre */
    public static function reserved(string $slug, array $extra = []): bool
    {
        $slug = strtolower(trim($slug));

        return in_array($slug, self::RESERVED, true) || in_array($slug, array_map('strtolower', $extra), true);
    }

    /** Carpeta d'una instància, o '' si el nom no és acceptable. */
    public static function dir(string $root, string $slug): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,29}$/', $slug)) {
            return '';
        }

        return $root . '/tenants/' . $slug;
    }

    /** Hi ha una instància instal·lada amb aquest subdomini? */
    public static function exists(string $root, string $slug): bool
    {
        $dir = self::dir($root, $slug);

        return $dir !== '' && is_file($dir . '/config.php');
    }

    /** Subdominis instal·lats, en ordre alfabètic. @return array<int,string> */
    public static function all(string $root): array
    {
        $slugs = [];
        foreach (glob($root . '/tenants/*/config.php') ?: [] as $file) {
            $slugs[] = basename(dirname($file));
        }
        sort($slugs);

        return $slugs;
    }
}
