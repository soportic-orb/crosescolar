<?php
/**
 * Rutes de la plataforma.
 *
 * La pàgina pública (crosescolar.com) i el panell de superadministració
 * (admin.crosescolar.com) són dues coses diferents encara que comparteixin
 * codi: cadascuna té les seves adreces i l'altra no les serveix.
 *
 * @var string $mode  'platform' (pàgina pública) o 'console' (panell)
 */
declare(strict_types=1);

use Cros\Core\Router;
use Cros\Core\View;
use Cros\Platform\Controllers\SiteController;

$router = new Router();

if (($mode ?? 'platform') === 'console') {
    // El panell encara s'està construint: de moment, només ho diu.
    $router->any('/{path}', static function (): void {
        http_response_code(503);
        View::render('errors/platform-soon', [
            'title' => 'Panell de superadministració',
            'console' => true,
            'noindex' => true,
        ], 'layouts/minimal');
    });
    $router->any('/', static function (): void {
        http_response_code(503);
        View::render('errors/platform-soon', [
            'title' => 'Panell de superadministració',
            'console' => true,
            'noindex' => true,
        ], 'layouts/minimal');
    });

    return $router;
}

$router->get('/', [SiteController::class, 'home']);
$router->post('/sollicitud', [SiteController::class, 'request']);
$router->get('/sollicitud/{code}', [SiteController::class, 'sent']);

return $router;
