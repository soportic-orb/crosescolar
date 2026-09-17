<?php
/**
 * Cros Escolar La Granada — controlador frontal.
 * Totes les peticions passen per aquí (vegeu .htaccess o la configuració d'nginx).
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Cros\Core\Router;
use Cros\Core\Settings;
use Cros\Core\Updater;
use Cros\Core\View;

// Instal·lació pendent
if (!is_installed()) {
    if (is_file(__DIR__ . '/install.php')) {
        header('Location: ' . base_url() . '/install.php');
        exit;
    }
    http_response_code(503);
    exit('El lloc encara no està configurat i falta el fitxer install.php.');
}

$path = Router::currentPath();

// Mode manteniment durant les actualitzacions
if (Updater::inMaintenance() && !str_starts_with($path, '/admin')) {
    http_response_code(503);
    header('Retry-After: 120');
    View::render('errors/maintenance', ['title' => 'Estem actualitzant el web'], 'layouts/minimal');
    exit;
}

Settings::load();

View::share('settings', Settings::load());
View::share('currentPath', $path);

/** @var Router $router */
$router = require CROS_APP . '/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
