<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

use Cros\Core\Settings;

/**
 * La IA de l'editor d'enviaments: textos (Claude o OpenAI) i imatges (OpenAI).
 *
 * És la del Mailing del Tauler de Gestió, amb les claus als ajustos del panell
 * (Configuració → Intel·ligència artificial, xifrades) i el que se li explica
 * del correu adaptat a la plataforma: qui escriu és el servei, i a qui escriu
 * són les escoles, les AFA, els clubs i qui organitza curses.
 *
 * Les crides van per HTTP directe (curl), com la resta de serveis de fora:
 * l'aplicació no fa servir cap biblioteca externa.
 */
final class Ai
{
    public const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    public const OPENAI_URL = 'https://api.openai.com/v1';

    public const FORMATS = [
        'paragraphs' => 'Paràgrafs',
        'list' => 'Llista',
        'heading' => 'Títol',
        'button' => 'Text de botó',
        'subject' => 'Assumpte i resum',
    ];

    public const TONES = [
        'proper' => 'Proper',
        'formal' => 'Formal',
        'entusiasta' => 'Entusiasta',
        'urgent' => 'Urgent i clar',
    ];

    public const LENGTHS = ['curt' => 'Curt', 'mitja' => 'Mitjà', 'llarg' => 'Llarg'];

    public const SHAPES = [
        'landscape' => ['label' => 'Horitzontal', 'size' => '1536x1024'],
        'square' => ['label' => 'Quadrada', 'size' => '1024x1024'],
        'portrait' => ['label' => 'Vertical', 'size' => '1024x1536'],
    ];

    public const STYLES = [
        'photo' => ['label' => 'Fotografia', 'prompt' => 'Fotografia realista, llum natural, composició neta.'],
        'illustration' => ['label' => 'Il·lustració', 'prompt' => 'Il·lustració editorial moderna, colors suaus, amb tocs del color %s.'],
        'flat' => ['label' => 'Dibuix pla', 'prompt' => 'Il·lustració plana i minimalista, formes simples, fons clar, colors %s i gris.'],
    ];

    /** Imatges de referència com a molt, i mida màxima de cadascuna. */
    public const MAX_REFS = 4;

    public const REF_MAX_BYTES = 10 * 1024 * 1024;

    private const REF_MAX_SIDE = 2048;

    /**
     * Per a les proves: fa la crida en comptes del servidor de debò.
     * Rep (mètode, adreça, capçaleres, cos) i torna [codi, cos de la resposta].
     *
     * @var (\Closure(string,string,array<int,string>,string):array{0:int,1:string})|null
     */
    public static ?\Closure $fake = null;

    // ------------------------------------------------------------------ configuració

    public static function enabled(): bool
    {
        return Settings::bool('ai_enabled', true);
    }

    public static function claudeReady(): bool
    {
        return self::key('ai_anthropic_key') !== '';
    }

    public static function openaiReady(): bool
    {
        return self::key('ai_openai_key') !== '';
    }

    /** @return array<string,string> els serveis de text que es poden fer servir */
    public static function textProviders(): array
    {
        if (!self::enabled()) {
            return [];
        }
        $out = [];
        if (self::claudeReady()) {
            $out['claude'] = 'Claude (Anthropic)';
        }
        if (self::openaiReady()) {
            $out['openai'] = 'OpenAI';
        }

        return $out;
    }

    /** Imatges: només OpenAI (Claude no en fa). */
    public static function imageReady(): bool
    {
        return self::enabled() && self::openaiReady();
    }

    public static function defaultTextProvider(): ?string
    {
        $providers = self::textProviders();
        $want = (string) Settings::get('ai_default_text', 'claude');

        return isset($providers[$want]) ? $want : (array_key_first($providers) ?: null);
    }

    public static function claudeModel(): string
    {
        return trim((string) Settings::get('ai_claude_model', '')) ?: 'claude-opus-5-5';
    }

    public static function openaiTextModel(): string
    {
        return trim((string) Settings::get('ai_openai_text_model', '')) ?: 'gpt-5';
    }

    public static function openaiImageModel(): string
    {
        return trim((string) Settings::get('ai_openai_image_model', '')) ?: 'gpt-image-1';
    }

    private static function key(string $name): string
    {
        return trim((string) Settings::get($name, ''));
    }

    // ------------------------------------------------------------------ text

    /**
     * Escriu un text per al correu.
     *
     * @param array{format?:string,tone?:string,length?:string,context?:string,current?:string,title?:string,brand?:array<string,string>} $opts
     * @return array{html:string,text:string,preheader:string,provider:string}
     */
    public static function text(string $provider, string $prompt, array $opts = []): array
    {
        if (!isset(self::textProviders()[$provider])) {
            throw new \RuntimeException('Aquest servei d\'IA no està configurat: poseu-ne la clau a Configuració → Intel·ligència artificial.');
        }
        $prompt = Doc::plain($prompt, 4000, true);
        if ($prompt === '') {
            throw new \RuntimeException('Escriviu què voleu que escrigui la IA.');
        }
        $format = isset(self::FORMATS[$opts['format'] ?? '']) ? (string) $opts['format'] : 'paragraphs';
        $system = self::system($format, $opts);
        $message = self::userMessage($prompt, $opts);
        $max = in_array($format, ['heading', 'button', 'subject'], true) ? 600 : 3000;

        $raw = $provider === 'claude' ? self::claude($system, $message, $max) : self::openaiText($system, $message, $max);

        return self::shape($raw, $format) + ['provider' => $provider];
    }

    /** Les instruccions: qui escriu, a qui, i en quin format. */
    public static function system(string $format, array $opts = []): string
    {
        $brand = (array) ($opts['brand'] ?? []);
        $name = (string) ($brand['name'] ?? 'EsportWeb');
        $domain = (string) ($brand['domain'] ?? '');
        $tone = self::TONES[$opts['tone'] ?? ''] ?? 'Proper';
        $length = [
            'curt' => 'Breu: un paràgraf curt o tres punts com a molt.',
            'llarg' => 'Desenvolupat: entre quatre i sis paràgrafs.',
        ][$opts['length'] ?? ''] ?? 'Mitjà: dos o tres paràgrafs.';

        $base = "Redactes els correus electrònics de {$name}" . ($domain !== '' ? " ({$domain})" : '') . ', un servei en línia perquè escoles, AFA, '
            . 'clubs, ajuntaments i organitzadors facin el web de la seva cursa (crosos escolars, curses populars, trails): inscripcions, '
            . 'dorsals, resultats i informació per a les famílies i els corredors. Els correus s\'adrecen als clients del servei: les '
            . 'persones que organitzen les curses i administren el seu web. '
            . "Escriu en català correcte, clar i natural (mai en castellà ni en anglès, encara que la petició ho estigui). To: {$tone}. "
            . 'Tracta els lectors de vós (vosaltres), com la resta de textos del servei. '
            . 'No t\'inventis dades concretes que no surtin a la petició o al context (dates, hores, preus, xifres, noms de persones o de curses): '
            . 'si en falta alguna, posa-la entre claudàtors, com [data] o [preu]. '
            . 'Pots fer servir aquests camps, que s\'omplen sols per a cada destinatari: {{nom}} (el nom de la persona) i {{entitat}} (la seva escola, entitat o web). '
            . 'El correu ja té capçalera, logotip, peu i l\'enllaç per donar-se de baixa: no hi afegeixis signatures, adreces ni dades de contacte inventades. '
            . 'Respon només amb el contingut demanat, sense explicacions ni comentaris sobre el que has fet.';

        return $base . ' ' . match ($format) {
            'list' => 'Format: una llista HTML (<ul> o <ol> amb <li>), si cal amb un paràgraf <p> d\'introducció. Pots fer servir <strong> per destacar. Cap altra etiqueta. ' . $length,
            'heading' => 'Format: un sol títol, en text pla, de 12 paraules com a molt, sense cometes ni punt final.',
            'button' => 'Format: el text d\'un botó, en text pla, de 2 a 5 paraules, començant amb un verb en imperatiu, sense cometes.',
            'subject' => 'Format: dues línies de text pla. Primera línia: l\'assumpte del correu (10 paraules com a molt, sense cometes). Segona línia: un resum d\'una frase (15 paraules com a molt) que es veurà a la safata d\'entrada.',
            default => 'Format: HTML amb paràgrafs <p>. Pots fer servir <strong>, <em>, <ul>/<ol> amb <li> i enllaços <a href="https://…"> si la petició en dona l\'adreça. Cap altra etiqueta, ni títols, ni estils. ' . $length,
        };
    }

    private static function userMessage(string $prompt, array $opts): string
    {
        $out = '';
        $title = Doc::plain($opts['title'] ?? '', 255);
        if ($title !== '') {
            $out .= "Assumpte del correu: {$title}\n\n";
        }
        $context = Doc::plain($opts['context'] ?? '', 8000, true);
        if ($context !== '') {
            $out .= "<context>\nAixò és el que ja diu el correu (fes-ho servir per mantenir la coherència; no ho repeteixis):\n{$context}\n</context>\n\n";
        }
        $current = Doc::plain($opts['current'] ?? '', 8000, true);

        return $out . ($current !== ''
            ? "<text_actual>\n{$current}\n</text_actual>\n\nReescriu el text actual seguint aquesta petició: {$prompt}"
            : "Petició: {$prompt}");
    }

    /** @return array{html:string,text:string,preheader:string} */
    public static function shape(string $raw, string $format): array
    {
        $raw = trim((string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($raw)));
        if ($raw === '') {
            throw new \RuntimeException('La IA no ha tornat cap text. Torneu-ho a provar.');
        }
        if (in_array($format, ['heading', 'button', 'subject'], true)) {
            // Primer fora l'etiqueta («Assumpte:») i després les cometes, que
            // la IA sovint hi posa: «Assumpte: «Inscripcions obertes»».
            $lines = array_values(array_filter(array_map(
                static fn (string $l): string => trim((string) preg_replace('/^\s*\**\s*(Assumpte|Resum|Títol|Botó)\s*:\s*\**\s*/iu', '', strip_tags($l)), " \t\"'«»“”*#"),
                preg_split('/\R/', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: []
            ), static fn (string $l): bool => $l !== ''));

            return [
                'html' => '',
                'text' => Doc::plain($lines[0] ?? '', $format === 'button' ? 80 : 255),
                'preheader' => $format === 'subject' ? Doc::plain($lines[1] ?? '', 255) : '',
            ];
        }
        // Text sense etiquetes: cada bloc de línies és un paràgraf.
        if (!preg_match('/<(p|ul|ol|li|br)\b/i', $raw)) {
            $raw = implode('', array_map(
                static fn (string $p): string => '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8'), false) . '</p>',
                preg_split('/\R{2,}/', $raw) ?: []
            ));
        }
        $html = RichText::clean($raw);
        if ($html === '') {
            throw new \RuntimeException('La IA no ha tornat cap text aprofitable. Torneu-ho a provar.');
        }

        return ['html' => $html, 'text' => RichText::text($html), 'preheader' => ''];
    }

    private static function claude(string $system, string $prompt, int $max): string
    {
        $model = self::claudeModel();
        $body = ['model' => $model, 'max_tokens' => $max, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $prompt]]];
        $headers = ['x-api-key: ' . self::key('ai_anthropic_key'), 'anthropic-version: 2023-06-01', 'content-type: application/json'];
        // Redacció rutinària: esforç baix (als models que ho admeten).
        if (preg_match('/^claude-(opus-(4-[5-9]|5)|sonnet-(4-6|5)|fable|haiku-5)/', $model)) {
            $body['output_config'] = ['effort' => 'low'];
        }
        // Si els filtres de seguretat la rebutgen, el servidor la torna a provar amb un altre model.
        if (preg_match('/^claude-(opus-5|sonnet-5-5|fable-5-1)/', $model)) {
            $body['fallbacks'] = 'default';
            $headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
        }
        [$status, $data] = self::json('POST', self::ANTHROPIC_URL, $headers, $body, 120);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Claude no ha respost: ' . self::errorOf($data, $status) . '.');
        }
        if (($data['stop_reason'] ?? '') === 'refusal') {
            throw new \RuntimeException('Claude no ha volgut escriure aquest text. Proveu de dir-ho d\'una altra manera.');
        }
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return $text;
    }

    /** OpenAI (API Responses). */
    private static function openaiText(string $system, string $prompt, int $max): string
    {
        $model = self::openaiTextModel();
        $body = ['model' => $model, 'instructions' => $system, 'input' => $prompt, 'max_output_tokens' => $max + 2000];
        if (preg_match('/^(gpt-5|o\d)/', $model)) {
            $body['reasoning'] = ['effort' => 'low'];
        }
        [$status, $data] = self::json('POST', self::OPENAI_URL . '/responses', self::openaiHeaders(true), $body, 120);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('OpenAI no ha respost: ' . self::errorOf($data, $status) . '.');
        }
        $text = (string) ($data['output_text'] ?? '');
        if ($text === '') {
            foreach ((array) ($data['output'] ?? []) as $item) {
                foreach ((array) ($item['content'] ?? []) as $c) {
                    if (($c['type'] ?? '') === 'output_text') {
                        $text .= (string) ($c['text'] ?? '');
                    } elseif (($c['type'] ?? '') === 'refusal') {
                        throw new \RuntimeException('OpenAI no ha volgut escriure aquest text. Proveu de dir-ho d\'una altra manera.');
                    }
                }
            }
        }

        return $text;
    }

    // ------------------------------------------------------------------ imatges

    /**
     * Crea una imatge i la desa amb les de l'editor. Amb imatges de referència
     * (fotos, captures, logotips) es fa servir l'edició d'imatges d'OpenAI,
     * que les té en compte segons el que digui la descripció.
     *
     * @param array<int,array{data:string,name:string,mime:string}> $refs vegeu prepareRef()
     * @param array<string,string> $brand
     * @return array{src:string,token:string,width:int,height:int}
     */
    public static function image(string $prompt, string $shape, string $style, bool $noText, array $refs = [], bool $keepDetails = false, array $brand = [], ?int $userId = null): array
    {
        if (!self::imageReady()) {
            throw new \RuntimeException('Per crear imatges cal la clau d\'OpenAI (Claude no en fa): poseu-la a Configuració → Intel·ligència artificial.');
        }
        $prompt = Doc::plain($prompt, 3000, true);
        if ($prompt === '') {
            throw new \RuntimeException('Descriviu la imatge que voleu.');
        }
        $refs = array_slice($refs, 0, self::MAX_REFS);
        $size = (self::SHAPES[$shape] ?? self::SHAPES['landscape'])['size'];
        $color = (string) ($brand['primary'] ?? '#2f6b3c');
        $full = $prompt . "\n\n" . sprintf((self::STYLES[$style] ?? self::STYLES['photo'])['prompt'], $color)
            . ' Imatge per a un correu d\'un servei de webs per a curses escolars i populars: alegre, esportiva i respectuosa; si hi surten infants, de lluny o d\'esquena, sense cares reconeixibles.'
            . ($refs ? ' Fes servir les imatges adjuntes com a referència, tal com indica la descripció.' : '')
            . ($noText ? ' Sense cap text, lletra, logotip ni marca a la imatge.' : '');

        $model = self::openaiImageModel();
        if ($refs) {
            // Les imatges de referència només les admeten els models gpt-image.
            if (!str_starts_with($model, 'gpt-image')) {
                throw new \RuntimeException('Per fer servir imatges de referència cal un model «gpt-image» (ara és «' . $model . '»).');
            }
            $fields = ['model' => $model, 'prompt' => $full, 'n' => '1', 'size' => $size, 'quality' => 'medium', 'output_format' => 'jpeg'];
            if ($keepDetails) {
                $fields['input_fidelity'] = 'high';
            }
            // Totes les referències van amb el mateix nom de camp (image[]), i
            // això el curl de PHP no ho sap fer amb una llista: el cos es fa a mà.
            $boundary = 'ai' . bin2hex(random_bytes(12));
            $multipart = '';
            foreach ($fields as $name => $value) {
                $multipart .= '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n" . $value . "\r\n";
            }
            foreach ($refs as $i => $ref) {
                $file = str_replace(['"', "\r", "\n"], '', $ref['name'] ?: 'referencia-' . ($i + 1) . '.png');
                $multipart .= '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="image[]"; filename="' . $file . "\"\r\n"
                    . 'Content-Type: ' . $ref['mime'] . "\r\n\r\n" . $ref['data'] . "\r\n";
            }
            $multipart .= '--' . $boundary . "--\r\n";
            [$status, $data] = self::request('POST', self::OPENAI_URL . '/images/edits',
                array_merge(self::openaiHeaders(false), ['content-type: multipart/form-data; boundary=' . $boundary]), $multipart, 240);
        } else {
            $body = ['model' => $model, 'prompt' => $full, 'n' => 1];
            if (str_starts_with($model, 'gpt-image')) {
                $body += ['size' => $size, 'quality' => 'medium', 'output_format' => 'jpeg'];
            } else {
                // dall-e-3: mides pròpies i resposta en base64.
                $body += ['size' => ['landscape' => '1792x1024', 'portrait' => '1024x1792'][$shape] ?? '1024x1024', 'response_format' => 'b64_json'];
            }
            [$status, $data] = self::json('POST', self::OPENAI_URL . '/images/generations', self::openaiHeaders(true), $body, 240);
        }
        if ($status < 200 || $status >= 300) {
            $msg = self::errorOf($data, $status);
            throw new \RuntimeException(str_contains(strtolower($msg), 'safety') || (($data['error']['code'] ?? '') === 'moderation_blocked')
                ? 'OpenAI no ha volgut crear aquesta imatge. Proveu de descriure-la d\'una altra manera' . ($refs ? ' o amb altres imatges de referència' : '') . '.'
                : 'OpenAI no ha creat la imatge: ' . $msg . '.');
        }
        $b64 = (string) ($data['data'][0]['b64_json'] ?? '');
        $bytes = $b64 !== '' ? base64_decode($b64, true) : false;
        if (!$bytes) {
            throw new \RuntimeException('OpenAI no ha tornat cap imatge. Torneu-ho a provar.');
        }

        return Images::storeData($bytes, 'imatge-ia.jpg', $userId);
    }

    /**
     * Comprova i prepara una imatge de referència: JPG, PNG, WebP o GIF; les
     * GIF passen a PNG i les molt grans es redueixen (2.048 px de costat).
     *
     * @return array{data:string,name:string,mime:string}
     */
    public static function prepareRef(string $data, string $name): array
    {
        if ($data === '' || strlen($data) > self::REF_MAX_BYTES) {
            throw new \RuntimeException('«' . $name . '» és massa gran (' . intdiv(self::REF_MAX_BYTES, 1048576) . ' MB com a molt).');
        }
        $info = @getimagesizefromstring($data);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];
        if (!$info || !isset($types[$info[2]])) {
            throw new \RuntimeException('«' . $name . '» no és una imatge JPG, PNG, WebP o GIF.');
        }
        [$w, $h] = $info;
        $mime = $types[$info[2]];
        $base = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) pathinfo($name, PATHINFO_FILENAME)));
        $base = trim($base, '-') ?: 'referencia';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'png';

        if (($mime === 'image/gif' || max($w, $h) > self::REF_MAX_SIDE) && function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($data);
            if (!$src) {
                throw new \RuntimeException('No s\'ha pogut llegir «' . $name . '».');
            }
            $k = min(1, self::REF_MAX_SIDE / max($w, $h));
            $nw = max(1, (int) round($w * $k));
            $nh = max(1, (int) round($h * $k));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 255, 255, 255, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, (int) $w, (int) $h);
            ob_start();
            imagepng($dst, null, 6);
            $data = (string) ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            [$mime, $ext] = ['image/png', 'png'];
        } elseif ($mime === 'image/gif') {
            throw new \RuntimeException('Les GIF no es poden fer servir de referència en aquest servidor: deseu-la com a PNG o JPG.');
        }

        return ['data' => $data, 'name' => $base . '.' . $ext, 'mime' => $mime];
    }

    // ------------------------------------------------------------------ prova (des de la configuració)

    /** @return array<string,array{ok:bool,message:string}> */
    public static function test(): array
    {
        $out = [];
        if (!self::enabled()) {
            return ['ai' => ['ok' => false, 'message' => 'L\'assistent d\'IA està desactivat.']];
        }
        if (self::claudeReady()) {
            try {
                $r = self::text('claude', 'Escriu només la paraula «Correcte».', ['format' => 'heading']);
                $out['claude'] = ['ok' => true, 'message' => 'Claude (' . self::claudeModel() . ') respon: «' . $r['text'] . '».'];
            } catch (\Throwable $e) {
                $out['claude'] = ['ok' => false, 'message' => $e->getMessage()];
            }
        }
        if (self::openaiReady()) {
            try {
                $r = self::text('openai', 'Escriu només la paraula «Correcte».', ['format' => 'heading']);
                $out['openai'] = ['ok' => true, 'message' => 'OpenAI (' . self::openaiTextModel() . ') respon: «' . $r['text'] . '». Les imatges, amb ' . self::openaiImageModel() . '.'];
            } catch (\Throwable $e) {
                $out['openai'] = ['ok' => false, 'message' => $e->getMessage()];
            }
        }
        if (!$out) {
            $out['ai'] = ['ok' => false, 'message' => 'No hi ha cap clau desada: poseu la de Claude, la d\'OpenAI o totes dues.'];
        }

        return $out;
    }

    // ------------------------------------------------------------------ HTTP

    /** @return array<int,string> */
    private static function openaiHeaders(bool $json): array
    {
        return array_merge(['Authorization: Bearer ' . self::key('ai_openai_key')], $json ? ['content-type: application/json'] : []);
    }

    /**
     * Una crida amb cos JSON que torna JSON.
     *
     * @param array<int,string> $headers
     * @return array{0:int,1:array<string,mixed>}
     */
    private static function json(string $method, string $url, array $headers, array $body, int $timeout): array
    {
        return self::request($method, $url, $headers, (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $timeout);
    }

    /**
     * @param array<int,string> $headers
     * @return array{0:int,1:array<string,mixed>}
     */
    private static function request(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        if (self::$fake !== null) {
            [$status, $raw] = (self::$fake)($method, $url, $headers, $body);
        } else {
            if (!function_exists('curl_init')) {
                throw new \RuntimeException('Aquest servidor no té l\'extensió curl de PHP, que cal per parlar amb el servei d\'IA.');
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: application/json']),
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new \RuntimeException('No s\'ha pogut connectar amb el servei d\'IA: ' . $error);
            }
        }
        $data = json_decode((string) $raw, true);

        return [(int) $status, is_array($data) ? $data : []];
    }

    private static function errorOf(array $data, int $status): string
    {
        $message = $data['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : 'error ' . $status;
    }
}
