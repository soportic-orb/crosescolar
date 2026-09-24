<?php
/**
 * Cros Escolar La Granada — controlador frontal.
 * Totes les peticions passen per aquí (vegeu .htaccess o la configuració d'nginx).
 */
declare(strict_types=1);

// Qui atén la petició es decideix abans d'arrencar res: amb una instal·lació de
// sempre no canvia res, i amb la plataforma en marxa diu de quin client és el
// web que s'ha demanat i on són les seves dades.
require __DIR__ . '/app/core/Tenancy.php';

use Cros\Core\Tenancy;

$instance = Tenancy::boot(__DIR__);

require __DIR__ . '/app/bootstrap.php';

use Cros\Core\Auth;
use Cros\Core\Router;
use Cros\Core\Settings;
use Cros\Core\Updater;
use Cros\Core\View;

// Adreces que no són el web de cap client
switch ($instance['mode']) {
    case 'unknown':
        http_response_code(404);
        View::render('errors/instance-unknown', [
            'title' => 'Aquesta adreça no existeix',
            'host' => $instance['host'],
            'noindex' => true,
        ], 'layouts/minimal');
        exit;

    case 'suspended':
        http_response_code(503);
        header('Retry-After: 3600');
        View::render('errors/instance-suspended', [
            'title' => 'Aquest web no està disponible',
            'host' => $instance['host'],
            'noindex' => true,
        ], 'layouts/minimal');
        exit;

    case 'platform':
    case 'console':
        // La pàgina pública de la plataforma i el panell de superadministració.
        try {
            \Cros\Platform\Platform::boot(__DIR__);
        } catch (\Throwable $e) {
            http_response_code(503);
            log_line('platform', 'No s\'ha pogut obrir la plataforma', ['error' => $e->getMessage()]);
            View::render('errors/platform-soon', [
                'title' => 'Cros Escolar · Plataforma',
                'console' => $instance['mode'] === 'console',
                'noindex' => true,
            ], 'layouts/minimal');
            exit;
        }
        // El panell posa al dia la seva pròpia base de dades tot sol: qui
        // actualitza el codi no ha de recordar cap ordre de consola. La
        // pàgina pública no ho fa, que és la que rep visites.
        if ($instance['mode'] === 'console') {
            try {
                \Cros\Platform\Platform::migrate();
            } catch (\Throwable $e) {
                log_line('platform', 'No s\'han pogut aplicar les migracions de la plataforma', ['error' => $e->getMessage()]);
            }
        }
        View::share('currentPath', Router::currentPath());
        $mode = $instance['mode'];
        /** @var Router $platformRouter */
        $platformRouter = require CROS_APP . '/platform/routes.php';
        $platformRouter->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', Router::currentPath());
        exit;
}

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

// Web en preparació: el públic només veu l'avís «Aviat publicarem el web».
// Les persones amb sessió iniciada al panell veuen el web complet.
// El webhook de Stripe i l'àrea de gestió sempre queden accessibles.
if (Settings::bool('coming_soon')
    && !str_starts_with($path, '/admin')
    && !str_starts_with($path, '/validar')
    && $path !== '/stripe/webhook'
    && $path !== '/robots.txt'
    && !Auth::check()
) {
    View::render('public/coming-soon', [
        'title' => setting('coming_soon_title', 'Aviat publicarem el web'),
    ], 'layouts/coming-soon');
    exit;
}

/** @var Router $router */
$router = require CROS_APP . '/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
