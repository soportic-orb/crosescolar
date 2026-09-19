<?php
/**
 * Prova de l'instal·lador contra un MySQL/MariaDB real.
 *
 * Recorre install.php tal com ho faria un navegador, incloent-hi la consulta
 * «?pas=2» a l'adreça, que és el cas que abans deixava l'assistent aturat.
 *
 * Ús:
 *   CROS_DB_NAME=cros CROS_DB_USER=cros CROS_DB_PASS=secret php tests/installer.php
 *
 * Si no hi ha dades de connexió, la prova s'omet (no falla).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = [
    'host' => getenv('CROS_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('CROS_DB_PORT') ?: '3306',
    'name' => getenv('CROS_DB_NAME') ?: '',
    'user' => getenv('CROS_DB_USER') ?: '',
    'pass' => getenv('CROS_DB_PASS') ?: '',
];
if ($db['name'] === '' || $db['user'] === '') {
    echo "Prova de l'instal·lador omesa: definiu CROS_DB_NAME i CROS_DB_USER.\n";
    exit(0);
}

$port = (int) (getenv('CROS_TEST_PORT') ?: 8126);
$base = 'http://127.0.0.1:' . $port;
$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

function post(string $url, array $fields): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_TIMEOUT => 90,
        CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/cros-installer-cookies.txt',
        CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/cros-installer-cookies.txt',
    ]);
    $body = (string) curl_exec($ch);
    curl_close($ch);
    return $body;
}

function errors(string $html): array
{
    preg_match_all('/alert alert--error">\s*([^<]{0,160})/', $html, $m);
    return array_map(static fn ($x) => trim(html_entity_decode($x)), $m[1]);
}

/* Estat inicial net -------------------------------------------------------- */
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
@unlink($root . '/app/config.php');
@unlink($root . '/storage/installed.lock');
@unlink($root . '/storage/install.token');

/* Servidor local ----------------------------------------------------------- */
$server = proc_open(
    sprintf('php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($root)),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);
for ($i = 0; $i < 40 && @file_get_contents($base . '/install.php') === false; $i++) {
    usleep(200000);
}

try {
    echo "\n== Assistent d'instal·lació ==\n";

    // Pas 2: el formulari s'envia des d'una adreça que ja porta «?pas=2»
    $html = post($base . '/install.php?pas=2', [
        'pas' => '2',
        'db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['name'],
        'db_user' => $db['user'], 'db_pass' => $db['pass'], 'db_socket' => '',
        'base_url' => $base,
    ]);
    check('El pas 2 comprova la connexió', str_contains($html, 'Connexió correcta'), implode(' | ', errors($html)));
    check('Arriba al formulari del pas 3', str_contains($html, 'name="fase" value="config"'));
    check('Els continguts d\'exemple estan marcats per defecte', (bool) preg_match('/name="demo" value="1" checked/', $html));

    // Pas 3: cada fase s'envia des de la mateixa adreça amb consulta antiga
    $fields = [
        'pas' => '3', 'fase' => 'config',
        'db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['name'],
        'db_user' => $db['user'], 'db_pass' => $db['pass'], 'db_socket' => '',
        'base_url' => $base, 'site_name' => 'Cros Escolar La Granada',
        'event_date' => '2026-10-04', 'admin_name' => 'Proves',
        'admin_email' => 'proves@example.cat', 'admin_pass' => 'provaprova', 'demo' => '1',
    ];
    $phases = [];
    $completed = false;
    for ($i = 0; $i < 10 && !$completed; $i++) {
        $phase = $fields['fase'];
        $html = post($base . '/install.php?pas=2', $fields);
        if ($problems = errors($html)) {
            check('Fase «' . $phase . '»', false, implode(' | ', $problems));
            break;
        }
        $phases[] = $phase;
        if (str_contains($html, 'Instal·lació completada correctament')) {
            $completed = true;
            break;
        }
        if (!preg_match('/<form method="post" action="install\.php" id="next-form">(.*?)<\/form>/s', $html, $form)) {
            check('Fase «' . $phase . '» continua', false, 'no hi ha formulari per a la fase següent');
            break;
        }
        preg_match_all('/name="([^"]+)" value="([^"]*)"/', $form[1], $inputs, PREG_SET_ORDER);
        $fields = [];
        foreach ($inputs as $input) {
            $fields[$input[1]] = html_entity_decode($input[2], ENT_QUOTES);
        }
    }

    check('La instal·lació arriba al final', $completed, 'fases executades: ' . implode(', ', $phases));
    check('S\'executen les sis fases', count($phases) === 6, count($phases) . ' fases');

    echo "\n== Resultat a la base de dades ==\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    check('Es creen totes les taules', count($tables) >= 20, count($tables) . ' taules');
    check('Es crea el compte d\'administració',
        (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'proves@example.cat' AND role = 'admin'")->fetchColumn() === 1);
    check('S\'inicialitza la configuració', (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() > 50);
    check('Es creen els continguts d\'exemple', (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn() === 3);
    check('Es crea el fitxer de configuració', is_file($root . '/app/config.php'));
    check('L\'instal·lador queda bloquejat', is_file($root . '/storage/installed.lock'));
    check('S\'esborra el testimoni temporal', !is_file($root . '/storage/install.token'));

    $again = (string) file_get_contents($base . '/install.php');
    check('No es pot tornar a executar', str_contains($again, 'ja està instal·lat'));

    echo "\n== El web funciona després d'instal·lar ==\n";
    foreach (['/' => 'portada', '/index.php?_p=/punt-de-recarrega' => 'tiquets', '/index.php?_p=/admin/acces' => 'accés al panell'] as $path => $label) {
        $page = (string) @file_get_contents($base . $path);
        check('Carrega la ' . $label, $page !== '' && !str_contains($page, 'Error 500'));
    }
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    @unlink($root . '/app/config.php');
    @unlink($root . '/storage/installed.lock');
}

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
