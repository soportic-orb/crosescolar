<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

/**
 * El contingut d'un correu de l'editor: estils generals («settings») i una
 * llista de blocs.
 *
 * Tot el que arriba de l'editor no és de fiar: aquí es reconstrueix amb només
 * els tipus, camps i valors admesos (colors, mides, enllaços i text amb format
 * net). El resultat és el que es desa i el que dibuixa el Renderer.
 *
 * És el model del Mailing del Tauler de Gestió, amb dues diferències: els blocs
 * que llegien dades de l'iSEM (esdeveniment, enquesta, formulari, publicació)
 * no hi són, i n'hi ha dos de la plataforma, «Cursa» (un web del directori) i
 * «Novetat». I els colors que es deixen en blanc són els del domini que envia:
 * la mateixa plantilla surt verda de crosescolar.cat i amb els colors
 * d'EsportWeb d'esportweb.cat.
 */
final class Doc
{
    public const MAX_BLOCKS = 150;

    public const WIDTH = 600;

    /** Tipus de bloc => nom que veu la persona. */
    public const TYPES = [
        'header' => 'Capçalera',
        'heading' => 'Títol',
        'text' => 'Text',
        'image' => 'Imatge',
        'button' => 'Botó',
        'link' => 'Enllaç',
        'box' => 'Requadre',
        'columns' => 'Columnes',
        'imagetext' => 'Imatge i text',
        'divider' => 'Separador',
        'spacer' => 'Espai',
        'footer' => 'Peu',
        'race' => 'Cursa',
        'news' => 'Novetat',
    ];

    /** Blocs que copien les dades d'una font de la plataforma. */
    public const SOURCES = ['race' => 'curses'];

    /** Blocs propis de la plataforma (van a part a la paleta). */
    public const PLATFORM = ['race', 'news'];

    /** Tipus de lletra segurs a tots els programes de correu. */
    public const FONTS = [
        'arial' => ['label' => 'Arial', 'stack' => "Arial, 'Helvetica Neue', Helvetica, sans-serif"],
        'segoe' => ['label' => 'Segoe UI', 'stack' => "'Segoe UI', Tahoma, Arial, sans-serif"],
        'verdana' => ['label' => 'Verdana', 'stack' => 'Verdana, Geneva, Arial, sans-serif'],
        'georgia' => ['label' => 'Georgia', 'stack' => "Georgia, 'Times New Roman', serif"],
    ];

    /**
     * Estils generals per defecte. Els colors en blanc són els del domini que
     * envia (el principal per als títols i els enllaços, el d'acompanyament per
     * als botons).
     */
    public const SETTINGS = [
        'bodyBg' => '#f4f6f3',
        'contentBg' => '#ffffff',
        'font' => 'arial',
        'textColor' => '#1f2a22',
        'textSize' => 15,
        'headingColor' => '',
        'linkColor' => '',
        'accent' => '',
        'padX' => 28,
    ];

    /** Camps personals que es poden posar al text, l'assumpte i els enllaços. */
    public const FIELDS = [
        '{{nom}}' => 'Nom',
        '{{entitat}}' => 'Entitat o web',
        '{{correu}}' => 'Correu',
        '{{web}}' => 'Domini que envia',
        '{{plataforma}}' => 'Nom de la plataforma',
        '{{any}}' => 'Any',
    ];

    /** Valors per defecte de cada tipus de bloc. */
    public static function defaults(string $type): array
    {
        $common = ['pt' => 10, 'pb' => 10, 'bg' => '', 'hideMobile' => false];

        return match ($type) {
            'header' => ['src' => 'brand:logo', 'alt' => '', 'width' => 150, 'align' => 'left', 'href' => 'https://{{web}}',
                'name' => '', 'borderTop' => 'brand', 'borderWidth' => 4, 'pt' => 22, 'pb' => 18] + $common,
            'heading' => ['text' => 'Títol', 'level' => 1, 'size' => 22, 'color' => '', 'align' => 'left', 'pt' => 18, 'pb' => 6] + $common,
            'text' => ['html' => '<p>Escriviu aquí el text.</p>', 'size' => 0, 'color' => '', 'align' => 'left'] + $common,
            'image' => ['src' => '', 'alt' => '', 'width' => 0, 'align' => 'center', 'href' => '', 'radius' => 8] + $common,
            'button' => ['text' => 'Més informació', 'href' => '', 'btnBg' => '', 'color' => '#ffffff', 'align' => 'center', 'full' => false,
                'mobileFull' => true, 'radius' => 8, 'size' => 15, 'pt' => 16, 'pb' => 16] + $common,
            'link' => ['text' => 'Més informació ›', 'href' => '', 'color' => '', 'bold' => true, 'align' => 'left', 'size' => 0] + $common,
            'box' => ['html' => '<p><strong>Text destacat</strong></p>', 'boxBg' => '#eef3ec', 'color' => '', 'radius' => 10, 'size' => 0] + $common,
            'columns' => ['count' => 2, 'cols' => [self::col(), self::col()]] + $common,
            'imagetext' => ['side' => 'left'] + self::col() + $common,
            'divider' => ['color' => '#dde4d9', 'thickness' => 1, 'widthPct' => 100] + $common,
            'spacer' => ['height' => 24, 'pt' => 0, 'pb' => 0] + $common,
            'footer' => ['src' => '', 'alt' => '', 'width' => 0, 'name' => '',
                'links' => [
                    ['text' => 'Web', 'href' => 'https://{{web}}'],
                    ['text' => 'Contacte', 'href' => 'https://{{web}}/contacte'],
                ],
                'note' => 'Rebeu aquest correu perquè teniu, o heu demanat, un web a {{plataforma}}.',
                'lineColor' => '#dde4d9', 'color' => '#7a857d', 'linkColor' => '', 'pt' => 28, 'pb' => 12] + $common,
            'race' => ['refId' => '', 'title' => '', 'when' => '', 'town' => '', 'desc' => '', 'src' => '',
                'btnText' => 'Visiteu el web ›', 'href' => '', 'boxBg' => '#f4f6f3', 'pt' => 12, 'pb' => 12] + $common,
            'news' => ['kicker' => 'Novetat', 'title' => 'Una novetat de la plataforma', 'desc' => 'Expliqueu en dues o tres frases què hi ha de nou i per a què serveix.',
                'src' => '', 'btnText' => 'Com funciona ›', 'href' => '', 'boxBg' => '#f4f6f3', 'pt' => 12, 'pb' => 12] + $common,
            default => $common,
        };
    }

    /** Una columna (també el bloc «Imatge i text»). */
    public static function col(): array
    {
        return ['src' => '', 'alt' => '', 'title' => 'Títol', 'html' => '<p>Text de la columna.</p>', 'linkText' => '', 'href' => ''];
    }

    /** Document nou amb els blocs indicats (tipus o [tipus, camps]). */
    public static function make(array $blocks, array $settings = []): array
    {
        $out = [];
        foreach ($blocks as $b) {
            [$type, $fields] = is_array($b) ? [$b[0], $b[1] ?? []] : [$b, []];
            $out[] = ['type' => $type] + $fields;
        }

        return self::clean(['settings' => $settings, 'blocks' => $out]);
    }

    /**
     * Document net a partir del que envia l'editor.
     *
     * @return array{settings: array<string,mixed>, blocks: array<int,array<string,mixed>>}
     */
    public static function clean(mixed $doc): array
    {
        $doc = is_array($doc) ? $doc : [];
        $s = is_array($doc['settings'] ?? null) ? $doc['settings'] : [];
        $d = self::SETTINGS;
        $settings = [
            'bodyBg' => self::color($s['bodyBg'] ?? null, $d['bodyBg']),
            'contentBg' => self::color($s['contentBg'] ?? null, $d['contentBg']),
            'font' => isset(self::FONTS[$s['font'] ?? '']) ? (string) $s['font'] : $d['font'],
            'textColor' => self::color($s['textColor'] ?? null, $d['textColor']),
            'textSize' => self::int($s['textSize'] ?? null, 12, 22, $d['textSize']),
            'headingColor' => self::color($s['headingColor'] ?? null, ''),
            'linkColor' => self::color($s['linkColor'] ?? null, ''),
            'accent' => self::color($s['accent'] ?? null, ''),
            'padX' => self::int($s['padX'] ?? null, 0, 48, $d['padX']),
        ];

        $blocks = [];
        $ids = [];
        $raw = is_array($doc['blocks'] ?? null) ? array_values($doc['blocks']) : [];
        foreach (array_slice($raw, 0, self::MAX_BLOCKS) as $item) {
            $block = is_array($item) ? self::block($item) : null;
            if ($block === null) {
                continue;
            }
            while (isset($ids[$block['id']])) {
                $block['id'] = self::newId();
            }
            $ids[$block['id']] = true;
            $blocks[] = $block;
        }

        return ['settings' => $settings, 'blocks' => $blocks];
    }

    /** Un document a partir del que hi hagi desat (JSON), o un de buit. */
    public static function decode(?string $json): array
    {
        $data = $json !== null && $json !== '' ? json_decode($json, true) : null;

        return self::clean(is_array($data) ? $data : []);
    }

    public static function encode(array $doc): string
    {
        return (string) json_encode(self::clean($doc), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function newId(): string
    {
        return 'b' . bin2hex(random_bytes(5));
    }

    /** @return array<string,mixed>|null */
    private static function block(array $raw): ?array
    {
        $type = (string) ($raw['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        $d = self::defaults($type);
        $id = preg_match('/^[a-z0-9]{1,24}$/i', (string) ($raw['id'] ?? '')) ? (string) $raw['id'] : self::newId();
        $b = [
            'id' => $id, 'type' => $type,
            'pt' => self::int($raw['pt'] ?? null, 0, 120, $d['pt']),
            'pb' => self::int($raw['pb'] ?? null, 0, 120, $d['pb']),
            'bg' => self::color($raw['bg'] ?? null, ''),
            'hideMobile' => (bool) ($raw['hideMobile'] ?? false),
        ];
        $f = static fn (string $k) => array_key_exists($k, $raw) ? $raw[$k] : ($d[$k] ?? null);

        switch ($type) {
            case 'header':
                $b += ['src' => self::src($f('src')), 'alt' => self::plain($f('alt'), 200), 'width' => self::int($f('width'), 60, 560, 150),
                    'align' => self::align($f('align'), 'left'), 'href' => self::url($f('href')), 'name' => self::plain($f('name'), 120),
                    'borderTop' => self::color($f('borderTop'), '', true), 'borderWidth' => self::int($f('borderWidth'), 0, 10, 4)];
                break;
            case 'heading':
                $level = (int) $f('level') === 2 ? 2 : 1;
                $b += ['text' => self::plain($f('text'), 300), 'level' => $level, 'size' => self::int($f('size'), 12, 40, $level === 2 ? 18 : 22),
                    'color' => self::color($f('color'), ''), 'align' => self::align($f('align'), 'left')];
                break;
            case 'text':
                $b += ['html' => RichText::clean((string) $f('html')), 'size' => self::int($f('size'), 0, 30, 0),
                    'color' => self::color($f('color'), ''), 'align' => self::align($f('align'), 'left')];
                break;
            case 'image':
                $b += ['src' => self::src($f('src')), 'alt' => self::plain($f('alt'), 200), 'width' => self::int($f('width'), 0, 600, 0),
                    'align' => self::align($f('align'), 'center'), 'href' => self::url($f('href')), 'radius' => self::int($f('radius'), 0, 40, 8)];
                break;
            case 'button':
                $b += ['text' => self::plain($f('text'), 80), 'href' => self::url($f('href')), 'btnBg' => self::color($f('btnBg'), ''),
                    'color' => self::color($f('color'), '#ffffff'), 'align' => self::align($f('align'), 'center'), 'full' => (bool) $f('full'),
                    'mobileFull' => (bool) $f('mobileFull'), 'radius' => self::int($f('radius'), 0, 30, 8), 'size' => self::int($f('size'), 12, 24, 15)];
                break;
            case 'link':
                $b += ['text' => self::plain($f('text'), 160), 'href' => self::url($f('href')), 'color' => self::color($f('color'), ''),
                    'bold' => (bool) $f('bold'), 'align' => self::align($f('align'), 'left'), 'size' => self::int($f('size'), 0, 30, 0)];
                break;
            case 'box':
                $b += ['html' => RichText::clean((string) $f('html')), 'boxBg' => self::color($f('boxBg'), '#eef3ec'),
                    'color' => self::color($f('color'), ''), 'radius' => self::int($f('radius'), 0, 30, 10), 'size' => self::int($f('size'), 0, 30, 0)];
                break;
            case 'columns':
                $count = (int) $f('count') === 3 ? 3 : 2;
                $cols = array_values(is_array($raw['cols'] ?? null) ? $raw['cols'] : (array) $d['cols']);
                $b += ['count' => $count, 'cols' => array_map(
                    static fn (int $i): array => self::column(is_array($cols[$i] ?? null) ? $cols[$i] : self::col()),
                    range(0, $count - 1)
                )];
                break;
            case 'imagetext':
                $b += ['side' => $f('side') === 'right' ? 'right' : 'left'] + self::column($raw + self::col());
                break;
            case 'divider':
                $b += ['color' => self::color($f('color'), '#dde4d9'), 'thickness' => self::int($f('thickness'), 1, 8, 1),
                    'widthPct' => self::int($f('widthPct'), 10, 100, 100)];
                break;
            case 'spacer':
                $b += ['height' => self::int($f('height'), 4, 160, 24)];
                break;
            case 'race':
                $b += [
                    'refId' => preg_match('/^[0-9]{1,12}$/', (string) $f('refId')) ? (string) $f('refId') : '',
                    'title' => self::plain($f('title'), 200), 'when' => self::plain($f('when'), 120),
                    'town' => self::plain($f('town'), 120), 'desc' => self::plain($f('desc'), 1000, true),
                    'src' => self::src($f('src')), 'btnText' => self::plain($f('btnText'), 80), 'href' => self::url($f('href')),
                    'boxBg' => self::color($f('boxBg'), '#f4f6f3'),
                ];
                break;
            case 'news':
                $b += [
                    'kicker' => self::plain($f('kicker'), 40), 'title' => self::plain($f('title'), 200),
                    'desc' => self::plain($f('desc'), 1500, true), 'src' => self::src($f('src')),
                    'btnText' => self::plain($f('btnText'), 80), 'href' => self::url($f('href')),
                    'boxBg' => self::color($f('boxBg'), '#f4f6f3'),
                ];
                break;
            case 'footer':
                $links = [];
                $source = is_array($raw['links'] ?? null) ? array_values($raw['links']) : (array) $d['links'];
                foreach (array_slice($source, 0, 6) as $l) {
                    $text = self::plain(is_array($l) ? ($l['text'] ?? '') : '', 60);
                    if ($text !== '') {
                        $links[] = ['text' => $text, 'href' => self::url(is_array($l) ? ($l['href'] ?? '') : '')];
                    }
                }
                $b += ['src' => self::src($f('src')), 'alt' => self::plain($f('alt'), 200), 'width' => self::int($f('width'), 0, 400, 0),
                    'name' => self::plain($f('name'), 200), 'links' => $links, 'note' => self::plain($f('note'), 1500, true),
                    'lineColor' => self::color($f('lineColor'), '', true), 'color' => self::color($f('color'), '#7a857d'),
                    'linkColor' => self::color($f('linkColor'), '')];
                break;
        }

        return $b;
    }

    private static function column(array $c): array
    {
        $d = self::col();

        return [
            'src' => self::src($c['src'] ?? ''),
            'alt' => self::plain($c['alt'] ?? '', 200),
            'title' => self::plain($c['title'] ?? $d['title'], 200),
            'html' => RichText::clean((string) ($c['html'] ?? '')),
            'linkText' => self::plain($c['linkText'] ?? '', 80),
            'href' => self::url($c['href'] ?? ''),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * Un color #rrggbb, o el valor per defecte. Amb $brand també s'admet
     * «brand», que vol dir el color principal del domini que envia.
     */
    public static function color(mixed $v, string $default, bool $brand = false): string
    {
        $v = strtolower(trim((string) (is_scalar($v) ? $v : '')));
        if ($brand && $v === 'brand') {
            return 'brand';
        }
        if (preg_match('/^#[0-9a-f]{3}$/', $v)) {
            $v = '#' . $v[1] . $v[1] . $v[2] . $v[2] . $v[3] . $v[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : $default;
    }

    public static function int(mixed $v, int $min, int $max, int $default): int
    {
        if (!is_numeric($v)) {
            return $default;
        }

        return max($min, min($max, (int) round((float) $v)));
    }

    private static function align(mixed $v, string $default): string
    {
        return in_array($v, ['left', 'center', 'right'], true) ? (string) $v : $default;
    }

    /** Text sense format (els salts de línia només si $lines). */
    public static function plain(mixed $v, int $max, bool $lines = false): string
    {
        $v = (string) (is_scalar($v) ? $v : '');
        $v = str_replace("\r", '', $v);
        $v = $lines ? (string) preg_replace('/[^\P{C}\n]/u', '', $v) : (string) preg_replace('/\p{C}+/u', ' ', $v);

        return mb_substr(trim($v), 0, $max);
    }

    /** Enllaç acceptat: http(s), correu o telèfon (també amb camps com {{web}}). La resta, buit. */
    public static function url(mixed $v): string
    {
        $v = trim((string) (is_scalar($v) ? $v : ''));
        if ($v === '' || mb_strlen($v) > 1000) {
            return '';
        }
        if (preg_match('/^www\./i', $v)) {
            $v = 'https://' . $v;
        }

        return preg_match('#^(https?://[^\s<>"]+|mailto:[^\s<>"]+|tel:[0-9+ ()-]+)$#i', $v) ? $v : '';
    }

    /** Imatge: pujada a l'editor (img:codi), el logotip del domini (brand:logo) o una adreça https. */
    public static function src(mixed $v): string
    {
        $v = trim((string) (is_scalar($v) ? $v : ''));
        if ($v === 'brand:logo' || preg_match('/^img:[A-Za-z0-9]{40}$/', $v)) {
            return $v;
        }

        return preg_match('#^https://[^\s<>"\']+$#i', $v) && mb_strlen($v) <= 1000 ? $v : '';
    }

    /** Totes les imatges pujades que fa servir un document (img:…). @return array<int,string> */
    public static function imageRefs(array $doc): array
    {
        $refs = [];
        $walk = static function (array $item) use (&$walk, &$refs): void {
            foreach ($item as $k => $v) {
                if (is_array($v)) {
                    $walk($v);
                } elseif ($k === 'src' && is_string($v) && str_starts_with($v, 'img:')) {
                    $refs[$v] = true;
                }
            }
        };
        $walk($doc['blocks'] ?? []);

        return array_keys($refs);
    }
}
