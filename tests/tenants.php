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

echo "\n== Qui atén cada adreça ==\n";

require_once __DIR__ . '/../app/core/Tenancy.php';

use Cros\Core\Tenancy;

// Sense el fitxer de la plataforma, tot funciona com una instal·lació de sempre.
$plain = sys_get_temp_dir() . '/cros-sense-plataforma-' . bin2hex(random_bytes(3));
@mkdir($plain, 0775, true);
check('Sense plataforma, una sola instal·lació',
    Tenancy::boot($plain, 'elmeucros.cat')['mode'] === 'single',
    Tenancy::boot($plain, 'elmeucros.cat')['mode']);

// Un servidor amb la plataforma engegada.
$server = sys_get_temp_dir() . '/cros-plataforma-' . bin2hex(random_bytes(3));
@mkdir($server . '/tenants', 0775, true);
file_put_contents($server . '/tenants/platform.php', "<?php return ['base_domain' => 'crosescolar.cat', "
    . "'domains' => ['crosescolar.com'], 'console' => ['admin'], 'reserved' => ['premsa']];\n");

$mode = static fn (string $host): string => Tenancy::boot($server, $host)['mode'];

check('El domini principal és la pàgina pública', $mode('crosescolar.cat') === 'platform');
check('I el segon domini, també', $mode('crosescolar.com') === 'platform');
check('Amb «www», també', $mode('www.crosescolar.com') === 'platform');
check('«admin» és el panell de superadministració', $mode('admin.crosescolar.com') === 'console');
check('Un subdomini sense instal·lar no existeix', $mode('ningu.crosescolar.com') === 'unknown');
check('Un domini de fora no és de ningú', $mode('unaltreweb.cat') === 'unknown',
    $mode('unaltreweb.cat'));
check('Ni tan sols si s\'hi assembla', $mode('altrecrosescolar.com') === 'unknown',
    $mode('altrecrosescolar.com'));

// Ara sí, una instància instal·lada.
foreach (['granada', 'vilafranca'] as $slug) {
    @mkdir($server . '/tenants/' . $slug . '/uploads', 0775, true);
    @mkdir($server . '/tenants/' . $slug . '/storage', 0775, true);
    file_put_contents($server . '/tenants/' . $slug . '/config.php', "<?php return ['db' => ['name' => 'cros_$slug']];\n");
}

$granada = Tenancy::boot($server, 'granada.crosescolar.com');
check('Un subdomini instal·lat és el web d\'un client', $granada['mode'] === 'tenant');
check('Se sap de quin client és', $granada['slug'] === 'granada');
check('I on té les seves dades', $granada['dir'] === $server . '/tenants/granada');
check('La configuració queda apuntada', getenv('CROS_CONFIG') === $server . '/tenants/granada/config.php');
check('Els fitxers pujats també', getenv('CROS_UPLOADS') === $server . '/tenants/granada/uploads');
check('I la carpeta de treball', getenv('CROS_STORAGE') === $server . '/tenants/granada/storage');

$vilafranca = Tenancy::boot($server, 'VILAFRANCA.CrosEscolar.com:8080');
check('L\'adreça pot venir en majúscules i amb port', $vilafranca['slug'] === 'vilafranca');
check('I apunta a les seves dades', getenv('CROS_CONFIG') === $server . '/tenants/vilafranca/config.php');

// Una instància aturada.
file_put_contents($server . '/tenants/granada/' . Tenancy::SUSPENDED, '');
check('Una instància aturada no serveix el web', $mode('granada.crosescolar.com') === 'suspended');
@unlink($server . '/tenants/granada/' . Tenancy::SUSPENDED);
check('En reactivar-la, torna', $mode('granada.crosescolar.com') === 'tenant');

check('Les instàncies instal·lades es poden llistar',
    Tenancy::all($server) === ['granada', 'vilafranca'],
    implode(', ', Tenancy::all($server)));

echo "\n== Més d'un domini ==\n";
check('El principal és el primer de la llista', Tenancy::primary($server) === 'crosescolar.cat');
check('I se saben tots', Tenancy::domains($server) === ['crosescolar.cat', 'crosescolar.com']);
check('Un cros es pot demanar pels dos dominis',
    $mode('granada.crosescolar.cat') === 'tenant' && $mode('granada.crosescolar.com') === 'tenant');
check('I se sap per quin ha entrat',
    Tenancy::boot($server, 'granada.crosescolar.cat')['domain'] === 'crosescolar.cat'
    && Tenancy::boot($server, 'granada.crosescolar.com')['domain'] === 'crosescolar.com');
check('Les dues adreces porten a les mateixes dades',
    Tenancy::boot($server, 'granada.crosescolar.com')['dir'] === $server . '/tenants/granada');
check('El panell també respon pels dos', $mode('admin.crosescolar.cat') === 'console'
    && $mode('admin.crosescolar.com') === 'console');
check('Un domini de fora continua sense ser de ningú', $mode('granada.crosescolar.org') === 'unknown');

echo "\n== Subdominis que no valen ==\n";

check('Un nom amb punts no és un subdomini', $mode('una.altra.crosescolar.com') === 'unknown');
check('Ni un que vulgui sortir de la carpeta', Tenancy::dir($server, '../../etc') === '');
check('Tampoc amb barres', Tenancy::dir($server, 'granada/../vilafranca') === '');
check('«admin» no es pot donar a ningú', Tenancy::reserved('admin'));
check('«correu» tampoc', Tenancy::reserved('correu'));
check('Ni els que afegeixi la plataforma', Tenancy::reserved('premsa', ['premsa']));
check('Un nom normal sí que val', Tenancy::valid('santjordi'));
check('Amb guió al mig, també', Tenancy::valid('sant-jordi'));
check('Però no amb guió al final', !Tenancy::valid('santjordi-'));
check('Ni amb dos guions seguits', !Tenancy::valid('sant--jordi'));
check('Ni amb accents o majúscules', !Tenancy::valid('Sant Jordi') && !Tenancy::valid('olèrdola'));
check('Ni massa curt', !Tenancy::valid('a'));
check('Ni massa llarg', !Tenancy::valid(str_repeat('a', 31)));

check('El subdomini se separa bé del domini',
    Tenancy::slug('granada.crosescolar.com', 'crosescolar.com') === 'granada'
    && Tenancy::slug('crosescolar.com', 'crosescolar.com') === ''
    && Tenancy::slug('altrecrosescolar.com', 'crosescolar.com') === '');

// Neteja
foreach (['granada', 'vilafranca'] as $slug) {
    @unlink($server . '/tenants/' . $slug . '/config.php');
    @rmdir($server . '/tenants/' . $slug . '/uploads');
    @rmdir($server . '/tenants/' . $slug . '/storage');
    @rmdir($server . '/tenants/' . $slug);
}
@unlink($server . '/tenants/platform.php');
@rmdir($server . '/tenants');
@rmdir($server);
@rmdir($plain);
putenv('CROS_CONFIG');
putenv('CROS_UPLOADS');
putenv('CROS_STORAGE');

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
