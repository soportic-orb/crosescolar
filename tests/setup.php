<?php
/**
 * Proves de l'assistent d'instal·lació de la plataforma.
 *
 * Es munta un servidor de mentida com el que deixa tools/instalar-vps.sh (amb el
 * fitxer de dades i el testimoni) i es recorre l'assistent pas a pas, com ho
 * faria qui instal·la.
 *
 * Ús:
 *   CROS_DB_USER=cros CROS_DB_PASS=cros CROS_DB_PLATFORM=cros_assistent \
 *   CROS_DB_ADMIN_USER=root CROS_DB_ADMIN_PASS=root php tests/setup.php
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
];
if ($db['user'] === '' || $db['platform'] === '' || $db['admin_user'] === '') {
    echo "Proves de l'assistent omeses: definiu CROS_DB_USER, CROS_DB_PLATFORM i CROS_DB_ADMIN_USER.\n";
    exit(0);
}

putenv('CROS_CONFIG');
putenv('CROS_UPLOADS');
putenv('CROS_STORAGE');

require $root . '/app/bootstrap.php';

use Cros\Platform\Setup;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

echo "\n== El que sap fer l'assistent ==\n";
check('Reconeix un domini', Setup::validDomain('crosescolar.cat'));
check('I en descarta un que no ho és',
    !Setup::validDomain('no és un domini') && !Setup::validDomain('localhost') && !Setup::validDomain('-mal.cat'));
check('Separa els dominis escrits de qualsevol manera',
    Setup::domains(" Crosescolar.CAT , crosescolar.com\n crosescolar.cat ") === ['crosescolar.cat', 'crosescolar.com']);

$config = Setup::render([
    'name' => 'Cros Escolar',
    'domains' => ['crosescolar.cat', 'crosescolar.com'],
    'console' => 'admin',
    'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'p', 'user' => 'u', 'pass' => 'x'],
    'provision' => ['db_host' => 'localhost', 'admin_user' => 'a', 'admin_pass' => 'b', 'db_prefix' => 'cros_'],
    'mail' => ['notify' => 'hola@crosescolar.cat', 'transport' => 'smtp'],
]);
$parsed = eval('?>' . $config);
check('La configuració que escriu és PHP vàlid', is_array($parsed));
check('Amb el domini principal el primer', ($parsed['base_domain'] ?? '') === 'crosescolar.cat');
check('I els altres a part', ($parsed['domains'] ?? []) === ['crosescolar.com']);
check('El panell, al subdomini que s\'ha dit', ($parsed['console'] ?? []) === ['admin']);
check('Les dades de les bases de dades hi són',
    ($parsed['db']['name'] ?? '') === 'p' && ($parsed['provision']['admin_user'] ?? '') === 'a');

/* Un servidor com el que deixa l'script ------------------------------------- */
$site = sys_get_temp_dir() . '/cros-assistent-' . bin2hex(random_bytes(3));
@mkdir($site . '/tenants', 0775, true);
@mkdir($site . '/storage/logs', 0775, true);
@mkdir($site . '/uploads', 0775, true);
foreach (['app', 'assets'] as $folder) {
    exec('cp -r ' . escapeshellarg($root . '/' . $folder) . ' ' . escapeshellarg($site . '/' . $folder));
}
copy($root . '/index.php', $site . '/index.php');
copy($root . '/install-plataforma.php', $site . '/install-plataforma.php');
@unlink($site . '/app/config.php');

$token = bin2hex(random_bytes(24));
file_put_contents($site . '/storage/' . Setup::TOKEN, $token);
file_put_contents($site . '/' . Setup::HINTS, json_encode([
    'arrel' => $site,
    'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    'dominis' => ['crosescolar.test', 'crosescolar.example'],
    'db' => [
        'host' => $db['host'], 'port' => $db['port'], 'name' => $db['platform'],
        'user' => $db['user'], 'pass' => $db['pass'],
    ],
    'provision' => ['admin_user' => $db['admin_user'], 'admin_pass' => $db['admin_pass']],
], JSON_PRETTY_PRINT));

// La base de dades de la plataforma, buida.
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['platform']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $row[0] . '`');
}

$port = (int) (getenv('CROS_TEST_PORT_SETUP') ?: 8133);
$busy = @fsockopen('127.0.0.1', $port, $errno, $error, 1);
if ($busy !== false) {
    fclose($busy);
    echo "  El port $port ja està ocupat: atureu el que hi hagi o definiu CROS_TEST_PORT_SETUP.\n";
    exit(1);
}
$server = proc_open(
    sprintf('exec php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($site)),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);
$base = 'http://127.0.0.1:' . $port;
$jar = sys_get_temp_dir() . '/cros-assistent-' . bin2hex(random_bytes(3)) . '.txt';

$web = static function (string $method, string $path, array $fields = []) use ($base, $jar): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
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

/** Els camps amagats d'un formulari, per tornar-los a enviar com faria el navegador. */
$fields = static function (string $html): array {
    preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $field) {
        $out[$field[1]] = html_entity_decode($field[2], ENT_QUOTES);
    }

    return $out;
};

for ($i = 0; $i < 40 && ($web('GET', '/install-plataforma.php')['status'] ?? 0) === 0; $i++) {
    usleep(200000);
}

try {
    echo "\n== Qui pot entrar-hi ==\n";
    $tancat = $web('GET', '/install-plataforma.php');
    check('Sense clau no s\'hi entra', str_contains($tancat['body'], 'necessita la clau'));
    check('Amb una clau inventada, tampoc',
        str_contains($web('GET', '/install-plataforma.php?clau=' . str_repeat('a', 48))['body'], 'necessita la clau'));

    $pas1 = $web('GET', '/install-plataforma.php?clau=' . $token);
    check('Amb la bona, sí', str_contains($pas1['body'], 'Com està el servidor'));
    check('I diu què ha deixat fet l\'script', str_contains($pas1['body'], 'crosescolar.test'));

    echo "\n== Pas a pas ==\n";
    $pas2 = $web('GET', '/install-plataforma.php?pas=2&clau=' . $token);
    check('Del primer pas es passa als dominis', str_contains($pas2['body'], 'Els dominis'));
    check('Amb els dominis ja escrits', str_contains($pas2['body'], 'crosescolar.test, crosescolar.example'));

    $mal = $web('POST', '/install-plataforma.php', $fields($pas2['body']) + [
        'name' => 'Cros Escolar', 'domains' => 'això no és un domini', 'console' => 'admin',
    ]);
    check('Un domini mal escrit no passa', str_contains($mal['body'], 'no sembla un domini'));

    $pas3 = $web('POST', '/install-plataforma.php', $fields($pas2['body']) + [
        'name' => 'Cros Escolar', 'domains' => 'crosescolar.test, crosescolar.example', 'console' => 'admin',
    ]);
    check('Amb els dominis bons, es passa a la base de dades', str_contains($pas3['body'], 'Les bases de dades'));

    $malament = $web('POST', '/install-plataforma.php', $fields($pas3['body']) + ['db_pass' => 'no-es-aquesta']);
    check('Una contrasenya equivocada es nota',
        str_contains($malament['body'], 'no són correctes') || str_contains($malament['body'], 'Base de dades de la plataforma'));

    $pas4 = $web('POST', '/install-plataforma.php', $fields($pas3['body']));
    check('Amb les bones, es prova la connexió', str_contains($pas4['body'], 'Connectat amb ' . $db['platform']));
    check('I que l\'usuari pot crear bases de dades', str_contains($pas4['body'], 'pot crear bases de dades'));
    check('Després toca el correu', str_contains($pas4['body'], 'El correu'));

    $senseCorreu = $web('POST', '/install-plataforma.php', $fields($pas4['body']) + ['mail_notify' => 'aixo-no-val']);
    check('Cal una adreça d\'avisos de debò', str_contains($senseCorreu['body'], 'adreça vàlida'));

    $pas5 = $web('POST', '/install-plataforma.php', $fields($pas4['body']) + [
        'mail_from_name' => 'Cros Escolar', 'mail_from_email' => 'no-reply@crosescolar.test',
        'mail_notify' => 'hola@crosescolar.test', 'mail_transport' => 'log',
        'smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '', 'smtp_secure' => 'tls',
    ]);
    check('Amb el correu posat, toca el compte', str_contains($pas5['body'], 'El vostre compte'));

    $curta = $web('POST', '/install-plataforma.php', $fields($pas5['body']) + [
        'super_name' => 'Octavi', 'super_email' => 'super@crosescolar.test',
        'super_pass' => 'curta', 'super_pass2' => 'curta',
    ]);
    check('Una contrasenya curta no s\'accepta', str_contains($curta['body'], 'com a mínim 8'));

    $diferents = $web('POST', '/install-plataforma.php', $fields($pas5['body']) + [
        'super_name' => 'Octavi', 'super_email' => 'super@crosescolar.test',
        'super_pass' => 'unaClauLlarga1', 'super_pass2' => 'unaAltra1234',
    ]);
    check('Ni dues de diferents', str_contains($diferents['body'], 'no coincideixen'));

    $pas6 = $web('POST', '/install-plataforma.php', $fields($pas5['body']) + [
        'super_name' => 'Octavi', 'super_email' => 'super@crosescolar.test',
        'super_pass' => 'unaClauLlarga1', 'super_pass2' => 'unaClauLlarga1',
    ]);
    check('Abans d\'engegar, es resumeix tot', str_contains($pas6['body'], 'Tot a punt per engegar'));
    check('Amb el panell que tindrà', str_contains($pas6['body'], 'admin.crosescolar.test'));

    echo "\n== Engegar-ho ==\n";
    $final = $web('POST', '/install-plataforma.php', $fields($pas6['body']));
    check('La plataforma s\'engega', str_contains($final['body'], 'Ja està en marxa'), 'estat ' . $final['status']);
    check('S\'escriu la configuració', is_file($site . '/tenants/platform.php'));
    check('Amb el domini principal',
        str_contains((string) @file_get_contents($site . '/tenants/platform.php'), "'base_domain' => 'crosescolar.test'"));
    check('I sense que la puguin llegir els altres', (fileperms($site . '/tenants/platform.php') & 0o007) === 0);

    $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
    check('Es creen les taules de la plataforma',
        in_array('instances', $tables, true) && in_array('platform_users', $tables, true), implode(', ', $tables));
    $user = $pdo->query("SELECT * FROM platform_users WHERE email = 'super@crosescolar.test'")->fetch(PDO::FETCH_ASSOC);
    check('I el compte de superadministració', $user !== false);
    check('Amb la contrasenya xifrada',
        $user !== false && password_verify('unaClauLlarga1', (string) $user['password_hash']));
    check('El resum diu què falta per fer', str_contains($final['body'], 'Què queda per fer'));

    echo "\n== Tancar-lo ==\n";
    $tancar = $web('POST', '/install-plataforma.php', $fields($final['body']));
    check('En tancar, se\'n va al panell', $tancar['status'] === 302);
    check('S\'esborra la clau', !is_file($site . '/storage/' . Setup::TOKEN));
    check('I les dades que havia deixat l\'script', !is_file($site . '/' . Setup::HINTS));

    $repetit = $web('GET', '/install-plataforma.php?clau=' . $token);
    check('L\'assistent ja no torna a obrir-se', str_contains($repetit['body'], 'ja està instal·lada'));
    check('I recorda que cal esborrar-lo', str_contains($repetit['body'], 'install-plataforma.php'));

    echo "\n== El web ja és la plataforma ==\n";
    $ch = curl_init($base . '/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Host: crosescolar.test'], CURLOPT_TIMEOUT => 20,
    ]);
    $home = (string) curl_exec($ch);
    $homeStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    check('La portada de la plataforma respon', $homeStatus === 200, 'estat ' . $homeStatus);
    check('Amb el formulari per demanar un cros', str_contains($home, 'name="entity"'));
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    @unlink($jar);
    exec('rm -rf ' . escapeshellarg($site));
}

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
