<?php
/** Encaminador per al servidor integrat de PHP durant les proves. */
declare(strict_types=1);

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$root = dirname(__DIR__);

if (preg_match('#^/(assets|uploads)/#', $path) && is_file($root . $path)) {
    return false;
}

require_once __DIR__ . '/env.php';

if ($path === '/install.php') {
    require $root . '/install.php';
    return true;
}

require $root . '/index.php';
