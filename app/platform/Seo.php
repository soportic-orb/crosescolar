<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Settings;

/**
 * El que fa que les webs públiques de la plataforma es trobin.
 *
 * Hi ha dues menes de visitants que no són persones. Els cercadors de sempre
 * (Google, Bing) llegeixen la pàgina i les dades estructurades per entendre
 * què hi ha. Els assistents d'IA (ChatGPT, Claude, Perplexity) fan el mateix,
 * però a més agraeixen un resum en text pla de tot el web: el /llms.txt, que
 * és el que es llegeixen d'una tirada en comptes d'anar pàgina per pàgina.
 *
 * Tot surt de la configuració de cada domini: no hi ha res a escriure a part.
 */
final class Seo
{
    /**
     * Els assistents d'IA que busquen per respondre una pregunta: són els que
     * fan que el web surti citat a les respostes.
     */
    public const AI_SEARCH = [
        'OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User',
        'PerplexityBot', 'Perplexity-User', 'DuckAssistBot', 'MistralAI-User',
    ];

    /** Els que llegeixen la web per aprendre: fan que els models coneguin el servei. */
    public const AI_TRAINING = [
        'GPTBot', 'ClaudeBot', 'anthropic-ai', 'Google-Extended', 'Applebot-Extended',
        'CCBot', 'meta-externalagent',
    ];

    /** Adreces que no diuen res a ningú de fora: formularis i passos d'un tràmit. */
    private const PRIVATE_PATHS = ['/registre', '/benvinguda', '/sollicitud/', '/pagament/', '/contacte/captcha'];

    /** L'adreça d'un camí en aquest domini. */
    public static function url(string $path = '/'): string
    {
        return 'https://' . Site::current() . ($path === '' ? '/' : $path);
    }

    /** La portada no ha de sortir a cap cercador? */
    public static function hidden(): bool
    {
        return Settings::bool('platform_noindex');
    }

    /** Què es diu als assistents d'IA: tots, només els que busquen, o cap. */
    public static function aiMode(): string
    {
        $mode = (string) setting('platform_ai_crawlers', 'all');

        return in_array($mode, ['all', 'search', 'none'], true) ? $mode : 'all';
    }

    /**
     * El robots.txt d'aquest domini.
     *
     * Un rastrejador obeeix el grup que porta el seu nom i, si no n'hi ha cap,
     * el general. Per això les adreces privades es repeteixen a cada grup: si
     * no, un assistent amb grup propi les tindria obertes.
     */
    public static function robots(): string
    {
        $lines = [];
        if (self::hidden()) {
            return "User-agent: *\nDisallow: /\n";
        }
        $block = static function (array $agents, bool $allowed) use (&$lines): void {
            foreach ($agents as $agent) {
                $lines[] = 'User-agent: ' . $agent;
            }
            if (!$allowed) {
                $lines[] = 'Disallow: /';
            } else {
                foreach (self::PRIVATE_PATHS as $path) {
                    $lines[] = 'Disallow: ' . $path;
                }
                $lines[] = 'Allow: /';
            }
            $lines[] = '';
        };

        $mode = self::aiMode();
        $lines[] = '# Cercadors: Google, Bing i la resta.';
        $block(['*'], true);
        $lines[] = '# Assistents d\'IA que busquen per respondre (ChatGPT, Claude, Perplexity…).';
        $block(self::AI_SEARCH, $mode !== 'none');
        $lines[] = '# Assistents d\'IA que llegeixen la web per aprendre.';
        $block(self::AI_TRAINING, $mode === 'all');

        $lines[] = 'Sitemap: ' . self::url('/sitemap.xml');
        if ($mode !== 'none') {
            $lines[] = '# Un resum de tot el web per als assistents d\'IA: ' . self::url('/llms.txt');
        }

        return implode("\n", $lines) . "\n";
    }

    /** Qui hi ha darrere del servei, en dades estructurades. */
    public static function organization(): array
    {
        $name = (string) setting('site_name', 'EsportWeb');
        $email = trim((string) setting('platform_contact_email', '')) ?: Platform::notifyEmail();
        $logo = trim((string) setting('platform_logo', ''));
        $node = [
            '@type' => 'Organization',
            '@id' => self::url('/#organitzacio'),
            'name' => $name,
            'url' => self::url('/'),
            'description' => self::description(),
            'email' => $email,
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $email,
                'availableLanguage' => ['ca', 'es'],
            ],
        ];
        if (Settings::bool('contact_enabled', true)) {
            $node['contactPoint']['url'] = self::url('/contacte');
        }
        if (($legal = trim((string) setting('platform_legal_entity', ''))) !== '') {
            $node['legalName'] = $legal;
        }
        if ($logo !== '') {
            $node['logo'] = upload_url($logo);
        }

        return $node;
    }

    /** El web en si, en dades estructurades. */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::url('/#web'),
            'url' => self::url('/'),
            'name' => (string) setting('site_name', 'EsportWeb'),
            'description' => self::description(),
            'inLanguage' => 'ca',
            'publisher' => ['@id' => self::url('/#organitzacio')],
        ];
    }

    /** El servei com a programa: què és i què fa. */
    public static function software(): array
    {
        $node = [
            '@type' => 'SoftwareApplication',
            '@id' => self::url('/#servei'),
            'name' => (string) setting('site_name', 'EsportWeb'),
            'url' => self::url('/'),
            'description' => self::description(),
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Gestió de curses i activitats esportives',
            'operatingSystem' => 'Web',
            'inLanguage' => 'ca',
            'provider' => ['@id' => self::url('/#organitzacio')],
        ];
        $features = array_values(array_filter(array_map(
            static fn (array $feature): string => trim((string) ($feature['title'] ?? '')),
            self::features()
        )));
        if ($features !== []) {
            $node['featureList'] = $features;
        }

        return $node;
    }

    /**
     * Les curses del llistat, com a esdeveniments esportius.
     *
     * Google només en vol amb data i lloc; les que encara no en tenen hi van
     * com a webs, que també diu prou: on és i com es diu.
     *
     * @param array<int,array<string,mixed>> $instances
     */
    public static function events(array $instances): array
    {
        $items = [];
        foreach (array_values($instances) as $i => $instance) {
            $url = Instance::url($instance);
            $date = (string) ($instance['event_date'] ?? '');
            $town = trim((string) ($instance['town'] ?? ''));
            if ($date !== '' && $town !== '') {
                $item = [
                    '@type' => 'SportsEvent',
                    'name' => (string) $instance['site_name'],
                    'url' => $url,
                    'startDate' => $date,
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'location' => [
                        '@type' => 'Place',
                        'name' => $town,
                        'address' => ['@type' => 'PostalAddress', 'addressLocality' => $town, 'addressCountry' => 'ES'],
                    ],
                ];
                if (($image = Instance::heroUrl($instance)) !== '') {
                    $item['image'] = $image;
                }
            } else {
                $item = ['@type' => 'WebSite', 'name' => (string) $instance['site_name'], 'url' => $url];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $item];
        }

        return [
            '@type' => 'ItemList',
            '@id' => self::url('/#curses'),
            'name' => (string) setting('platform_directory_title', 'Les curses que ja hi són'),
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ];
    }

    /** La pàgina de contacte, en dades estructurades. */
    public static function contactPage(): array
    {
        return [
            '@type' => 'ContactPage',
            '@id' => self::url('/contacte'),
            'url' => self::url('/contacte'),
            'name' => (string) setting('contact_title', 'Parlem-ne'),
            'description' => (string) setting('contact_intro', ''),
            'isPartOf' => ['@id' => self::url('/#web')],
            'about' => ['@id' => self::url('/#organitzacio')],
        ];
    }

    /**
     * El bloc de dades estructurades d'una pàgina: l'organització i el web,
     * que hi són sempre, i el que hi afegeixi cada pàgina.
     *
     * @param array<int,array<string,mixed>> $extra
     */
    public static function jsonLd(array $extra = []): string
    {
        $graph = array_merge([self::organization(), self::website()], $extra);
        $json = (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // Que cap text no pugui tancar l'etiqueta <script> abans d'hora.
        return '<script type="application/ld+json">' . str_replace('<', '<', $json) . '</script>';
    }

    /**
     * El /llms.txt: tot el que cal saber del servei, en un sol text.
     *
     * Segueix el format de llmstxt.org: un títol, una frase que ho resumeix, i
     * apartats amb enllaços. Un assistent el llegeix sencer i ja sap què és el
     * servei, què fa, què costa d'entendre i on escriure.
     *
     * @param array<int,array<string,mixed>> $instances
     */
    public static function llms(array $instances): string
    {
        $name = (string) setting('site_name', 'EsportWeb');
        $out = [];
        $out[] = '# ' . $name;
        $out[] = '';
        $out[] = '> ' . self::oneLine(self::description());
        $out[] = '';
        if (($lead = self::oneLine((string) setting('platform_hero_lead', ''))) !== '') {
            $out[] = $lead;
            $out[] = '';
        }
        $out[] = 'Web: ' . self::url('/') . ' · Idioma: català.';
        $out[] = '';

        $out[] = '## Pàgines principals';
        $out[] = '';
        $out[] = '- [Portada](' . self::url('/') . '): què és el servei, les curses que ja hi són i com crear-ne una.';
        if (Settings::bool('features_enabled', true)) {
            $out[] = '- [Funcionalitats](' . self::url('/funcionalitats') . '): '
                . self::oneLine((string) setting('features_intro', 'Tot el que fa la plataforma.'));
        }
        if (Settings::bool('contact_enabled', true)) {
            $out[] = '- [Contacte](' . self::url('/contacte') . '): '
                . self::oneLine((string) setting('contact_intro', 'Formulari per escriure\'ns.'));
        }
        $out[] = '- [Condicions del servei](' . self::url('/condicions') . ')';
        $out[] = '- [Política de privadesa](' . self::url('/privadesa') . ')';
        $out[] = '';

        $features = self::features();
        if ($features !== []) {
            $out[] = '## Què fa';
            $out[] = '';
            foreach ($features as $feature) {
                $title = self::oneLine((string) ($feature['title'] ?? ''));
                $text = self::oneLine((string) ($feature['text'] ?? ''));
                if ($title !== '') {
                    $out[] = '- **' . $title . '**' . ($text !== '' ? ': ' . $text : '');
                }
            }
            $out[] = '';
        }

        $faqs = self::faqs();
        if ($faqs !== []) {
            $out[] = '## Preguntes freqüents';
            $out[] = '';
            foreach ($faqs as $faq) {
                $out[] = '### ' . self::oneLine((string) $faq['q']);
                $out[] = '';
                $out[] = trim((string) $faq['a']);
                $out[] = '';
            }
        }

        if ($instances !== []) {
            $out[] = '## ' . self::oneLine((string) setting('platform_directory_title', 'Les curses que ja hi són'));
            $out[] = '';
            foreach ($instances as $instance) {
                $detail = array_filter([
                    trim((string) ($instance['town'] ?? '')),
                    !empty($instance['event_date']) ? ca_date((string) $instance['event_date'], true) : '',
                ]);
                $out[] = '- [' . self::oneLine((string) $instance['site_name']) . '](' . Instance::url($instance) . ')'
                    . ($detail !== [] ? ': ' . implode(' · ', $detail) : '');
            }
            $out[] = '';
        }

        $out[] = '## Contacte';
        $out[] = '';
        if (Settings::bool('contact_enabled', true)) {
            $out[] = '- Formulari: ' . self::url('/contacte');
        }
        $out[] = '- Correu: ' . (trim((string) setting('platform_contact_email', '')) ?: Platform::notifyEmail());

        return implode("\n", $out) . "\n";
    }

    /** La frase que descriu el servei: la de cercadors, o la de la portada. */
    public static function description(): string
    {
        $text = trim((string) setting('platform_meta_description', ''));
        if ($text === '') {
            $text = trim((string) setting('platform_tagline', ''));
        }

        return $text !== '' ? $text
            : 'Inscripcions, dorsals, resultats i cobraments per a curses i activitats esportives, cadascuna amb el seu web.';
    }

    /** Les funcionalitats de la pàgina de funcionalitats, ja netes. */
    private static function features(): array
    {
        if (!Settings::bool('features_enabled', true)) {
            return [];
        }
        $rows = json_decode((string) setting('features_list', ''), true);

        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    /** Les preguntes freqüents de la portada, si s'hi ensenyen. */
    private static function faqs(): array
    {
        if (!Settings::bool('platform_faqs_show', true)) {
            return [];
        }
        $rows = json_decode((string) setting('platform_faqs', ''), true);
        $faqs = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q !== '' && $a !== '') {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }

        return $faqs;
    }

    /** Un text en una sola línia, sense etiquetes ni salts. */
    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }
}
