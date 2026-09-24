<?php
/**
 * Instal·lador automàtic del Cros Escolar La Granada.
 *
 * La instal·lació s'executa en fases curtes i independents (una petició cada una)
 * perquè cap servidor la pugui tallar per temps d'execució, i sense fer servir la
 * sessió, de manera que mai no es queda bloquejada esperant el fitxer de sessió.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Cros\Core\Crypto;
use Cros\Core\Db;
use Cros\Core\Migrator;
use Cros\Core\Seeder;
use Cros\Core\Settings;

// L'instal·lador no ha de dependre del bloqueig de sessió ni dels límits de temps.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
@set_time_limit(300);
@ini_set('max_execution_time', '300');
@ini_set('display_errors', '1');
@ini_set('implicit_flush', '1');
error_reporting(E_ALL);
ignore_user_abort(true);

$lockFile = storage_path('installed.lock');
$configFile = CROS_APP . '/config.php';
$tokenFile = storage_path('install.token');
$alreadyInstalled = is_file($lockFile) && is_file($configFile);

/** Escriu una línia al registre d'instal·lació. */
function install_log(string $message, array $context = []): void
{
    $dir = storage_path('logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(
        $dir . '/install-' . date('Y-m-d') . '.log',
        sprintf("[%s] %s %s\n", date('H:i:s'), $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''),
        FILE_APPEND | LOCK_EX
    );
}

// Qualsevol error fatal s'ha de veure: mai una pàgina en blanc.
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        install_log('ERROR FATAL: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
        echo '<div style="font-family:system-ui;max-width:700px;margin:2rem auto;padding:1.2rem;'
            . 'border:1px solid #efc4b3;background:#fbeee9;border-radius:12px;color:#8f3618">'
            . '<strong>Error fatal durant la instal·lació</strong><br>'
            . htmlspecialchars($error['message'], ENT_QUOTES) . '<br><small>'
            . htmlspecialchars(basename((string) $error['file']), ENT_QUOTES) . ':' . (int) $error['line']
            . '</small><p>Trobareu el detall a <code>storage/logs/install-' . date('Y-m-d') . '.log</code>.</p></div>';
    }
});

/** Comprovacions del servidor. */
function requirements(): array
{
    $writable = static fn (string $path): bool => is_dir($path) ? is_writable($path) : is_writable(dirname($path));
    return [
        ['PHP 8.0 o superior', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION, true],
        ['Extensió PDO MySQL', extension_loaded('pdo_mysql'), 'pdo_mysql', true],
        ['Extensió mbstring', extension_loaded('mbstring'), 'mbstring', true],
        ['Extensió OpenSSL', extension_loaded('openssl'), 'openssl', true],
        ['Extensió JSON', extension_loaded('json'), 'json', true],
        ['Extensió GD (imatges)', extension_loaded('gd'), 'gd', false],
        ['Extensió ZIP (actualitzacions)', class_exists('ZipArchive'), 'zip', false],
        ['Extensió cURL (Stripe)', function_exists('curl_init'), 'curl', false],
        ['Carpeta app/ escrivible', $writable(CROS_APP . '/config.php'), 'app/', true],
        ['Carpeta dels fitxers pujats escrivible', $writable(CROS_UPLOADS), basename(CROS_UPLOADS) . '/', true],
        ['Carpeta de treball escrivible', $writable(CROS_STORAGE), basename(CROS_STORAGE) . '/', true],
    ];
}

/** Connexió a la base de dades amb temps d'espera curt. */
function install_connect(array $data): PDO
{
    return Db::connect([
        'host' => trim((string) $data['db_host']),
        'port' => (int) $data['db_port'],
        'name' => trim((string) $data['db_name']),
        'user' => trim((string) $data['db_user']),
        'pass' => (string) $data['db_pass'],
        'charset' => 'utf8mb4',
        'socket' => trim((string) ($data['db_socket'] ?? '')),
        'timeout' => 10,
    ]);
}

/** Camps que viatgen d'una fase a la següent. */
const CARRY = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'db_socket', 'base_url',
    'site_name', 'event_date', 'admin_name', 'admin_email', 'admin_pass', 'token', 'log'];

$phases = [
    'config'   => 'Escrivint la configuració',
    'schema'   => 'Creant les taules de la base de dades',
    'admin'    => 'Creant el compte d\'administració',
    'settings' => 'Aplicant la configuració inicial',
    'demo'     => 'Creant els continguts d\'exemple',
    'finish'   => 'Finalitzant la instal·lació',
];

/**
 * Executa una fase. Retorna [correcte, missatge].
 * Totes les fases es poden repetir sense efectes secundaris.
 */
function run_phase(string $phase, array &$data): array
{
    $started = microtime(true);
    install_log('Inici de la fase «' . $phase . '»');

    switch ($phase) {
        case 'config':
            $pdo = install_connect($data);
            Db::setConnection($pdo);
            $config = "<?php\n/**\n * Configuració generada per l'instal·lador el " . date('d/m/Y H:i') . ".\n */\nreturn " . var_export([
                'db' => [
                    'host' => trim((string) $data['db_host']),
                    'port' => (int) $data['db_port'],
                    'name' => trim((string) $data['db_name']),
                    'user' => trim((string) $data['db_user']),
                    'pass' => (string) $data['db_pass'],
                    'charset' => 'utf8mb4',
                    'socket' => trim((string) ($data['db_socket'] ?? '')),
                ],
                'app_key' => Crypto::generateKey(),
                'base_url' => rtrim((string) $data['base_url'], '/'),
                'debug' => false,
                'timezone' => 'Europe/Madrid',
            ], true) . ";\n";
            if (@file_put_contents(CROS_APP . '/config.php', $config) === false) {
                throw new RuntimeException('No s\'ha pogut escriure app/config.php. Doneu permisos d\'escriptura a la carpeta app/ (chmod 775 app).');
            }
            @chmod(CROS_APP . '/config.php', 0640);

            $token = bin2hex(random_bytes(16));
            if (!is_dir(storage_path())) {
                @mkdir(storage_path(), 0775, true);
            }
            if (@file_put_contents(storage_path('install.token'), $token) === false) {
                throw new RuntimeException('No s\'ha pogut escriure a la carpeta storage/. Doneu-hi permisos d\'escriptura (chmod 775 storage).');
            }
            $data['token'] = $token;
            $message = 'Configuració desada i connexió amb «' . $data['db_name'] . '» comprovada.';
            break;

        case 'schema':
            Db::setConnection(install_connect($data));
            $applied = Migrator::run();
            $tables = count(Db::all('SHOW TABLES'));
            $message = ($applied ? count($applied) . ' migracions aplicades' : 'Les taules ja existien')
                . ' · ' . $tables . ' taules a la base de dades.';
            break;

        case 'admin':
            Db::setConnection(install_connect($data));
            $email = mb_strtolower(trim((string) $data['admin_email']));
            $existing = Db::one('SELECT id FROM users WHERE email = :email', ['email' => $email]);
            $fields = [
                'name' => trim((string) $data['admin_name']),
                'email' => $email,
                'password_hash' => password_hash((string) $data['admin_pass'], PASSWORD_DEFAULT),
                'role' => 'admin',
                'active' => 1,
            ];
            if ($existing) {
                Db::update('users', $fields, 'id = :id', ['id' => $existing['id']]);
                $message = 'Compte d\'administració actualitzat (' . $email . ').';
            } else {
                Db::insert('users', $fields + ['created_at' => date('Y-m-d H:i:s')]);
                $message = 'Compte d\'administració creat (' . $email . ').';
            }
            break;

        case 'settings':
            Db::setConnection(install_connect($data));
            Settings::seedDefaults();
            Settings::set('site_name', (string) $data['site_name']);
            Settings::set('hero_title', (string) $data['site_name']);
            Settings::set('event_date', (string) $data['event_date']);
            Settings::set('contact_email', mb_strtolower(trim((string) $data['admin_email'])));
            $host = parse_url((string) $data['base_url'], PHP_URL_HOST) ?: 'localhost';
            Settings::set('mail_from_email', 'no-reply@' . preg_replace('/^www\./', '', (string) $host));
            Settings::set('mail_admin_notify', mb_strtolower(trim((string) $data['admin_email'])));
            $message = count(Settings::defaults()) . ' opcions de configuració inicialitzades.';
            break;

        case 'demo':
            if ((string) ($data['demo'] ?? '') !== '1') {
                $message = 'Continguts d\'exemple omesos.';
                break;
            }
            Db::setConnection(install_connect($data));
            Seeder::run();
            $message = sprintf(
                '%d recorreguts, %d categories, %d actes del programa i %d tipus de tiquet creats.',
                (int) Db::val('SELECT COUNT(*) FROM courses', [], 0),
                (int) Db::val('SELECT COUNT(*) FROM categories', [], 0),
                (int) Db::val('SELECT COUNT(*) FROM schedule_items', [], 0),
                (int) Db::val('SELECT COUNT(*) FROM ticket_types', [], 0)
            );
            break;

        case 'finish':
            $lock = storage_path('installed.lock');
            if (@file_put_contents($lock, json_encode([
                'installed_at' => date('c'),
                'version' => app_version(),
            ], JSON_PRETTY_PRINT)) === false) {
                throw new RuntimeException('No s\'ha pogut crear storage/installed.lock. Reviseu els permisos de storage/.');
            }
            @unlink(storage_path('install.token'));
            $message = 'Instal·lació completada.';
            break;

        default:
            throw new RuntimeException('Fase desconeguda: ' . $phase);
    }

    install_log('Fi de la fase «' . $phase . '»', ['ms' => (int) ((microtime(true) - $started) * 1000)]);
    return [true, $message];
}

/* ------------------------------------------------------------- Processos */

// El pas del formulari (POST) mana sobre el de l'URL: si no, en enviar el formulari
// des d'una adreça com «install.php?pas=2» es tornaria a mostrar el pas anterior.
$step = (int) ($_POST['pas'] ?? $_GET['pas'] ?? 1);
$phase = (string) ($_POST['fase'] ?? '');
$errors = [];
$phaseMessages = [];
$serverInfo = [];

$data = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'db_pass' => $_POST['db_pass'] ?? '',
    'db_socket' => $_POST['db_socket'] ?? '',
    'site_name' => $_POST['site_name'] ?? 'Cros Escolar La Granada',
    'base_url' => $_POST['base_url'] ?? base_url(),
    'event_date' => $_POST['event_date'] ?? '2026-10-04',
    'admin_name' => $_POST['admin_name'] ?? '',
    'admin_email' => $_POST['admin_email'] ?? '',
    'admin_pass' => $_POST['admin_pass'] ?? '',
    // Marcat per defecte; només es desactiva si l'usuari el desmarca al formulari.
    'demo' => (($_POST['demo'] ?? '') === '1') ? '1' : ((($_POST['fase'] ?? '') !== '') ? '' : '1'),
    'token' => $_POST['token'] ?? '',
    'log' => $_POST['log'] ?? '',
];

/** Registre acumulat de les fases ja completades (viatja en un camp ocult). */
$completed = array_values(array_filter(array_map(
    static fn (string $line): array => array_pad(explode('|', base64_decode($line, true) ?: '', 2), 2, ''),
    array_filter(explode(',', (string) $data['log']))
)));

$requirements = requirements();
$requirementsOk = true;
foreach ($requirements as [$label, $ok, $value, $required]) {
    if ($required && !$ok) {
        $requirementsOk = false;
    }
}

if (!$alreadyInstalled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($step === 2) {
        // Comprovació de la connexió a la base de dades
        foreach (['db_name' => 'nom de la base de dades', 'db_user' => 'usuari'] as $field => $label) {
            if (trim((string) $data[$field]) === '') {
                $errors[] = 'Cal indicar el ' . $label . '.';
            }
        }
        if (!$errors) {
            try {
                $pdo = install_connect($data);
                $serverInfo['version'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
                $grants = [];
                foreach ($pdo->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_NUM) as $row) {
                    $grants[] = (string) $row[0];
                }
                $serverInfo['create'] = (bool) preg_grep('/\b(ALL PRIVILEGES|CREATE)\b/i', $grants);
                install_log('Connexió correcta amb ' . $data['db_name'], ['servidor' => $serverInfo['version']]);
                $step = 3;
            } catch (Throwable $e) {
                $hint = '';
                if (str_contains($e->getMessage(), '2002') || str_contains($e->getMessage(), 'timed out')) {
                    $hint = ' Proveu amb «127.0.0.1» en comptes de «localhost» (o a l\'inrevés), o indiqueu el camí del sòcol.';
                }
                $errors[] = 'No s\'ha pogut connectar: ' . $e->getMessage() . $hint;
                install_log('Error de connexió', ['error' => $e->getMessage()]);
            }
        }
    } elseif ($step === 3 && $phase !== '') {
        // Execució d'una fase de la instal·lació
        $order = array_keys($phases);
        $index = array_search($phase, $order, true);
        if ($index === false) {
            $errors[] = 'Fase desconeguda.';
        } elseif ($phase !== 'config' && (!is_file($tokenFile) || !hash_equals(trim((string) @file_get_contents($tokenFile)), (string) $data['token']))) {
            $errors[] = 'La instal·lació ha caducat. Torneu a començar des del pas 1.';
        } else {
            if ($phase === 'config') {
                if (trim((string) $data['admin_name']) === '') {
                    $errors[] = 'Indiqueu el nom de la persona administradora.';
                }
                if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'L\'adreça electrònica de l\'administrador no és vàlida.';
                }
                if (mb_strlen((string) $data['admin_pass']) < 8) {
                    $errors[] = 'La contrasenya ha de tenir com a mínim 8 caràcters.';
                }
            }
            if (!$errors) {
                try {
                    [$ok, $message] = run_phase($phase, $data);
                    $phaseMessages[] = [$phases[$phase], $message];
                    $completed[] = [$phases[$phase], $message];
                    $data['log'] = implode(',', array_map(
                        static fn (array $item): string => base64_encode($item[0] . '|' . $item[1]),
                        $completed
                    ));
                    $nextPhase = $order[$index + 1] ?? null;
                    if ($nextPhase === null) {
                        $step = 4;
                    }
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                    install_log('ERROR a la fase «' . $phase . '»', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}

$progress = ['Requisits', 'Base de dades', 'Administració', 'Llest!'];
$order = array_keys($phases);
$currentIndex = $phase !== '' ? (int) array_search($phase, $order, true) : -1;
$nextPhase = ($phase !== '' && !$errors && $step !== 4) ? ($order[$currentIndex + 1] ?? null) : null;
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instal·lació · Cros Escolar La Granada</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%232f6b3c'/%3E%3Ctext x='16' y='22' font-size='16' font-family='sans-serif' fill='%23fff' text-anchor='middle'%3EC%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
  body{background:linear-gradient(160deg,#1b452a,#2f6b3c 60%,#4f9457);min-height:100vh;padding:2rem 1rem;margin:0}
  .wizard{max-width:760px;margin:0 auto;background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.25);overflow:hidden}
  .wizard__head{padding:1.6rem 2rem;border-bottom:1px solid #e3eade}
  .wizard__head h1{margin:0 0 .2rem;font-size:1.4rem}
  .wizard__body{padding:1.8rem 2rem 2rem}
  .steps{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:1rem}
  .steps span{padding:.25rem .8rem;border-radius:999px;background:#eef3ec;color:#5a6b60;font-size:.82rem;font-weight:600}
  .steps span.is-active{background:#2f6b3c;color:#fff}
  .steps span.is-done{background:#d7ead7;color:#2a6b35}
  .req{display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid #eef1ec;font-size:.92rem}
  .req:last-child{border-bottom:0}
  .ok{color:#2a6b35;font-weight:700}
  .ko{color:#a5401d;font-weight:700}
  .warn{color:#8a6114;font-weight:700}
  .phase{display:flex;gap:.7rem;align-items:flex-start;padding:.5rem 0;font-size:.93rem}
  .phase__mark{width:22px;height:22px;border-radius:50%;flex:none;display:grid;place-items:center;font-size:.75rem;font-weight:700;background:#d7ead7;color:#2a6b35}
  .phase__mark--wait{background:#eef3ec;color:#8d9a92}
  .bar{height:8px;border-radius:999px;background:#eef3ec;overflow:hidden;margin:.8rem 0 1.2rem}
  .bar span{display:block;height:100%;background:linear-gradient(90deg,#2f6b3c,#7ba05b);transition:width .3s}
  .env{font-size:.82rem;color:#5a6b60;margin-top:1rem;line-height:1.7}
</style>
</head>
<body class="admin">
<div class="wizard">
  <div class="wizard__head">
    <h1>Instal·lació del Cros Escolar La Granada</h1>
    <p class="text-soft" style="margin:0">Assistent de configuració · versió <?= e(app_version()) ?></p>
    <div class="steps">
      <?php foreach ($progress as $index => $label): ?>
        <span class="<?= $index + 1 === $step ? 'is-active' : ($index + 1 < $step ? 'is-done' : '') ?>"><?= ($index + 1) . '. ' . e($label) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="wizard__body">
    <?php if ($alreadyInstalled): ?>
      <div class="alert alert--info">
        El web ja està instal·lat. Per motius de seguretat, <strong>esborreu el fitxer install.php</strong> del servidor.
      </div>
      <p>Si voleu tornar a executar l'instal·lador, esborreu el fitxer <span class="mono">storage/installed.lock</span>.</p>
      <a class="btn" href="<?= e(url('/admin')) ?>">Anar al panell</a>

    <?php else: ?>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
      <?php endforeach; ?>

      <?php if ($step === 1): ?>
        <h2>Comprovació del servidor</h2>
        <div>
          <?php foreach ($requirements as [$label, $ok, $value, $required]): ?>
            <div class="req">
              <span><?= e($label) ?> <span class="text-soft mono"><?= e($value) ?></span></span>
              <span class="<?= $ok ? 'ok' : ($required ? 'ko' : 'warn') ?>"><?= $ok ? 'Correcte' : ($required ? 'Falta' : 'Recomanat') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="env">
          Temps màxim d'execució: <strong><?= e((string) (ini_get('max_execution_time') ?: '0')) ?> s</strong> ·
          Memòria: <strong><?= e((string) ini_get('memory_limit')) ?></strong> ·
          Mida màxima de pujada: <strong><?= e((string) ini_get('upload_max_filesize')) ?></strong><br>
          La instal·lació es fa en <?= count($phases) ?> passos curts, de manera que funciona encara que el servidor
          tingui un temps màxim d'execució baix.
        </p>
        <?php if (!$requirementsOk): ?>
          <div class="alert alert--error mt-2">Cal resoldre els requisits marcats abans de continuar.</div>
        <?php endif; ?>
        <div class="form-actions">
          <a class="btn <?= $requirementsOk ? '' : 'btn--ghost' ?>" href="?pas=2" <?= $requirementsOk ? '' : 'onclick="return false"' ?>>Continuar</a>
          <a class="btn btn--ghost" href="?pas=1">Tornar a comprovar</a>
        </div>

      <?php elseif ($step === 2): ?>
        <h2>Connexió amb la base de dades</h2>
        <p class="text-soft">Introduïu les dades de la base de dades MySQL/MariaDB creada a CloudPanel.</p>
        <form method="post" action="install.php">
          <input type="hidden" name="pas" value="2">
          <div class="form-grid form-grid--2">
            <div class="field"><label for="db_host">Servidor</label><input type="text" id="db_host" name="db_host" value="<?= e($data['db_host']) ?>" required>
              <span class="hint">Normalment «localhost» o «127.0.0.1».</span></div>
            <div class="field"><label for="db_port">Port</label><input type="number" id="db_port" name="db_port" value="<?= e($data['db_port']) ?>"></div>
            <div class="field"><label for="db_name">Nom de la base de dades</label><input type="text" id="db_name" name="db_name" value="<?= e($data['db_name']) ?>" required></div>
            <div class="field"><label for="db_user">Usuari</label><input type="text" id="db_user" name="db_user" value="<?= e($data['db_user']) ?>" required></div>
            <div class="field"><label for="db_pass">Contrasenya</label><input type="password" id="db_pass" name="db_pass" value="<?= e($data['db_pass']) ?>"></div>
            <div class="field"><label for="base_url">URL del web</label><input type="url" id="base_url" name="base_url" value="<?= e($data['base_url']) ?>"></div>
            <div class="field" style="grid-column:1/-1"><label for="db_socket">Sòcol Unix (opcional)</label>
              <input type="text" id="db_socket" name="db_socket" value="<?= e($data['db_socket']) ?>" placeholder="/var/run/mysqld/mysqld.sock">
              <span class="hint">Ompliu-ho només si la connexió per servidor i port no funciona.</span></div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Comprovar i continuar</button>
            <a class="btn btn--ghost" href="?pas=1">Enrere</a>
          </div>
        </form>

      <?php elseif ($step === 3): ?>
        <?php if ($phase === '' || $errors): ?>
          <h2>Compte d'administració i dades bàsiques</h2>
          <?php if (!empty($serverInfo['version'])): ?>
            <div class="alert alert--success">
              Connexió correcta amb <strong><?= e($data['db_name']) ?></strong> (<?= e($serverInfo['version']) ?>).
              <?php if (isset($serverInfo['create']) && !$serverInfo['create']): ?>
                <br><strong>Atenció:</strong> sembla que aquest usuari no té permisos per crear taules.
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <form method="post" action="install.php" id="install-form">
            <input type="hidden" name="pas" value="3">
            <input type="hidden" name="fase" value="config">
            <?php foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'db_socket', 'base_url'] as $hidden): ?>
              <input type="hidden" name="<?= e($hidden) ?>" value="<?= e($data[$hidden]) ?>">
            <?php endforeach; ?>
            <div class="form-grid form-grid--2">
              <div class="field"><label for="site_name">Nom del web</label><input type="text" id="site_name" name="site_name" value="<?= e($data['site_name']) ?>" required></div>
              <div class="field"><label for="event_date">Data de la cursa</label><input type="date" id="event_date" name="event_date" value="<?= e($data['event_date']) ?>" required></div>
              <div class="field"><label for="admin_name">El vostre nom</label><input type="text" id="admin_name" name="admin_name" value="<?= e($data['admin_name']) ?>" required></div>
              <div class="field"><label for="admin_email">Correu electrònic</label><input type="email" id="admin_email" name="admin_email" value="<?= e($data['admin_email']) ?>" required></div>
              <div class="field"><label for="admin_pass">Contrasenya (mín. 8 caràcters)</label><input type="password" id="admin_pass" name="admin_pass" required minlength="8"></div>
            </div>
            <label class="switch mt-2"><input type="checkbox" name="demo" value="1" <?= $data['demo'] === '1' ? 'checked' : '' ?>> <span>Crear continguts d'exemple (recorreguts, categories, programa, tiquets…)</span></label>
            <div class="form-actions">
              <button class="btn" type="submit" id="install-button">Instal·lar</button>
              <a class="btn btn--ghost" href="?pas=2">Enrere</a>
            </div>
          </form>
          <script>
            // Evita el doble enviament, que és el que sol deixar la pantalla aturada.
            document.getElementById('install-form').addEventListener('submit', function () {
              var button = document.getElementById('install-button');
              button.disabled = true;
              button.textContent = 'Instal·lant…';
            });
          </script>

        <?php else: ?>
          <h2>Instal·lant…</h2>
          <?php $done = $currentIndex + 1; ?>
          <div class="bar"><span style="width:<?= (int) round($done / count($phases) * 100) ?>%"></span></div>
          <?php foreach ($order as $index => $key): ?>
            <div class="phase">
              <span class="phase__mark <?= $index <= $currentIndex ? '' : 'phase__mark--wait' ?>"><?= $index <= $currentIndex ? '✓' : ($index + 1) ?></span>
              <span>
                <?= e($phases[$key]) ?>
                <?php if (isset($completed[$index][1])): ?>
                  <div class="text-soft"><?= e($completed[$index][1]) ?></div>
                <?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>

          <form method="post" action="install.php" id="next-form">
            <input type="hidden" name="pas" value="3">
            <input type="hidden" name="fase" value="<?= e((string) $nextPhase) ?>">
            <?php foreach (CARRY as $hidden): ?>
              <input type="hidden" name="<?= e($hidden) ?>" value="<?= e((string) ($data[$hidden] ?? '')) ?>">
            <?php endforeach; ?>
            <?php if ($data['demo'] === '1'): ?><input type="hidden" name="demo" value="1"><?php endif; ?>
            <div class="form-actions">
              <button class="btn" type="submit">Continuar amb «<?= e($phases[$nextPhase] ?? '') ?>»</button>
            </div>
          </form>
          <script>setTimeout(function () { document.getElementById('next-form').submit(); }, 400);</script>
        <?php endif; ?>

      <?php else: ?>
        <div class="alert alert--success">Instal·lació completada correctament!</div>
        <?php foreach ($completed as [$label, $message]): ?>
          <div class="phase"><span class="phase__mark">✓</span><span><?= e($label) ?><div class="text-soft"><?= e($message) ?></div></span></div>
        <?php endforeach; ?>
        <h2 class="mt-3">Últims passos</h2>
        <ol class="help-list" style="font-size:.95rem">
          <li><strong>Esborreu el fitxer <span class="mono">install.php</span></strong> del servidor.</li>
          <li>Entreu al panell i configureu les credencials de Stripe (Configuració → Pagaments).</li>
          <li>Pugeu el banner de la portada i els logotips dels patrocinadors.</li>
          <li>Reviseu els recorreguts i enganxeu-hi els identificadors de Wikiloc.</li>
        </ol>
        <div class="form-actions">
          <a class="btn" href="<?= e(url('/admin')) ?>">Entrar al panell</a>
          <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Veure el web</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
