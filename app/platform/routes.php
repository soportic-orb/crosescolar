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
use Cros\Platform\Controllers\ChargeController;
use Cros\Platform\Controllers\ConfigController;
use Cros\Platform\Controllers\ConsoleController;
use Cros\Platform\Controllers\ContactController;
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

    /* Els pagaments que la plataforma cobra als seus clients. */
    $router->get('/pagaments', [ChargeController::class, 'index']);
    $router->get('/pagaments/factures', [ChargeController::class, 'invoices']);
    $router->get('/pagaments/{id:\d+}', [ChargeController::class, 'show']);
    $router->post('/pagaments/{id:\d+}/accio', [ChargeController::class, 'action']);
    $router->get('/pagaments/{id:\d+}/factura', [ChargeController::class, 'invoice']);

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

    /* Els missatges del formulari de contacte de les webs públiques. */
    $router->get('/contacte', [ContactController::class, 'index']);
    $router->get('/contacte/{id:\d+}', [ContactController::class, 'show']);
    $router->post('/contacte/{id:\d+}/accio', [ContactController::class, 'action']);

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
// El registre lliure: el web es crea al moment i l'accés va per correu.
$router->post('/registre', [SiteController::class, 'signup']);
// Si l'adreça triada està lliure, que el formulari consulta mentre s'escriu.
$router->get('/registre/adreca', [SiteController::class, 'slugCheck']);
$router->get('/benvinguda', [SiteController::class, 'welcome']);
$router->post('/benvinguda/reenviar', [SiteController::class, 'resendWelcome']);
$router->post('/sollicitud', [SiteController::class, 'request']);
$router->get('/sollicitud/{code}', [SiteController::class, 'sent']);
$router->get('/funcionalitats', [SiteController::class, 'features']);
$router->get('/contacte', [SiteController::class, 'contact']);
$router->post('/contacte', [SiteController::class, 'contactSend']);
$router->get('/contacte/captcha', [SiteController::class, 'captcha']);
// L'avís de Stripe sobre els pagaments de la plataforma. Va al web públic
// perquè no hi ha sessió: qui truca és el servidor de Stripe.
$router->post('/pagament/avis', [SiteController::class, 'stripeWebhook']);
$router->get('/{page:condicions|privadesa|galetes}', [SiteController::class, 'legal']);
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);
$router->get('/robots.txt', [SiteController::class, 'robots']);
$router->get('/llms.txt', [SiteController::class, 'llms']);

return $router;
