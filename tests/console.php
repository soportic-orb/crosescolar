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
use Cros\Platform\Console;
use Cros\Platform\Dns;
use Cros\Platform\Health;
use Cros\Platform\Importer;
use Cros\Platform\Instance;
use Cros\Platform\Charge;
use Cros\Platform\Invoice;
use Cros\Platform\Plan;
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
    check('I la del comodí és la de sempre',
        str_contains(Certificate::command($site, true), "-d '*.crosescolar.test'"),
        Certificate::command($site, true));

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
        !str_contains($web('GET', '/configuracio/mail')['body'], 'site-tabs'));

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

    // Un subdomini reservat no es dona, i no es crea res.
    $dolent = $web('POST', '/registre', [
        '_token' => $token($portada['body']),
        'site_name' => 'Cursa de prova', 'admin_name' => 'Ona Vidal',
        'admin_email' => 'ona@example.cat', 'slug' => 'admin',
        'domain' => 'esportweb.test', 'consent' => '1',
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
        'event_date' => date('Y-m-d', strtotime('+2 months')), 'consent' => '1',
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
    $correus = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
    check('Se li envia el correu de confirmació', str_contains($correus, 'ona@example.cat'));

    $benvinguda = $web('GET', '/benvinguda', [], 'esportweb.test');
    check('La pantalla de després diu on mirar',
        $benvinguda['status'] === 200 && str_contains(text($benvinguda['body']), 'ona@example.cat'),
        'estat ' . $benvinguda['status']);
    check('I quina serà l\'adreça del panell',
        str_contains(text($benvinguda['body']), 'lariera.esportweb.test/admin'));
    check('Sense cap drecera per entrar-hi sense el correu',
        !str_contains($benvinguda['body'], '/admin/clau/'));
    check('Tornar-hi no ensenya res', $web('GET', '/benvinguda', [], 'esportweb.test')['status'] === 302);

    check('Al panell hi surt marcada com a pendent de validar',
        str_contains($web('GET', '/instancies')['body'], 'Correu per validar'));

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
        'domain' => 'esportweb.test', 'consent' => '1',
    ], 'esportweb.test');
    check('I tampoc no s\'hi pot entrar per la porta del darrere',
        $intent['status'] === 302 && Db::one("SELECT * FROM instances WHERE slug = 'unaaltra'") === null);
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
        foreach (['santjordi', 'elbosc', 'lagranada', 'lariera'] as $slug) {
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
