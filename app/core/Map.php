<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Ubicació de l'esdeveniment al mapa.
 *
 * El punt es dedueix, per ordre, de les coordenades configurades, de l'enllaç
 * del mapa (si s'hi ha enganxat un d'OpenStreetMap o de Google Maps) i, si no
 * n'hi ha cap, de l'adreça de la cursa. Així el mapa sempre assenyala el lloc
 * que hi ha escrit a «Dades de la cursa».
 */
final class Map
{
    /** Apropament per defecte del mapa (17 ≈ un carrer). */
    public const ZOOM = 17;

    /** Coordenades de la sortida, o null si no se'n poden deduir. */
    public static function coords(): ?array
    {
        $lat = self::number(self::stored('map_lat'));
        $lng = self::number(self::stored('map_lng'));
        if ($lat !== null && $lng !== null && self::valid($lat, $lng)) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        return self::parse(self::stored('map_embed'));
    }

    /**
     * Valor desat d'una opció, respectant-ne el buit.
     *
     * Settings::get() torna el valor per defecte quan l'opció és buida; aquí cal
     * distingir «no s'ha tocat mai» de «s'ha buidat expressament», perquè buidar
     * les coordenades vol dir «situa el mapa a partir de l'adreça».
     */
    private static function stored(string $key): string
    {
        $all = Settings::load();

        return array_key_exists($key, $all) ? (string) $all[$key] : (string) setting($key, '');
    }

    /** Adreça completa per cercar-la al mapa. */
    public static function address(): string
    {
        $parts = [];
        foreach (['event_place', 'event_address', 'event_town'] as $key) {
            $value = trim((string) setting($key, ''));
            if ($value !== '' && !in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        // La població sovint ja surt dins l'adreça: no cal repetir-la.
        if (count($parts) === 3 && stripos($parts[1], explode(',', $parts[2])[0]) !== false) {
            array_pop($parts);
        }

        return implode(', ', $parts);
    }

    /** Enllaç per obrir la ubicació en un mapa. Cadena buida si no n'hi ha prou dades. */
    public static function link(): string
    {
        $coords = self::coords();
        if ($coords !== null) {
            return self::pointUrl($coords['lat'], $coords['lng']);
        }

        // Un enllaç que no és d'un mapa conegut (p. ex. un mapa propi) es respecta tal qual.
        $custom = trim((string) setting('map_embed', ''));
        if ($custom !== '' && preg_match('#^https?://#i', $custom)) {
            return $custom;
        }

        $address = self::address();

        return $address === '' ? '' : 'https://www.openstreetmap.org/search?query=' . rawurlencode($address);
    }

    /** Enllaç d'OpenStreetMap amb el punt marcat i el mapa centrat a sobre. */
    public static function pointUrl(float $lat, float $lng, int $zoom = self::ZOOM): string
    {
        $la = self::format($lat);
        $ln = self::format($lng);

        return 'https://www.openstreetmap.org/?mlat=' . $la . '&mlon=' . $ln
            . '#map=' . $zoom . '/' . $la . '/' . $ln;
    }

    /** Mapa incrustable amb el punt marcat, o cadena buida si no hi ha coordenades. */
    public static function embed(): string
    {
        $coords = self::coords();
        if ($coords === null) {
            return '';
        }

        // Marge d'uns 350 m al voltant del punt (l'alçada del marc és menor que l'amplada).
        $dx = 0.0045;
        $dy = 0.0026;
        $bbox = self::format($coords['lng'] - $dx) . ',' . self::format($coords['lat'] - $dy)
            . ',' . self::format($coords['lng'] + $dx) . ',' . self::format($coords['lat'] + $dy);

        return 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox
            . '&layer=mapnik&marker=' . self::format($coords['lat']) . ',' . self::format($coords['lng']);
    }

    /** Hi ha prou dades per ensenyar el mapa? */
    public static function has(): bool
    {
        return self::link() !== '';
    }

    /**
     * Treu unes coordenades d'un text: un enllaç d'OpenStreetMap o de Google Maps,
     * una adreça «geo:» o un parell de números enganxat.
     */
    public static function parse(string $value): ?array
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
        if ($value === '') {
            return null;
        }
        $value = rawurldecode($value);

        $num = '(-?\d+(?:[.,]\d+)?)';
        $patterns = [
            '#[?&]mlat=' . $num . '[^\#]*?[?&]mlon=' . $num . '#i',        // OpenStreetMap, marcador
            '#!3d' . $num . '!4d' . $num . '#',                            // Google Maps, punt exacte
            '#[?&](?:q|ll|query|center|daddr|destination|saddr)=' . $num . '\s*,\s*' . $num . '#i',
            '#[\#&]map=[\d.]+/' . $num . '/' . $num . '#i',                // OpenStreetMap, vista
            '#[@:]' . $num . '\s*,\s*' . $num . '#',                       // Google Maps @lat,lng · geo:lat,lng
            '#^' . $num . '\s*[,;\s]\s*' . $num . '$#',                    // coordenades enganxades
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $m)) {
                $lat = self::number($m[1]);
                $lng = self::number($m[2]);
                if ($lat !== null && $lng !== null && self::valid($lat, $lng)) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return null;
    }

    /**
     * Normalitza el que s'escriu a un camp de coordenada: accepta la coma decimal,
     * els graus amb lletra («41,3778 N») i fins i tot un enllaç o el parell sencer
     * enganxat al camp, del qual s'agafa la part que toca.
     *
     * @param string $axis «lat» o «lng»
     */
    public static function coord(string $value, string $axis): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $pair = self::parse($value);
        if ($pair !== null) {
            return self::format($pair[$axis === 'lng' ? 'lng' : 'lat']);
        }

        $number = self::number($value);
        if ($number === null) {
            return null;
        }
        $limit = $axis === 'lng' ? 180.0 : 90.0;

        return abs($number) > $limit ? null : self::format($number);
    }

    /** Converteix un text en número decimal, o null si no ho és. */
    public static function number(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $clean = str_replace(',', '.', (string) preg_replace('/[^0-9,.]/u', '', $value));
        if ($clean === '' || substr_count($clean, '.') > 1 || !is_numeric($clean)) {
            return null;
        }
        $number = (float) $clean;

        // Si hi ha lletra de l'hemisferi, mana ella; si no, el signe escrit.
        if (preg_match('/[NSEWOnsewo]\s*$/u', $value, $m)) {
            return in_array(strtoupper($m[0]), ['S', 'W', 'O'], true) ? -$number : $number;
        }

        return preg_match('/^\s*-/', $value) ? -$number : $number;
    }

    /** Les coordenades són a la Terra i no són el punt zero (que vol dir «sense configurar»). */
    public static function valid(float $lat, float $lng): bool
    {
        return abs($lat) <= 90 && abs($lng) <= 180 && !(abs($lat) < 0.0001 && abs($lng) < 0.0001);
    }

    /** Coordenada amb punt decimal i sense zeros sobrers, tal com l'espera OpenStreetMap. */
    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') ?: '0';
    }
}
