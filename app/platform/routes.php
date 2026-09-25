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
use Cros\Platform\Controllers\ConfigController;
use Cros\Platform\Controllers\ConsoleController;
use Cros\Platform\Controllers\InstanceController;
use Cros\Platform\Controllers\RequestController;
use Cros\Platform\Controllers\SiteController;
use Cros\Platform\Controllers\UpdatesController;

$router = new Router();

if (($mode ?? 'platform') === 'console') {
    $router->any('/acces', [ConsoleController::class, 'login']);
    $router->get('/sortir', [ConsoleController::class, 'logout']);

    $router->get('/', [ConsoleController::class, 'home']);
    $router->get('/clients', [ConsoleController::class, 'clients']);
    $router->get('/registre', [ConsoleController::class, 'activity']);

    $router->get('/configuracio', [ConfigController::class, 'index']);
    $router->get('/configuracio/{group}', [ConfigController::class, 'edit']);
    $router->post('/configuracio/{group}', [ConfigController::class, 'update']);

    $router->get('/actualitzacions', [UpdatesController::class, 'index']);
    $router->post('/actualitzacions/comprovar', [UpdatesController::class, 'check']);
    $router->post('/actualitzacions/instalar', [UpdatesController::class, 'install']);
    $router->post('/actualitzacions/pujar', [UpdatesController::class, 'upload']);
    $router->post('/actualitzacions/copia', [UpdatesController::class, 'backup']);
    $router->get('/actualitzacions/copia', [UpdatesController::class, 'download']);

    $router->get('/sollicituds', [RequestController::class, 'index']);
    $router->get('/sollicituds/{id:\d+}', [RequestController::class, 'show']);
    $router->post('/sollicituds/{id:\d+}/decidir', [RequestController::class, 'decide']);

    $router->get('/instancies', [InstanceController::class, 'index']);
    $router->get('/instancies/nova', [InstanceController::class, 'create']);
    $router->post('/instancies/nova', [InstanceController::class, 'store']);
    $router->post('/instancies/actualitzar', [InstanceController::class, 'upgradeAll']);
    $router->get('/instancies/{id:\d+}', [InstanceController::class, 'show']);
    $router->post('/instancies/{id:\d+}/accio', [InstanceController::class, 'action']);
    $router->get('/instancies/{id:\d+}/copia', [InstanceController::class, 'backup']);

    return $router;
}

$router->get('/', [SiteController::class, 'home']);
$router->post('/sollicitud', [SiteController::class, 'request']);
$router->get('/sollicitud/{code}', [SiteController::class, 'sent']);

return $router;
