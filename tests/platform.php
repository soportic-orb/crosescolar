<?php
/**
 * Proves de la plataforma: la base de dades on hi ha els clients, les seves
 * instàncies i les sol·licituds.
 *
 * Necessita un MySQL/MariaDB de debò, com la prova de l'instal·lador; si no hi
 * ha dades de connexió, s'omet (no falla).
 *
 * Ús:
 *   CROS_DB_USER=cros CROS_DB_PASS=cros CROS_DB_PLATFORM=cros_plataforma \
 *   CROS_DB_TENANT=cros_instancia php tests/platform.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = [
    'host' => getenv('CROS_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('CROS_DB_PORT') ?: 3306),
    'user' => getenv('CROS_DB_USER') ?: '',
    'pass' => getenv('CROS_DB_PASS') ?: '',
    'platform' => getenv('CROS_DB_PLATFORM') ?: '',
    'tenant' => getenv('CROS_DB_TENANT') ?: '',
];
if ($db['user'] === '' || $db['platform'] === '') {
    echo "Proves de la plataforma omeses: definiu CROS_DB_USER i CROS_DB_PLATFORM.\n";
    exit(0);
}

require $root . '/app/bootstrap.php';

use Cros\Core\Db;
use Cros\Core\Installer;
use Cros\Core\Tenancy;
use Cros\Platform\Client;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Request;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/* Un servidor de plataforma de mentida, amb les seves carpetes --------------- */
$server = sys_get_temp_dir() . '/cros-plataforma-' . bin2hex(random_bytes(3));
@mkdir($server . '/tenants', 0775, true);
file_put_contents($server . '/tenants/platform.php', "<?php return " . var_export([
    'base_domain' => 'crosescolar.test',
    'console' => ['admin'],
    'reserved' => ['premsa'],
    'db' => [
        'host' => $db['host'], 'port' => $db['port'], 'name' => $db['platform'],
        'user' => $db['user'], 'pass' => $db['pass'], 'charset' => 'utf8mb4', 'socket' => '',
    ],
], true) . ";\n");

// Base de dades neta
$pdo = Db::connect(['host' => $db['host'], 'port' => $db['port'], 'name' => $db['platform'],
    'user' => $db['user'], 'pass' => $db['pass'], 'charset' => 'utf8mb4', 'socket' => '']);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\n== La base de dades de la plataforma ==\n";

Platform::boot($server);
check('S\'hi connecta amb el que diu tenants/platform.php', Db::connection() !== null);
check('Sap quin és el domini', Platform::domain($server) === 'crosescolar.test');
check('I com és l\'adreça d\'una instància',
    Platform::url('granada', $server) === 'https://granada.crosescolar.test');

$applied = Platform::migrate();
check('Es crea l\'esquema', count($applied) >= 1, implode(', ', $applied));
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['clients', 'instances', 'instance_requests', 'platform_users', 'platform_activity'] as $table) {
    check('Hi ha la taula ' . $table, in_array($table, $tables, true));
}
check('Tornar-hi no aplica res més', Platform::migrate() === []);

echo "\n== Sol·licituds ==\n";

$request = Request::create([
    'entity' => 'AFA Escola Sant Jordi', 'nif' => 'G61234567', 'town' => 'Vilafranca del Penedès',
    'contact_name' => 'Mireia Soler', 'contact_role' => 'Presidència',
    'contact_email' => 'MIREIA@example.cat', 'contact_phone' => '661223344',
    'slug' => 'SantJordi', 'event_date' => '2027-03-14', 'participants' => 350,
    'message' => 'Tercera edició del cros de l\'escola.',
]);
check('Es desa la sol·licitud', ($request['entity'] ?? '') === 'AFA Escola Sant Jordi');
check('Amb un número per dir-lo a la gent', (bool) preg_match('/^\d{4}-\d{3}$/', (string) $request['code']), (string) $request['code']);
check('El correu es desa en minúscules', $request['contact_email'] === 'mireia@example.cat');
check('I el subdomini també', $request['slug'] === 'santjordi');
check('Neix pendent', $request['status'] === 'pending');
check('I compta com a pendent', Request::pending() === 1);

$second = Request::create(['entity' => 'Club Atlètic', 'contact_name' => 'Jordi', 'contact_email' => 'jordi@example.cat']);
check('El número següent va per ordre',
    (int) substr((string) $second['code'], -3) === (int) substr((string) $request['code'], -3) + 1,
    $request['code'] . ' → ' . $second['code']);

check('Una adreça no pot enviar-ne tantes com vulgui', !Request::tooMany('mireia@example.cat'));
Request::create(['entity' => 'Prova', 'contact_name' => 'M', 'contact_email' => 'mireia@example.cat']);
Request::create(['entity' => 'Prova', 'contact_name' => 'M', 'contact_email' => 'mireia@example.cat']);
check('...i a la tercera es talla', Request::tooMany('mireia@example.cat'));

echo "\n== Subdominis ==\n";

check('Un de lliure es pot donar', Instance::slugProblem('santjordi', null, $server) === '');
check('Un de reservat pel codi, no', str_contains(Instance::slugProblem('admin', null, $server), 'reservat'));
check('Un de reservat per la plataforma, tampoc',
    str_contains(Instance::slugProblem('premsa', null, $server), 'reservat'));
check('Ni un amb accents', Instance::slugProblem('olèrdola', null, $server) !== '');
check('Ni un de buit', Instance::slugProblem('', null, $server) !== '');

echo "\n== Clients i instàncies ==\n";

$clientId = Client::fromRequest($request);
check('El client surt de la sol·licitud', $clientId > 0);
check('Amb les seves dades', (Client::find($clientId)['name'] ?? '') === 'AFA Escola Sant Jordi');
check('Si torna a demanar-ne una, no es duplica', Client::fromRequest($request) === $clientId);

$instanceId = Instance::create([
    'client_id' => $clientId, 'slug' => 'santjordi', 'site_name' => 'Cros Escolar Sant Jordi',
    'town' => 'Vilafranca del Penedès', 'language' => 'ca', 'db_name' => 'cros_santjordi',
    'admin_email' => 'mireia@example.cat', 'event_date' => '2027-03-14',
]);
check('Es desa la instància', $instanceId > 0);
$instance = Instance::find($instanceId);
check('Neix sense estrenar', $instance['status'] === 'new');
check('Amb la versió que hi ha ara', $instance['version'] === app_version());
check('Es troba pel subdomini', (int) (Instance::bySlug('santjordi')['id'] ?? 0) === $instanceId);
check('El seu subdomini ja no és lliure',
    str_contains(Instance::slugProblem('santjordi', null, $server), 'Ja hi ha un cros'));
check('Però sí per a ella mateixa', Instance::slugProblem('santjordi', $instanceId, $server) === '');

Request::decide((int) $request['id'], 'approved', '', $instanceId);
check('La sol·licitud queda aprovada', (Request::find((int) $request['id'])['status'] ?? '') === 'approved');
check('I apunta a la seva instància',
    (int) (Request::find((int) $request['id'])['instance_id'] ?? 0) === $instanceId);
check('Ja no compta com a pendent', Request::pending() === 3);

echo "\n== Aturar, tornar a engegar i donar de baixa ==\n";

@mkdir($server . '/tenants/santjordi', 0775, true);
file_put_contents($server . '/tenants/santjordi/config.php', "<?php return ['db' => ['name' => 'cros_santjordi']];\n");

check('S\'atura', Instance::suspend($instanceId, $server));
check('I es nota a la carpeta', is_file($server . '/tenants/santjordi/' . Tenancy::SUSPENDED));
check('El web deixa de servir-se', Tenancy::boot($server, 'santjordi.crosescolar.test')['mode'] === 'suspended');
check('Queda apuntat quan s\'ha aturat', !empty(Instance::find($instanceId)['suspended_at']));

check('Es torna a engegar', Instance::resume($instanceId, $server));
check('I el web torna', Tenancy::boot($server, 'santjordi.crosescolar.test')['mode'] === 'tenant');
check('Sense data d\'aturada', Instance::find($instanceId)['suspended_at'] === null);

check('Es dona de baixa', Instance::cancel($instanceId, $server));
$cancelled = Instance::find($instanceId);
check('Amb la data de baixa', !empty($cancelled['cancelled_at']));
check('I amb els 90 dies fins a esborrar-ho',
    $cancelled['purge_at'] === date('Y-m-d', strtotime('+' . Instance::PURGE_DAYS . ' days')),
    (string) $cancelled['purge_at']);
check('El web ja no es serveix', Tenancy::boot($server, 'santjordi.crosescolar.test')['mode'] === 'suspended');
Instance::update($instanceId, ['status' => 'active', 'cancelled_at' => null, 'purge_at' => null]);
@unlink($server . '/tenants/santjordi/' . Tenancy::SUSPENDED);

echo "\n== El llistat públic de cros ==\n";

Instance::update($instanceId, ['published' => 1, 'listed' => 1, 'event_date' => date('Y-m-d', strtotime('+30 days'))]);
$amagada = Instance::create(['client_id' => $clientId, 'slug' => 'nopublicat', 'site_name' => 'Cros sense publicar', 'db_name' => 'x']);
Instance::update($amagada, ['status' => 'active', 'published' => 0]);
$noVol = Instance::create(['client_id' => $clientId, 'slug' => 'novolsortir', 'site_name' => 'Cros discret', 'db_name' => 'y', 'listed' => 0]);
Instance::update($noVol, ['status' => 'active', 'published' => 1]);
$passada = Instance::create(['client_id' => $clientId, 'slug' => 'passada', 'site_name' => 'Cros de l\'any passat', 'db_name' => 'z']);
Instance::update($passada, ['status' => 'active', 'published' => 1, 'event_date' => date('Y-m-d', strtotime('-60 days'))]);

$directory = array_column(Instance::directory(), 'slug');
check('Hi surten els publicats', in_array('santjordi', $directory, true));
check('No hi surt el que encara no s\'ha publicat', !in_array('nopublicat', $directory, true));
check('Ni el que no vol sortir-hi', !in_array('novolsortir', $directory, true));
check('Les curses que vénen van primer', ($directory[0] ?? '') === 'santjordi', implode(', ', $directory));
check('I les passades, al final', end($directory) === 'passada', implode(', ', $directory));

/* Llegir les dades d'una instància de debò ---------------------------------- */
if ($db['tenant'] !== '') {
    echo "\n== La plataforma llegeix una instància de debò ==\n";

    $tenantPdo = Db::connect(['host' => $db['host'], 'port' => $db['port'], 'name' => $db['tenant'],
        'user' => $db['user'], 'pass' => $db['pass'], 'charset' => 'utf8mb4', 'socket' => '']);
    $tenantPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tenantPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $tenantPdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
    $tenantPdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $dir = $server . '/tenants/lesvinyes';
    @mkdir($dir . '/storage', 0775, true);
    @mkdir($dir . '/uploads', 0775, true);
    (new Installer([
        'db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['tenant'],
        'db_user' => $db['user'], 'db_pass' => $db['pass'], 'db_socket' => '',
        'base_url' => 'https://lesvinyes.crosescolar.test',
        'site_name' => 'Cros Escolar Les Vinyes', 'event_date' => '2027-02-21',
        'admin_name' => 'Marta', 'admin_email' => 'marta@example.cat',
        'admin_pass' => 'provaprova', 'demo' => '1',
    ], ['config_file' => $dir . '/config.php', 'storage_dir' => $dir . '/storage']))->run();

    $vinyes = Instance::create(['client_id' => $clientId, 'slug' => 'lesvinyes',
        'site_name' => 'nom antic', 'db_name' => $db['tenant']]);

    check('La plataforma es queda amb la seva base de dades',
        (int) Db::val('SELECT COUNT(*) FROM instances', [], 0) > 0);
    check('Es posa al dia llegint la instància', Instance::sync($vinyes, $server));

    $synced = Instance::find($vinyes);
    check('N\'agafa el nom del web', $synced['site_name'] === 'Cros Escolar Les Vinyes', (string) $synced['site_name']);
    check('I la data de la cursa', (string) $synced['event_date'] === '2027-02-21', (string) $synced['event_date']);
    check('Amb el recompte d\'inscripcions', (int) $synced['registrations'] === 0);
    check('Queda apuntat quan s\'ha mirat', !empty($synced['synced_at']));
    check('I la plataforma continua parlant amb la seva base de dades',
        (int) Db::val('SELECT COUNT(*) FROM instance_requests', [], 0) === 4);

    // Un web «en preparació» encara no és públic.
    $tenantPdo->exec("UPDATE settings SET v = '1' WHERE k = 'coming_soon'");
    Instance::sync($vinyes, $server);
    check('Un web en preparació no compta com a publicat', (int) Instance::find($vinyes)['published'] === 0);
    $tenantPdo->exec("UPDATE settings SET v = '0' WHERE k = 'coming_soon'");
    Instance::sync($vinyes, $server);
    check('En publicar-lo, ja hi és', (int) Instance::find($vinyes)['published'] === 1);

    foreach (glob($dir . '/storage/*') ?: [] as $file) {
        @unlink($file);
    }
    @unlink($dir . '/config.php');
    @rmdir($dir . '/storage');
    @rmdir($dir . '/uploads');
    @rmdir($dir);
}

echo "\n== Registre d'activitat ==\n";
Platform::log('instance_create', 'instance', $instanceId, ['slug' => 'santjordi']);
$activity = Db::one('SELECT * FROM platform_activity ORDER BY id DESC');
check('S\'apunta el que es fa', ($activity['action'] ?? '') === 'instance_create');
check('Amb el detall', str_contains((string) ($activity['context'] ?? ''), 'santjordi'));

/* Neteja -------------------------------------------------------------------- */
@unlink($server . '/tenants/santjordi/config.php');
@rmdir($server . '/tenants/santjordi');
@unlink($server . '/tenants/platform.php');
@rmdir($server . '/tenants');
@rmdir($server);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
