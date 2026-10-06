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
use Cros\Core\Settings;
use Cros\Platform\Backup;
use Cros\Platform\Certificate;
use Cros\Platform\Client;
use Cros\Platform\Console;
use Cros\Platform\Dns;
use Cros\Platform\Health;
use Cros\Platform\Contact;
use Cros\Platform\Importer;
use Cros\Platform\Instance;
use Cros\Platform\Charge;
use Cros\Platform\Invoice;
use Cros\Platform\Plan;
use Cros\Platform\Site;
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
    'domains' => ['crosescolar.example', 'esportweb.test'],
    'name' => 'Cros Escolar',
    'console' => ['admin'],
    'mail' => ['from_email' => 'hola@crosescolar.test', 'notify' => 'hola@crosescolar.test', 'transport' => 'log'],
    // A les proves no hi ha cap servidor a crosescolar.test: només es mira la base de dades.
    'monitor' => ['web' => false],
    'backups' => ['keep' => 2],
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

// Per mirar les bases de dades dels clients cal qui les pugui veure: l'usuari
// de la plataforma només té permisos sobre la seva, i information_schema no
// ensenya el que no es pot tocar.
$admin = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
    $db['admin_user'],
    $db['admin_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$dbExists = static fn (string $name): bool => (bool) $admin->query(
    'SELECT 1 FROM information_schema.schemata WHERE schema_name = ' . $admin->quote($name)
)->fetchColumn();
$dbTables = static fn (string $name): int => (int) $admin->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $admin->quote($name)
)->fetchColumn();
foreach ([
    'platform_activity', 'instance_requests', 'instances', 'clients', 'platform_users',
    'support_messages', 'support_tickets',
    'platform_mailing_recipients', 'platform_mailings', 'mail_contacts', 'mail_lists',
] as $table) {
    $pdo->exec('DELETE FROM ' . $table);
}
// Els departaments no: el que ve de fàbrica l'ha posat la migració i no
// tornaria. Només se'n treuen els que hagi deixat una passada anterior.
$pdo->exec("DELETE FROM support_departments WHERE name <> 'Suport general'");
// La configuració també: una plataforma acabada d'instal·lar no en té cap, i
// el que digui el fitxer de la instal·lació ha de manar.
$pdo->exec('DROP TABLE IF EXISTS settings');
$pdo->exec("DELETE FROM platform_migrations WHERE name LIKE '%configuracio%'");
Platform::migrate();
Settings::forget();
Platform::boot($site);

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
// Amb encaminador: sense ell, el servidor integrat de PHP 8.2 respon 404 a
// «/sitemap.xml» i «/robots.txt» sense passar per l'aplicació.
$webServer = proc_open(
    sprintf(
        'exec php -S 127.0.0.1:%d -t %s %s',
        $port,
        escapeshellarg($site),
        escapeshellarg($root . '/tests/server-copia.php')
    ),
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
        $hasFile = false;
        foreach ($fields as $value) {
            $hasFile = $hasFile || $value instanceof CURLFile;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $fields : http_build_query($fields));
    }
    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
};
$token = static fn (string $html): string => preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';

/* Les mateixes ajudes que a les proves funcionals: el text d'una pàgina amb
   les entitats HTML desfetes, i els camps d'un formulari per poder-ne canviar
   només un sense esborrar la resta. */
function text(string $html): string
{
    return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formData(string $html): array
{
    $data = [];
    preg_match_all('/<input[^>]*>/i', $html, $inputs);
    foreach ($inputs[0] as $tag) {
        if (!preg_match('/name="([^"]+)"/', $tag, $name)) {
            continue;
        }
        if (str_ends_with($name[1], '[]')) {
            continue; // llistes (recorreguts i voltes): les posa qui crida la funció
        }
        if (preg_match('/type="(checkbox|file|radio)"/i', $tag, $type)) {
            if (strtolower($type[1]) === 'checkbox' && str_contains($tag, 'checked')) {
                $data[$name[1]] = '1';
            }
            continue;
        }
        preg_match('/value="([^"]*)"/', $tag, $value);
        $data[$name[1]] = html_entity_decode($value[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    preg_match_all('#<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>#s', $html, $areas, PREG_SET_ORDER);
    foreach ($areas as $area) {
        $data[$area[1]] = html_entity_decode($area[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    preg_match_all('#<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>#s', $html, $selects, PREG_SET_ORDER);
    foreach ($selects as $select) {
        if (str_ends_with($select[1], '[]')) {
            continue;
        }
        if (preg_match('/<option value="([^"]*)"[^>]*selected/', $select[2], $option)) {
            $data[$select[1]] = html_entity_decode($option[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $data;
}


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
    check('La base de dades té les taules del cros', $dbTables($prefix . 'santjordi') > 20,
        $dbTables($prefix . 'santjordi') . ' taules');
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

    echo "\n== Enllaços d'accés d'un sol ús ==\n";
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
    check('En crear la instància s\'hi ha deixat un enllaç d\'estrena',
        (string) $tenantPdo->query("SELECT COUNT(*) FROM login_links WHERE purpose = 'welcome'")->fetchColumn() === '1');
    check('I no s\'hi desa l\'enllaç, només la seva empremta',
        (int) $tenantPdo->query('SELECT LENGTH(token_hash) FROM login_links LIMIT 1')->fetchColumn() === 64);
    $tenantPdo = null;

    $access = Instance::accessLink($instanceId, 'welcome', $site, 'prova');
    check('La plataforma en pot crear un de nou', $access['ok'], $access['error']);
    check('Va a parar a qui gestiona el cros', $access['email'] === 'laia@example.cat');
    check('I apunta al seu web', str_starts_with($access['url'], 'http://santjordi.crosescolar.test/admin/clau/'));

    $path = (string) parse_url($access['url'], PHP_URL_PATH);
    $used = $web('GET', $path, [], 'santjordi.crosescolar.test');
    check('En prémer-lo s\'entra al panell',
        $used['status'] === 302 && str_contains($used['headers'], '/admin/clau'));
    $choose = $web('GET', '/admin/clau', [], 'santjordi.crosescolar.test');
    check('I demana triar una contrasenya',
        $choose['status'] === 200 && str_contains($choose['body'], 'Poseu-vos una contrasenya'));

    $saved = $web('POST', '/admin/clau', [
        '_token' => $token($choose['body']),
        'password' => 'unaAltraClauLlarga1', 'password_confirm' => 'unaAltraClauLlarga1',
    ], 'santjordi.crosescolar.test');
    check('La contrasenya es desa', $saved['status'] === 302);

    $again = $web('GET', $path, [], 'santjordi.crosescolar.test');
    check('El mateix enllaç ja no serveix una segona vegada',
        $again['status'] === 302 && str_contains($again['headers'], '/admin/acces'));
    check('Un enllaç inventat tampoc',
        $web('GET', '/admin/clau/' . str_repeat('a', 48), [], 'santjordi.crosescolar.test')['status'] === 302);

    // Entrar a donar suport des del panell de la plataforma.
    $detail = $web('GET', '/instancies/' . $instanceId);
    $support = $web('POST', '/instancies/' . $instanceId . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'support',
    ]);
    preg_match('#Location: (\S+)#', $support['headers'], $m);
    check('El panell dona un enllaç de suport',
        $support['status'] === 302 && str_contains((string) ($m[1] ?? ''), '/admin/clau/'));
    $supportPath = (string) parse_url(trim((string) ($m[1] ?? '')), PHP_URL_PATH);
    $entered = $web('GET', $supportPath, [], 'santjordi.crosescolar.test');
    check('Que deixa entrar al panell del client',
        $entered['status'] === 302 && str_contains($entered['headers'], '/admin'));

    $tenant = require $configFile;
    $tenantPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    $entry = $tenantPdo->query("SELECT * FROM activity_log WHERE action = 'login_link' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    check('I queda apuntat al registre del seu web', $entry !== false);
    check('Dient que ve de la plataforma',
        str_contains((string) ($entry['details'] ?? ''), 'Suport'));
    $tenantPdo = null;

    echo "\n== El client s'emporta les seves dades ==\n";
    // Es tanca la sessió de suport: qui entra ara és qui gestiona el cros.
    $web('GET', '/admin/sortir', [], 'santjordi.crosescolar.test');
    $login = $web('GET', '/admin/acces', [], 'santjordi.crosescolar.test');
    $entered = $web('POST', '/admin/acces', [
        '_token' => $token($login['body']), 'email' => 'laia@example.cat', 'password' => 'unaAltraClauLlarga1',
    ], 'santjordi.crosescolar.test');
    check('Qui gestiona el cros entra amb la contrasenya que s\'ha triat', $entered['status'] === 302);

    // Un web de la plataforma no té servidor de correu propi: envia pel d'ella.
    $correusJordi = $web('GET', '/admin/correus', [], 'santjordi.crosescolar.test');
    $prova = $web('POST', '/admin/correus/prova', [
        '_token' => $token($correusJordi['body']), 'to' => 'laia@example.cat',
    ], 'santjordi.crosescolar.test');
    $jordiPdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $prefix . 'santjordi'),
        $db['admin_user'], $db['admin_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    check('Un web creat des de la plataforma envia pel correu d\'ella',
        $jordiPdo->query("SELECT v FROM settings WHERE k = 'mail_transport'")->fetchColumn() === 'platform');
    $provaRegistre = $jordiPdo->query("SELECT status, error FROM email_log WHERE subject LIKE 'Prova de correu%' ORDER BY id DESC LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    check('I un correu del web surt',
        $prova['status'] === 302 && is_array($provaRegistre) && $provaRegistre['status'] === 'sent',
        'estat ' . $correusJordi['status'] . '/' . $prova['status'] . ' ' . json_encode($provaRegistre));
    $logJordi = (string) @file_get_contents($site . '/tenants/santjordi/storage/logs/app-' . date('Y-m') . '.log');
    check('Amb la manera d\'enviar de la plataforma, no amb la funció mail() del servidor',
        str_contains($logJordi, 'Assaig') && str_contains($logJordi, 'Prova de correu'));
    // I pel del seu domini: si crosescolar.test té el seu propi remitent, és
    // aquest el que fa servir, no el de tota la plataforma.
    $pdo->prepare('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute(['site:crosescolar.test:mail_from_email', 'correu@crosescolar.test']);
    $web('POST', '/admin/correus/prova', [
        '_token' => $token($web('GET', '/admin/correus', [], 'santjordi.crosescolar.test')['body']), 'to' => 'laia@example.cat',
    ], 'santjordi.crosescolar.test');
    $darreraProva = '';
    foreach (explode("\n", (string) @file_get_contents($site . '/tenants/santjordi/storage/logs/app-' . date('Y-m') . '.log')) as $linia) {
        if (str_contains($linia, 'Prova de correu')) {
            $darreraProva = $linia;
        }
    }
    check('Pel servidor de correu del seu domini', str_contains($darreraProva, 'correu@crosescolar.test'), $darreraProva);
    $pdo->prepare('UPDATE settings SET v = ? WHERE k = ?')->execute(['', 'site:crosescolar.test:mail_from_email']);

    check('El client no pot actualitzar el codi de tothom',
        $web('GET', '/admin/actualitzacions', [], 'santjordi.crosescolar.test')['status'] === 403);
    check('Ni li surt al menú',
        !str_contains($web('GET', '/admin', [], 'santjordi.crosescolar.test')['body'], '/admin/actualitzacions'));

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

    echo "\n== Suport: el client obre una consulta ==\n";
    // Els tiquets viuen a la base de dades de la plataforma, però s'obren des
    // del panell del client: això prova el pont entre les dues bases de dades.
    $menu = $web('GET', '/admin', [], 'santjordi.crosescolar.test');
    check('Al panell del client hi surt el Suport', str_contains($menu['body'], '/admin/suport'));

    $suport = $web('GET', '/admin/suport', [], 'santjordi.crosescolar.test');
    check('L\'apartat respon', $suport['status'] === 200, 'estat ' . $suport['status']);
    check('I diu que encara no n\'hi ha cap', str_contains(text($suport['body']), 'Encara no heu obert cap consulta'));

    $nova = $web('GET', '/admin/suport/nou', [], 'santjordi.crosescolar.test');
    check('Hi ha el formulari de consulta nova', $nova['status'] === 200);
    check('Amb el departament que ve de fàbrica', str_contains($nova['body'], 'Suport general'));

    $departmentId = (int) Db::val("SELECT id FROM support_departments WHERE name = 'Suport general'", [], 0);
    $oberta = $web('POST', '/admin/suport/nou', [
        '_token' => $token($nova['body']),
        'department_id' => (string) $departmentId,
        'priority' => 'high',
        'subject' => 'No puc canviar la data de la cursa',
        'body' => '<p>Ho provo des de Dades de la cursa i no es desa.</p>',
    ], 'santjordi.crosescolar.test');
    check('La consulta s\'envia', $oberta['status'] === 302, 'estat ' . $oberta['status']);

    $ticket = Db::one('SELECT * FROM support_tickets ORDER BY id DESC LIMIT 1');
    $ticketId = (int) ($ticket['id'] ?? 0);
    check('I arriba a la plataforma', $ticketId > 0);
    check('Amb un número que es pot dir per telèfon',
        (bool) preg_match('/^S-\d{4}-\d{3}$/', (string) ($ticket['reference'] ?? '')), (string) ($ticket['reference'] ?? ''));
    check('Sabent de quin web ve',
        (string) ($ticket['slug'] ?? '') === 'santjordi'
        && (int) ($ticket['instance_id'] ?? 0) === $instanceId
        && (string) ($ticket['site_name'] ?? '') === 'Cros Escola Sant Jordi');
    check('I de qui l\'escriu', (string) ($ticket['author_email'] ?? '') === 'laia@example.cat');
    check('Neix oberta i esperant-nos',
        (string) ($ticket['status'] ?? '') === 'open' && (string) ($ticket['last_sender'] ?? '') === 'client');
    check('Amb la prioritat que ha triat', (string) ($ticket['priority'] ?? '') === 'high');
    check('I el primer missatge',
        (int) Db::val('SELECT COUNT(*) FROM support_messages WHERE ticket_id = :id', ['id' => $ticketId], 0) === 1);

    $log = (string) @file_get_contents($site . '/tenants/santjordi/storage/logs/app-' . date('Y-m') . '.log');
    check('S\'avisa el suport per correu', str_contains($log, 'Consulta nova ' . $ticket['reference']));

    check('El web del client continua funcionant després de tocar la plataforma',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 200);
    check('I el seu nom no s\'ha barrejat amb el de la plataforma',
        str_contains($web('GET', '/admin', [], 'santjordi.crosescolar.test')['body'], 'Cros Escola Sant Jordi'));

    echo "\n== Suport: la plataforma contesta ==\n";
    $safata = $web('GET', '/suport');
    check('La consulta surt a la safata', $safata['status'] === 200 && str_contains($safata['body'], (string) $ticket['reference']));
    check('Marcada com que espera resposta', str_contains(text($safata['body']), 'Espera resposta'));
    check('I el menú ho canta', str_contains($web('GET', '/')['body'], 'Suport'));

    $fitxa = $web('GET', '/suport/' . $ticketId);
    check('La fitxa s\'obre', $fitxa['status'] === 200 && str_contains($fitxa['body'], 'no es desa'));
    check('Amb l\'enllaç a la instància', str_contains($fitxa['body'], '/instancies/' . $instanceId));

    $nota = $web('POST', '/suport/' . $ticketId . '/respondre', [
        '_token' => $token($fitxa['body']),
        'body' => '<p>Mirar si li falta el permís de configuracio.</p>',
        'internal' => '1',
    ]);
    check('S\'hi pot deixar una nota interna', $nota['status'] === 302);
    $ticket = Db::one('SELECT * FROM support_tickets WHERE id = :id', ['id' => $ticketId]);
    check('Que no mou la consulta de lloc', (string) $ticket['status'] === 'open');
    check('Ni compta com a missatge', (int) $ticket['messages'] === 1);

    $resposta = $web('POST', '/suport/' . $ticketId . '/respondre', [
        '_token' => $token($web('GET', '/suport/' . $ticketId)['body']),
        'body' => '<p>Cal desar la data amb el format del calendari. Ho hem provat i va.</p>',
    ]);
    check('I contestar el client', $resposta['status'] === 302);
    $ticket = Db::one('SELECT * FROM support_tickets WHERE id = :id', ['id' => $ticketId]);
    check('La consulta queda resposta',
        (string) $ticket['status'] === 'answered' && (string) $ticket['last_sender'] === 'support');

    $llegida = $web('GET', '/admin/suport/' . $ticketId, [], 'santjordi.crosescolar.test');
    check('El client veu la resposta', str_contains(text($llegida['body']), 'format del calendari'));
    check('Però no la nota interna', !str_contains($llegida['body'], 'li falta el permís'));

    $seva = $web('POST', '/admin/suport/' . $ticketId . '/respondre', [
        '_token' => $token($llegida['body']),
        'body' => '<p>Doncs ara sí. Gràcies!</p>',
    ], 'santjordi.crosescolar.test');
    check('I hi pot tornar a escriure', $seva['status'] === 302);
    $ticket = Db::one('SELECT * FROM support_tickets WHERE id = :id', ['id' => $ticketId]);
    check('Cosa que la torna a obrir',
        (string) $ticket['status'] === 'open' && (string) $ticket['last_sender'] === 'client');

    $tancada = $web('POST', '/admin/suport/' . $ticketId . '/tancar', [
        '_token' => $token($web('GET', '/admin/suport/' . $ticketId, [], 'santjordi.crosescolar.test')['body']),
    ], 'santjordi.crosescolar.test');
    check('El client la pot donar per resolta', $tancada['status'] === 302);
    check('I queda tancada',
        (string) Db::val('SELECT status FROM support_tickets WHERE id = :id', ['id' => $ticketId], '') === 'closed');

    // Un tiquet d'un altre web no s'ha de poder llegir des d'aquest.
    $altre = Db::insert('support_tickets', [
        'reference' => 'S-' . date('Y') . '-999', 'subject' => 'Consulta d\'un altre cros',
        'slug' => 'unaltrecros', 'author_email' => 'algu@example.cat', 'status' => 'open',
        'priority' => 'normal', 'last_sender' => 'client', 'last_message_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    check('Un client no pot llegir la consulta d\'un altre',
        $web('GET', '/admin/suport/' . $altre, [], 'santjordi.crosescolar.test')['status'] === 404);
    Db::delete('support_tickets', 'id = :id', ['id' => $altre]);

    echo "\n== Suport: els departaments ==\n";
    $deps = $web('GET', '/suport/departaments');
    check('La pantalla de departaments respon', $deps['status'] === 200);
    $nouDep = $web('POST', '/suport/departaments/desar', [
        '_token' => $token($deps['body']), 'id' => '0', 'name' => 'Inscripcions i dorsals',
        'description' => 'Tot el que té a veure amb qui corre.', 'email' => 'inscripcions@crosescolar.test',
        'sort_order' => '20', 'active' => '1',
    ]);
    check('Se\'n pot crear un de nou', $nouDep['status'] === 302);
    $dep = Db::one("SELECT * FROM support_departments WHERE name = 'Inscripcions i dorsals'");
    check('Amb la seva adreça d\'avisos', (string) ($dep['email'] ?? '') === 'inscripcions@crosescolar.test');
    check('I el client el pot triar',
        str_contains($web('GET', '/admin/suport/nou', [], 'santjordi.crosescolar.test')['body'], 'Inscripcions i dorsals'));

    $desat = $web('POST', '/suport/departaments/desar', [
        '_token' => $token($web('GET', '/suport/departaments')['body']), 'id' => (string) (int) $dep['id'],
        'name' => 'Inscripcions i dorsals', 'description' => '', 'email' => 'aixòNoÉsUnCorreu',
        'sort_order' => '20', 'active' => '1',
    ]);
    check('Una adreça que no ho és no s\'accepta', $desat['status'] === 302
        && (string) Db::val('SELECT email FROM support_departments WHERE id = :id', ['id' => (int) $dep['id']], '')
           === 'inscripcions@crosescolar.test');

    Db::update('support_tickets', ['department_id' => (int) $dep['id']], 'id = :id', ['id' => $ticketId]);
    $fora = $web('POST', '/suport/departaments/' . (int) $dep['id'] . '/esborrar', [
        '_token' => $token($web('GET', '/suport/departaments')['body']),
    ]);
    check('En esborrar-ne un, la consulta no es perd', $fora['status'] === 302
        && Db::one('SELECT id FROM support_tickets WHERE id = :id', ['id' => $ticketId]) !== null);
    check('Només es queda sense departament',
        Db::val('SELECT department_id FROM support_tickets WHERE id = :id', ['id' => $ticketId]) === null);

    echo "\n== Pagament d'activació ==\n";
    // Dos sistemes de cobrament que no s'han de barrejar: aquest és el que la
    // plataforma cobra als seus clients.
    $planForm = $web('GET', '/configuracio/plan');
    check('Hi ha la pantalla del pla', $planForm['status'] === 200);
    $web('POST', '/configuracio/plan', array_merge(formData($planForm['body']), [
        '_token' => $token($planForm['body']),
        'plan_enabled' => '1',
        'plan_price_cents' => '30,00',
        'plan_vat_enabled' => '1',
        'plan_vat_rate' => '21',
        'plan_irpf_enabled' => '1',
        'plan_irpf_rate' => '15',
        'plan_name' => 'Activació del web',
    ]));
    $fiscals = $web('GET', '/configuracio/platform_billing');
    $web('POST', '/configuracio/platform_billing', array_merge(formData($fiscals['body']), [
        '_token' => $token($fiscals['body']),
        'platform_billing_entity' => 'Serveis Web del Penedès SL',
        'platform_billing_nif' => 'B99887766',
        'platform_billing_address' => 'Avinguda del Cros, 7',
        'platform_billing_postcode' => '08792',
        'platform_billing_town' => 'La Granada',
        'platform_billing_series' => 'A',
    ]));
    Settings::forget();
    Platform::boot($site);
    check('El pla queda actiu amb el seu preu base', Plan::enabled() && Plan::price() === 3000, (string) Plan::price());
    check('I les dades fiscals de la plataforma hi són', Invoice::complete());

    // El preu configurat és la base: l'IVA s'hi suma i l'IRPF s'hi resta.
    $imports = Plan::amounts();
    check('L\'IVA se suma sobre la base', $imports['vat'] === 630, (string) $imports['vat']);
    check('I l\'IRPF s\'hi resta', $imports['irpf'] === 450, (string) $imports['irpf']);
    check('El que es cobra és base + IVA − IRPF',
        $imports['total'] === 3180 && Plan::total() === 3180, (string) $imports['total']);

    // El client amaga el web i mira de tornar-lo a publicar.
    $amaga = $web('POST', '/admin/properament', [
        '_token' => $token($web('GET', '/admin/configuracio/coming_soon', [], 'santjordi.crosescolar.test')['body']),
        'enable' => '1',
    ], 'santjordi.crosescolar.test');
    check('El client pot amagar el web sempre', $amaga['status'] === 302);

    $activacio = $web('GET', '/admin/activacio', [], 'santjordi.crosescolar.test');
    check('Té la pantalla d\'activació', $activacio['status'] === 200, 'estat ' . $activacio['status']);
    check('Amb el total que pagarà', str_contains(text($activacio['body']), '31,80'));
    check('I el desglossament a la vista',
        str_contains(text($activacio['body']), 'base 30,00') && str_contains(text($activacio['body']), 'IRPF'));
    check('I dient que no barregi els dos sistemes',
        str_contains(text($activacio['body']), 'Això no són els vostres cobraments'));
    check('Sense Stripe, no es pot pagar encara',
        str_contains(text($activacio['body']), 'encara no està disponible'));

    // L'avís de dalt de tot, que és el que veu qui entra al panell.
    $panell = $web('GET', '/admin', [], 'santjordi.crosescolar.test');
    check('El panell avisa a dalt que el web no és públic',
        str_contains($panell['body'], 'publish-bar')
        && str_contains(text($panell['body']), 'El web encara no està publicat'));
    check('Amb el botó de publicar-lo', str_contains(text($panell['body']), 'Publicar web'));
    check('Que mena al pagament', str_contains($panell['body'], '/admin/activacio'));
    check('I sense Stripe no ofereix la pantalla de la targeta',
        !str_contains($panell['body'], '/admin/activacio/publicar'));
    $mirall = $web('GET', '/admin/activacio/publicar', [], 'santjordi.crosescolar.test');
    check('Que tampoc no es pot obrir a mà',
        $mirall['status'] === 302 && str_contains($mirall['headers'], '/admin/activacio'),
        'estat ' . $mirall['status']);

    $publica = $web('POST', '/admin/properament', [
        '_token' => $token($web('GET', '/admin/configuracio/coming_soon', [], 'santjordi.crosescolar.test')['body']),
        'enable' => '0',
    ], 'santjordi.crosescolar.test');
    check('Però no el pot publicar sense activar-lo',
        $publica['status'] === 302 && str_contains($publica['headers'], '/admin/activacio'), $publica['headers']);
    $tenantConfig = require $site . '/tenants/santjordi/config.php';
    $tenantPdo = Db::connect((array) $tenantConfig['db'] + ['charset' => 'utf8mb4']);
    check('I el web es queda amagat',
        (string) $tenantPdo->query("SELECT v FROM settings WHERE k = 'coming_soon'")->fetchColumn() === '1');
    $tenantPdo = null;

    // Es cobra: aquí, per transferència, que a les proves no hi ha cap Stripe.
    $instance = Instance::find($instanceId);
    $charge = Charge::forActivation($instance, [
        'name' => 'AFA Escola Sant Jordi', 'email' => 'laia@example.cat', 'nif' => 'G12345678',
        'address' => 'Carrer Major, 1', 'postcode' => '08870', 'town' => 'Sitges',
    ]);
    check('El cobrament es prepara amb el total a cobrar', (int) ($charge['total_cents'] ?? 0) === 3180,
        (string) ($charge['total_cents'] ?? 0));
    check('Amb la base, l\'IVA i la retenció desglossats',
        (int) $charge['subtotal_cents'] === 3000 && (int) $charge['tax_cents'] === 630
        && (int) $charge['irpf_cents'] === 450,
        $charge['subtotal_cents'] . ' + ' . $charge['tax_cents'] . ' − ' . $charge['irpf_cents']);
    check('I un codi propi de la plataforma',
        (bool) preg_match('/^A-\d{4}-\d{4}$/', (string) $charge['code']), (string) $charge['code']);
    check('Demanar-lo dues vegades no en fa dos',
        (int) Charge::forActivation($instance)['id'] === (int) $charge['id']);

    $fitxa = $web('GET', '/pagaments/' . (int) $charge['id']);
    check('Surt al panell de la plataforma', $fitxa['status'] === 200 && str_contains($fitxa['body'], (string) $charge['code']));
    $cobrat = $web('POST', '/pagaments/' . (int) $charge['id'] . '/accio', [
        '_token' => $token($fitxa['body']), 'action' => 'paid',
    ]);
    check('Es pot donar per pagat', $cobrat['status'] === 302);
    check('I el web queda activat', !empty(Instance::find($instanceId)['activated_at'] ?? null));
    $invoice = Invoice::forPayment((int) $charge['id']);
    check('Amb la factura emesa', $invoice !== null && str_starts_with((string) ($invoice['full_number'] ?? ''), 'A-'),
        (string) ($invoice['full_number'] ?? ''));

    $publica = $web('POST', '/admin/properament', [
        '_token' => $token($web('GET', '/admin/configuracio/coming_soon', [], 'santjordi.crosescolar.test')['body']),
        'enable' => '0',
    ], 'santjordi.crosescolar.test');
    check('Ara sí que el pot publicar',
        $publica['status'] === 302 && !str_contains($publica['headers'], '/admin/activacio'));
    check('I l\'avís de dalt desapareix',
        !str_contains($web('GET', '/admin', [], 'santjordi.crosescolar.test')['body'], 'publish-bar'));

    $pdf = $web('GET', '/admin/activacio/factura/' . (int) $charge['id'], [], 'santjordi.crosescolar.test');
    check('El client se la pot descarregar',
        $pdf['status'] === 200 && str_contains($pdf['headers'], 'application/pdf'), 'estat ' . $pdf['status']);

    // I ara el que més importa: que cada factura porti les dades de qui toca.
    $fitxerPdf = sys_get_temp_dir() . '/cros-factura-' . bin2hex(random_bytes(3)) . '.pdf';
    file_put_contents($fitxerPdf, Invoice::pdf($invoice));
    $textPdf = trim((string) @shell_exec('pdftotext ' . escapeshellarg($fitxerPdf) . ' - 2>/dev/null'));
    @unlink($fitxerPdf);
    if ($textPdf === '') {
        echo "  (sense pdftotext: no es pot llegir el text de la factura)\n";
    } else {
        check('La factura de la plataforma porta les seves dades fiscals',
            str_contains($textPdf, 'Serveis Web del Penedès SL') && str_contains($textPdf, 'B99887766'));
        check('I el client hi surt com a client', str_contains($textPdf, 'AFA Escola Sant Jordi'));
        check('Amb la retenció d\'IRPF ben dita',
            str_contains($textPdf, 'Retenció IRPF') && str_contains($textPdf, '15 %'));
        check('I explicant qui la ingressa', str_contains($textPdf, 'ingressa el client a Hisenda'));
        check('Però el NIF del client no és el de l\'emissor',
            substr_count($textPdf, 'B99887766') === 1);
    }

    check('Al panell del client no hi surt cap dada fiscal de la plataforma',
        !str_contains($web('GET', '/admin/activacio', [], 'santjordi.crosescolar.test')['body'], 'B99887766'));

    // Es deixa el pla com estava perquè la resta de proves no se'n ressentin.
    $planForm = $web('GET', '/configuracio/plan');
    $camps = array_merge(formData($planForm['body']), ['_token' => $token($planForm['body'])]);
    unset($camps['plan_enabled']);
    $web('POST', '/configuracio/plan', $camps);
    Settings::forget();
    Platform::boot($site);
    check('El pagament d\'activació es pot desactivar', !Plan::enabled());

    echo "\n== Vigilància ==\n";
    $report = Health::run($site);
    check('Es miren les instàncies en marxa', $report['checked'] >= 1);
    check('I aquesta respon', !in_array('santjordi', $report['failing'], true));
    $instance = Instance::find($instanceId);
    check('Queda apuntat que va bé', (string) $instance['health'] === 'ok');
    check('Amb l\'hora del repàs', !empty($instance['health_checked_at']));

    // Es trenca la connexió de la instància a posta.
    $configFile = $site . '/tenants/santjordi/config.php';
    $good = (string) file_get_contents($configFile);
    file_put_contents($configFile, str_replace("'name' => '" . $prefix . "santjordi'", "'name' => 'no_existeix_aquesta'", $good));

    $report = Health::run($site);
    $instance = Instance::find($instanceId);
    check('Si la base de dades no hi és, es nota', (string) $instance['health'] === 'error');
    check('I es diu què passa', str_contains((string) $instance['health_error'], 'Base de dades'));
    check('Consta com a caiguda de nou', in_array('santjordi', $report['broke'], true));
    $alerts = static fn (string $action): int => (int) Db::val(
        'SELECT COUNT(*) FROM platform_activity WHERE action = :a AND subject_id = :id',
        ['a' => $action, 'id' => $instanceId],
        0
    );
    check('S\'avisa la superadministració', $alerts('health_error') === 1);

    Health::run($site);
    check('Però no s\'avisa dues vegades del mateix', $alerts('health_error') === 1);

    file_put_contents($configFile, $good);
    $report = Health::run($site);
    $instance = Instance::find($instanceId);
    check('Quan torna, també es nota', (string) $instance['health'] === 'ok');
    check('I s\'avisa que ha tornat', in_array('santjordi', $report['recovered'], true));
    check('Amb un avís, un de sol', $alerts('health_ok') === 1);

    $dashboard = $web('GET', '/');
    check('El tauler no dona l\'alarma si tot va bé', !str_contains($dashboard['body'], 'no responen'));

    echo "\n== El certificat del servidor ==\n";
    // El panell no renova res (per això cal ser root), però ha de saber com
    // està el certificat i quins amfitrions ha de cobrir.
    $amfitrions = Certificate::hosts($site);
    check('Sap quins noms ha de cobrir el certificat',
        in_array('crosescolar.test', $amfitrions, true)
        && in_array('admin.crosescolar.test', $amfitrions, true),
        implode(', ', $amfitrions));
    check('Amb el web de cada cros en marxa',
        in_array('santjordi.crosescolar.test', $amfitrions, true), implode(', ', $amfitrions));
    check('I el domini secundari', in_array('crosescolar.example', $amfitrions, true));

    check('Un comodí cobreix un subdomini',
        Certificate::covers(['*.crosescolar.test'], 'santjordi.crosescolar.test'));
    check('Però no el domini pelat',
        !Certificate::covers(['*.crosescolar.test'], 'crosescolar.test'));
    check('Ni un subdomini de dos pisos',
        !Certificate::covers(['*.crosescolar.test'], 'a.b.crosescolar.test'));
    check('Un nom exacte sí', Certificate::covers(['crosescolar.test'], 'crosescolar.test'));
    check('I el de l\'altre domini no', !Certificate::covers(['*.crosescolar.test'], 'x.crosescolar.example'));
    check('Es reconeix un certificat de comodí', Certificate::looksWildcard(['*.crosescolar.test']));

    $ordre = Certificate::command($site);
    check('L\'ordre per renovar-lo porta tots els noms',
        str_contains($ordre, '-d admin.crosescolar.test') && str_contains($ordre, '-d santjordi.crosescolar.test'),
        $ordre);
    // La del comodí ha de portar TOTS els dominis de la plataforma: amb la
    // meitat dels noms, el dia que es renovés deixaria l'altre sense cobrir.
    $comodi = Certificate::command($site, true);
    check('La del comodí porta tots els dominis',
        str_contains($comodi, "-d crosescolar.test -d '*.crosescolar.test'")
        && str_contains($comodi, "-d crosescolar.example -d '*.crosescolar.example'")
        && str_contains($comodi, "-d esportweb.test -d '*.esportweb.test'"),
        $comodi);
    check('I demana el nom del certificat, que no s\'endevina',
        str_contains($comodi, '--cert-name EL-NOM-DEL-CERTIFICAT'), $comodi);

    // Un servidor TLS de mentida per llegir-ne el certificat de debò.
    $certDir = $site . '/storage/tmp';
    @mkdir($certDir, 0775, true);
    $certPort = (int) (getenv('CROS_TEST_PORT_TLS') ?: 8443);
    exec(sprintf(
        'openssl req -x509 -newkey rsa:2048 -nodes -days 30 -subj /CN=prova.crosescolar.test '
        . '-addext %s -keyout %s -out %s 2>/dev/null',
        escapeshellarg('subjectAltName=DNS:prova.crosescolar.test,DNS:*.crosescolar.test'),
        escapeshellarg($certDir . '/prova.key'),
        escapeshellarg($certDir . '/prova.crt')
    ), $sortida, $estat);
    if ($estat === 0) {
        $tls = proc_open(
            sprintf('exec openssl s_server -quiet -accept %d -cert %s -key %s -www',
                $certPort, escapeshellarg($certDir . '/prova.crt'), escapeshellarg($certDir . '/prova.key')),
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $tubs
        );
        for ($i = 0; $i < 40; $i++) {
            $prova = @fsockopen('127.0.0.1', $certPort, $e, $missatge, 1);
            if ($prova !== false) { fclose($prova); break; }
            usleep(150000);
        }
        $llegit = Certificate::read('127.0.0.1', $certPort);
        check('Es llegeix el certificat que serveix el servidor',
            $llegit['error'] === '' && $llegit['ok'], $llegit['error']);
        check('Amb els dies que li queden',
            $llegit['days'] >= 28 && $llegit['days'] <= 30, (string) $llegit['days']);
        check('I els noms que cobreix',
            in_array('*.crosescolar.test', $llegit['names'], true), implode(', ', $llegit['names']));
        if (is_resource($tls)) {
            proc_terminate($tls);
            proc_close($tls);
        }
    } else {
        echo "  (sense openssl per fer el certificat de prova, es passa)\n";
    }
    $mort = Certificate::read('127.0.0.1', $certPort);
    check('Si no hi ha ningú escoltant, es diu clar', !$mort['ok'] && $mort['error'] !== '');

    // El tauler no ha d'obrir cap connexió: ensenya l'últim repàs de la
    // vigilància, i mentre no n'hi hagi cap no en diu res.
    Settings::set('platform_cert_status', '');
    Settings::forget();
    Platform::boot($site);
    $abansDeMirar = Certificate::status($site);
    check('Sense cap repàs fet, el tauler no diu res del certificat',
        ($abansDeMirar['checked_at'] ?? 'x') === '');
    $inici = microtime(true);
    Certificate::status($site);
    check('I no s\'espera per cap connexió', microtime(true) - $inici < 0.5,
        round((microtime(true) - $inici) * 1000) . ' ms');
    $tauler = $web('GET', '/');
    check('El tauler continua carregant', $tauler['status'] === 200);

    // Els avisos: un per llindar, i quan es renova es tornen a armar.
    $avisos = static fn (): int => (int) Db::val(
        "SELECT COUNT(*) FROM platform_activity WHERE action = 'cert_warning'", [], 0);
    $fals = static fn (int $dies): array => [
        'host' => 'crosescolar.test', 'ok' => $dies > 0, 'error' => '',
        'expires_at' => date('Y-m-d H:i:s', time() + $dies * 86400), 'days' => $dies,
        'issuer' => 'Prova', 'names' => ['*.crosescolar.test'], 'uncovered' => [],
        'checked_at' => date('Y-m-d H:i:s'),
    ];
    Settings::set('platform_cert_warned', '0');
    check('Amb molts dies per davant no s\'avisa ningú',
        Certificate::warn($site, $fals(45)) === '' && $avisos() === 0);
    check('A tres setmanes vista, sí', Certificate::warn($site, $fals(20)) !== '' && $avisos() === 1);
    check('I no es repeteix l\'endemà', Certificate::warn($site, $fals(19)) === '' && $avisos() === 1);
    check('Però quan queda una setmana torna a avisar',
        Certificate::warn($site, $fals(6)) !== '' && $avisos() === 2);
    check('I si ja ha caducat, també', Certificate::warn($site, $fals(-1)) !== '' && $avisos() === 3);
    check('En renovar-lo, els avisos es tornen a armar',
        Certificate::warn($site, $fals(89)) === '' && (int) Settings::get('platform_cert_warned') === 0);

    // El que deixa escrit el guió de renovació del cron de root.
    check('Sense cap renovació feta, no se\'n diu res', Certificate::renewal($site)['resultat'] === '');
    file_put_contents($site . '/storage/certificat.json', json_encode([
        'quan' => date('Y-m-d H:i:s'), 'resultat' => 'error', 'detall' => 'certbot no ha pogut connectar',
    ]));
    $renovacio = Certificate::renewal($site);
    check('I si ha fallat, el panell ho sap',
        $renovacio['resultat'] === 'error' && str_contains($renovacio['detall'], 'certbot'));
    check('El tauler ho ensenya',
        str_contains($web('GET', '/')['body'], 'renovació automàtica del certificat va fallar'));
    @unlink($site . '/storage/certificat.json');

    echo "\n== El DNS de la plataforma ==\n";
    // Amb un resolutor de mentida: les proves no han de sortir a internet ni
    // dependre de com estigui el DNS de ningú.
    $zona = [
        'crosescolar.test' => ['10.0.0.1'],
        'admin.crosescolar.test' => ['10.0.0.1'],
        'santjordi.crosescolar.test' => ['10.0.0.1'],
        'crosescolar.example' => ['10.0.0.1'],
        'admin.crosescolar.example' => ['10.0.0.1'],
        'esportweb.test' => ['10.0.0.1'],
        'admin.esportweb.test' => ['10.0.0.1'],
    ];
    // Amb comodí: qualsevol nom del domini respon.
    Dns::using(static function (string $host) use ($zona): array {
        if (isset($zona[$host])) {
            return $zona[$host];
        }
        return str_ends_with($host, '.crosescolar.test') ? ['10.0.0.1'] : [];
    });
    $dns = Dns::status($site, true);
    check('Amb comodí, el DNS no dona cap avís',
        $dns['wildcard'] && $dns['missing'] === [] && $dns['error'] === '',
        implode(', ', $dns['missing']));
    check('I sap a quina IP resol el domini', $dns['ips'] === ['10.0.0.1'], implode(', ', $dns['ips']));

    // Sense comodí: els que hi són van bé, però un cros nou no existiria.
    Dns::using(static fn (string $host): array => $zona[$host] ?? []);
    $dns = Dns::status($site, true);
    check('Sense comodí, es nota', !$dns['wildcard']);
    check('Però els cros que ja hi són continuen bé', $dns['missing'] === [], implode(', ', $dns['missing']));
    check('I es diu quin registre falta',
        str_contains(Dns::record($site), '*') && str_contains(Dns::record($site), '10.0.0.1'),
        Dns::record($site));
    check('El tauler ho avisa', str_contains($web('GET', '/')['body'], 'El DNS no té comodí'));

    // Un web que ha desaparegut del DNS, i un que apunta a un altre servidor.
    $trencada = $zona;
    unset($trencada['santjordi.crosescolar.test']);
    $trencada['admin.crosescolar.test'] = ['10.9.9.9'];
    Dns::using(static fn (string $host): array => $trencada[$host] ?? []);
    $dns = Dns::status($site, true);
    check('Un nom que no existeix, es diu',
        in_array('santjordi.crosescolar.test', $dns['missing'], true), implode(', ', $dns['missing']));
    check('I un que apunta a un altre servidor, també',
        in_array('admin.crosescolar.test', $dns['elsewhere'], true), implode(', ', $dns['elsewhere']));
    check('El tauler també ho ensenya',
        str_contains($web('GET', '/')['body'], 'no existeixen al DNS')
        || str_contains($web('GET', '/')['body'], 'Adreces que no existeixen'));

    // I si el domini no resol enlloc, no s'inventa res.
    Dns::using(static fn (string $host): array => []);
    $dns = Dns::status($site, true);
    check('Si el domini no resol, es diu clar', $dns['error'] !== '', $dns['error']);
    Dns::using(null);

    echo "\n== Còpies de seguretat ==\n";
    $report = Backup::run($site);
    check('Se\'n fa una de cada instància', in_array('santjordi', $report['done'], true));
    check('I ocupa alguna cosa', $report['bytes'] > 0);
    $copies = Backup::all('santjordi', $site);
    check('Queda desada a la seva carpeta', count($copies) === 1);
    check('Dins de la carpeta del client, no barrejada',
        str_contains(Backup::dir($site, 'santjordi'), '/backups/santjordi'));
    $instance = Instance::find($instanceId);
    check('Queda apuntat quan s\'ha fet', !empty($instance['backup_at']));
    check('I què ocupa', (int) $instance['backup_size'] > 0);

    $copy = new ZipArchive();
    $copy->open(Backup::dir($site, 'santjordi') . '/' . $copies[0]['name']);
    check('La còpia porta la base de dades del client',
        str_contains((string) $copy->getFromName('base-de-dades.sql'), 'Cros Escola Sant Jordi'));
    $copy->close();

    // Se'n guarden només les últimes.
    Backup::create($instanceId, $site);
    Backup::create($instanceId, $site);
    check('Només es guarden les que s\'ha dit', count(Backup::all('santjordi', $site)) === 2);

    $detail = $web('GET', '/instancies/' . $instanceId);
    check('El panell les ensenya', str_contains($detail['body'], 'Còpies de seguretat'));
    $name = Backup::all('santjordi', $site)[0]['name'];
    $got = $web('GET', '/instancies/' . $instanceId . '/copia?fitxer=' . rawurlencode($name));
    check('I se\'n pot descarregar una',
        $got['status'] === 200 && str_contains($got['headers'], 'application/zip'), 'estat ' . $got['status']);
    check('Un nom inventat no dona res',
        $web('GET', '/instancies/' . $instanceId . '/copia?fitxer=' . rawurlencode('../../platform.php'))['status'] === 404);

    // El tauler avisa si una instància fa dies que no es copia, i diu per què:
    // si és que el cron no passa, o si passa però la còpia falla.
    $pdo->prepare('UPDATE instances SET backup_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s', time() - 86400 * 5), $instanceId]);
    $pdo->exec("DELETE FROM platform_activity WHERE action IN ('backup_run', 'backup_failed')");
    $tauler = text($web('GET', '/')['body']);
    check('Una instància sense còpia de fa dies surt al tauler', str_contains($tauler, 'santjordi')
        && str_contains($tauler, 'fa dies que no es copia'));
    check('I si el cron no ha passat mai, ho diu', str_contains($tauler, 'no ha passat mai'), $tauler);

    $configCopia = $configFile . '.amagat';
    rename($configFile, $configCopia);
    $fallida = Backup::run($site);
    rename($configCopia, $configFile);
    check('Si la còpia falla, el cron ho diu', isset($fallida['failed']['santjordi']));
    check('I queda apuntat a la instància',
        Backup::lastError($instanceId, (string) Instance::find($instanceId)['backup_at']) !== '');
    $tauler = text($web('GET', '/')['body']);
    check('El tauler diu que el cron sí que passa', str_contains($tauler, 'sí que passa'), $tauler);
    check('I per què falla la d\'aquesta instància', str_contains($tauler, 'No hi ha la carpeta de la instància'), $tauler);

    Backup::run($site);
    check('Quan torna a sortir bé, l\'avís desapareix',
        !str_contains(text($web('GET', '/')['body']), 'fa dies que no es copia'));

    echo "\n== Portar un cros que ja existia ==\n";
    // El cros «antic» és el que ja tenim: se li posen textos amb l'adreça de
    // sempre i un fitxer pujat, i se'n fa el paquet com ho faria el seu panell.
    $tenant = require $configFile;
    $tenantPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    $tricky = "Primera línia;\nsegona línia\n\n-- això no és un comentari\nI unes 'cometes'.";
    $tenantPdo->prepare("INSERT INTO settings (k, v) VALUES ('home_intro', ?)
        ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([
        '<p>Mireu la <a href="http://santjordi.crosescolar.test/categories-i-premis">llista</a> '
        . 'i la <img src="http://santjordi.crosescolar.test/uploads/fotos/cartell.jpg"></p>',
    ]);
    $tenantPdo->prepare("INSERT INTO settings (k, v) VALUES ('rules_text', ?)
        ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([$tricky]);
    $tenantPdo = null;
    @mkdir($site . '/tenants/santjordi/uploads/fotos', 0775, true);
    file_put_contents($site . '/tenants/santjordi/uploads/fotos/cartell.jpg', 'una foto de mentida');

    $package = Backup::create($instanceId, $site);
    check('El cros antic en fa el paquet', $package['ok'], $package['error']);
    $manifest = Importer::inspect($package['file']);
    check('Que es reconeix com un cros escolar', ($manifest['format'] ?? '') === 'cros-escolar-export');
    check('Amb l\'adreça d\'on ve', ($manifest['base_url'] ?? '') === 'http://santjordi.crosescolar.test');
    check('I amb el fitxer pujat a dins', (int) ($manifest['uploads']['files'] ?? 0) >= 1);

    // Un paquet que no ho és no s'accepta.
    $fake = sys_get_temp_dir() . '/cros-fals-' . bin2hex(random_bytes(3)) . '.zip';
    $fakeZip = new ZipArchive();
    $fakeZip->open($fake, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $fakeZip->addFromString('qualsevol.txt', 'res de res');
    $fakeZip->close();
    $rejected = '';
    try {
        Importer::inspect($fake);
    } catch (Throwable $e) {
        $rejected = $e->getMessage();
    }
    check('Un ZIP qualsevol es rebutja', str_contains($rejected, 'migracio.json'));

    // I ara el porten al sistema nou, pujant-lo des del panell.
    $form = $web('GET', '/instancies/nova');
    check('El formulari deixa pujar el paquet', str_contains($form['body'], 'name="migration"'));
    $imported = $web('POST', '/instancies/nova', [
        '_token' => $token($form['body']),
        'slug' => 'lagranada', 'site_name' => 'Nom que no es farà servir', 'town' => 'La Granada',
        'admin_name' => 'Marta Puig', 'admin_email' => 'marta@example.cat', 'listed' => '1',
        'migration' => new CURLFile($package['file'], 'application/zip', basename($package['file'])),
    ]);
    $granada = Db::one("SELECT * FROM instances WHERE slug = 'lagranada'");
    $why = '';
    if ($granada === null) {
        // Si no ha anat bé, val més ensenyar què deia el panell que no pas «no».
        preg_match('/alert--error">([^<]*)/', $web('GET', '/instancies/nova')['body'], $m);
        $why = html_entity_decode(trim((string) ($m[1] ?? '')), ENT_QUOTES);
    }
    check('La instància es crea amb el cros importat',
        $imported['status'] === 302 && $granada !== null, $why !== '' ? $why : 'estat ' . $imported['status']);
    if ($granada === null) {
        throw new RuntimeException('Sense instància importada no es pot continuar: ' . $why);
    }

    $new = require $site . '/tenants/lagranada/config.php';
    $newPdo = Db::connect((array) $new['db'] + ['charset' => 'utf8mb4']);
    $value = static fn (string $key): string => (string) $newPdo->query(
        "SELECT v FROM settings WHERE k = " . $newPdo->quote($key)
    )->fetchColumn();

    check('Hi arriba la configuració del cros antic', $value('site_name') === 'Cros Escola Sant Jordi');
    check('I les seves inscripcions',
        (int) $newPdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn()
        === (int) ($manifest['rows']['registrations'] ?? -1));
    check('Els textos amb punt i coma i guions arriben igual', $value('rules_text') === $tricky);
    check('Els enllaços apunten a la casa nova',
        str_contains($value('home_intro'), '://lagranada.crosescolar.test/categories-i-premis')
        && str_contains($value('home_intro'), '://lagranada.crosescolar.test/uploads/fotos/cartell.jpg')
        && !str_contains($value('home_intro'), 'santjordi.crosescolar.test'),
        $value('home_intro'));
    check('Els fitxers pujats també hi són',
        is_file($site . '/tenants/lagranada/uploads/fotos/cartell.jpg'));
    check('Amb el seu contingut',
        (string) @file_get_contents($site . '/tenants/lagranada/uploads/fotos/cartell.jpg') === 'una foto de mentida');
    check('La plataforma n\'agafa el nom de debò', (string) $granada['site_name'] === 'Cros Escola Sant Jordi');
    $newPdo = null;

    // El web nou funciona i qui hi entrava, hi continua entrant.
    file_put_contents($site . '/tenants/lagranada/config.php', str_replace(
        "'base_url' => 'https://lagranada.crosescolar.test'",
        "'base_url' => 'http://lagranada.crosescolar.test'",
        (string) file_get_contents($site . '/tenants/lagranada/config.php')
    ));
    $moved = $web('GET', '/', [], 'lagranada.crosescolar.test');
    check('El web importat es serveix', $moved['status'] === 200, 'estat ' . $moved['status']);
    $web('GET', '/admin/sortir', [], 'lagranada.crosescolar.test');
    $login = $web('GET', '/admin/acces', [], 'lagranada.crosescolar.test');
    $entered = $web('POST', '/admin/acces', [
        '_token' => $token($login['body']), 'email' => 'laia@example.cat', 'password' => 'unaAltraClauLlarga1',
    ], 'lagranada.crosescolar.test');
    check('I qui gestionava el cros antic hi entra amb la mateixa contrasenya',
        $entered['status'] === 302 && !str_contains($entered['headers'], '/admin/acces'));

    check('El cros antic no s\'ha tocat',
        is_file($site . '/tenants/santjordi/config.php')
        && is_file($site . '/tenants/santjordi/uploads/fotos/cartell.jpg'));

    @unlink($fake);

    echo "\n== Importar dades a sobre d'una instància que ja hi és ==\n";
    // El cros antic canvia, se'n fa un paquet nou i s'escriu a sobre del web
    // que ja existeix: el que hi havia es perd i hi queda el del paquet.
    $santPdo = Db::connect((array) $tenant['db'] + ['charset' => 'utf8mb4']);
    $santPdo->prepare("INSERT INTO settings (k, v) VALUES ('site_name', ?)
        ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute(['Cros de la reimportació']);
    $santPdo->prepare("INSERT INTO settings (k, v) VALUES ('results_intro', ?)
        ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute(['<p>Text que ha de viatjar.</p>']);
    $santPdo = null;
    $segon = Backup::create($instanceId, $site);
    check('Se\'n fa un paquet nou', $segon['ok'], $segon['error']);

    $granadaId = (int) $granada['id'];
    $fitxa = $web('GET', '/instancies/' . $granadaId);
    check('La fitxa ofereix importar-hi dades',
        str_contains($fitxa['body'], 'value="import"')
        && str_contains($fitxa['body'], 'name="migration"')
        && str_contains(text($fitxa['body']), 'Importar les dades d\'un altre web'));

    // Sense escriure el nom, no es toca res.
    $web('POST', '/instancies/' . $granadaId . '/accio', [
        '_token' => $token($fitxa['body']), 'action' => 'import', 'confirm' => 'una-altra-cosa',
        'migration' => new CURLFile($segon['file'], 'application/zip', basename($segon['file'])),
    ]);
    $abans = Db::connect((array) (require $site . '/tenants/lagranada/config.php')['db'] + ['charset' => 'utf8mb4']);
    check('Sense confirmar, no s\'escriu res a sobre',
        (string) $abans->query("SELECT v FROM settings WHERE k = 'site_name'")->fetchColumn()
        !== 'Cros de la reimportació');
    $abans = null;

    $copiesAbans = count(Backup::all('lagranada', $site));
    $fitxa = $web('GET', '/instancies/' . $granadaId);
    $fet = $web('POST', '/instancies/' . $granadaId . '/accio', [
        '_token' => $token($fitxa['body']), 'action' => 'import', 'confirm' => 'lagranada',
        'migration' => new CURLFile($segon['file'], 'application/zip', basename($segon['file'])),
    ]);
    check('Confirmant-ho, s\'importa', $fet['status'] === 302, 'estat ' . $fet['status']);

    $granadaPdo = Db::connect((array) (require $site . '/tenants/lagranada/config.php')['db'] + ['charset' => 'utf8mb4']);
    $valor = static fn (string $key): string => (string) $granadaPdo->query(
        "SELECT v FROM settings WHERE k = " . $granadaPdo->quote($key)
    )->fetchColumn();
    check('Les dades són les del paquet', $valor('site_name') === 'Cros de la reimportació', $valor('site_name'));
    check('Amb els textos que portava', $valor('results_intro') === '<p>Text que ha de viatjar.</p>');
    check('I els enllaços apunten a aquest web',
        str_contains($valor('home_intro'), '://lagranada.crosescolar.test/categories-i-premis')
        && !str_contains($valor('home_intro'), 'santjordi.crosescolar.test'),
        $valor('home_intro'));
    check('Qui el gestionava hi continua constant',
        (int) $granadaPdo->query("SELECT COUNT(*) FROM users WHERE email = 'marta@example.cat'
            AND role = 'admin' AND active = 1")->fetchColumn() === 1);
    $granadaPdo = null;

    check('Abans de tocar res se n\'ha fet una còpia', count(Backup::all('lagranada', $site)) > $copiesAbans,
        $copiesAbans . ' → ' . count(Backup::all('lagranada', $site)));
    check('La plataforma es posa al dia tota sola',
        (string) Instance::find($granadaId)['site_name'] === 'Cros de la reimportació');
    check('El web d\'on venia el paquet no s\'ha tocat',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 200);

    echo "\n== D'una sol·licitud a una instància ==\n";
    $home = $web('GET', '/', [], 'crosescolar.test');
    $web('POST', '/sollicitud', [
        '_token' => $token($home['body']),
        'entity' => 'AFA Escola del Bosc', 'town' => 'Reus', 'contact_name' => 'Pau Roca',
        'contact_email' => 'pau@example.cat', 'contact_phone' => '600333444',
        'slug' => 'elbosc', 'domain' => 'crosescolar.example', 'consent' => '1',
    ], 'crosescolar.test');
    $request = Db::one("SELECT * FROM instance_requests WHERE contact_email = 'pau@example.cat'");
    check('La sol·licitud arriba al panell', $request !== null);
    check('Amb el domini que ha demanat', (string) $request['domain'] === 'crosescolar.example');
    $list = $web('GET', '/sollicituds');
    check('I surt a la llista', str_contains($list['body'], 'AFA Escola del Bosc'));
    check('Amb l\'adreça sencera', str_contains($list['body'], 'elbosc.crosescolar.example'));

    $prefilled = $web('GET', '/instancies/nova?peticio=' . (int) $request['id']);
    check('El formulari ve omplert amb les seves dades',
        str_contains($prefilled['body'], 'value="elbosc"') && str_contains($prefilled['body'], 'pau@example.cat'));

    $web('POST', '/instancies/nova', [
        '_token' => $token($prefilled['body']), 'request_id' => (int) $request['id'],
        'slug' => 'elbosc', 'site_name' => 'Cros Escola del Bosc', 'town' => 'Reus',
        'domain' => 'crosescolar.example', 'admin_name' => 'Pau Roca', 'admin_email' => 'pau@example.cat',
        'client_id' => '0', 'listed' => '1',
    ]);
    $bosc = Db::one("SELECT * FROM instances WHERE slug = 'elbosc'");
    $request = Db::one('SELECT * FROM instance_requests WHERE id = :id', ['id' => $request['id']]);
    check('En crear-la, la sol·licitud queda aprovada', (string) $request['status'] === 'approved');
    check('I apunta a la instància', (int) $request['instance_id'] === (int) $bosc['id']);
    $clientRow = Db::one("SELECT * FROM clients WHERE contact_email = 'pau@example.cat'");
    check('S\'ha creat la fitxa del client', $clientRow !== null);
    check('I la instància és seva', (int) $bosc['client_id'] === (int) $clientRow['id']);

    echo "\n== Més d'un domini ==\n";
    check('La instància es queda al domini demanat', (string) $bosc['domain'] === 'crosescolar.example');
    $boscConfig = (string) file_get_contents($site . '/tenants/elbosc/config.php');
    check('I el seu web ho sap',
        str_contains($boscConfig, "'base_url' => 'https://elbosc.crosescolar.example'"));

    $other = $web('GET', '/', [], 'elbosc.crosescolar.test');
    check('Demanat per l\'altre domini, hi mena',
        $other['status'] === 301 && str_contains($other['headers'], 'elbosc.crosescolar.example'),
        'estat ' . $other['status']);
    check('La portada de la plataforma surt pels dos dominis',
        $web('GET', '/', [], 'crosescolar.test')['status'] === 200);
    $secondary = $web('GET', '/', [], 'crosescolar.example');
    check('Cada domini es queda a casa seva', $secondary['status'] === 200, 'estat ' . $secondary['status']);
    $ambWww = $web('GET', '/', [], 'www.crosescolar.example');
    check('I amb «www» hi mena sense sortir del domini',
        $ambWww['status'] === 301 && str_contains($ambWww['headers'], '//crosescolar.example'),
        'estat ' . $ambWww['status']);

    // Canviar de domini un web que ja funciona.
    $detail = $web('GET', '/instancies/' . (int) $bosc['id']);
    check('La fitxa deixa triar el domini', str_contains($detail['body'], 'Canviar de domini'));
    $moved = $web('POST', '/instancies/' . (int) $bosc['id'] . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'domain', 'domain' => 'crosescolar.test',
    ]);
    $bosc = Instance::find((int) $bosc['id']);
    check('Es pot canviar de domini', $moved['status'] === 302 && (string) $bosc['domain'] === 'crosescolar.test');
    check('I la seva configuració canvia',
        str_contains((string) file_get_contents($site . '/tenants/elbosc/config.php'),
            "'base_url' => 'https://elbosc.crosescolar.test'"));
    check('Amb còpia de la configuració anterior', is_file($site . '/tenants/elbosc/config.php.bak'));
    $back = $web('GET', '/', [], 'elbosc.crosescolar.example');
    check('Ara és l\'adreça vella la que hi mena',
        $back['status'] === 301 && str_contains($back['headers'], 'elbosc.crosescolar.test'));

    echo "\n== El mapa del web de la plataforma ==\n";
    $mapa = $web('GET', '/sitemap.xml', [], 'crosescolar.test');
    check('La plataforma té mapa del web',
        $mapa['status'] === 200 && str_contains($mapa['body'], '<urlset'), 'estat ' . $mapa['status']);
    check('Amb la seva portada', str_contains($mapa['body'], '<loc>https://crosescolar.test/</loc>'));
    $directori = Instance::directory();
    $hiSurten = $directori !== [];
    foreach ($directori as $fitxa) {
        $hiSurten = $hiSurten && str_contains($mapa['body'], Instance::url($fitxa, $site));
    }
    check('I el web de cada cros publicat, perquè Google els trobi',
        $hiSurten, count($directori) . ' al llistat');
    $robotsPlataforma = $web('GET', '/robots.txt', [], 'crosescolar.test');
    check('El robots.txt diu on és el mapa',
        str_contains($robotsPlataforma['body'], 'Sitemap: https://crosescolar.test/sitemap.xml'));
    check('I no deixa rastrejar les sol·licituds enviades',
        str_contains($robotsPlataforma['body'], 'Disallow: /sollicitud/'));

    echo "\n== El llistat de curses de la plataforma ==\n";
    // El Bosc torna a l'altre domini i se'l fa sortir al llistat: el seu quadre
    // ha de portar al seu web de debò, no al domini principal.
    $boscId = (int) $bosc['id'];
    $detail = $web('GET', '/instancies/' . $boscId);
    $web('POST', '/instancies/' . $boscId . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'domain', 'domain' => 'crosescolar.example',
    ]);
    Db::update('instances', ['status' => 'active', 'published' => 1, 'listed' => 1], 'id = :id', ['id' => $boscId]);
    $quadre = static function (string $html, string $slug): string {
        // El quadre d'una cursa: des del seu «<a class="cros-card"» fins al següent.
        // El primer tros és tot el que hi ha abans del primer quadre, dades
        // estructurades incloses, i allà també hi surten les adreces.
        $trossos = array_slice(explode('<a class="cros-card', $html), 1);
        foreach ($trossos as $tros) {
            if (str_contains($tros, '//' . $slug . '.')) {
                return $tros;
            }
        }

        return '';
    };
    $llistat = $web('GET', '/', [], 'crosescolar.test');
    $delBosc = $quadre($llistat['body'], 'elbosc');
    check('El Bosc surt al llistat', $delBosc !== '');
    check('I el quadre porta al seu web de debò',
        str_contains($delBosc, 'href="https://elbosc.crosescolar.example"'), mb_substr(strip_tags($delBosc), 0, 120));
    check('Amb l\'adreça que s\'hi llegeix, també la seva',
        str_contains($delBosc, '>elbosc.crosescolar.example<')
        && !str_contains($delBosc, 'elbosc.crosescolar.test'));

    // Una instància d'abans dels dos dominis no té el domini a la fitxa: el
    // treu de la configuració del seu web en repassar-la.
    Db::update('instances', ['domain' => null], 'id = :id', ['id' => $boscId]);
    Instance::sync($boscId, $site);
    check('A les d\'abans, el domini surt de la configuració del seu web',
        (string) Instance::find($boscId)['domain'] === 'crosescolar.example',
        (string) Instance::find($boscId)['domain']);

    // La imatge de fons de la portada de cada web, al seu quadre.
    $boscConfig = require $site . '/tenants/elbosc/config.php';
    $boscPdo = Db::connect((array) $boscConfig['db'] + ['charset' => 'utf8mb4']);
    @mkdir($site . '/tenants/elbosc/uploads/portada', 0775, true);
    file_put_contents($site . '/tenants/elbosc/uploads/portada/el bosc.jpg', 'una foto de mentida');
    $boscPdo->prepare("INSERT INTO settings (k, v) VALUES ('hero_image', ?)
        ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute(['portada/el bosc.jpg']);
    // Ha de ser un web publicat de debò: en repassar-lo, la plataforma ho mira.
    $boscPdo->exec("INSERT INTO settings (k, v) VALUES ('coming_soon', '0')
        ON DUPLICATE KEY UPDATE v = VALUES(v)");
    $llistat = $web('GET', '/', [], 'crosescolar.test');
    check('Sense repassar el web, encara no la coneix',
        !str_contains($quadre($llistat['body'], 'elbosc'), 'cros-card__image'));
    Instance::sync($boscId, $site);
    $delBosc = $quadre($web('GET', '/', [], 'crosescolar.test')['body'], 'elbosc');
    check('En repassar-lo, el quadre porta la imatge de la seva portada',
        str_contains($delBosc,
            '<img class="cros-card__image" src="https://elbosc.crosescolar.example/uploads/portada/el%20bosc.jpg"'),
        mb_substr($delBosc, 0, 300));
    check('Amb l\'ombra perquè l\'etiqueta es llegeixi a sobre', str_contains($delBosc, 'cros-card__top--image'));

    unlink($site . '/tenants/elbosc/uploads/portada/el bosc.jpg');
    Instance::sync($boscId, $site);
    check('Una imatge que ja no hi és no deixa un forat al llistat',
        !str_contains($quadre($web('GET', '/', [], 'crosescolar.test')['body'], 'elbosc'), 'cros-card__image'));
    check('Ni s\'accepta un camí que surti de la carpeta',
        Instance::heroImage($site . '/tenants/elbosc', '../config.php') === null
        && Instance::heroImage($site . '/tenants/elbosc', 'https://altra.example/foto.jpg') === null);
    $boscPdo = null;

    // Quan el client la canvia des del seu panell, la plataforma ho sap de
    // seguida, sense esperar el repàs de la nit.
    $web('GET', '/admin/sortir', [], 'lagranada.crosescolar.test');
    $acces = $web('GET', '/admin/acces', [], 'lagranada.crosescolar.test');
    $web('POST', '/admin/acces', [
        '_token' => $token($acces['body']), 'email' => 'laia@example.cat', 'password' => 'unaAltraClauLlarga1',
    ], 'lagranada.crosescolar.test');
    $foto = sys_get_temp_dir() . '/cros-portada-' . bin2hex(random_bytes(3)) . '.jpg';
    $imatge = imagecreatetruecolor(320, 180);
    imagefilledrectangle($imatge, 0, 0, 320, 180, imagecolorallocate($imatge, 40, 110, 60));
    imagejpeg($imatge, $foto, 80);
    imagedestroy($imatge);
    $portada = $web('GET', '/admin/configuracio/home', [], 'lagranada.crosescolar.test');
    $desada = $web('POST', '/admin/configuracio/home', array_merge(formData($portada['body']), [
        'hero_image' => new CURLFile($foto, 'image/jpeg', 'portada.jpg'),
    ]), 'lagranada.crosescolar.test');
    @unlink($foto);
    $granadaImatge = (string) Db::val("SELECT hero_image FROM instances WHERE slug = 'lagranada'", [], '');
    check('El client desa una imatge nova al seu panell', $desada['status'] === 302, 'estat ' . $desada['status']);
    check('I la plataforma la té al moment', $granadaImatge !== '' && is_file($site . '/tenants/lagranada/uploads/' . $granadaImatge),
        $granadaImatge !== '' ? $granadaImatge : 'cap');

    echo "\n== La pàgina de contacte de la plataforma ==\n";
    // La resposta del captcha viu a la sessió del servidor, com ha de ser. Les
    // proves la hi van a buscar: la mateixa galeta que porta curl diu quin
    // fitxer de sessió és.
    $respostaCaptcha = static function (string $host = 'crosescolar.test') use ($jar): string {
        // curl desa una galeta per amfitrió, i les que són «HttpOnly» les
        // escriu amb aquest prefix davant del domini.
        $cookie = '';
        foreach (@file($jar) ?: [] as $line) {
            $parts = explode("\t", trim($line));
            $domain = preg_replace('/^#HttpOnly_/', '', $parts[0] ?? '');
            if (count($parts) >= 7 && $parts[5] === 'cros_session' && ltrim((string) $domain, '.') === $host) {
                $cookie = $parts[6];
            }
        }
        foreach (array_unique([session_save_path() ?: '', '/var/lib/php/sessions', sys_get_temp_dir()]) as $dir) {
            $file = rtrim($dir, '/') . '/sess_' . $cookie;
            if ($cookie !== '' && $dir !== '' && is_file($file)) {
                $raw = (string) @file_get_contents($file);
                if (preg_match('/"platform-contact";a:\d+:\{[^}]*?s:6:"answer";s:\d+:"([^"]+)"/', $raw, $m)) {
                    return $m[1];
                }
            }
        }

        return '';
    };
    $missatges = static fn (): int => (int) Db::val('SELECT COUNT(*) FROM platform_contacts', [], 0);
    Platform::migrate();
    Db::q('DELETE FROM platform_contacts');

    $pagina = $web('GET', '/contacte', [], 'crosescolar.test');
    check('Hi ha la pàgina de contacte', $pagina['status'] === 200, 'estat ' . $pagina['status']);
    check('Amb el títol i l\'entradeta del domini', str_contains(text($pagina['body']), 'Parlem-ne')
        && str_contains(text($pagina['body']), 'Escriviu-nos i us respondrem'));
    $tots = true;
    foreach (['name', 'entity', 'email', 'phone', 'message', 'privacy', 'news', 'captcha'] as $camp) {
        $tots = $tots && str_contains($pagina['body'], 'name="' . $camp . '"');
    }
    check('Amb tots els camps: nom, entitat, correu, telèfon, missatge, privadesa i captcha', $tots);
    check('El captcha és una imatge feta aquí mateix',
        preg_match('#<img class="captcha__image" src="[^"]*/contacte/captcha\?#', $pagina['body']) === 1
        && !preg_match('#google|recaptcha|hcaptcha#i', $pagina['body']));
    check('La casella de privadesa porta a la política',
        str_contains($pagina['body'], 'href="' . 'http://crosescolar.test/privadesa"')
        || str_contains($pagina['body'], '/privadesa" target="_blank"'));
    check('El menú i el peu hi porten', substr_count($pagina['body'], '/contacte"') >= 2);

    // Sense extensió a l'adreça: el servidor integrat de PHP 8.2 pren per
    // fitxer tot el que acaba en «.png» i no ho passa a l'aplicació.
    $imatge = $web('GET', '/contacte/captcha', [], 'crosescolar.test');
    check('La imatge del captcha es dibuixa',
        $imatge['status'] === 200 && str_starts_with($imatge['body'], "\x89PNG")
        && str_contains(strtolower($imatge['headers']), 'image/png'), 'estat ' . $imatge['status']);
    check('I no es guarda a cap memòria cau', str_contains(strtolower($imatge['headers']), 'no-store'));
    $resposta = $respostaCaptcha();
    check('La resposta és a la sessió del servidor', strlen($resposta) === 5, $resposta !== '' ? $resposta : 'no s\'ha trobat');

    $dades = [
        'name' => 'Núria Vidal', 'entity' => 'Club Atlètic de Prova', 'email' => 'nuria@example.cat',
        'phone' => '+34 600 11 22 33', 'message' => "Hola! Voldríem muntar la cursa de primavera.\nEns truqueu?",
        'privacy' => '1', 'news' => '1',
    ];
    $envia = static function (array $canvis) use ($web, $token, $dades): array {
        $form = $web('GET', '/contacte', [], 'crosescolar.test');

        return ['form' => $form, 'fields' => array_merge(['_token' => $token($form['body'])], $dades, $canvis)];
    };

    // Un codi equivocat no passa.
    $intent = $envia([]);
    sleep(3);
    $mal = $web('POST', '/contacte', $intent['fields'] + ['captcha' => 'XXXXX'], 'crosescolar.test');
    check('Amb el codi equivocat, no es desa res', $missatges() === 0);
    check('I es diu per què', str_contains(text($mal['body']), 'El codi de la imatge no és correcte'));
    check('Sense perdre el que s\'havia escrit', str_contains($mal['body'], 'Club Atlètic de Prova'));

    // Massa de pressa, encara que el codi sigui bo: és un robot.
    $intent = $envia([]);
    $web('POST', '/contacte', $intent['fields'] + ['captcha' => $respostaCaptcha()], 'crosescolar.test');
    check('Enviat en el mateix segon que es rep, no es desa', $missatges() === 0);

    // El parany per a robots: rep el «gràcies» però no es desa res.
    $intent = $envia(['website_url' => 'http://spam.example']);
    $codi = $respostaCaptcha();
    sleep(3);
    $parany = $web('POST', '/contacte', $intent['fields'] + ['captcha' => $codi], 'crosescolar.test');
    check('Qui cau al parany no deixa res a la safata', $parany['status'] === 302 && $missatges() === 0);

    // Sense acceptar la privadesa, no.
    $intent = $envia(['privacy' => '']);
    $codi = $respostaCaptcha();
    sleep(3);
    $web('POST', '/contacte', $intent['fields'] + ['captcha' => $codi], 'crosescolar.test');
    check('Sense acceptar la privadesa, no es desa', $missatges() === 0);

    // I ara bé.
    $intent = $envia([]);
    $codi = $respostaCaptcha();
    sleep(3);
    $bo = $web('POST', '/contacte', $intent['fields'] + ['captcha' => strtolower($codi)], 'crosescolar.test');
    $desat = Db::one('SELECT * FROM platform_contacts ORDER BY id DESC LIMIT 1');
    check('Amb tot bé, el missatge es desa', $bo['status'] === 302 && $desat !== null, 'estat ' . $bo['status']);
    check('Amb totes les dades', $desat !== null && $desat['name'] === 'Núria Vidal'
        && $desat['entity'] === 'Club Atlètic de Prova' && $desat['email'] === 'nuria@example.cat'
        && $desat['phone'] === '+34 600 11 22 33' && str_contains((string) $desat['message'], 'cursa de primavera')
        && (int) $desat['news'] === 1 && !empty($desat['privacy_at']));
    check('I sap de quin domini ve', ($desat['domain'] ?? '') === 'crosescolar.test', (string) ($desat['domain'] ?? ''));
    check('Qui l\'ha enviat veu que ha arribat',
        str_contains(text($web('GET', '/contacte', [], 'crosescolar.test')['body']), 'Hem rebut el vostre missatge'));
    $log = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('I se n\'avisa la plataforma per correu', str_contains($log, 'Contacte: Núria Vidal'));

    // El mateix codi no serveix dues vegades.
    $intent = $envia([]);
    sleep(3);
    $web('POST', '/contacte', $intent['fields'] + ['captcha' => $codi], 'crosescolar.test');
    check('Un codi ja gastat no torna a servir', $missatges() === 1);

    // Una mateixa adreça no pot omplir la safata.
    for ($i = 0; $i < Contact::PER_HOUR; $i++) {
        Db::insert('platform_contacts', ['name' => 'Repetit', 'email' => 'r@example.cat', 'message' => 'Hola hola hola',
            'privacy_at' => date('Y-m-d H:i:s'), 'status' => 'archived', 'ip' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s')]);
    }
    $intent = $envia([]);
    $codi = $respostaCaptcha();
    sleep(3);
    $massa = $web('POST', '/contacte', $intent['fields'] + ['captcha' => $codi], 'crosescolar.test');
    check('Massa missatges seguits des de la mateixa adreça, no', $missatges() === 1 + Contact::PER_HOUR
        && str_contains(text($massa['body']), 'Ja ens heu escrit unes quantes vegades'));
    Db::q("DELETE FROM platform_contacts WHERE name = 'Repetit'");

    // L'altre domini té la seva pàgina, i el missatge ho recorda.
    $altre = $web('GET', '/contacte', [], 'esportweb.test');
    check('L\'altre domini també té la pàgina de contacte', $altre['status'] === 200);
    $botoFuncionalitats = $web('GET', '/funcionalitats', [], 'crosescolar.test');
    check('El botó «Pregunta\'ns el que et calgui» porta a la pàgina de contacte',
        preg_match('#href="[^"]*/contacte">Pregunta\'ns el que et calgui#', html_entity_decode($botoFuncionalitats['body'], ENT_QUOTES)) === 1);

    // Un domini que no la vulgui, no la té, ni al menú.
    Db::q("INSERT INTO settings (k, v) VALUES ('site:esportweb.test:contact_enabled', '0')
        ON DUPLICATE KEY UPDATE v = VALUES(v)");
    $tancada = $web('GET', '/contacte', [], 'esportweb.test');
    check('Desactivada en un domini, no hi és', $tancada['status'] === 404, 'estat ' . $tancada['status']);
    check('Ni surt al seu menú', !str_contains($web('GET', '/', [], 'esportweb.test')['body'], '/contacte"'));
    check('I a l\'altre domini continua igual', $web('GET', '/contacte', [], 'crosescolar.test')['status'] === 200);
    check('El botó de funcionalitats torna al correu',
        !str_contains($web('GET', '/funcionalitats', [], 'esportweb.test')['body'], '/contacte">Pregunta'));
    Db::q("UPDATE settings SET v = '1' WHERE k = 'site:esportweb.test:contact_enabled'");

    echo "\n== L'apartat de Contacte del panell ==\n";
    $web('POST', '/acces', ['_token' => $token($web('GET', '/acces')['body']),
        'email' => 'super@crosescolar.test', 'password' => 'unaClauBenLlarga1']);
    $safata = $web('GET', '/contacte');
    check('Hi ha l\'apartat de Contacte', $safata['status'] === 200, 'estat ' . $safata['status']);
    check('Amb el missatge que ha arribat', str_contains($safata['body'], 'Núria Vidal')
        && str_contains($safata['body'], 'crosescolar.test'));
    check('I la xifra dels que queden per llegir al menú',
        preg_match('#/contacte">\s*<svg[^>]*>.*?</svg>\s*Contacte\s*<span class="badge badge--amber">1</span>#s', $safata['body']) === 1);
    $id = (int) $desat['id'];
    $fitxa = $web('GET', '/contacte/' . $id);
    check('El missatge s\'obre sencer', $fitxa['status'] === 200
        && str_contains($fitxa['body'], '+34 600 11 22 33') && str_contains($fitxa['body'], 'Ens truqueu?')
        && str_contains($fitxa['body'], 'mailto:nuria@example.cat'));
    check('I en obrir-lo queda llegit', (string) Contact::find($id)['status'] === 'read');
    $web('POST', '/contacte/' . $id . '/accio', ['_token' => $token($fitxa['body']), 'action' => 'answered']);
    check('Es pot marcar com a respost', (string) Contact::find($id)['status'] === 'answered');
    $web('POST', '/contacte/' . $id . '/accio', ['_token' => $token($web('GET', '/contacte/' . $id)['body']), 'action' => 'archived']);
    check('Un cop arxivat, ja no és a la safata', !str_contains($web('GET', '/contacte')['body'], 'Núria Vidal'));
    check('Sinó als arxivats', str_contains($web('GET', '/contacte?estat=archived')['body'], 'Núria Vidal'));
    check('Es pot cercar', str_contains($web('GET', '/contacte?estat=archived&q=primavera')['body'], 'Núria Vidal')
        && !str_contains($web('GET', '/contacte?estat=archived&q=res-de-res')['body'], 'Núria Vidal'));
    $web('POST', '/contacte/' . $id . '/accio', ['_token' => $token($web('GET', '/contacte/' . $id)['body']), 'action' => 'delete']);
    check('I esborrar', Contact::find($id) === null);
    check('Els textos de la pàgina es configuren per domini',
        str_contains($web('GET', '/configuracio/contact')['body'], 'name="contact_intro"'));

    echo "\n== Les webs públiques, per a cercadors i assistents d'IA ==\n";
    $ld = static function (string $html): array {
        // Tots els nodes de totes les dades estructurades de la pàgina.
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $nodes = [];
        foreach ($m[1] as $json) {
            $data = json_decode(str_replace('<', '<', $json), true) ?: [];
            foreach ((array) ($data['@graph'] ?? [$data]) as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    };
    $tipus = static fn (array $nodes): array => array_map(static fn ($n): string => (string) ($n['@type'] ?? ''), $nodes);
    $setSite = static function (string $domain, string $key, string $value): void {
        Db::q('INSERT INTO settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            ['k' => 'site:' . $domain . ':' . $key, 'v' => $value]);
    };
    // El Bosc, amb data i poble, perquè surti com a esdeveniment esportiu.
    Db::update('instances', ['event_date' => date('Y-m-d', strtotime('+40 days')), 'town' => 'Reus'],
        'id = :id', ['id' => $boscId]);

    $portada = $web('GET', '/', [], 'crosescolar.test');
    $nodes = $ld($portada['body']);
    check('La portada diu qui hi ha al darrere i quin web és',
        in_array('Organization', $tipus($nodes), true) && in_array('WebSite', $tipus($nodes), true),
        implode(', ', $tipus($nodes)));
    check('I què és el servei, amb el que fa',
        in_array('SoftwareApplication', $tipus($nodes), true));
    $llista = array_values(array_filter($nodes, static fn ($n): bool => ($n['@type'] ?? '') === 'ItemList'))[0] ?? [];
    $cursa = null;
    foreach ((array) ($llista['itemListElement'] ?? []) as $element) {
        if (str_contains((string) ($element['item']['url'] ?? ''), 'elbosc.')) {
            $cursa = $element['item'];
        }
    }
    check('Les curses del llistat hi van com a esdeveniments esportius',
        ($cursa['@type'] ?? '') === 'SportsEvent' && ($cursa['location']['address']['addressLocality'] ?? '') === 'Reus'
        && !empty($cursa['startDate']), json_encode($cursa));
    check('Amb l\'adreça real de cada web', ($cursa['url'] ?? '') === 'https://elbosc.crosescolar.example');
    check('Els fragments poden ser sencers', str_contains($portada['body'], 'max-snippet:-1'));

    $contacte = $ld($web('GET', '/contacte', [], 'crosescolar.test')['body']);
    check('La pàgina de contacte es presenta com a tal', in_array('ContactPage', $tipus($contacte), true));
    $org = array_values(array_filter($contacte, static fn ($n): bool => ($n['@type'] ?? '') === 'Organization'))[0] ?? [];
    check('I diu on escriure', ($org['contactPoint']['url'] ?? '') === 'https://crosescolar.test/contacte');

    // El robots.txt fa entrar els assistents d'IA, cadascun amb el seu grup.
    $robots = $web('GET', '/robots.txt', [], 'crosescolar.test')['body'];
    check('El robots.txt dona pas als assistents que busquen',
        str_contains($robots, 'User-agent: OAI-SearchBot') && str_contains($robots, 'User-agent: Claude-SearchBot')
        && str_contains($robots, 'User-agent: PerplexityBot'));
    check('I als que aprenen', str_contains($robots, 'User-agent: GPTBot') && str_contains($robots, 'User-agent: ClaudeBot'));
    check('Cada grup amaga també els formularis', substr_count($robots, 'Disallow: /registre') === 3);
    check('I diu on és el resum per a ells', str_contains($robots, 'https://crosescolar.test/llms.txt'));

    $llms = $web('GET', '/llms.txt', [], 'crosescolar.test');
    check('Hi ha el /llms.txt', $llms['status'] === 200 && str_contains(strtolower($llms['headers']), 'text/markdown'),
        'estat ' . $llms['status']);
    check('Amb el nom del servei i què és', preg_match('/^# .+\n\n> .+/u', $llms['body']) === 1,
        mb_substr($llms['body'], 0, 120));
    check('Les pàgines principals, enllaçades', str_contains($llms['body'], '](https://crosescolar.test/funcionalitats)')
        && str_contains($llms['body'], '](https://crosescolar.test/contacte)'));
    check('Les curses que hi ha, amb la seva adreça', str_contains($llms['body'], '](https://elbosc.crosescolar.example)'));
    check('I on escriure', str_contains($llms['body'], '## Contacte'));
    check('Cada domini té el seu', str_contains($web('GET', '/llms.txt', [], 'esportweb.test')['body'], '](https://esportweb.test/'));

    // Només els que busquen: els que aprenen es queden a fora.
    $setSite('crosescolar.test', 'platform_ai_crawlers', 'search');
    $robots = $web('GET', '/robots.txt', [], 'crosescolar.test')['body'];
    // El grup dels que aprenen és l'últim i, ara, només diu «Disallow: /».
    $grupGpt = substr($robots, (int) strpos($robots, 'User-agent: GPTBot'));
    $grupGpt = substr($grupGpt, 0, (int) strpos($grupGpt, "\n\n"));
    check('Es pot deixar fora els que aprenen',
        str_contains($grupGpt, "Disallow: /\n") === false && str_ends_with($grupGpt, 'Disallow: /')
        && !str_contains($grupGpt, 'Allow: /'), $grupGpt);
    check('Sense tancar la porta als que busquen',
        preg_match('/User-agent: OAI-SearchBot.*?Allow: \//s', $robots) === 1);
    // Cap assistent: ni grup obert ni resum.
    $setSite('crosescolar.test', 'platform_ai_crawlers', 'none');
    $robots = $web('GET', '/robots.txt', [], 'crosescolar.test')['body'];
    check('O no deixar-hi entrar cap assistent',
        preg_match('/User-agent: OAI-SearchBot.*?Disallow: \/\n/s', $robots) === 1 && !str_contains($robots, 'llms.txt'));
    check('I llavors no hi ha resum', $web('GET', '/llms.txt', [], 'crosescolar.test')['status'] === 404);
    $setSite('crosescolar.test', 'platform_ai_crawlers', 'all');

    // En compartir l'enllaç, la imatge gran del banner.
    check('Sense banner, la targeta és la petita',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], '<meta name="twitter:card" content="summary">'));
    $setSite('crosescolar.test', 'platform_hero_image', 'plataforma/banner.jpg');
    $ambBanner = $web('GET', '/', [], 'crosescolar.test')['body'];
    check('Amb banner, la imatge gran per compartir',
        str_contains($ambBanner, 'property="og:image" content="') && str_contains($ambBanner, 'uploads/plataforma/banner.jpg"')
        && str_contains($ambBanner, '<meta name="twitter:card" content="summary_large_image">'));
    $setSite('crosescolar.test', 'platform_hero_image', '');

    // La verificació de Bing, que és on busca ChatGPT.
    $setSite('crosescolar.test', 'bing_verification', 'codi-de-bing-123');
    check('Hi ha la verificació de Bing quan s\'hi posa',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], '<meta name="msvalidate.01" content="codi-de-bing-123">'));
    $setSite('crosescolar.test', 'bing_verification', '');
    check('I la configuració del SEO ho explica',
        str_contains($web('GET', '/configuracio/seo')['body'], 'name="platform_ai_crawlers"')
        && str_contains($web('GET', '/configuracio/seo')['body'], 'name="bing_verification"'));

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

    // Mentre les dades hi siguin, donar-la de baixa es pot desfer.
    $detail = $web('GET', '/instancies/' . $instanceId);
    check('La fitxa ofereix tornar-la a donar d\'alta',
        str_contains($detail['body'], 'value="reactivate"')
        && str_contains(text($detail['body']), 'Tornar a donar d\'alta el web'));
    $web('POST', '/instancies/' . $instanceId . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'reactivate',
    ]);
    $instance = Instance::find($instanceId);
    check('I torna a estar d\'alta', (string) $instance['status'] === 'active', (string) $instance['status']);
    check('Sense data d\'esborrat', $instance['purge_at'] === null && $instance['cancelled_at'] === null);
    $tornat = $web('GET', '/', [], 'santjordi.crosescolar.test');
    check('El web es torna a servir', $tornat['status'] === 200, 'estat ' . $tornat['status']);
    check('I amb les seves dades de sempre',
        str_contains($tornat['body'], 'Cros de la reimportació'));

    // I es torna a donar de baixa per continuar amb l'esborrat.
    $detail = $web('GET', '/instancies/' . $instanceId);
    $web('POST', '/instancies/' . $instanceId . '/accio', [
        '_token' => $token($detail['body']), 'action' => 'cancel', 'confirm' => 'santjordi',
    ]);
    check('Es pot tornar a donar de baixa',
        (string) Instance::find($instanceId)['status'] === 'cancelled');

    check('Una instància activa no s\'esborra', !Provisioner::purge((int) $bosc['id'], $site));
    check('Una de donada de baixa, sí', Provisioner::purge($instanceId, $site));
    check('La base de dades desapareix', !$dbExists($prefix . 'santjordi'));
    check('I la carpeta també', !is_dir($site . '/tenants/santjordi'));
    check('La fitxa es queda, marcada com a esborrada',
        (string) Instance::find($instanceId)['status'] === 'purged');
    check('I el seu amfitrió ja no existeix',
        $web('GET', '/', [], 'santjordi.crosescolar.test')['status'] === 404);

    echo "\n== La configuració de la plataforma ==\n";
    $config = $web('GET', '/configuracio/general');
    check('Hi ha la pantalla de configuració',
        $config['status'] === 200 && str_contains($config['body'], 'Nom del servei'), 'estat ' . $config['status']);
    check('I diu què no es toca des d\'aquí',
        str_contains($config['body'], 'tenants/platform.php') && str_contains($config['body'], 'crosescolar.test'));
    check('El menú porta a tots els grups',
        str_contains($config['body'], '/configuracio/mail') && str_contains($config['body'], '/configuracio/appearance'));

    $desat = $web('POST', '/configuracio/general', [
        '_token' => $token($config['body']),
        'site_name' => 'Cros Escolar del Penedès',
        'platform_tagline' => 'El web del vostre cros',
        'platform_intro' => '<p>Som una colla que <b>organitza</b> curses.</p>',
        'platform_contact_email' => 'hola@crosescolar.test',
    ]);
    check('Es desa el que s\'hi escriu', $desat['status'] === 302);
    check('I queda a la base de dades, al seu domini',
        (string) Db::val("SELECT v FROM settings WHERE k = 'site:crosescolar.test:site_name'", [], '') === 'Cros Escolar del Penedès');
    check('El panell ja en porta el nom',
        str_contains($web('GET', '/')['body'], 'Cros Escolar del Penedès'));
    check('I la portada pública també',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], 'El web del vostre cros'));

    // Els colors i el logotip.
    $imatge = $web('GET', '/configuracio/appearance');
    $web('POST', '/configuracio/appearance', [
        '_token' => $token($imatge['body']),
        'color_primary' => '#8a2f2f',
        'platform_color_accent' => '#c05252',
        'platform_color_dark' => '#3a1414',
    ]);
    check('El color del panell es pot canviar',
        str_contains($web('GET', '/')['body'], '--a-green: #8a2f2f'));
    check('I el de la pàgina pública',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], '--platform-dark: #3a1414'));

    echo "\n== La portada, personalitzada ==\n";
    $portada = $web('GET', '/configuracio/home');
    check('Hi ha la pantalla de la portada',
        $portada['status'] === 200 && str_contains(text($portada['body']), 'Imatge de fons del banner'),
        'estat ' . $portada['status']);
    check('Amb els passos de «Com funciona»', str_contains(text($portada['body']), 'Pas 1: títol'));
    check('I la llista de preguntes', str_contains($portada['body'], 'platform_faqs_q[]'));

    $web('POST', '/configuracio/home', [
        '_token' => $token($portada['body']),
        'platform_hero_overlay' => '60',
        'platform_hero_eyebrow' => 'Per a qui organitza curses',
        'platform_hero_lead' => 'Tot el que cal per a un cros escolar.',
        'platform_steps_show' => '1',
        'platform_steps_title' => 'En tres passos',
        'platform_step1_title' => 'Ho demaneu',
        'platform_step1_text' => 'Amb el formulari de sota.',
        'platform_step2_title' => 'Ho mirem',
        'platform_step2_text' => 'I us responem.',
        'platform_step3_title' => '',
        'platform_step3_text' => '',
        'platform_faqs_show' => '1',
        'platform_faqs_title' => 'Dubtes de sempre',
        'platform_faqs_q' => ['Quant costa?', 'Quan trigueu?', ''],
        'platform_faqs_a' => ['Res de res.', 'Un parell de dies.', 'sense pregunta'],
    ]);
    $publica = $web('GET', '/', [], 'crosescolar.test');
    $textPublica = text($publica['body']);
    check('La frase de sobre el títol es canvia', str_contains($textPublica, 'Per a qui organitza curses'));
    check('I la de sota', str_contains($textPublica, 'Tot el que cal per a un cros escolar.'));
    check('Els passos porten el que s\'hi ha escrit',
        str_contains($textPublica, 'En tres passos') && str_contains($textPublica, 'Ho demaneu'));
    check('Un pas sense títol no surt', !str_contains($textPublica, 'Rebeu les claus'));
    check('Les preguntes surten a la portada',
        str_contains($textPublica, 'Dubtes de sempre') && str_contains($textPublica, 'Quant costa?'));
    check('Desplegables, sense cap script', str_contains($publica['body'], '<details class="faq-item"'));
    check('La fila buida no es desa', !str_contains($textPublica, 'sense pregunta'));
    check('I van als cercadors com a FAQPage', str_contains($publica['body'], '"@type": "FAQPage"')
        || str_contains($publica['body'], '"@type":"FAQPage"'));

    $web('POST', '/configuracio/home', array_merge(formData($portada['body']), [
        '_token' => $token($web('GET', '/configuracio/home')['body']),
        'platform_faqs_show' => '0',
    ]));
    check('Es poden amagar les preguntes',
        !str_contains(text($web('GET', '/', [], 'crosescolar.test')['body']), 'Quant costa?'));

    // La imatge del banner: es puja de debò i ha de quedar desada i servida.
    $fitxer = sys_get_temp_dir() . '/cros-banner-' . bin2hex(random_bytes(3)) . '.jpg';
    $imatge = imagecreatetruecolor(1400, 700);
    imagefill($imatge, 0, 0, imagecolorallocate($imatge, 60, 110, 70));
    imagejpeg($imatge, $fitxer, 80);
    imagedestroy($imatge);
    $pujada = $web('POST', '/configuracio/home', array_merge(formData($web('GET', '/configuracio/home')['body']), [
        '_token' => $token($web('GET', '/configuracio/home')['body']),
        'platform_hero_image' => new CURLFile($fitxer, 'image/jpeg', basename($fitxer)),
    ]));
    check('La imatge del banner es puja', $pujada['status'] === 302, 'estat ' . $pujada['status']);
    $desada = (string) Db::val("SELECT v FROM settings WHERE k = 'site:crosescolar.test:platform_hero_image'", [], '');
    check('I queda desada a la configuració', $desada !== '', $desada);
    check('El fitxer és al disc', $desada !== '' && is_file($site . '/uploads/' . $desada),
        $site . '/uploads/' . $desada);
    $ambBanner = $web('GET', '/', [], 'crosescolar.test');
    check('La portada la porta de fons',
        str_contains($ambBanner['body'], 'platform-hero--image') && str_contains($ambBanner['body'], $desada),
        substr($desada, 0, 40));
    check('I el web la serveix',
        $desada !== '' && $web('GET', '/uploads/' . $desada, [], 'crosescolar.test')['status'] === 200,
        'estat ' . ($desada !== '' ? $web('GET', '/uploads/' . $desada, [], 'crosescolar.test')['status'] : '?'));
    @unlink($fitxer);

    echo "\n== La pàgina de funcionalitats ==\n";
    $func = $web('GET', '/funcionalitats', [], 'crosescolar.test');
    check('La pàgina respon', $func['status'] === 200, 'estat ' . $func['status']);
    check('Amb la llista que ve de fàbrica',
        str_contains(text($func['body']), 'Dorsals a punt d\'imprimir'));
    check('I surt al menú',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], '/funcionalitats'));
    check('I al mapa del web',
        str_contains($web('GET', '/sitemap.xml', [], 'crosescolar.test')['body'], '/funcionalitats'));

    $pantalla = $web('GET', '/configuracio/features');
    check('S\'edita des del panell', $pantalla['status'] === 200 && str_contains($pantalla['body'], 'features_list_title'));
    $desat = $web('POST', '/configuracio/features', array_merge(formData($pantalla['body']), [
        '_token' => $token($pantalla['body']),
        'features_enabled' => '1',
        'features_list_icon' => ['trophy', ''],
        'features_list_title' => ['Cronometratge', 'Sense explicació'],
        'features_list_text' => ['Arribades per dorsal i classificació al moment.', ''],
    ]));
    check('S\'hi poden canviar les funcionalitats', $desat['status'] === 302);
    $func = $web('GET', '/funcionalitats', [], 'crosescolar.test');
    check('La pàgina ensenya les noves', str_contains($func['body'], 'Cronometratge'));
    check('I descarta les files a mitges', !str_contains($func['body'], 'Sense explicació'));

    $pantalla = $web('GET', '/configuracio/features');
    $camps = array_merge(formData($pantalla['body']), [
        '_token' => $token($pantalla['body']),
        'features_list_icon' => ['trophy'],
        'features_list_title' => ['Cronometratge'],
        'features_list_text' => ['Arribades per dorsal i classificació al moment.'],
    ]);
    unset($camps['features_enabled']); // una casella sense marcar no s'envia
    $tancada = $web('POST', '/configuracio/features', $camps);
    check('Es pot amagar del tot', $tancada['status'] === 302
        && $web('GET', '/funcionalitats', [], 'crosescolar.test')['status'] === 404);
    check('I llavors tampoc no surt al mapa del web',
        !str_contains($web('GET', '/sitemap.xml', [], 'crosescolar.test')['body'], '/funcionalitats'));

    echo "\n== Una pàgina pública per domini ==\n";
    // La portada de crosescolar.test ja porta el nom i el lema que s'hi han
    // desat abans. La d'esportweb.test no n'ha de saber res: és un altre web.
    $cros = $web('GET', '/', [], 'crosescolar.test');
    $esport = $web('GET', '/', [], 'esportweb.test');
    check('El domini nou respon', $esport['status'] === 200, 'estat ' . $esport['status']);
    check('Amb el nom que li toca', str_contains($esport['body'], 'EsportWeb'));
    check('I sense el text de l\'altre domini',
        !str_contains($esport['body'], 'Cros Escolar del Penedès'));
    check('Que sí que surt al seu', str_contains($cros['body'], 'Cros Escolar del Penedès'));
    check('Cada domini parla del que li pertoca',
        str_contains(text($esport['body']), 'Les curses que ja hi són')
        && str_contains(text($cros['body']), 'Els cros escolars que ja hi corren'));
    check('I diu l\'adreça del seu domini',
        str_contains($esport['body'], 'lavostracursa<span>.esportweb.test</span>'));
    check('La canònica és la seva',
        str_contains($esport['body'], 'rel="canonical" href="https://esportweb.test/'));

    // Al panell, els grups que van per domini porten les pestanyes.
    $general = $web('GET', '/configuracio/general');
    check('La configuració avisa que hi ha un web per domini',
        str_contains($general['body'], 'site-tabs') && str_contains($general['body'], 'domini=esportweb.test'));
    check('I el que és de tota la plataforma, no',
        !str_contains($web('GET', '/configuracio/monitor')['body'], 'site-tabs'));
    check('El correu, en canvi, també va per domini',
        str_contains($web('GET', '/configuracio/mail')['body'], 'site-tabs'));

    $altre = $web('GET', '/configuracio/general?domini=esportweb.test');
    check('S\'hi pot triar l\'altre domini', $altre['status'] === 200, 'estat ' . $altre['status']);
    check('I ensenya els seus valors', str_contains($altre['body'], 'value="EsportWeb"'));
    check('Sense que el panell canviï de marca',
        str_contains($altre['body'], '<strong>Cros Escolar del Penedès</strong><small>crosescolar.test</small>'));
    $canviat = $web('POST', '/configuracio/general?domini=esportweb.test', array_merge(formData($altre['body']), [
        '_token' => $token($altre['body']),
        'site_name' => 'EsportWeb Catalunya',
        'platform_tagline' => 'Qualsevol cursa, qualsevol esport.',
    ]));
    check('El canvi es desa', $canviat['status'] === 302, 'estat ' . $canviat['status']);
    check('I es veu al seu domini',
        str_contains($web('GET', '/', [], 'esportweb.test')['body'], 'Qualsevol cursa, qualsevol esport.'));
    check('Sense tocar el domini de sempre',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], 'Cros Escolar del Penedès'));
    check('Cadascun es desa a la seva clau',
        (string) Db::val("SELECT v FROM settings WHERE k = 'site:esportweb.test:site_name'", [], '') === 'EsportWeb Catalunya'
        && (string) Db::val("SELECT v FROM settings WHERE k = 'site:crosescolar.test:site_name'", [], '') === 'Cros Escolar del Penedès');

    // Un domini que no és nostre no es pot editar: es cau al principal.
    $inventat = $web('GET', '/configuracio/general?domini=uncosinventat.test');
    check('Un domini que no és nostre no s\'edita',
        $inventat['status'] === 200 && str_contains($inventat['body'], 'value="Cros Escolar del Penedès"'));

    echo "\n== La plataforma es diu com es diu ella ==\n";
    // Als correus automàtics hi ha de sortir la marca de la plataforma, no la
    // del cros que s'acaba de tocar ni la del domini per on hagi entrat ningú.
    Settings::scope();
    Settings::set('site_name', 'Un nom vell que no s\'ha de veure');
    Settings::load(true);
    Platform::prime($site);
    check('Després de tornar de la base de dades d\'un client, mana la seva marca',
        (string) setting('site_name', '') === 'Cros Escolar del Penedès',
        (string) setting('site_name', ''));
    check('I no el que hi ha desat sense domini',
        (string) setting('site_name', '') !== 'Un nom vell que no s\'ha de veure');

    // La capçalera del correu surt d'aquí mateix.
    $capcalera = \Cros\Core\View::make('emails/layout', ['subject' => 'Prova', 'content' => '<p>Hola</p>']);
    check('I és el que es veu a la capçalera del correu',
        str_contains($capcalera, 'Cros Escolar del Penedès'), mb_substr(strip_tags($capcalera), 0, 80));

    echo "\n== La retenció, només a les entitats ==\n";
    // Base 30,00 · IVA 21 % · IRPF 15 %, tal com s'ha configurat més amunt.
    $entitat = Plan::amountsFor(['kind' => 'company']);
    $particular = Plan::amountsFor(['kind' => 'person']);
    check('A una entitat se li reté', $entitat['irpf'] === 450 && $entitat['total'] === 3180,
        $entitat['irpf'] . ' / ' . $entitat['total']);
    check('A un particular, no', $particular['irpf'] === 0 && $particular['irpf_rate'] === 0.0,
        (string) $particular['irpf']);
    check('I per tant en paga més', $particular['total'] === 3630, (string) $particular['total']);
    check('L\'IVA el paguen tots dos igual', $particular['vat'] === 630 && $entitat['vat'] === 630);
    check('Sense fitxa es reté, que és el que són gairebé tots',
        Plan::amountsFor(null)['irpf'] === 450);

    // I el cobrament que se li prepara ho ha de respectar.
    $clientParticular = Client::create([
        'name' => 'Marta Soler', 'kind' => 'person',
        'contact_name' => 'Marta Soler', 'contact_email' => 'marta@example.cat',
    ]);
    $instanciaFalsa = Instance::find($instanceId);
    $instanciaFalsa['client_id'] = $clientParticular;
    $instanciaFalsa['id'] = 0; // perquè no reculli el cobrament que ja hi ha
    $pdo->exec('DELETE FROM platform_payments WHERE instance_id = 0');
    $cobramentParticular = Charge::forActivation($instanciaFalsa, ['name' => 'Marta Soler']);
    check('El cobrament d\'un particular no porta retenció',
        (int) $cobramentParticular['irpf_cents'] === 0 && (float) $cobramentParticular['irpf_rate'] === 0.0,
        (string) $cobramentParticular['irpf_cents']);
    check('I el total és base + IVA', (int) $cobramentParticular['total_cents'] === 3630,
        (string) $cobramentParticular['total_cents']);
    check('Queda apuntat quina mena de client era',
        (string) $cobramentParticular['payer_kind'] === 'person', (string) $cobramentParticular['payer_kind']);

    // La factura que se li emeti tampoc no ha de parlar de retenció.
    Charge::markPaid($cobramentParticular, '', 'Prova');
    $facturaParticular = Invoice::forPayment((int) $cobramentParticular['id']);
    check('La seva factura s\'emet', $facturaParticular !== null);
    $fitxerP = sys_get_temp_dir() . '/cros-factura-p-' . bin2hex(random_bytes(3)) . '.pdf';
    file_put_contents($fitxerP, Invoice::pdf($facturaParticular));
    $textP = trim((string) @shell_exec('pdftotext ' . escapeshellarg($fitxerP) . ' - 2>/dev/null'));
    @unlink($fitxerP);
    if ($textP === '') {
        echo "  (sense pdftotext: no es pot llegir el text de la factura)\n";
    } else {
        check('I no hi surt cap retenció', !str_contains($textP, 'Retenció'), mb_substr($textP, 0, 120));
        check('Però sí l\'IVA', str_contains($textP, 'IVA'));
    }
    $pdo->exec('DELETE FROM platform_payments WHERE instance_id = 0');

    echo "\n== L'avís de Stripe a la plataforma ==\n";
    // És l'única xarxa que hi ha si el navegador no torna del pagament, i ha
    // d'entendre les dues maneres de pagar: la pàgina de Stripe (una sessió)
    // i el formulari de la targeta del panell (un PaymentIntent).
    $secret = 'whsec_de_prova_per_a_la_plataforma';
    Settings::scope();
    Settings::set('stripe_mode', 'test');
    Settings::set('stripe_webhook_test', $secret);
    Settings::load(true);

    $signa = static function (string $cos) use ($secret): array {
        $ara = time();

        return ['Content-Type: application/json',
            'Stripe-Signature: t=' . $ara . ',v1=' . hash_hmac('sha256', $ara . '.' . $cos, $secret)];
    };
    $avis = static function (string $cos, array $capçaleres) use ($base, $jar): array {
        $ch = curl_init($base . '/pagament/avis');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cos, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge(['Host: crosescolar.test'], $capçaleres),
        ]);
        $r = (string) curl_exec($ch);
        $estat = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $estat, 'body' => substr($r, (int) strpos($r, "\r\n\r\n") + 4)];
    };

    $dolent = json_encode(['id' => 'evt_1', 'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_inventat']]]);
    check('Una signatura que no quadra es rebutja',
        $avis($dolent, ['Content-Type: application/json', 'Stripe-Signature: t=' . time() . ',v1=' . str_repeat('0', 64)])['status'] === 400);

    $desconegut = json_encode(['id' => 'evt_2', 'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_que_no_es_de_ningu']]]);
    check('Un pagament que no és nostre s\'accepta i es deixa córrer',
        $avis($desconegut, $signa($desconegut))['status'] === 200);

    $altre = json_encode(['id' => 'evt_3', 'type' => 'invoice.paid',
        'data' => ['object' => ['id' => 'in_1']]]);
    check('I un avís que no va amb nosaltres, també',
        $avis($altre, $signa($altre))['status'] === 200);

    // I ara el que importa: que un PaymentIntent nostre s'hi reconegui.
    $instanciaAvis = Instance::find($instanceId);
    $instanciaAvis['id'] = 0;
    $pdo->exec('DELETE FROM platform_payments WHERE instance_id = 0');
    $cobramentAvis = Charge::forActivation($instanciaAvis, ['name' => 'Prova avís']);
    Db::update('platform_payments', ['stripe_payment_intent' => 'pi_de_prova_nostre'],
        'id = :id', ['id' => (int) $cobramentAvis['id']]);
    check('Es troba el cobrament pel seu PaymentIntent',
        (int) (Charge::findByIntent('pi_de_prova_nostre')['id'] ?? 0) === (int) $cobramentAvis['id']);
    $nostre = json_encode(['id' => 'evt_4', 'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_de_prova_nostre']]]);
    $resposta = $avis($nostre, $signa($nostre));
    check('I l\'avís del seu PaymentIntent s\'atén', $resposta['status'] === 200, (string) $resposta['status']);
    // Sense un Stripe de debò no es pot acabar de confirmar, però al registre
    // hi ha de quedar que s\'hi ha arribat amb aquest cobrament a la mà. Si
    // l\'avís s\'hagués descartat per no ser una sessió, aquí no hi hauria res.
    $registre = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('I es veu que ha arribat al cobrament que toca',
        str_contains($registre, (string) $cobramentAvis['code']), (string) $cobramentAvis['code']);
    $pdo->exec('DELETE FROM platform_payments WHERE instance_id = 0');

    echo "\n== Afegir un domini més endavant ==\n";
    // Un domini que s'afegeix quan la plataforma ja fa dies que roda no ha
    // d'heretar el text del web que hi havia, ni encara que passi a ser el
    // principal: el que hi ha desat sense àmbit és de l'altre, no seu.
    $altraArrel = $site . '-ampliat';
    @mkdir($altraArrel . '/tenants', 0775, true);
    copy($site . '/tenants/platform.php', $altraArrel . '/tenants/platform.php');
    $fitxer = (string) file_get_contents($altraArrel . '/tenants/platform.php');
    $fitxer = str_replace(
        "'base_domain' => 'crosescolar.test'",
        "'base_domain' => 'esportweb.nou'",
        $fitxer
    );
    file_put_contents($altraArrel . '/tenants/platform.php', $fitxer);

    Site::seed($altraArrel);
    check('El domini nou neix amb el text d\'EsportWeb',
        (string) Db::val("SELECT v FROM settings WHERE k = 'site:esportweb.nou:site_name'", [], '') === 'EsportWeb',
        (string) Db::val("SELECT v FROM settings WHERE k = 'site:esportweb.nou:site_name'", [], '(res)'));
    check('I no s\'endú el de qui ja hi era',
        (string) Db::val("SELECT v FROM settings WHERE k = 'site:crosescolar.test:site_name'", [], '') === 'Cros Escolar del Penedès');
    check('Que es queda igual que estava',
        (string) Db::val("SELECT v FROM settings WHERE k = 'site:esportweb.test:site_name'", [], '') === 'EsportWeb Catalunya');
    exec('rm -rf ' . escapeshellarg($altraArrel));

    echo "\n== Un servidor de correu per domini ==\n";
    // Mentre un domini no en diu res, fa servir el que hi havia per a tota la
    // plataforma: en actualitzar, els correus continuen sortint com sortien.
    Settings::forget();
    Platform::prime($site);
    check('Un domini sense correu propi fa servir el de tota la plataforma',
        Site::value('esportweb.test', 'mail_from_email') === 'hola@crosescolar.test'
        && Site::value('crosescolar.test', 'mail_from_email') === 'hola@crosescolar.test');
    check('I la manera d\'enviar també', Site::value('esportweb.test', 'mail_transport') === 'log');
    $correuEsport = $web('GET', '/configuracio/mail?domini=esportweb.test');
    check('El correu de cada domini es configura al panell',
        $correuEsport['status'] === 200 && str_contains($correuEsport['body'], 'name="smtp_host"')
        && str_contains($correuEsport['body'], 'domini=crosescolar.test'));
    $web('POST', '/configuracio/mail?domini=esportweb.test', [
        '_token' => $token($correuEsport['body']),
        'mail_from_email' => 'hola@esportweb.test', 'mail_transport' => 'log',
        'smtp_host' => 'smtp.esportweb.test', 'smtp_port' => '587', 'smtp_secure' => 'tls',
    ]);
    $correuCros = $web('GET', '/configuracio/mail?domini=crosescolar.test');
    $web('POST', '/configuracio/mail?domini=crosescolar.test', [
        '_token' => $token($correuCros['body']),
        'mail_from_email' => 'correu@crosescolar.test', 'mail_transport' => 'log',
        'smtp_host' => 'smtp.crosescolar.test', 'smtp_port' => '465', 'smtp_secure' => 'ssl',
    ]);
    Settings::forget();
    Platform::prime($site);
    check('Cada domini es queda el seu',
        Site::value('esportweb.test', 'mail_from_email') === 'hola@esportweb.test'
        && Site::value('crosescolar.test', 'mail_from_email') === 'correu@crosescolar.test');
    check('Amb el seu servidor',
        Site::value('esportweb.test', 'smtp_host') === 'smtp.esportweb.test'
        && Site::value('crosescolar.test', 'smtp_host') === 'smtp.crosescolar.test'
        && Site::value('crosescolar.test', 'smtp_port') === '465');
    check('I el formulari ensenya el de cada un',
        str_contains($web('GET', '/configuracio/mail?domini=crosescolar.test')['body'], 'value="smtp.crosescolar.test"')
        && !str_contains($web('GET', '/configuracio/mail?domini=crosescolar.test')['body'], 'value="smtp.esportweb.test"'));


    echo "\n== Registre lliure ==\n";
    $portada = $web('GET', '/', [], 'esportweb.test');
    check('La portada porta el formulari de registre',
        str_contains($portada['body'], 'action="' . $base . '/registre"')
        || str_contains($portada['body'], '/registre"'));
    check('Amb el nom, el correu i l\'adreça',
        str_contains($portada['body'], 'name="site_name"')
        && str_contains($portada['body'], 'name="admin_email"')
        && str_contains($portada['body'], 'name="slug"'));
    check('I deixa triar el domini',
        str_contains($portada['body'], 'value="esportweb.test"')
        && str_contains($portada['body'], 'value="crosescolar.test"'));
    check('I diu on serà el panell', str_contains(text($portada['body']), '/admin'));
    check('L\'adreça es comprova mentre s\'escriu',
        str_contains($portada['body'], 'data-slug-check="') && str_contains($portada['body'], 'id="slug-status"'));
    check('I demana quina mena de client és',
        str_contains($portada['body'], 'name="client_kind"')
        && str_contains($portada['body'], 'value="person"')
        && str_contains($portada['body'], 'value="company"'));

    $sensemena = $web('POST', '/registre', [
        '_token' => $token($portada['body']),
        'site_name' => 'Cursa sense dir-ho', 'admin_name' => 'Ona Vidal',
        'admin_email' => 'ona@example.cat', 'slug' => 'sensedirho',
        'domain' => 'esportweb.test', 'consent' => '1',
    ], 'esportweb.test');
    check('Sense dir-ho no es continua',
        $sensemena['status'] === 200 && str_contains(text($sensemena['body']), 'entitat o un particular'));

    // Un subdomini reservat no es dona, i no es crea res.
    $dolent = $web('POST', '/registre', [
        '_token' => $token($portada['body']),
        'site_name' => 'Cursa de prova', 'admin_name' => 'Ona Vidal',
        'admin_email' => 'ona@example.cat', 'slug' => 'admin',
        'domain' => 'esportweb.test', 'client_kind' => 'company', 'consent' => '1',
    ], 'esportweb.test');
    check('Un subdomini reservat no es dona', $dolent['status'] === 200
        && str_contains(text($dolent['body']), 'reservat'));
    check('I no es crea res', Db::one("SELECT * FROM instances WHERE slug = 'admin'") === null);

    // Sense acceptar les condicions, tampoc.
    $sensePermis = $web('POST', '/registre', [
        '_token' => $token($portada['body']),
        'site_name' => 'Cursa de prova', 'admin_name' => 'Ona Vidal',
        'admin_email' => 'ona@example.cat', 'slug' => 'lacursadona',
        'domain' => 'esportweb.test',
    ], 'esportweb.test');
    check('Sense acceptar les condicions no es continua',
        $sensePermis['status'] === 200 && str_contains(text($sensePermis['body']), 'acceptar les condicions'));

    // I ara, l'alta de debò.
    $alta = $web('POST', '/registre', [
        '_token' => $token($portada['body']),
        'site_name' => 'Cursa de la Riera', 'town' => 'Arenys',
        'admin_name' => 'Ona Vidal', 'admin_email' => 'ona@example.cat',
        'slug' => 'lariera', 'domain' => 'esportweb.test',
        'event_date' => date('Y-m-d', strtotime('+2 months')),
        'client_kind' => 'company', 'consent' => '1',
    ], 'esportweb.test');
    check('L\'alta lliure passa', $alta['status'] === 302 && str_contains($alta['headers'], '/benvinguda'),
        'estat ' . $alta['status']);
    $riera = Db::one("SELECT * FROM instances WHERE slug = 'lariera'");
    check('El web queda creat de seguida', $riera !== null);
    check('Amb el domini que ha triat', (string) ($riera['domain'] ?? '') === 'esportweb.test');
    check('I apuntat com a alta feta des del web', (string) ($riera['source'] ?? '') === 'signup');
    check('Amb el correu encara per validar', ($riera['verified_at'] ?? null) === null);
    check('La carpeta i la base de dades hi són',
        is_file($site . '/tenants/lariera/config.php') && $dbExists($prefix . 'lariera'));
    check('El web neix amagat', (int) ($riera['published'] ?? 1) === 0);
    $fitxa = Db::one("SELECT * FROM clients WHERE contact_email = 'ona@example.cat'");
    check('I es crea la fitxa del client', $fitxa !== null);
    check('Que és qui té el web', (int) ($riera['client_id'] ?? 0) === (int) ($fitxa['id'] ?? -1));
    check('Amb la mena de client que ha dit', Client::kindOf($fitxa) === 'company');
    $correus = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('Se li envia el correu de confirmació', str_contains($correus, 'ona@example.cat'));
    $linia = '';
    foreach (explode("\n", $correus) as $l) {
        if (str_contains($l, 'ona@example.cat') && str_contains($l, 'Confirmeu el correu')) {
            $linia = $l;
        }
    }
    check('Pel servidor de correu del domini on és el web', str_contains($linia, 'hola@esportweb.test'), $linia);
    $registreCorreu = Db::one("SELECT * FROM email_log WHERE recipient = 'ona@example.cat' ORDER BY id DESC LIMIT 1");
    check('I la plataforma apunta que ha sortit',
        $registreCorreu !== null && (string) $registreCorreu['status'] === 'sent'
        && str_contains((string) $registreCorreu['subject'], 'Confirmeu el correu'));

    $benvinguda = $web('GET', '/benvinguda', [], 'esportweb.test');
    check('La pantalla de després diu on mirar',
        $benvinguda['status'] === 200 && str_contains(text($benvinguda['body']), 'ona@example.cat'),
        'estat ' . $benvinguda['status']);
    check('I quina serà l\'adreça del panell',
        str_contains(text($benvinguda['body']), 'lariera.esportweb.test/admin'));
    check('Sense cap drecera per entrar-hi sense el correu',
        !str_contains($benvinguda['body'], '/admin/clau/'));
    check('Tornar-hi no ensenya res', $web('GET', '/benvinguda', [], 'esportweb.test')['status'] === 302);
    check('Si no arriba, des d\'allà mateix el poden tornar a demanar',
        str_contains($benvinguda['body'], '/benvinguda/reenviar'));
    $massaAviat = $web('POST', '/benvinguda/reenviar', ['_token' => $token($benvinguda['body'])], 'esportweb.test');
    check('Però no tot seguit: cal esperar una mica',
        $massaAviat['status'] === 200 && str_contains(text($massaAviat['body']), 'Espereu un minut'));
    check('I sense sessió d\'alta no es pot demanar per a cap altre web',
        $web('POST', '/benvinguda/reenviar', ['_token' => $token($web('GET', '/', [], 'crosescolar.test')['body'])], 'crosescolar.test')['status'] === 302);

    // Mentre s'escriu l'adreça, el formulari pregunta si està lliure.
    $adreca = static function (array $query) use ($web): array {
        $resposta = $web('GET', '/registre/adreca?' . http_build_query($query), [], 'esportweb.test');

        return ['status' => $resposta['status'], 'headers' => $resposta['headers'],
            'data' => (array) json_decode($resposta['body'], true)];
    };
    $agafada = $adreca(['slug' => 'lariera']);
    check('Una adreça que ja és d\'un altre es diu al moment',
        $agafada['status'] === 200 && ($agafada['data']['ok'] ?? true) === false
        && str_contains((string) ($agafada['data']['message'] ?? ''), 'Ja hi ha'),
        json_encode($agafada['data']));
    check('I se\'n proposa una de semblant que està lliure',
        ($agafada['data']['alternative'] ?? '') === 'lariera-2');
    check('La resposta és JSON i no es guarda',
        str_contains(strtolower($agafada['headers']), 'application/json')
        && str_contains(strtolower($agafada['headers']), 'no-store'));
    $lliure = $adreca(['slug' => 'LaRieraNova']);
    check('Una de lliure es dona per bona',
        ($lliure['data']['ok'] ?? false) === true && ($lliure['data']['slug'] ?? '') === 'larieranova'
        && ($lliure['data']['alternative'] ?? 'x') === '');
    $reservada = $adreca(['slug' => 'admin']);
    check('Una de reservada també es diu',
        ($reservada['data']['ok'] ?? true) === false
        && str_contains((string) ($reservada['data']['message'] ?? ''), 'reservat'));
    $malescrita = $adreca(['slug' => 'La Riera de Dalt']);
    check('Una de mal escrita en proposa la bona',
        ($malescrita['data']['ok'] ?? true) === false && ($malescrita['data']['alternative'] ?? '') === 'la-riera-de-dalt');
    $delNom = $adreca(['slug' => '', 'nom' => 'La Riera']);
    check('Sense adreça, es mira la que sortiria del nom, i si és d\'un altre se\'n fa servir una de semblant',
        ($delNom['data']['ok'] ?? false) === true && ($delNom['data']['slug'] ?? '') === 'lariera-2'
        && ($delNom['data']['from_name'] ?? false) === true, json_encode($delNom['data']));
    check('Sense res, no diu res', ($adreca([])['data']['message'] ?? 'x') === '');
    check('Els cercadors no hi han d\'entrar',
        str_contains($web('GET', '/robots.txt', [], 'esportweb.test')['body'], 'Disallow: /registre'));

    check('Al panell hi surt marcada com a pendent de validar',
        str_contains($web('GET', '/instancies')['body'], 'Correu per validar'));

    // Si no li ha arribat, el correu de benvinguda es pot tornar a enviar.
    $fitxaRiera = $web('GET', '/instancies/' . (int) $riera['id']);
    check('La fitxa diu quins correus li hem enviat i com han anat',
        str_contains($fitxaRiera['body'], 'Últims correus') && str_contains(text($fitxaRiera['body']), 'Confirmeu el correu'));
    check('I, mentre no l\'ha validat, deixa tornar a enviar la benvinguda',
        str_contains($fitxaRiera['body'], 'value="welcome"'));
    $abansCorreus = (int) Db::val("SELECT COUNT(*) FROM email_log WHERE recipient = 'ona@example.cat'", [], 0);
    $reenviat = $web('POST', '/instancies/' . (int) $riera['id'] . '/accio',
        ['_token' => $token($fitxaRiera['body']), 'action' => 'welcome']);
    check('Es torna a enviar des del panell', $reenviat['status'] === 302
        && (int) Db::val("SELECT COUNT(*) FROM email_log WHERE recipient = 'ona@example.cat'", [], 0) === $abansCorreus + 1);
    check('I ho diu', str_contains(text($web('GET', '/instancies/' . (int) $riera['id'])['body']), 'enviat de nou a ona@example.cat'));
    check('I queda al registre de la instància',
        Db::one("SELECT * FROM platform_activity WHERE action = 'welcome_resent' AND subject_id = :id", ['id' => (int) $riera['id']]) !== null);

    // El botó del correu: entra al panell i, alhora, valida l'adreça.
    $porta = Instance::accessLink((int) $riera['id'], 'welcome', $site, 'Prova del registre');
    check('Es pot fer l\'enllaç d\'estrena', $porta['ok'], (string) $porta['error']);
    $cami = (string) parse_url((string) $porta['url'], PHP_URL_PATH);
    $entrada = $web('GET', $cami, [], 'lariera.esportweb.test');
    check('El botó del correu fa entrar al panell',
        $entrada['status'] === 302 && str_contains($entrada['headers'], '/admin/clau'),
        'estat ' . $entrada['status']);
    $riera = Db::one("SELECT * FROM instances WHERE slug = 'lariera'");
    check('I amb això el correu queda validat', ($riera['verified_at'] ?? null) !== null);
    check('Al panell ja no hi surt l\'avís',
        !str_contains($web('GET', '/instancies/' . (int) $riera['id'])['body'], 'Correu per validar'));
    check('I diu que el correu és bo',
        str_contains($web('GET', '/instancies/' . (int) $riera['id'])['body'], 'Correu validat'));

    // També queda apuntat al seu web.
    $seuaPdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $prefix . 'lariera'),
        $db['admin_user'], $db['admin_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $quan = $seuaPdo->query("SELECT email_verified_at FROM users WHERE email = 'ona@example.cat'")->fetchColumn();
    check('I al seu propi web també', $quan !== null && $quan !== false);

    // El web nou no té servidor de correu propi: envia pel de la plataforma.
    $transportRiera = $seuaPdo->query("SELECT v FROM settings WHERE k = 'mail_transport'")->fetchColumn();
    check('El web nou neix enviant pel correu de la plataforma', $transportRiera === 'platform', (string) $transportRiera);

    // Amb les altes tancades no se'n pot fer cap més.
    $altes = $web('GET', '/configuracio/requests?domini=esportweb.test');
    $web('POST', '/configuracio/requests?domini=esportweb.test', [
        '_token' => $token($altes['body']),
        'platform_requests_closed_text' => 'Ara no en donem.',
        'platform_directory' => '1',
    ], 'admin.crosescolar.test');
    $tancat = $web('GET', '/', [], 'esportweb.test');
    check('Amb les altes tancades el formulari desapareix',
        !str_contains($tancat['body'], 'name="admin_email"'));
    $intent = $web('POST', '/registre', [
        '_token' => $token($portada['body']),
        'site_name' => 'Una altra', 'admin_name' => 'Ona Vidal',
        'admin_email' => 'ona@example.cat', 'slug' => 'unaaltra',
        'domain' => 'esportweb.test', 'client_kind' => 'company', 'consent' => '1',
    ], 'esportweb.test');
    check('I tampoc no s\'hi pot entrar per la porta del darrere',
        $intent['status'] === 302 && Db::one("SELECT * FROM instances WHERE slug = 'unaaltra'") === null);
    // Una instal·lació que acaba d'actualitzar-se encara no té les columnes
    // noves: la pàgina pública no migra a cada visita, però una alta sí que
    // ha de trobar-ho tot al seu lloc.
    $pdo->exec('ALTER TABLE instances DROP COLUMN verified_at');
    $pdo->exec('ALTER TABLE instances DROP COLUMN source');
    $pdo->exec("DELETE FROM platform_migrations WHERE name = '0010_registre.sql'");
    $reoberta = $web('GET', '/configuracio/requests?domini=esportweb.test');
    $web('POST', '/configuracio/requests?domini=esportweb.test', [
        '_token' => $token($reoberta['body']),
        'platform_requests_open' => '1',
        'platform_requests_closed_text' => 'Ara no en donem.',
        'platform_directory' => '1',
    ]);
    $tardana = $web('POST', '/registre', [
        '_token' => $token($web('GET', '/', [], 'esportweb.test')['body']),
        'site_name' => 'Duatló de Tardor', 'admin_name' => 'Roc Vila',
        'admin_email' => 'roc@example.cat', 'slug' => 'duatlotardor',
        'domain' => 'esportweb.test', 'client_kind' => 'company', 'consent' => '1',
    ], 'esportweb.test');
    check('Una alta just després d\'actualitzar posa la base de dades al dia',
        $tardana['status'] === 302 && str_contains($tardana['headers'], '/benvinguda'),
        'estat ' . $tardana['status']);
    $duatlo = Db::one("SELECT * FROM instances WHERE slug = 'duatlotardor'");
    check('I el web queda creat igualment',
        $duatlo !== null && (string) ($duatlo['source'] ?? '') === 'signup');

    // I es tornen a obrir, que la resta de proves compten que hi són.
    $reobre = $web('GET', '/configuracio/requests?domini=esportweb.test');
    $web('POST', '/configuracio/requests?domini=esportweb.test', [
        '_token' => $token($reobre['body']),
        'platform_requests_open' => '1',
        'platform_requests_closed_text' => 'Ara no en donem.',
        'platform_directory' => '1',
    ]);
    check('I es poden tornar a obrir',
        str_contains($web('GET', '/', [], 'esportweb.test')['body'], 'name="admin_email"'));

    echo "\n== Legal i galetes ==\n";
    $legal = $web('GET', '/configuracio/legal');
    check('Hi ha la pantalla legal',
        $legal['status'] === 200 && str_contains(text($legal['body']), 'Condicions del servei'),
        'estat ' . $legal['status']);
    $web('POST', '/configuracio/legal', array_merge(formData($legal['body']), [
        '_token' => $token($legal['body']),
        'platform_legal_entity' => 'Associació Cros Escolar',
        'platform_legal_nif' => 'G12345678',
        'platform_legal_address' => 'Carrer Major 1, La Granada',
    ]));

    $condicions = $web('GET', '/condicions', [], 'crosescolar.test');
    check('Les condicions es publiquen',
        $condicions['status'] === 200 && str_contains(text($condicions['body']), 'Condicions del servei'),
        'estat ' . $condicions['status']);
    check('Amb el nom de l\'entitat posat',
        str_contains(text($condicions['body']), 'Associació Cros Escolar'));
    check('I el NIF', str_contains(text($condicions['body']), 'G12345678'));
    check('Sense cap marcador per substituir', !str_contains($condicions['body'], '{{'));

    $privadesa = $web('GET', '/privadesa', [], 'crosescolar.test');
    check('La privadesa també',
        $privadesa['status'] === 200 && str_contains(text($privadesa['body']), 'responsable'));
    check('I diu qui respon de les dades de cada cursa',
        str_contains(text($privadesa['body']), 'encarregats del tractament'));

    $galetes = $web('GET', '/galetes', [], 'crosescolar.test');
    check('I la política de galetes',
        $galetes['status'] === 200 && str_contains($galetes['body'], 'cros_session'));
    check('Una pàgina legal que no existeix dona 404',
        $web('GET', '/aixo-no-hi-es', [], 'crosescolar.test')['status'] === 404);
    check('El peu hi enllaça',
        str_contains($publica['body'], '/condicions') && str_contains($publica['body'], '/privadesa')
        && str_contains($publica['body'], '/galetes'));
    check('I surten al mapa del web',
        str_contains($web('GET', '/sitemap.xml', [], 'crosescolar.test')['body'], '/privadesa'));

    check('L\'avís de galetes hi és', str_contains($publica['body'], 'id="cookie-notice"'));
    check('I diu on llegir-ne més', str_contains($publica['body'], 'cookie-notice__box'));
    $web('POST', '/configuracio/legal', array_merge(formData($web('GET', '/configuracio/legal')['body']), [
        '_token' => $token($web('GET', '/configuracio/legal')['body']),
        'platform_cookie_banner' => '0',
    ]));
    check('Es pot treure',
        !str_contains($web('GET', '/', [], 'crosescolar.test')['body'], 'id="cookie-notice"'));

    // Tancar les altes.
    // Es guarda un testimoni del formulari obert per poder provar què passa si
    // algú l'envia igualment un cop tancades les altes.
    $abans = $token($web('GET', '/', [], 'crosescolar.test')['body']);

    $peticions = $web('GET', '/configuracio/requests');
    $web('POST', '/configuracio/requests', [
        '_token' => $token($peticions['body']),
        'platform_requests_closed_text' => 'Tornem al setembre.',
    ]);
    $portada = $web('GET', '/', [], 'crosescolar.test');
    check('Es poden tancar les altes noves',
        str_contains($portada['body'], 'Tornem al setembre') && !str_contains($portada['body'], 'name="admin_email"'));
    $rebutjada = $web('POST', '/sollicitud', [
        '_token' => $abans, 'entity' => 'Qui sigui', 'town' => 'Enlloc',
        'contact_name' => 'Ningú', 'contact_email' => 'ningu@example.cat',
        'contact_phone' => '600000000', 'consent' => '1',
    ], 'crosescolar.test');
    check('I una sol·licitud que arribi igualment no es desa',
        $rebutjada['status'] === 302
        && Db::one("SELECT id FROM instance_requests WHERE contact_email = 'ningu@example.cat'") === null,
        'estat ' . $rebutjada['status']);

    $web('POST', '/configuracio/requests', [
        '_token' => $token($web('GET', '/configuracio/requests')['body']),
        'platform_requests_open' => '1',
        'platform_directory' => '1',
        'platform_requests_closed_text' => 'Tornem al setembre.',
    ]);
    check('I es poden tornar a obrir',
        str_contains($web('GET', '/', [], 'crosescolar.test')['body'], 'name="admin_email"'));

    echo "\n== Llistes de correu ==\n";
    $llistes = $web('GET', '/enviaments/llistes');
    check('La pantalla de llistes respon', $llistes['status'] === 200);
    $novaLlista = $web('POST', '/enviaments/llistes/desar', [
        '_token' => $token($llistes['body']), 'id' => '0',
        'name' => 'Escoles del Penedès', 'description' => 'Contactes de la fira d\'entitats',
    ]);
    $listId = (int) Db::val("SELECT id FROM mail_lists WHERE name = 'Escoles del Penedès'", [], 0);
    check('Se\'n pot crear una', $novaLlista['status'] === 302 && $listId > 0);

    $fitxaLlista = $web('GET', '/enviaments/llistes/' . $listId);
    check('I obrir-la', $fitxaLlista['status'] === 200);
    $afegides = $web('POST', '/enviaments/llistes/' . $listId . '/contactes', [
        '_token' => $token($fitxaLlista['body']),
        'contacts' => "anna@example.cat\n"
            . "Pau Soler <pau@example.cat>\n"
            . "marta@example.cat; Marta Vila; AFA Sant Jordi\n"
            . "ANNA@example.cat\n"
            . "això no és cap adreça\n",
    ]);
    check('S\'hi enganxen adreces de qualsevol manera', $afegides['status'] === 302);
    $contactes = Db::all('SELECT * FROM mail_contacts WHERE list_id = :id ORDER BY email', ['id' => $listId]);
    check('N\'entren tres i prou', count($contactes) === 3, (string) count($contactes));
    $per = [];
    foreach ($contactes as $contacte) {
        $per[(string) $contacte['email']] = $contacte;
    }
    check('L\'adreça sola hi és', isset($per['anna@example.cat']));
    check('La forma «Nom <adreça>» en treu el nom',
        (string) ($per['pau@example.cat']['name'] ?? '') === 'Pau Soler');
    check('I les columnes, el nom i l\'entitat',
        (string) ($per['marta@example.cat']['name'] ?? '') === 'Marta Vila'
        && (string) ($per['marta@example.cat']['entity'] ?? '') === 'AFA Sant Jordi');
    check('Una adreça repetida no es duplica',
        (int) Db::val('SELECT COUNT(*) FROM mail_contacts WHERE list_id = :id AND email = :e',
            ['id' => $listId, 'e' => 'anna@example.cat'], 0) === 1);
    check('I el que no és cap adreça es diu', str_contains(text($web('GET', '/enviaments/llistes/' . $listId)['body']), 'Marta Vila'));

    $baixa = $web('POST', '/enviaments/contactes/' . (int) $per['anna@example.cat']['id'], [
        '_token' => $token($web('GET', '/enviaments/llistes/' . $listId)['body']),
        'list_id' => (string) $listId, 'action' => 'toggle',
    ]);
    check('Una adreça es pot donar de baixa sense esborrar-la', $baixa['status'] === 302
        && (int) Db::val('SELECT active FROM mail_contacts WHERE id = :id',
            ['id' => (int) $per['anna@example.cat']['id']], 1) === 0);

    echo "\n== La plantilla del correu ==\n";
    $plantilla = $web('GET', '/enviaments/plantilla');
    check('L\'editor de plantilla respon', $plantilla['status'] === 200);
    check('I porta la capçalera de fàbrica', str_contains($plantilla['body'], '{{plataforma}}'));
    $mostra = $web('GET', '/enviaments/plantilla/vista-previa');
    check('La vista prèvia és un correu sencer',
        str_contains($mostra['body'], '<!doctype html>') && str_contains($mostra['body'], 'Anna Duran'));
    check('Amb els marcadors ja substituïts', !str_contains($mostra['body'], '{{nom}}'));

    $desada = $web('POST', '/enviaments/plantilla', [
        '_token' => $token($plantilla['body']),
        'header' => '<div style="padding:20px">Hola de part de {{plataforma}}</div>',
        'footer' => '<div style="padding:20px">Escriviu-nos a hola@{{web}} · {{any}}</div>',
    ]);
    check('La plantilla es desa', $desada['status'] === 302);
    $mostra = $web('GET', '/enviaments/plantilla/vista-previa');
    check('I la vista prèvia la fa servir',
        str_contains($mostra['body'], 'Hola de part de Cros Escolar')
        && str_contains($mostra['body'], 'hola@crosescolar.test'));

    echo "\n== Enviaments de la plataforma ==\n";
    $nouEnv = $web('GET', '/enviaments/nou');
    check('El formulari d\'enviament respon', $nouEnv['status'] === 200);
    $creat = $web('POST', '/enviaments/nou', [
        '_token' => $token($nouEnv['body']),
        'subject' => 'Novetats per a {{entitat}}',
        'body' => '<p>Hola, {{nom}}. Us escrivim a {{correu}}.</p>',
        'audience' => 'list', 'list_id' => (string) $listId,
        'instance_status' => 'active', 'manual_emails' => '',
    ]);
    $mailingId = (int) Db::val('SELECT id FROM platform_mailings ORDER BY id DESC LIMIT 1', [], 0);
    check('L\'esborrany es desa', $creat['status'] === 302 && $mailingId > 0);

    $fitxaEnv = $web('GET', '/enviaments/' . $mailingId);
    check('La fitxa s\'obre', $fitxaEnv['status'] === 200);
    check('I només compta les adreces d\'alta',
        str_contains($fitxaEnv['body'], 'Veure les 2 adreces'), 'no hi diu 2');

    $previa = $web('GET', '/enviaments/' . $mailingId . '/vista-previa');
    check('La vista prèvia porta la plantilla i el cos',
        str_contains($previa['body'], 'Hola de part de Cros Escolar') && str_contains($previa['body'], 'Us escrivim a'));

    $preparat = $web('POST', '/enviaments/' . $mailingId . '/preparar', ['_token' => $token($fitxaEnv['body'])]);
    check('Es prepara', $preparat['status'] === 302);
    check('Amb un destinatari per adreça d\'alta',
        (int) Db::val('SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id', ['id' => $mailingId], 0) === 2);

    $tanda = $web('POST', '/enviaments/' . $mailingId . '/tanda', [
        '_token' => $token($web('GET', '/enviaments/' . $mailingId)['body']),
    ]);
    $enviament = Db::one('SELECT * FROM platform_mailings WHERE id = :id', ['id' => $mailingId]);
    check('I s\'envia', $tanda['status'] === 302 && (string) $enviament['status'] === 'sent');
    check('Sense cap error', (int) $enviament['sent'] === 2 && (int) $enviament['failed'] === 0,
        $enviament['sent'] . '/' . $enviament['failed']);

    $log = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('L\'assumpte porta els marcadors substituïts',
        str_contains($log, 'Novetats per a AFA Sant Jordi'), 'no surt al registre');
    check('I no queda cap marcador per posar', !str_contains($log, 'Novetats per a {{entitat}}'));

    $repetit = $web('POST', '/enviaments/' . $mailingId . '/preparar', [
        '_token' => $token($web('GET', '/enviaments/' . $mailingId)['body']),
    ]);
    check('Tornar-hi no repeteix cap correu', $repetit['status'] === 302
        && (int) Db::val('SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id', ['id' => $mailingId], 0) === 2);

    // Un enviament a les administradores dels webs: la llista surt de les instàncies.
    $alsWebs = $web('POST', '/enviaments/nou', [
        '_token' => $token($web('GET', '/enviaments/nou')['body']),
        'subject' => 'Manteniment del dissabte',
        'body' => '<p>Aquest dissabte el servei estarà aturat una estona.</p>',
        'audience' => 'instances', 'list_id' => '0', 'instance_status' => 'active', 'manual_emails' => '',
    ]);
    $webId = (int) Db::val('SELECT id FROM platform_mailings ORDER BY id DESC LIMIT 1', [], 0);
    check('També se\'n pot fer un a les administradores', $alsWebs['status'] === 302);
    $web('POST', '/enviaments/' . $webId . '/preparar', ['_token' => $token($web('GET', '/enviaments/' . $webId)['body'])]);
    $esperats = array_column(Db::all(
        "SELECT DISTINCT admin_email FROM instances WHERE status = 'active' AND admin_email <> ''"
    ), 'admin_email');
    $rebran = array_column(Db::all(
        'SELECT email FROM platform_mailing_recipients WHERE mailing_id = :id ORDER BY email', ['id' => $webId]
    ), 'email');
    sort($esperats);
    check('Que agafa l\'adreça de cada web en marxa i cap més',
        $rebran === $esperats && $rebran !== [], implode(', ', $rebran) . ' vs ' . implode(', ', $esperats));

    $sense = $web('POST', '/enviaments/nou', [
        '_token' => $token($web('GET', '/enviaments/nou')['body']),
        'subject' => '', 'body' => '', 'audience' => 'clients', 'list_id' => '0',
        'instance_status' => 'active', 'manual_emails' => '',
    ]);
    check('Un enviament sense assumpte ni cos no es desa',
        $sense['status'] === 200 && str_contains(text($sense['body']), 'Cal posar un assumpte'));

    echo "\n== Actualitzacions del sistema ==\n";
    $updates = $web('GET', '/actualitzacions');
    check('Hi ha la pantalla d\'actualitzacions',
        $updates['status'] === 200 && str_contains($updates['body'], 'Versió instal·lada'));
    check('Amb la versió que hi ha ara', str_contains($updates['body'], app_version()));
    check('I avisa que falta dir d\'on surten les versions',
        str_contains($updates['body'], 'adreça del manifest'));

    $copia = $web('POST', '/actualitzacions/copia', ['_token' => $token($updates['body'])]);
    check('Se\'n pot fer una còpia del sistema', $copia['status'] === 302);
    $copies = glob($site . '/storage/backups/backup-*.zip') ?: [];
    check('Que queda desada', count($copies) === 1, implode(', ', array_map('basename', $copies)));
    $llista = $web('GET', '/actualitzacions');
    check('I surt a la llista', count($copies) === 1 && str_contains($llista['body'], basename($copies[0])));
    $baixada = $web('GET', '/actualitzacions/copia?fitxer=' . rawurlencode(basename($copies[0] ?? 'res.zip')));
    check('Se la pot descarregar',
        $baixada['status'] === 200 && str_contains($baixada['headers'], 'application/zip'),
        'estat ' . $baixada['status'] . ', ' . trim(strtok($baixada['headers'], "\n")));
    check('Un nom inventat no dona res',
        $web('GET', '/actualitzacions/copia?fitxer=' . rawurlencode('../tenants/platform.php'))['status'] === 404);

    $dolent = $web('POST', '/actualitzacions/instalar', ['_token' => $token($llista['body'])]);
    check('Sense paquet no s\'actualitza res', $dolent['status'] === 302);
    check('I el sistema es queda com estava', app_version() === (string) (require $site . '/app/version.php')['version']);

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
        foreach (['santjordi', 'elbosc', 'lagranada', 'lariera', 'duatlotardor'] as $slug) {
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
