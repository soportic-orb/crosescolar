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

/* La pàgina pública, servida de debò ---------------------------------------- */
echo "\n== La pàgina pública de la plataforma ==\n";

// Una còpia del codi en una carpeta a part, amb la plataforma engegada: així
// les proves no toquen el projecte ni les altres bateries.
// Les proves d'abans han deixat apuntades unes carpetes d'instància a
// l'entorn, i el servidor que arrencarem l'heretaria: es netegen primer.
putenv('CROS_CONFIG');
putenv('CROS_UPLOADS');
putenv('CROS_STORAGE');

$site = sys_get_temp_dir() . '/cros-web-plataforma-' . bin2hex(random_bytes(3));
@mkdir($site . '/tenants', 0775, true);
@mkdir($site . '/storage/logs', 0775, true);
foreach (['app', 'assets'] as $folder) {
    exec('cp -r ' . escapeshellarg($root . '/' . $folder) . ' ' . escapeshellarg($site . '/' . $folder));
}
copy($root . '/index.php', $site . '/index.php');
@unlink($site . '/app/config.php');
file_put_contents($site . '/tenants/platform.php', "<?php return " . var_export([
    'base_domain' => 'crosescolar.test',
    'name' => 'Cros Escolar',
    'console' => ['admin'],
    'mail' => ['from_email' => 'hola@crosescolar.test', 'notify' => 'hola@crosescolar.test', 'transport' => 'log'],
    'db' => [
        'host' => $db['host'], 'port' => $db['port'], 'name' => $db['platform'],
        'user' => $db['user'], 'pass' => $db['pass'], 'charset' => 'utf8mb4', 'socket' => '',
    ],
], true) . ";\n");

$port = (int) (getenv('CROS_TEST_PORT_PLATFORM') ?: 8129);
// Si el port ja està ocupat, les peticions anirien a parar a un altre servidor
// i les proves dirien coses que no són: val més aturar-se aquí.
$busy = @fsockopen('127.0.0.1', $port, $errno, $error, 1);
if ($busy !== false) {
    fclose($busy);
    echo "  El port $port ja està ocupat: atureu el que hi hagi o definiu CROS_TEST_PORT_PLATFORM.\n";
    exit(1);
}
$webServer = proc_open(
    sprintf('exec php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($site)),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);
$base = 'http://127.0.0.1:' . $port;
$jar = sys_get_temp_dir() . '/cros-platform-cookies-' . bin2hex(random_bytes(3)) . '.txt';

/** Petició al web de la plataforma, dient per quin amfitrió hi entrem. */
$web = static function (string $method, string $path, array $fields = [], string $host = 'crosescolar.test') use ($base, $jar): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
};
$token = static fn (string $html): string => preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';

for ($i = 0; $i < 40 && ($web('GET', '/')['status'] ?? 0) === 0; $i++) {
    usleep(200000);
}

try {
    // Un parell d'instàncies publicades per al llistat.
    $pdo->exec("UPDATE instances SET status = 'active', published = 1 WHERE slug <> 'novolsortir'");

    $home = $web('GET', '/');
    check('La portada de la plataforma respon', $home['status'] === 200, 'estat ' . $home['status']);
    check('Hi surt el llistat de cros', str_contains($home['body'], 'cros-card'));
    check('Amb el nom d\'un cros publicat', str_contains($home['body'], 'Cros Escolar Sant Jordi'));
    check('I l\'enllaç al seu subdomini',
        str_contains($home['body'], 'https://santjordi.crosescolar.test'));
    check('No hi surt el que no vol sortir-hi', !str_contains($home['body'], 'Cros discret'));
    check('Hi ha el botó de crear-ne un', str_contains(html_entity_decode($home['body']), 'Crea la web per al teu cros'));
    check('I el formulari', str_contains($home['body'], 'name="entity"') && str_contains($home['body'], 'name="contact_email"'));

    // El panell encara no hi és, però l'adreça ja la reconeix.
    check('El subdomini del panell no és cap client',
        $web('GET', '/', [], 'admin.crosescolar.test')['status'] === 503);
    check('Un subdomini que no existeix contesta 404',
        $web('GET', '/', [], 'ningu.crosescolar.test')['status'] === 404);

    // Una sol·licitud incompleta no es desa.
    $before = (int) $pdo->query('SELECT COUNT(*) FROM instance_requests')->fetchColumn();
    $bad = $web('POST', '/sollicitud', ['_token' => $token($home['body']), 'entity' => '', 'contact_email' => 'aixo-no-es-un-correu']);
    check('Una sol·licitud incompleta es rebutja', $bad['status'] === 200 && str_contains($bad['body'], 'field--error'));
    check('I no es desa res',
        (int) $pdo->query('SELECT COUNT(*) FROM instance_requests')->fetchColumn() === $before);

    // Un subdomini reservat, tampoc.
    $reserved = $web('POST', '/sollicitud', [
        '_token' => $token($web('GET', '/')['body']),
        'entity' => 'Prova', 'town' => 'Prova', 'contact_name' => 'Prova',
        'contact_email' => 'prova@example.cat', 'contact_phone' => '600111222',
        'slug' => 'admin', 'consent' => '1',
    ]);
    check('Un subdomini reservat no s\'accepta',
        $reserved['status'] === 200 && str_contains(html_entity_decode($reserved['body']), 'reservat'));

    // I ara, una de bona.
    $good = $web('POST', '/sollicitud', [
        '_token' => $token($web('GET', '/')['body']),
        'entity' => 'AMPA Les Vinyes', 'nif' => 'G99887766', 'town' => 'Sant Sadurní d\'Anoia',
        'contact_name' => 'Roser Guasch', 'contact_role' => 'Secretaria',
        'contact_email' => 'roser@example.cat', 'contact_phone' => '677889900',
        'slug' => 'lesvinyes2', 'language' => 'es', 'event_date' => '2027-04-11',
        'participants' => '220', 'message' => 'Volem treure les inscripcions de paper.',
        'consent' => '1',
    ]);
    check('Una sol·licitud completa es desa', $good['status'] === 302, 'estat ' . $good['status']);
    check('I porta a la pantalla de confirmació', str_contains($good['headers'], '/sollicitud/'));

    $saved = Db::one("SELECT * FROM instance_requests WHERE contact_email = 'roser@example.cat'");
    check('Amb totes les dades', ($saved['entity'] ?? '') === 'AMPA Les Vinyes' && ($saved['slug'] ?? '') === 'lesvinyes2');
    check('I l\'idioma que ha triat', ($saved['language'] ?? '') === 'es');
    check('Queda pendent de revisar', ($saved['status'] ?? '') === 'pending');

    preg_match('#/sollicitud/([0-9-]+)#', $good['headers'], $m);
    $sent = $web('GET', '/sollicitud/' . ($m[1] ?? ''));
    check('La confirmació mostra el número', $sent['status'] === 200 && str_contains($sent['body'], (string) $saved['code']));
    check('I l\'adreça demanada', str_contains($sent['body'], 'lesvinyes2.crosescolar.test'));
    check('Una confirmació inventada no existeix', $web('GET', '/sollicitud/2026-999')['status'] === 404);

    // Els dos correus.
    $log = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('S\'avisa qui l\'ha demanada', str_contains($log, 'roser@example.cat'));
    check('I la superadministració', str_contains($log, 'hola@crosescolar.test'));

    // El parany per a robots.
    $bot = $web('POST', '/sollicitud', [
        '_token' => $token($web('GET', '/')['body']),
        'entity' => 'Robot', 'town' => 'Enlloc', 'contact_name' => 'Robot',
        'contact_email' => 'robot@example.cat', 'contact_phone' => '600000000',
        'website_url' => 'https://spam.example', 'consent' => '1',
    ]);
    check('Un robot que omple el camp amagat no desa res',
        $bot['status'] === 302 && Db::one("SELECT id FROM instance_requests WHERE contact_email = 'robot@example.cat'") === null);
} finally {
    if (is_resource($webServer)) {
        proc_terminate($webServer);
        proc_close($webServer);
    }
    @unlink($jar);
    exec('rm -rf ' . escapeshellarg($site));
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
