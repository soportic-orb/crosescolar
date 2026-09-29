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
use Cros\Platform\Controllers\MailoutController;
use Cros\Platform\Controllers\RequestController;
use Cros\Platform\Controllers\SiteController;
use Cros\Platform\Controllers\SupportController;
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

    /* Suport: la safata dels tiquets i els departaments. */
    $router->get('/suport', [SupportController::class, 'index']);
    $router->get('/suport/departaments', [SupportController::class, 'departments']);
    $router->post('/suport/departaments/desar', [SupportController::class, 'saveDepartment']);
    $router->post('/suport/departaments/{id:\d+}/esborrar', [SupportController::class, 'deleteDepartment']);
    $router->get('/suport/{id:\d+}', [SupportController::class, 'show']);
    $router->post('/suport/{id:\d+}/respondre', [SupportController::class, 'reply']);
    $router->post('/suport/{id:\d+}/estat', [SupportController::class, 'update']);
    $router->post('/suport/{id:\d+}/esborrar', [SupportController::class, 'destroy']);

    /* Enviaments de correu, llistes i plantilla. */
    $router->get('/enviaments', [MailoutController::class, 'index']);
    $router->get('/enviaments/nou', [MailoutController::class, 'create']);
    $router->post('/enviaments/nou', [MailoutController::class, 'store']);
    $router->get('/enviaments/plantilla', [MailoutController::class, 'template']);
    $router->post('/enviaments/plantilla', [MailoutController::class, 'saveTemplate']);
    $router->get('/enviaments/plantilla/vista-previa', [MailoutController::class, 'templatePreview']);
    $router->get('/enviaments/llistes', [MailoutController::class, 'lists']);
    $router->post('/enviaments/llistes/desar', [MailoutController::class, 'saveList']);
    $router->get('/enviaments/llistes/{id:\d+}', [MailoutController::class, 'listShow']);
    $router->post('/enviaments/llistes/{id:\d+}/contactes', [MailoutController::class, 'addContacts']);
    $router->post('/enviaments/llistes/{id:\d+}/esborrar', [MailoutController::class, 'deleteList']);
    $router->post('/enviaments/contactes/{id:\d+}', [MailoutController::class, 'contactAction']);
    $router->get('/enviaments/{id:\d+}', [MailoutController::class, 'show']);
    $router->get('/enviaments/{id:\d+}/editar', [MailoutController::class, 'edit']);
    $router->post('/enviaments/{id:\d+}/editar', [MailoutController::class, 'update']);
    $router->get('/enviaments/{id:\d+}/vista-previa', [MailoutController::class, 'preview']);
    $router->post('/enviaments/{id:\d+}/prova', [MailoutController::class, 'test']);
    $router->post('/enviaments/{id:\d+}/preparar', [MailoutController::class, 'start']);
    $router->post('/enviaments/{id:\d+}/tanda', [MailoutController::class, 'batch']);
    $router->post('/enviaments/{id:\d+}/esborrar', [MailoutController::class, 'destroy']);

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
$router->get('/{page:condicions|privadesa|galetes}', [SiteController::class, 'legal']);
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);
$router->get('/robots.txt', [SiteController::class, 'robots']);

return $router;
