<?php
/**
 * Taula de rutes de l'aplicació.
 */
declare(strict_types=1);

use Cros\Controllers\Admin\AuthController;
use Cros\Controllers\Admin\CrudController;
use Cros\Controllers\Admin\DashboardController;
use Cros\Controllers\Admin\OrdersController;
use Cros\Controllers\Admin\RegistrationsController;
use Cros\Controllers\Admin\ResultsController;
use Cros\Controllers\Admin\SettingsController;
use Cros\Controllers\Admin\ToolsController;
use Cros\Controllers\Admin\UpdateController;
use Cros\Controllers\Admin\UsersController;
use Cros\Controllers\HomeController;
use Cros\Controllers\PageController;
use Cros\Controllers\RegistrationController;
use Cros\Controllers\TicketsController;
use Cros\Controllers\WebhookController;
use Cros\Core\Router;

$router = new Router();

/* ---------------------------------------------------------------- Públic */
$router->get('/', [HomeController::class, 'index']);
$router->get('/categories-i-premis', [PageController::class, 'categories']);
$router->get('/recorreguts', [PageController::class, 'courses']);
$router->get('/recorreguts/{slug}', [PageController::class, 'course']);
$router->get('/preguntes-frequents', [PageController::class, 'faqs']);
$router->get('/avis-legal', [PageController::class, 'legal']);
$router->get('/privacitat', [PageController::class, 'privacy']);
$router->get('/contacte', [PageController::class, 'contact']);
$router->post('/contacte', [PageController::class, 'contactSubmit']);
$router->get('/sitemap.xml', [PageController::class, 'sitemap']);
$router->get('/robots.txt', [PageController::class, 'robots']);

/* --------------------------------------------------------------- Tiquets */
$router->get('/esmorzar', [TicketsController::class, 'index']);
$router->post('/esmorzar', [TicketsController::class, 'checkout']);
$router->get('/esmorzar/pagament-correcte', [TicketsController::class, 'success']);
$router->get('/esmorzar/pagament-cancellat', [TicketsController::class, 'cancelled']);
$router->get('/els-meus-tiquets', [TicketsController::class, 'lookup']);
$router->post('/els-meus-tiquets', [TicketsController::class, 'lookupSubmit']);
$router->get('/tiquets/{token}', [TicketsController::class, 'show']);
$router->get('/tiquets/{token}/imprimir', [TicketsController::class, 'printable']);
$router->get('/qr/{code}', [TicketsController::class, 'qr']);
$router->post('/stripe/webhook', [WebhookController::class, 'stripe']);

/* ----------------------------------------------------------- Inscripcions */
$router->get('/inscripcio', [RegistrationController::class, 'form']);
$router->post('/inscripcio', [RegistrationController::class, 'submit']);
$router->get('/inscripcio/confirmada/{code}', [RegistrationController::class, 'done']);
$router->get('/inscripcio/dorsal/{token}', [RegistrationController::class, 'bib']);
$router->get('/inscripcio/dorsals/{token}', [RegistrationController::class, 'bibs']);
$router->get('/resultats', [PageController::class, 'results']);
$router->get('/resultats/pdf', [PageController::class, 'resultsPdf']);

/* ---------------------------------------------------------------- Panell */
$router->get('/admin/acces', [AuthController::class, 'showLogin']);
$router->post('/admin/acces', [AuthController::class, 'login']);
$router->any('/admin/sortir', [AuthController::class, 'logout']);
$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/perfil', [AuthController::class, 'profile']);
$router->post('/admin/perfil', [AuthController::class, 'updateProfile']);

$router->post('/admin/properament', [SettingsController::class, 'toggleComingSoon']);
$router->get('/admin/configuracio', [SettingsController::class, 'index']);
$router->get('/admin/configuracio/{group}', [SettingsController::class, 'edit']);
$router->post('/admin/configuracio/{group}', [SettingsController::class, 'update']);

$router->get('/admin/comandes', [OrdersController::class, 'index']);
$router->get('/admin/comandes/exportar', [OrdersController::class, 'export']);
$router->get('/admin/comandes/nova', [OrdersController::class, 'create']);
$router->post('/admin/comandes/nova', [OrdersController::class, 'store']);
$router->get('/admin/comandes/{id}', [OrdersController::class, 'show']);
$router->post('/admin/comandes/{id}/pagada', [OrdersController::class, 'markPaid']);
$router->post('/admin/comandes/{id}/cancellar', [OrdersController::class, 'cancel']);
$router->post('/admin/comandes/{id}/reenviar', [OrdersController::class, 'resend']);
$router->post('/admin/comandes/{id}/esborrar', [OrdersController::class, 'destroy']);
$router->post('/admin/tiquets/{id}/restablir', [OrdersController::class, 'resetTicket']);

$router->get('/admin/resultats', [ResultsController::class, 'index']);
$router->post('/admin/resultats/arribada', [ResultsController::class, 'store']);
$router->post('/admin/resultats/{id}/esborrar', [ResultsController::class, 'destroy']);
$router->post('/admin/resultats/{id}/moure', [ResultsController::class, 'move']);
$router->post('/admin/resultats/publicar', [ResultsController::class, 'publish']);
$router->get('/admin/resultats/pdf', [ResultsController::class, 'pdf']);
$router->get('/admin/resultats/csv', [ResultsController::class, 'csv']);

$router->get('/admin/validacio', [ToolsController::class, 'scanner']);
$router->post('/admin/validacio', [ToolsController::class, 'validateTicket']);
$router->get('/validar/{code}', [ToolsController::class, 'validateLink']);

$router->get('/admin/inscripcions', [RegistrationsController::class, 'index']);
$router->get('/admin/inscripcions/exportar', [RegistrationsController::class, 'export']);
$router->get('/admin/inscripcions/dorsals', [RegistrationsController::class, 'bibsPdf']);
$router->get('/admin/inscripcions/dorsal-de-prova', [RegistrationsController::class, 'sampleBib']);
$router->post('/admin/inscripcions/assignar-dorsals', [RegistrationsController::class, 'assignBibs']);
$router->get('/admin/inscripcions/nova', [RegistrationsController::class, 'create']);
$router->post('/admin/inscripcions/nova', [RegistrationsController::class, 'store']);
$router->get('/admin/inscripcions/{id}/dorsal', [RegistrationsController::class, 'bibPdf']);
$router->get('/admin/inscripcions/{id}', [RegistrationsController::class, 'edit']);
$router->post('/admin/inscripcions/{id}', [RegistrationsController::class, 'update']);
$router->post('/admin/inscripcions/{id}/esborrar', [RegistrationsController::class, 'destroy']);

$router->get('/admin/usuaris', [UsersController::class, 'index']);
$router->get('/admin/usuaris/nou', [UsersController::class, 'create']);
$router->post('/admin/usuaris/nou', [UsersController::class, 'store']);
$router->get('/admin/usuaris/{id}', [UsersController::class, 'edit']);
$router->post('/admin/usuaris/{id}', [UsersController::class, 'update']);
$router->post('/admin/usuaris/{id}/esborrar', [UsersController::class, 'destroy']);

$router->get('/admin/actualitzacions', [UpdateController::class, 'index']);
$router->post('/admin/actualitzacions/comprovar', [UpdateController::class, 'check']);
$router->post('/admin/actualitzacions/instalar', [UpdateController::class, 'install']);
$router->post('/admin/actualitzacions/pujar', [UpdateController::class, 'upload']);
$router->post('/admin/actualitzacions/copia', [UpdateController::class, 'backup']);
$router->get('/admin/actualitzacions/descarregar/{file}', [UpdateController::class, 'download']);

$router->get('/admin/registre', [ToolsController::class, 'activity']);
$router->get('/admin/correus', [ToolsController::class, 'emails']);
$router->post('/admin/correus/prova', [ToolsController::class, 'testEmail']);
$router->post('/admin/stripe/prova', [ToolsController::class, 'testStripe']);

/* Gestió genèrica de continguts (patrocinadors, recorreguts, categories...) */
$router->get('/admin/contingut/{resource}', [CrudController::class, 'index']);
$router->get('/admin/contingut/{resource}/nou', [CrudController::class, 'create']);
$router->post('/admin/contingut/{resource}/nou', [CrudController::class, 'store']);
$router->post('/admin/contingut/{resource}/ordre', [CrudController::class, 'reorder']);
$router->get('/admin/contingut/{resource}/{id:\d+}', [CrudController::class, 'edit']);
$router->post('/admin/contingut/{resource}/{id:\d+}', [CrudController::class, 'update']);
$router->post('/admin/contingut/{resource}/{id:\d+}/esborrar', [CrudController::class, 'destroy']);
$router->post('/admin/contingut/{resource}/{id:\d+}/duplicar', [CrudController::class, 'duplicate']);

return $router;
