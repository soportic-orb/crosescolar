<?php
/**
 * Proves del panell de superadministració i de l'alta d'instàncies.
 *
 * Necessiten un MySQL/MariaDB de debò i, a més, un usuari que pugui crear
 * bases de dades (l'alta d'una instància en crea una de nova). Si no hi són,
 * les proves s'ometen i no fallen.
 *
 * Ús:
 *   CROS_DB_USER=cros CROS_DB_PASS=cros CROS_DB_PLATFORM=cros_plataforma \
 *   CROS_DB_ADMIN_USER=root CROS_DB_ADMIN_PASS=root php tests/console.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = [
    'host' => getenv('CROS_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('CROS_DB_PORT') ?: 3306),
    'user' => getenv('CROS_DB_USER') ?: '',
    'pass' => getenv('CROS_DB_PASS') ?: '',
    'platform' => getenv('CROS_DB_PLATFORM') ?: '',
    'admin_user' => getenv('CROS_DB_ADMIN_USER') ?: '',
    'admin_pass' => getenv('CROS_DB_ADMIN_PASS') ?: '',
    // Des d'on es connectaran les instàncies que es creïn. En un servidor és
    // «localhost»; si la base de dades és en un altre contenidor (com a la
    // integració contínua), cal obrir-ho.
    'grant_host' => getenv('CROS_DB_GRANT_HOST') ?: 'localhost',
];
if ($db['user'] === '' || $db['platform'] === '' || $db['admin_user'] === '') {
    echo "Proves del panell omeses: definiu CROS_DB_USER, CROS_DB_PLATFORM i CROS_DB_ADMIN_USER.\n";
    exit(0);
}

// Les proves de les carpetes per instància deixen aquestes variables posades i
// el servidor que engegarem les heretaria.
putenv('CROS_CONFIG');
putenv('CROS_UPLOADS');
putenv('CROS_STORAGE');

require $root . '/app/bootstrap.php';

use Cros\Core\Db;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Provisioner;

$passed = 0;
$failed = 0;
$prefix = 'crostest_';

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/* Un servidor de plataforma sencer, amb el codi i les seves carpetes --------- */
$site = sys_get_temp_dir() . '/cros-consola-' . bin2hex(random_bytes(3));
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
    'provision' => [
        'db_host' => $db['host'], 'db_port' => $db['port'], 'db_socket' => '',
        'admin_user' => $db['admin_user'], 'admin_pass' => $db['admin_pass'],
        'db_prefix' => $prefix, 'tenant_from' => $db['grant_host'], 'tenant_host' => $db['host'],
    ],
], true) . ";\n");

/* La base de dades de la plataforma, buida ---------------------------------- */
$pdo = Platform::boot($site);
Platform::migrate();
foreach (['platform_activity', 'instance_requests', 'instances', 'clients', 'platform_users'] as $table) {
    $pdo->exec('DELETE FROM ' . $table);
}

echo "\n== Superadministradors ==\n";
$userId = Console::save('Superadministradora', 'super@crosescolar.test', 'unaClauBenLlarga1');
check('Es crea el primer usuari', $userId > 0);
check('I el sistema sap que n\'hi ha', Console::any());
$again = Console::save('Superadministradora Nova', 'super@crosescolar.test', '');
check('Tornar-hi no en crea un altre', $again === $userId);
check('Però actualitza el nom',
    Db::val('SELECT name FROM platform_users WHERE id = :id', ['id' => $userId]) === 'Superadministradora Nova');
check('La contrasenya es desa xifrada',
    password_verify('unaClauBenLlarga1', (string) Db::val('SELECT password_hash FROM platform_users WHERE id = :id', ['id' => $userId])));

/* El panell, servit de debò ------------------------------------------------- */
$port = (int) (getenv('CROS_TEST_PORT_CONSOLE') ?: 8132);
$busy = @fsockopen('127.0.0.1', $port, $errno, $error, 1);
if ($busy !== false) {
    fclose($busy);
    echo "  El port $port ja està ocupat: atureu el que hi hagi o definiu CROS_TEST_PORT_CONSOLE.\n";
    exit(1);
}
$webServer = proc_open(
    sprintf('exec php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($site)),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);
$base = 'http://127.0.0.1:' . $port;
$jar = sys_get_temp_dir() . '/cros-consola-cookies-' . bin2hex(random_bytes(3)) . '.txt';

/** Petició al panell (o a qualsevol amfitrió de la plataforma). */
$web = static function (string $method, string $path, array $fields = [], string $host = 'admin.crosescolar.test') use ($base, $jar): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Host: ' . $host],
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 60,
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

for ($i = 0; $i < 40 && ($web('GET', '/acces')['status'] ?? 0) === 0; $i++) {
    usleep(200000);
}

try {
    echo "\n== Accés al panell ==\n";
    $closed = $web('GET', '/');
    check('Sense sessió no s\'hi entra', $closed['status'] === 302 && str_contains($closed['headers'], '/acces'));
    $form = $web('GET', '/acces');
    check('El formulari d\'accés respon', $form['status'] === 200, 'estat ' . $form['status']);
    check('I no diu el nom de cap client', !str_contains($form['body'], 'La Granada'));

    $wrong = $web('POST', '/acces', ['_token' => $token($form['body']), 'email' => 'super@crosescolar.test', 'password' => 'aixòNoÉs']);
    check('Amb la contrasenya equivocada no s\'entra',
        $wrong['status'] === 302 && str_contains($wrong['headers'], '/acces'));
    check('I queda apuntat l\'intent',
        (int) Db::val("SELECT COUNT(*) FROM platform_activity WHERE action = 'login_failed'", [], 0) === 1);

    $ok = $web('POST', '/acces', ['_token' => $token($web('GET', '/acces')['body']), 'email' => 'super@crosescolar.test', 'password' => 'unaClauBenLlarga1']);
    check('Amb les bones, sí', $ok['status'] === 302 && !str_contains($ok['headers'], '/acces'));
    $board = $web('GET', '/');
    check('I es veu el tauler', $board['status'] === 200 && str_contains($board['body'], 'Tauler'));

    echo "\n== Alta d'una instància ==\n";
    $form = $web('GET', '/instancies/nova');
    check('El formulari d\'alta respon', $form['status'] === 200);

    $bad = $web('POST', '/instancies/nova', [
        '_token' => $token($form['body']), 'slug' => 'admin', 'site_name' => 'Cros de prova',
        'admin_name' => 'Marta Puig', 'admin_email' => 'marta@example.cat',
    ]);
    check('Un subdomini reservat no es dona', $bad['status'] === 302 && str_contains($bad['headers'], '/instancies/nova'));
    check('I no s\'ha creat res', (int) Db::val('SELECT COUNT(*) FROM instances', [], 0) === 0);

    $created = $web('POST', '/instancies/nova', [
        '_token' => $token($web('GET', '/instancies/nova')['body']),
        'slug' => 'santjordi', 'site_name' => 'Cros Escola Sant Jordi', 'town' => 'Sitges',
        'language' => 'ca', 'event_date' => date('Y-m-d', strtotime('+3 months')),
        'admin_name' => 'Laia Ferrer', 'admin_email' => 'laia@example.cat', 'listed' => '1',
    ]);
    $instance = Db::one("SELECT * FROM instances WHERE slug = 'santjordi'");
    $instanceId = (int) ($instance['id'] ?? 0);
    check('La instància es crea', $created['status'] === 302 && $instanceId > 0, 'estat ' . $created['status']);
    check('Amb la seva base de dades', (string) ($instance['db_name'] ?? '') === $prefix . 'santjordi');
    check('I el seu usuari', (string) ($instance['db_user'] ?? '') === $prefix . 'santjordi');
    check('La carpeta de la instància hi és', is_file($site . '/tenants/santjordi/config.php'));
    check('Amb carpetes per als fitxers i els registres',
        is_dir($site . '/tenants/santjordi/uploads') && is_dir($site . '/tenants/santjordi/storage'));
    check('La base de dades té les taules del cros',
        (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = ' . $pdo->quote($prefix . 'santjordi'))->fetchColumn() > 20);
    check('Neix sense estrenar', (string) ($instance['status'] ?? '') === 'new');
    check('I amagada al públic', (int) ($instance['published'] ?? 1) === 0);

    $log = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('S\'envien les claus a qui el gestionarà', str_contains($log, 'laia@example.cat'));
    check('Amb el correu de la plataforma, no el d\'un client', !str_contains($log, 'Enviament fallit'));

    echo "\n== El web del client ==\n";
    $client = $web('GET', '/', [], 'santjordi.crosescolar.test');
    check('El web de la instància es serveix', $client['status'] === 200, 'estat ' . $client['status']);
    check('I diu que encara no està publicat', str_contains($client['body'], 'Aviat'));
    check('El seu panell demana les credencials',
        $web('GET', '/admin', [], 'santjordi.crosescolar.test')['status'] === 302);

    // El client publica el seu web i la plataforma se n'assabenta en repassar-lo.
    $tenant = require $site . '/tenants/santjordi/config.php';
    $tenantPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    $tenantPdo->exec("UPDATE settings SET v = '0' WHERE k = 'coming_soon'");
    $tenantPdo = null;

    $detail = $web('GET', '/instancies/' . $instanceId);
    $sync = $web('POST', '/instancies/' . $instanceId . '/accio', ['_token' => $token($detail['body']), 'action' => 'sync']);
    $instance = Instance::find($instanceId);
    check('En repassar-la, consta publicada', $sync['status'] === 302 && (int) $instance['published'] === 1);
    check('I passa a estar activa', (string) $instance['status'] === 'active');
    check('Ara surt al llistat públic',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], 'Cros Escola Sant Jordi'));

    echo "\n== Actualitzar les instàncies ==\n";
    check('Una instància acabada de crear ja té la versió del codi',
        (string) $instance['version'] === app_version());
    check('I no consta pendent d\'actualitzar', Instance::outdated() === []);

    // Una instància que es va quedar en una versió antiga i a qui, a més, li
    // falta un canvi a la base de dades.
    Db::update('instances', ['version' => '0.9.0'], 'id = :id', ['id' => $instanceId]);
    $tenant = require $site . '/tenants/santjordi/config.php';
    $tenantPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    $last = (string) $tenantPdo->query('SELECT name FROM migrations ORDER BY name DESC LIMIT 1')->fetchColumn();
    $tenantPdo->exec('DELETE FROM migrations WHERE name = ' . $tenantPdo->quote($last));
    $tenantPdo = null;

    $pending = Instance::outdated();
    check('La plataforma veu que li falta la versió nova',
        count($pending) === 1 && (int) $pending[0]['id'] === $instanceId);
    $list = $web('GET', '/instancies');
    check('I el panell ho avisa', str_contains($list['body'], 'Actualitzar-les totes'));

    $upgrade = $web('POST', '/instancies/actualitzar', ['_token' => $token($list['body'])]);
    $instance = Instance::find($instanceId);
    check('S\'actualitzen totes de cop', $upgrade['status'] === 302);
    check('I queda apuntada la versió nova', (string) $instance['version'] === app_version());
    check('Cap instància queda pendent', Instance::outdated() === []);

    $tenant = require $site . '/tenants/santjordi/config.php';
    $tenantPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    check('El canvi que faltava s\'ha aplicat a la seva base de dades',
        (string) $tenantPdo->query('SELECT COUNT(*) FROM migrations WHERE name = '
            . $tenantPdo->quote($last))->fetchColumn() === '1');
    $tenantPdo = null;

    check('La plataforma no ha perdut la seva connexió',
        (int) Db::val('SELECT COUNT(*) FROM instances', [], 0) > 0);

    echo "\n== Aturar i tornar a engegar ==\n";
    $detail = $web('GET', '/instancies/' . $instanceId);
    $web('POST', '/instancies/' . $instanceId . '/accio', ['_token' => $token($detail['body']), 'action' => 'suspend']);
    check('En aturar-la, el web deixa de servir-se',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 503);
    check('I ja no surt al llistat',
        !str_contains($web('GET', '/', [], 'crosescolar.test')['body'], 'Cros Escola Sant Jordi'));

    $detail = $web('GET', '/instancies/' . $instanceId);
    $web('POST', '/instancies/' . $instanceId . '/accio', ['_token' => $token($detail['body']), 'action' => 'resume']);
    check('En tornar-la a engegar, el web torna',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 200);

    echo "\n== El client s'emporta les seves dades ==\n";
    // El web d'una instància s'adreça per https; per poder-hi entrar amb curl
    // durant la prova, se li diu que de moment parla per http.
    $configFile = $site . '/tenants/santjordi/config.php';
    file_put_contents($configFile, str_replace(
        "'base_url' => 'https://santjordi.crosescolar.test'",
        "'base_url' => 'http://santjordi.crosescolar.test'",
        (string) file_get_contents($configFile)
    ));
    $tenant = require $configFile;
    $tenantPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    $tenantPdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')
        ->execute([password_hash('unaAltraClauLlarga1', PASSWORD_DEFAULT), 'laia@example.cat']);
    $tenantPdo = null;

    $login = $web('GET', '/admin/acces', [], 'santjordi.crosescolar.test');
    $entered = $web('POST', '/admin/acces', [
        '_token' => $token($login['body']), 'email' => 'laia@example.cat', 'password' => 'unaAltraClauLlarga1',
    ], 'santjordi.crosescolar.test');
    check('Qui gestiona el cros entra al seu panell', $entered['status'] === 302);

    $page = $web('GET', '/admin/dades', [], 'santjordi.crosescolar.test');
    check('Hi té la pàgina de les seves dades',
        $page['status'] === 200 && str_contains($page['body'], 'Emporteu-vos les vostres dades'));
    check('I l\'avís de les dades personals', str_contains($page['body'], 'dades personals'));

    $download = $web('POST', '/admin/dades', ['_token' => $token($page['body'])], 'santjordi.crosescolar.test');
    check('La descàrrega respon un ZIP',
        $download['status'] === 200 && str_contains($download['headers'], 'application/zip'), 'estat ' . $download['status']);
    check('Amb nom de fitxer', str_contains($download['headers'], '.zip"'));

    $zipFile = sys_get_temp_dir() . '/cros-export-' . bin2hex(random_bytes(3)) . '.zip';
    file_put_contents($zipFile, $download['body']);
    $zip = new ZipArchive();
    $opened = $zip->open($zipFile) === true;
    check('El fitxer s\'obre', $opened);
    if ($opened) {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        check('Hi ha l\'explicació de què conté', in_array('llegeix-me.txt', $names, true));
        check('Els fulls de càlcul de les llistes',
            in_array('fulls/inscripcions.csv', $names, true) && in_array('fulls/configuracio.csv', $names, true));
        check('I la còpia de la base de dades', in_array('base-de-dades.sql', $names, true));
        $sql = (string) $zip->getFromName('base-de-dades.sql');
        check('La còpia porta l\'estructura', str_contains($sql, 'CREATE TABLE'));
        check('I les dades de configuració', str_contains($sql, 'INSERT INTO `settings`'));
        check('Amb el nom del cros dins', str_contains($sql, 'Cros Escola Sant Jordi'));
        $csv = (string) $zip->getFromName('fulls/configuracio.csv');
        check('Els fulls porten capçaleres', str_contains($csv, 'k;v') || str_contains($csv, '"k";"v"'));
        check('No s\'hi cola la taula de control de versions',
            !in_array('fulls/migrations.csv', $names, true));
        $zip->close();
    }
    @unlink($zipFile);
    check('El servidor no es queda el fitxer',
        (glob($site . '/tenants/santjordi/storage/exports/*.zip') ?: []) === []);

    echo "\n== D'una sol·licitud a una instància ==\n";
    $home = $web('GET', '/', [], 'crosescolar.test');
    $web('POST', '/sollicitud', [
        '_token' => $token($home['body']),
        'entity' => 'AFA Escola del Bosc', 'town' => 'Reus', 'contact_name' => 'Pau Roca',
        'contact_email' => 'pau@example.cat', 'contact_phone' => '600333444',
        'slug' => 'elbosc', 'language' => 'ca', 'consent' => '1',
    ], 'crosescolar.test');
    $request = Db::one("SELECT * FROM instance_requests WHERE contact_email = 'pau@example.cat'");
    check('La sol·licitud arriba al panell', $request !== null);
    $list = $web('GET', '/sollicituds');
    check('I surt a la llista', str_contains($list['body'], 'AFA Escola del Bosc'));

    $prefilled = $web('GET', '/instancies/nova?peticio=' . (int) $request['id']);
    check('El formulari ve omplert amb les seves dades',
        str_contains($prefilled['body'], 'value="elbosc"') && str_contains($prefilled['body'], 'pau@example.cat'));

    $web('POST', '/instancies/nova', [
        '_token' => $token($prefilled['body']), 'request_id' => (int) $request['id'],
        'slug' => 'elbosc', 'site_name' => 'Cros Escola del Bosc', 'town' => 'Reus',
        'language' => 'ca', 'admin_name' => 'Pau Roca', 'admin_email' => 'pau@example.cat',
        'client_id' => '0', 'listed' => '1',
    ]);
    $bosc = Db::one("SELECT * FROM instances WHERE slug = 'elbosc'");
    $request = Db::one('SELECT * FROM instance_requests WHERE id = :id', ['id' => $request['id']]);
    check('En crear-la, la sol·licitud queda aprovada', (string) $request['status'] === 'approved');
    check('I apunta a la instància', (int) $request['instance_id'] === (int) $bosc['id']);
    $clientRow = Db::one("SELECT * FROM clients WHERE contact_email = 'pau@example.cat'");
    check('S\'ha creat la fitxa del client', $clientRow !== null);
    check('I la instància és seva', (int) $bosc['client_id'] === (int) $clientRow['id']);

    echo "\n== Desestimar una sol·licitud ==\n";
    $home = $web('GET', '/', [], 'crosescolar.test');
    $web('POST', '/sollicitud', [
        '_token' => $token($home['body']),
        'entity' => 'Club que no tira endavant', 'town' => 'Lleida', 'contact_name' => 'Nora Vidal',
        'contact_email' => 'nora@example.cat', 'contact_phone' => '600555666', 'consent' => '1',
    ], 'crosescolar.test');
    $nora = Db::one("SELECT * FROM instance_requests WHERE contact_email = 'nora@example.cat'");
    $detail = $web('GET', '/sollicituds/' . (int) $nora['id']);
    $web('POST', '/sollicituds/' . (int) $nora['id'] . '/decidir', [
        '_token' => $token($detail['body']), 'action' => 'reject', 'reason' => 'Aquest any no hi arribem.',
    ]);
    $nora = Db::one('SELECT * FROM instance_requests WHERE id = :id', ['id' => $nora['id']]);
    check('Queda desestimada', (string) $nora['status'] === 'rejected');
    check('Amb el motiu apuntat', str_contains((string) $nora['reason'], 'no hi arribem'));
    $log = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('I se n\'avisa qui la va enviar', str_contains($log, 'nora@example.cat'));

    echo "\n== Donar de baixa i esborrar ==\n";
    $detail = $web('GET', '/instancies/' . $instanceId);
    $web('POST', '/instancies/' . $instanceId . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'cancel', 'confirm' => 'una-altra-cosa',
    ]);
    check('Sense escriure bé el nom, no es dona de baixa',
        (string) Instance::find($instanceId)['status'] !== 'cancelled');

    $detail = $web('GET', '/instancies/' . $instanceId);
    $web('POST', '/instancies/' . $instanceId . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'cancel', 'confirm' => 'santjordi',
    ]);
    $instance = Instance::find($instanceId);
    check('Escrivint-lo, sí', (string) $instance['status'] === 'cancelled');
    check('I es guarden les dades ' . Instance::PURGE_DAYS . ' dies',
        (string) $instance['purge_at'] === date('Y-m-d', strtotime('+' . Instance::PURGE_DAYS . ' days')));
    check('El web ja no es serveix',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 503);

    check('Una instància activa no s\'esborra', !Provisioner::purge((int) $bosc['id'], $site));
    check('Una de donada de baixa, sí', Provisioner::purge($instanceId, $site));
    check('La base de dades desapareix',
        (int) $pdo->query('SELECT COUNT(*) FROM information_schema.schemata
            WHERE schema_name = ' . $pdo->quote($prefix . 'santjordi'))->fetchColumn() === 0);
    check('I la carpeta també', !is_dir($site . '/tenants/santjordi'));
    check('La fitxa es queda, marcada com a esborrada',
        (string) Instance::find($instanceId)['status'] === 'purged');
    check('I el seu amfitrió ja no existeix',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 404);

    echo "\n== Sortir ==\n";
    $out = $web('GET', '/sortir');
    check('Es tanca la sessió', $out['status'] === 302);
    check('I el panell torna a demanar les credencials',
        str_contains($web('GET', '/')['headers'], '/acces'));
} finally {
    if (is_resource($webServer)) {
        proc_terminate($webServer);
        proc_close($webServer);
    }
    @unlink($jar);

    // Les bases de dades i els usuaris que hagin quedat de les proves.
    try {
        $admin = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
            $db['admin_user'],
            $db['admin_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        foreach (['santjordi', 'elbosc'] as $slug) {
            $admin->exec('DROP DATABASE IF EXISTS `' . $prefix . $slug . '`');
            $drop = $admin->prepare('DROP USER IF EXISTS ?@?');
            $drop->execute([$prefix . $slug, $db['grant_host']]);
        }
    } catch (Throwable $e) {
        echo '  Avís: no s\'han pogut esborrar les bases de dades de prova: ' . $e->getMessage() . "\n";
    }
    exec('rm -rf ' . escapeshellarg($site));
}

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
