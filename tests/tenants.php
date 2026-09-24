<?php
/**
 * Proves de la plataforma multi-instància.
 *
 * De moment cobreixen el primer pas: una instal·lació pot tenir les seves
 * carpetes de dades fora del codi, de manera que diverses instal·lacions
 * puguin compartir la mateixa còpia de l'aplicació.
 *
 * Ús:  php tests/tenants.php
 */
declare(strict_types=1);

// Aquesta bateria no necessita base de dades: cada comprovació arrenca
// l'aplicació en un procés a part, que és l'única manera de provar unes
// constants que es fixen en arrencar.
$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/**
 * Executa codi en un procés a part, amb les carpetes que se li diguin.
 * Cal un procés nou perquè les constants es fixen en arrencar l'aplicació.
 */
function inProcess(string $code, array $env = []): string
{
    $root = dirname(__DIR__);
    $file = sys_get_temp_dir() . '/cros-tenant-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($file, "<?php\nrequire '" . $root . "/app/bootstrap.php';\n" . $code . "\n");
    $prefix = '';
    foreach ($env as $key => $value) {
        $prefix .= $key . '=' . escapeshellarg((string) $value) . ' ';
    }
    $out = (string) shell_exec($prefix . escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
    @unlink($file);

    return trim($out);
}

echo "\n== Carpetes de dades de cada instal·lació ==\n";

$root = dirname(__DIR__);
check('Sense dir res, els fitxers pujats són on sempre',
    inProcess('echo CROS_UPLOADS;') === $root . '/uploads',
    inProcess('echo CROS_UPLOADS;'));
check('I la carpeta de treball també',
    inProcess('echo CROS_STORAGE;') === $root . '/storage');

$dataDir = sys_get_temp_dir() . '/cros-instancia-' . bin2hex(random_bytes(3));
@mkdir($dataDir . '/uploads', 0775, true);
@mkdir($dataDir . '/storage', 0775, true);
$env = ['CROS_UPLOADS' => $dataDir . '/uploads', 'CROS_STORAGE' => $dataDir . '/storage'];

check('Es poden posar en una altra banda',
    inProcess('echo CROS_UPLOADS;', $env) === $dataDir . '/uploads',
    inProcess('echo CROS_UPLOADS;', $env));
check('Amb una barra final de més, s\'entén igual',
    inProcess('echo CROS_STORAGE;', ['CROS_STORAGE' => $dataDir . '/storage/']) === $dataDir . '/storage');
check('Una variable buida no compta',
    inProcess('echo CROS_UPLOADS;', ['CROS_UPLOADS' => '   ']) === $root . '/uploads');

echo "\n== Els fitxers hi van de debò ==\n";

check('La ruta d\'un fitxer pujat surt de la carpeta de la instal·lació',
    inProcess('echo upload_path("images/cartell.jpg");', $env) === $dataDir . '/uploads/images/cartell.jpg');
check('Tant si el nom porta barra al davant com si no',
    inProcess('echo upload_path("/images/cartell.jpg");', $env) === $dataDir . '/uploads/images/cartell.jpg');
check('La carpeta de treball es demana igual',
    inProcess('echo storage_path("logs");', $env) === $dataDir . '/storage/logs');
check('I sense res, és la carpeta mateixa',
    inProcess('echo storage_path();', $env) === $dataDir . '/storage');

inProcess('log_line("prova", "una línia de prova");', $env);
$logs = glob($dataDir . '/storage/logs/app-*.log') ?: [];
check('El registre s\'escriu a la carpeta de la instal·lació', count($logs) === 1, (string) count($logs));
check('I hi diu el que toca',
    $logs && str_contains((string) file_get_contents($logs[0]), 'una línia de prova'));
check('No s\'ha escrit res a la carpeta del codi',
    !is_file($root . '/storage/logs/app-' . date('Y-m') . '.log')
    || !str_contains((string) file_get_contents($root . '/storage/logs/app-' . date('Y-m') . '.log'), 'una línia de prova'));

echo "\n== Dues instal·lacions no es trepitgen ==\n";

$altre = sys_get_temp_dir() . '/cros-instancia-' . bin2hex(random_bytes(3));
@mkdir($altre . '/uploads', 0775, true);
@mkdir($altre . '/storage', 0775, true);
$env2 = ['CROS_UPLOADS' => $altre . '/uploads', 'CROS_STORAGE' => $altre . '/storage'];

file_put_contents($dataDir . '/uploads/cartell.jpg', 'primera');
file_put_contents($altre . '/uploads/cartell.jpg', 'segona');
check('Cada instal·lació llegeix el seu fitxer',
    file_get_contents(inProcess('echo upload_path("cartell.jpg");', $env)) === 'primera'
    && file_get_contents(inProcess('echo upload_path("cartell.jpg");', $env2)) === 'segona');

inProcess('log_line("prova", "només de la segona");', $env2);
$primers = (string) file_get_contents($logs[0] ?? '/dev/null');
check('I el registre d\'una no surt a l\'altra', !str_contains($primers, 'només de la segona'));

echo "\n== L'adreça pública dels fitxers no canvia ==\n";
check('Continua penjant de /uploads',
    str_ends_with(inProcess('echo upload_url("images/cartell.jpg");', $env), '/uploads/images/cartell.jpg'),
    inProcess('echo upload_url("images/cartell.jpg");', $env));

// Neteja
foreach ([$dataDir, $altre] as $dir) {
    foreach (['uploads', 'storage/logs', 'storage'] as $sub) {
        foreach (glob($dir . '/' . $sub . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    @rmdir($dir . '/uploads');
    @rmdir($dir . '/storage/logs');
    @rmdir($dir . '/storage');
    @rmdir($dir);
}

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
