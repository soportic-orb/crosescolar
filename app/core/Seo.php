<?php
declare(strict_types=1);

namespace Cros\Core;

use Cros\Models\Content;

/**
 * El que els cercadors i les xarxes socials llegeixen de cada pàgina.
 *
 * Tot surt de la configuració del web (apartat «SEO i cercadors» i «Dades de
 * la cursa»), de manera que cada cros diu qui és sense tocar codi. Aquí es
 * construeixen tres coses:
 *
 *   - l'adreça canònica, perquè la mateixa pàgina no compti dues vegades;
 *   - les etiquetes d'Open Graph i de les targetes d'X, per quan es comparteix;
 *   - les dades estructurades (schema.org), que són com Google entén que això
 *     és una cursa, quin dia és i on es fa.
 */
class Seo
{
    /** Mides màximes que accepten les xarxes per a la imatge destacada. */
    private const IMAGE_MAX = 5 * 1024 * 1024;

    /** El web ha de quedar fora dels cercadors? */
    public static function hidden(): bool
    {
        return Settings::bool('seo_noindex') || Settings::bool('coming_soon');
    }

    /** Valor de l'etiqueta «robots» d'una pàgina. */
    public static function robots(bool $noindex = false): string
    {
        if ($noindex || self::hidden()) {
            return 'noindex, nofollow';
        }

        // max-image-preview:large deixa que Google ensenyi la foto gran al
        // resultat; els altres dos, que no talli el resum ni el vídeo.
        return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }

    /**
     * Adreça canònica d'una pàgina.
     *
     * Sempre sense paràmetres: cap pàgina pública no canvia de contingut
     * segons la consulta, i així «/contacte?enviat=1» i «/contacte» són la
     * mateixa cosa per a Google.
     */
    public static function canonical(string $path = '/'): string
    {
        $path = '/' . trim(explode('?', $path)[0], '/');

        return url($path === '/' ? '/' : $path);
    }

    /**
     * La imatge amb què es comparteix el web.
     * @return array{url:string,alt:string,width:int,height:int}
     */
    public static function image(): array
    {
        $file = (string) (setting('og_image', '') ?: setting('hero_image', ''));
        if ($file === '') {
            return ['url' => '', 'alt' => '', 'width' => 0, 'height' => 0];
        }
        $alt = trim((string) setting('og_image_alt', ''));
        if ($alt === '') {
            $alt = (string) setting('site_name', '');
        }

        $width = 0;
        $height = 0;
        $path = upload_path($file);
        if (is_file($path) && filesize($path) <= self::IMAGE_MAX) {
            $size = @getimagesize($path);
            if (is_array($size)) {
                $width = (int) $size[0];
                $height = (int) $size[1];
            }
        }

        return ['url' => upload_url($file), 'alt' => $alt, 'width' => $width, 'height' => $height];
    }

    /** Les paraules clau configurades, netes i sense repeticions. */
    public static function keywords(): array
    {
        $raw = explode(',', (string) setting('meta_keywords', ''));
        $words = [];
        foreach ($raw as $word) {
            $word = trim(preg_replace('/\s+/u', ' ', $word) ?? '');
            if ($word !== '' && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        return array_slice($words, 0, 20);
    }

    /** El compte d'X, sempre amb arrova i sense espais. */
    public static function twitterSite(): string
    {
        $handle = trim((string) setting('twitter_site', ''));
        if ($handle === '') {
            return '';
        }
        $handle = preg_replace('#^https?://(www\.)?(x|twitter)\.com/#i', '', $handle) ?? $handle;

        return '@' . ltrim(trim($handle), '@');
    }

    /** Data i hora de la cursa en el format que entenen els cercadors. */
    public static function startDate(): string
    {
        $date = trim((string) setting('event_date', ''));
        if ($date === '') {
            return '';
        }
        $time = trim((string) setting('event_time', '')) ?: '00:00';
        try {
            return (new \DateTimeImmutable($date . ' ' . $time))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Les dades estructurades de la pàgina, ja empaquetades en un <script>.
     *
     * A la portada s'hi explica la cursa sencera; a les pàgines internes, el
     * camí de molles de pa, que és el que Google ensenya sobre el títol.
     *
     * @param array<int,array{0:string,1:string}> $trail Molles de pa: [títol, adreça]
     */
    public static function tags(string $path = '/', array $trail = []): string
    {
        if (!Settings::bool('seo_structured_data', true) || self::hidden()) {
            return '';
        }

        $path = '/' . trim(explode('?', $path)[0], '/');
        $graph = [];
        if ($path === '/') {
            $graph[] = self::event();
        } else {
            if ($trail !== []) {
                $graph[] = self::breadcrumbs($trail);
            }
            if ($path === '/preguntes-frequents') {
                $faq = self::faqPage();
                if ($faq !== []) {
                    $graph[] = $faq;
                }
            }
        }
        $graph = array_values(array_filter($graph));
        if ($graph === []) {
            return '';
        }

        $data = count($graph) === 1
            ? ['@context' => 'https://schema.org'] + $graph[0]
            : ['@context' => 'https://schema.org', '@graph' => $graph];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return '';
        }

        // Les dades estructurades van dins d'un <script>: no hi pot haver cap
        // «</» que el tanqui abans d'hora.
        return '<script type="application/ld+json">' . str_replace('<', '<', $json) . '</script>';
    }

    /** La cursa: què és, quan és, on és i qui l'organitza. */
    public static function event(): array
    {
        $image = self::image();
        $event = [
            '@type' => 'SportsEvent',
            '@id' => url('/') . '#cursa',
            'name' => (string) setting('site_name', ''),
            'url' => url('/'),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'sport' => 'Cros',
            'inLanguage' => 'ca',
        ];
        $description = trim((string) setting('meta_description', ''));
        if ($description !== '') {
            $event['description'] = $description;
        }
        $start = self::startDate();
        if ($start !== '') {
            $event['startDate'] = $start;
        }
        if ($image['url'] !== '') {
            $event['image'] = [$image['url']];
        }
        $keywords = self::keywords();
        if ($keywords !== []) {
            $event['keywords'] = implode(', ', $keywords);
        }
        $place = self::place();
        if ($place !== []) {
            $event['location'] = $place;
        }
        $organizer = self::organization();
        if ($organizer !== []) {
            $event['organizer'] = $organizer;
            $event['performer'] = ['@type' => 'Organization', 'name' => $organizer['name']];
        }

        return $event;
    }

    /** On es fa, amb les coordenades si n'hi ha. */
    public static function place(): array
    {
        $name = trim((string) setting('event_place', ''));
        $address = trim((string) setting('event_address', ''));
        if ($name === '' && $address === '') {
            return [];
        }
        $place = ['@type' => 'Place', 'name' => $name !== '' ? $name : $address];
        if ($address !== '') {
            $place['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address,
                'addressLocality' => trim((string) setting('event_town', '')), 'addressCountry' => 'ES'];
        }
        $lat = (float) setting('map_lat', '0');
        $lng = (float) setting('map_lng', '0');
        if ($lat !== 0.0 && $lng !== 0.0) {
            $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        return $place;
    }

    /** Qui hi ha darrere del web, amb les seves xarxes. */
    public static function organization(): array
    {
        $name = trim((string) (setting('organizer', '') ?: setting('legal_entity', '')));
        if ($name === '') {
            return [];
        }
        $org = ['@type' => 'Organization', 'name' => $name, 'url' => url('/')];
        $email = trim((string) setting('contact_email', ''));
        if ($email !== '') {
            $org['email'] = $email;
        }
        $phone = trim((string) setting('contact_phone', ''));
        if ($phone !== '') {
            $org['telephone'] = $phone;
        }
        $logo = trim((string) setting('logo', ''));
        if ($logo !== '') {
            $org['logo'] = upload_url($logo);
        }
        $social = [];
        foreach (['social_instagram', 'social_facebook'] as $key) {
            $url = trim((string) setting($key, ''));
            if ($url !== '') {
                $social[] = $url;
            }
        }
        if ($social !== []) {
            $org['sameAs'] = $social;
        }

        return $org;
    }

    /**
     * El camí fins a la pàgina: Inici → on som.
     * @param array<int,array{0:string,1:string}> $trail
     */
    public static function breadcrumbs(array $trail): array
    {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Inici', 'item' => url('/')]];
        foreach ($trail as $step) {
            $name = trim((string) ($step[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $item = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $name];
            $path = trim((string) ($step[1] ?? ''));
            if ($path !== '') {
                $item['item'] = self::canonical($path);
            }
            $items[] = $item;
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** Les preguntes freqüents, tal com les vol Google. */
    public static function faqPage(): array
    {
        try {
            $faqs = Content::faqs();
        } catch (\Throwable) {
            return [];
        }
        $items = [];
        foreach ($faqs as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim(strip_tags((string) ($faq['answer'] ?? '')));
            if ($question === '' || $answer === '') {
                continue;
            }
            $items[] = ['@type' => 'Question', 'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
        }
        if ($items === []) {
            return [];
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $items];
    }
}
