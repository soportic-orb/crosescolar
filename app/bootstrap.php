<?php
/**
 * Arrencada de l'aplicació: constants, autocàrrega, sessió i gestió d'errors.
 */
declare(strict_types=1);

// L'arrencada només s'executa una vegada encara que s'inclogui diverses vegades.
if (defined('CROS_BOOTED')) {
    return;
}

define('CROS_START', microtime(true));
define('CROS_ROOT', dirname(__DIR__));
define('CROS_APP', __DIR__);
define('CROS_BOOTED', true);

require __DIR__ . '/core/functions.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Cros\\')) {
        return;
    }
    $parts = explode('\\', substr($class, strlen('Cros\\')));
    $className = array_pop($parts);
    $directory = implode('/', array_map('strtolower', $parts));
    $file = CROS_APP . '/' . ($directory !== '' ? $directory . '/' : '') . $className . '.php';
    if (is_file($file)) {
        require $file;
    }
});

date_default_timezone_set((string) config('timezone', 'Europe/Madrid'));
mb_internal_encoding('UTF-8');
setlocale(LC_TIME, 'ca_ES.UTF-8', 'ca_ES', 'catalan');

$debug = (bool) config('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Sessió amb galetes segures
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    $https = str_starts_with(base_url(), 'https://');
    session_name('cros_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

set_exception_handler(static function (\Throwable $e): void {
    \Cros\Core\ErrorHandler::handle($e);
});

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0) use ($debug): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    // Els avisos menors es registren però no aturen la petició en producció.
    $minor = E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_USER_NOTICE;
    if (($severity & $minor) || !$debug) {
        log_line('php', $message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
        return true;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        log_line('fatal', $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
    }
});
