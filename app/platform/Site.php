<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Core\Tenancy;

/**
 * Les pàgines públiques de la plataforma, una per domini.
 *
 * El nucli és EsportWeb i serveix qualsevol esport. Però un domini com
 * crosescolar.cat parla de cros escolars i no de natació, i qui hi arriba
 * ha de llegir el que hi ha anat a buscar. Per això cada domini té el seu
 * text, el seu banner i les seves preguntes, i tots es gestionen des del
 * mateix Panell de Superadministració.
 *
 * El que no canvia és tota la resta: els clients, la facturació, el correu i
 * el panell de cadascú són els mateixos vinguin d'on vinguin. Només és
 * diferent el que es veu des de fora.
 */
final class Site
{
    /** Com comencen les claus d'un domini a la taula de configuració. */
    private const PREFIX = 'site:';

    /** La clau que diu que un domini ja té els seus textos escrits. */
    public const SEEDED = '__nascut';

    /** Quin domini s'està servint ara mateix. */
    private static string $current = '';

    /** El prefix de les claus d'un domini. */
    public static function prefix(string $domain): string
    {
        return self::PREFIX . strtolower(trim($domain)) . ':';
    }

    /**
     * Quines opcions són d'una pàgina i quines de tota la plataforma.
     * Ho diu l'esquema: els grups marcats amb «per_site» van per domini.
     *
     * @return array<int,string>
     */
    public static function keys(): array
    {
        static $keys = null;
        if ($keys !== null) {
            return $keys;
        }
        $keys = [];
        foreach (self::schema() as $group) {
            if (empty($group['per_site'])) {
                continue;
            }
            foreach (array_keys((array) ($group['fields'] ?? [])) as $name) {
                $keys[] = (string) $name;
            }
        }

        return $keys;
    }

    /** Aquest grup de configuració és d'una pàgina concreta? */
    public static function isPerSite(string $group): bool
    {
        return !empty(self::schema()[$group]['per_site']);
    }

    /**
     * Amb què neix un domini: el que digui site_content.php i, si no hi surt,
     * el que digui l'esquema.
     *
     * Es mira primer el domini sencer i després només el nom, sense
     * l'extensió: crosescolar.cat, crosescolar.com i crosescolar.test són el
     * mateix web i han de dir el mateix.
     *
     * @return array<string,string>
     */
    public static function birth(string $domain): array
    {
        static $content = null;
        if ($content === null) {
            $file = CROS_APP . '/platform/site_content.php';
            $content = is_file($file) ? (array) require $file : [];
        }
        $domain = strtolower(trim($domain));
        $nom = explode('.', $domain)[0];
        $valors = (array) ($content[$domain] ?? ($content[$nom] ?? []));

        return array_map('strval', $valors);
    }

    /** Fa servir els textos d'aquest domini fins que es digui el contrari. */
    public static function activate(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            Settings::scope();
            self::$current = '';

            return;
        }
        self::$current = $domain;
        Settings::scope(self::prefix($domain), self::keys(), self::birth($domain));
    }

    /** El domini que s'està servint, o el principal si no n'hi ha cap. */
    public static function current(): string
    {
        return self::$current !== '' ? self::$current : Platform::domain();
    }

    /**
     * Fa el que calgui perquè cada domini tingui els seus textos.
     *
     * El domini principal es queda el que ja hi havia desat, que és el que
     * es veia abans que hi hagués una pàgina per domini. La resta neixen
     * amb el text que els toca —el de site_content.php si n'hi ha, i si no
     * el de l'esquema— sense heretar el del veí.
     *
     * Cada opció es mira per separat, de manera que una que s'afegeixi més
     * endavant també arriba als dominis que ja hi eren.
     */
    public static function seed(?string $root = null): void
    {
        $domains = Platform::domains($root);
        if ($domains === []) {
            return;
        }
        $desat = [];
        foreach (Db::all('SELECT k, v FROM settings') as $row) {
            $desat[(string) $row['k']] = (string) $row['v'];
        }
        $previ = Settings::scopeName();
        Settings::scope();
        $defaults = Settings::defaults();
        foreach ($domains as $i => $domain) {
            $prefix = self::prefix($domain);
            // Només el principal hereta el que hi havia abans dels dominis,
            // i només el primer cop.
            $hereta = $i === 0 && !isset($desat[$prefix . self::SEEDED]);
            $birth = self::birth($domain);
            foreach (self::keys() as $key) {
                if (array_key_exists($prefix . $key, $desat)) {
                    continue;
                }
                $value = (string) ($birth[$key] ?? ($defaults[$key] ?? ''));
                if ($hereta && isset($desat[$key]) && $desat[$key] !== '') {
                    $value = $desat[$key];
                }
                Settings::set($prefix . $key, $value);
            }
            Settings::set($prefix . self::SEEDED, '1');
        }
        if ($previ !== '') {
            self::activate(self::$current);
        }
        Settings::load(true);
    }

    /**
     * Hi ha algun domini a qui li falti alguna opció?
     *
     * @param array<string,string> $loaded el que hi ha carregat a memòria
     */
    public static function incomplete(array $loaded, ?string $root = null): bool
    {
        foreach (Platform::domains($root) as $domain) {
            $prefix = self::prefix($domain);
            if (!isset($loaded[$prefix . self::SEEDED])) {
                return true;
            }
            foreach (self::keys() as $key) {
                if (!array_key_exists($prefix . $key, $loaded)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Una opció tal com la veu un domini concret, sense canviar el que
     * s'estigui servint ara. Va bé al panell, que porta més d'una pàgina.
     */
    public static function value(string $domain, string $key, string $default = ''): string
    {
        $previ = self::$current;
        self::activate($domain);
        $valor = (string) Settings::get($key, $default !== '' ? $default : null);
        self::activate($previ);

        return $valor;
    }

    /** Un domini nostre; si no ho és, el principal. */
    public static function valid(string $domain, ?string $root = null): string
    {
        return Platform::validDomain($domain, $root);
    }

    /** @return array<string,mixed> */
    private static function schema(): array
    {
        static $schema = null;
        if ($schema === null) {
            $schema = (array) require CROS_APP . '/platform/config_schema.php';
        }

        return $schema;
    }
}
