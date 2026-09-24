<?php
/**
 * Funcions d'ajuda globals.
 */
declare(strict_types=1);

use Cros\Core\Settings;

/** Ruta del fitxer de configuració (la variable d'entorn CROS_CONFIG només s'usa a les proves). */
function config_path(): string
{
    $override = getenv('CROS_CONFIG');
    return ($override !== false && $override !== '' && is_file($override)) ? $override : CROS_APP . '/config.php';
}

/** Retorna un valor de la configuració (app/config.php) amb notació de punt. */
function config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = is_file(config_path()) ? require config_path() : [];
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Indica si l'aplicació ja està instal·lada. */
function is_installed(): bool
{
    return is_file(config_path()) && (string) config('db.name', '') !== '';
}

/** URL base del lloc, sense barra final. */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) config('base_url', ''));
    if ($configured !== '') {
        return $base = rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $dir = rtrim($dir, '/');
    return $base = ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/** Construeix una URL interna respectant la configuració d'URLs amigables. */
function url(string $path = '', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    if ($path !== '/' && !pretty_urls()) {
        $path = '/index.php' . $path;
    }
    $url = base_url() . ($path === '/' ? '/' : $path);
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
    return $url;
}

/** URLs amigables actives? */
function pretty_urls(): bool
{
    static $pretty = null;
    if ($pretty === null) {
        $pretty = is_installed() ? (bool) setting('pretty_urls', '1') : true;
    }
    return $pretty;
}

/** URL d'un fitxer d'assets amb "cache busting" per versió. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $version = app_version();
    return base_url() . '/assets/' . $path . '?v=' . substr(md5($version), 0, 8);
}

/** URL pública d'un fitxer pujat. */
function upload_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return base_url() . '/uploads/' . ltrim($path, '/');
}

/** Ruta absoluta d'un fitxer pujat. */
function upload_path(?string $path = ''): string
{
    return CROS_UPLOADS . '/' . ltrim((string) $path, '/');
}

/** Ruta absoluta dins la carpeta de treball de la instal·lació (registres, còpies, temporals). */
function storage_path(string $path = ''): string
{
    $path = ltrim($path, '/');

    return $path === '' ? CROS_STORAGE : CROS_STORAGE . '/' . $path;
}

/** Versió actual de l'aplicació. */
function app_version(): string
{
    static $v = null;
    if ($v === null) {
        $data = require CROS_APP . '/version.php';
        $v = (string) ($data['version'] ?? '0.0.0');
    }
    return $v;
}

/** Escapa text per a HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escapa i converteix salts de línia en <br>. */
function nl($value): string
{
    return nl2br(e($value));
}

/** Retorna un valor de configuració del lloc (taula settings). */
function setting(string $key, $default = null)
{
    return Settings::get($key, $default);
}

/** Text de configuració pensat per mostrar-se com a HTML simple. */
function setting_html(string $key, string $default = ''): string
{
    $value = (string) Settings::get($key, $default);
    return \Cros\Core\Html::clean($value);
}

/**
 * Text legal amb les dades de l'entitat posades al seu lloc.
 *
 * Als textos de l'avís legal i la privacitat s'hi poden escriure marcadors com
 * ara {{entitat}} o {{correu}}: així, quan canvien les dades de contacte, els
 * textos no es queden antics.
 */
function legal_html(string $key): string
{
    $email = trim((string) setting('contact_email', ''));
    $replacements = [
        '{{entitat}}' => e((string) setting('legal_entity', '')),
        '{{cursa}}' => e((string) setting('site_name', '')),
        '{{poble}}' => e((string) setting('event_town', '')),
        '{{telefon}}' => e((string) setting('contact_phone', '')),
        '{{web}}' => e((string) (parse_url(base_url(), PHP_URL_HOST) ?: '')),
        '{{correu}}' => $email === '' ? '' : '<a href="mailto:' . e($email) . '">' . e($email) . '</a>',
    ];

    return strtr(setting_html($key), $replacements);
}

/** Redirecció HTTP i aturada de l'execució. */
function redirect(string $path, int $status = 302): void
{
    $location = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $location, true, $status);
    exit;
}

/** Token CSRF de la sessió actual. */
function csrf_token(): string
{
    return \Cros\Core\Csrf::token();
}

/** Camp ocult amb el token CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Desa o recupera missatges flash. */
function flash(?string $type = null, ?string $message = null)
{
    if ($type !== null && $message !== null) {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
        return null;
    }
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** Desa els valors d'un formulari per repoblar-lo després d'un error. */
function set_old(array $data): void
{
    unset($data['_token'], $data['password'], $data['password_confirm']);
    $_SESSION['_old'] = $data;
}

/** Recupera un valor antic d'un formulari. */
function old(string $key, $default = '')
{
    $old = $_SESSION['_old'] ?? [];
    return $old[$key] ?? $default;
}

/** Neteja els valors antics (es crida en renderitzar la vista). */
function clear_old(): void
{
    unset($_SESSION['_old']);
}

/** Formata un import en cèntims com a moneda. */
function money(int $cents, string $currency = 'EUR'): string
{
    $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
    $symbol = $symbols[strtoupper($currency)] ?? strtoupper($currency);
    return number_format($cents / 100, 2, ',', '.') . ' ' . $symbol;
}

/** Converteix un import introduït per l'usuari (ex. "3,50") a cèntims. */
function to_cents($amount): int
{
    $normalized = str_replace([' ', '€'], '', (string) $amount);
    $normalized = str_replace(',', '.', $normalized);
    return (int) round(((float) $normalized) * 100);
}

/** Noms dels mesos en català. */
function ca_months(): array
{
    return [1 => 'gener', 'febrer', 'març', 'abril', 'maig', 'juny', 'juliol',
        'agost', 'setembre', 'octubre', 'novembre', 'desembre'];
}

/** Noms dels dies de la setmana en català. */
function ca_weekdays(): array
{
    return ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];
}

/** Formata una data en català: 4 d'octubre de 2026. */
function ca_date(?string $date, bool $withWeekday = false, bool $withYear = true): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $day = (int) date('j', $ts);
    $month = ca_months()[(int) date('n', $ts)];
    $preposition = in_array(strtolower(substr($month, 0, 1)), ['a', 'e', 'i', 'o', 'u'], true) ? "d'" : 'de ';
    $text = $day . ' ' . $preposition . $month;
    if ($withYear) {
        $text .= ' de ' . date('Y', $ts);
    }
    if ($withWeekday) {
        $text = ca_weekdays()[(int) date('w', $ts)] . ', ' . $text;
    }
    return $text;
}

/** Formata data i hora curta: 04/10/2026 09:30 */
function dt(?string $value, string $format = 'd/m/Y H:i'): string
{
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '';
}

/** Genera un "slug" a partir d'un text. */
function slugify(string $text): string
{
    $text = strtr($text, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', 'l·l' => 'll',
        'À' => 'a', 'Á' => 'a', 'È' => 'e', 'É' => 'e', 'Í' => 'i', 'Ò' => 'o', 'Ó' => 'o', 'Ú' => 'u', 'Ç' => 'c',
    ]);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-') ?: 'element';
}

/** Cadena aleatòria segura (hexadecimal). */
function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/** Codi curt llegible (sense caràcters ambigus). */
function random_code(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

/** Retalla un text respectant paraules. */
function excerpt(?string $text, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length - 1) . '…';
}

/** Adreça IP del client. */
function client_ip(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded) {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** La petició actual és POST? */
function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Valor d'entrada net (trim) de GET/POST. */
function input(string $key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

/** Valor booleà d'un camp de formulari. */
function input_bool(string $key): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return in_array((string) $value, ['1', 'on', 'true', 'yes', 'si', 'sí'], true) ? 1 : 0;
}

/** Escriu una línia al registre de l'aplicació. */
function log_line(string $channel, string $message, array $context = []): void
{
    $dir = storage_path('logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "[%s] %s: %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($channel),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** Atura la petició amb un codi d'error. */
function abort(int $status = 404, string $message = ''): void
{
    throw new \Cros\Core\HttpException($status, $message);
}

/** Resposta JSON. */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Comparació seca de cadenes (per a tokens). */
function token_equals(?string $a, ?string $b): bool
{
    return is_string($a) && is_string($b) && hash_equals($a, $b);
}
