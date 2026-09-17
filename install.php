<?php
/**
 * Instal·lador automàtic del Cros Escolar La Granada.
 * Pugeu tots els fitxers al servidor i obriu aquesta adreça al navegador.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Cros\Core\Crypto;
use Cros\Core\Db;
use Cros\Core\Migrator;
use Cros\Core\Seeder;
use Cros\Core\Settings;

$lockFile = CROS_ROOT . '/storage/installed.lock';
$configFile = CROS_APP . '/config.php';
$alreadyInstalled = is_file($lockFile) && is_file($configFile);

$step = (int) ($_GET['pas'] ?? $_POST['pas'] ?? 1);
$errors = [];
$data = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'db_pass' => $_POST['db_pass'] ?? '',
    'site_name' => $_POST['site_name'] ?? 'Cros Escolar La Granada',
    'base_url' => $_POST['base_url'] ?? base_url(),
    'event_date' => $_POST['event_date'] ?? '2026-10-04',
    'admin_name' => $_POST['admin_name'] ?? '',
    'admin_email' => $_POST['admin_email'] ?? '',
    'admin_pass' => $_POST['admin_pass'] ?? '',
    'demo' => isset($_POST['demo']) ? '1' : ($step > 1 ? '' : '1'),
];

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
        ['Carpeta uploads/ escrivible', $writable(CROS_ROOT . '/uploads'), 'uploads/', true],
        ['Carpeta storage/ escrivible', $writable(CROS_ROOT . '/storage'), 'storage/', true],
    ];
}

$requirements = requirements();
$requirementsOk = true;
foreach ($requirements as [$label, $ok, $value, $required]) {
    if ($required && !$ok) {
        $requirementsOk = false;
    }
}

/* ------------------------------------------------------------- Processos */
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
                $pdo = Db::connect([
                    'host' => $data['db_host'],
                    'port' => (int) $data['db_port'],
                    'name' => $data['db_name'],
                    'user' => $data['db_user'],
                    'pass' => $data['db_pass'],
                    'charset' => 'utf8mb4',
                ]);
                $_SESSION['install'] = $data;
                $step = 3;
            } catch (\Throwable $e) {
                $errors[] = 'No s\'ha pogut connectar: ' . $e->getMessage();
            }
        }
    } elseif ($step === 3) {
        $data = array_merge($_SESSION['install'] ?? [], $data);
        if (trim((string) $data['admin_name']) === '') {
            $errors[] = 'Indiqueu el nom de la persona administradora.';
        }
        if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adreça electrònica de l\'administrador no és vàlida.';
        }
        if (mb_strlen((string) $data['admin_pass']) < 8) {
            $errors[] = 'La contrasenya ha de tenir com a mínim 8 caràcters.';
        }

        if (!$errors) {
            try {
                $pdo = Db::connect([
                    'host' => $data['db_host'],
                    'port' => (int) $data['db_port'],
                    'name' => $data['db_name'],
                    'user' => $data['db_user'],
                    'pass' => $data['db_pass'],
                    'charset' => 'utf8mb4',
                ]);
                Db::setConnection($pdo);

                // 1. Fitxer de configuració
                $config = "<?php\n/**\n * Configuració generada per l'instal·lador el " . date('d/m/Y H:i') . ".\n */\nreturn " . var_export([
                    'db' => [
                        'host' => $data['db_host'],
                        'port' => (int) $data['db_port'],
                        'name' => $data['db_name'],
                        'user' => $data['db_user'],
                        'pass' => $data['db_pass'],
                        'charset' => 'utf8mb4',
                        'socket' => '',
                    ],
                    'app_key' => Crypto::generateKey(),
                    'base_url' => rtrim((string) $data['base_url'], '/'),
                    'debug' => false,
                    'timezone' => 'Europe/Madrid',
                ], true) . ";\n";
                if (@file_put_contents($configFile, $config) === false) {
                    throw new \RuntimeException('No s\'ha pogut escriure app/config.php. Comproveu els permisos.');
                }
                @chmod($configFile, 0640);

                // 2. Taules
                Migrator::run();

                // 3. Usuari administrador
                $existing = Db::val('SELECT id FROM users WHERE email = :email', ['email' => mb_strtolower($data['admin_email'])]);
                if (!$existing) {
                    Db::insert('users', [
                        'name' => $data['admin_name'],
                        'email' => mb_strtolower($data['admin_email']),
                        'password_hash' => password_hash($data['admin_pass'], PASSWORD_DEFAULT),
                        'role' => 'admin',
                        'active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // 4. Configuració inicial i continguts d'exemple
                Settings::seedDefaults();
                Settings::set('site_name', $data['site_name']);
                Settings::set('hero_title', $data['site_name']);
                Settings::set('event_date', $data['event_date']);
                Settings::set('contact_email', $data['admin_email']);
                Settings::set('mail_from_email', 'no-reply@' . (parse_url((string) $data['base_url'], PHP_URL_HOST) ?: 'localhost'));
                Settings::set('mail_admin_notify', $data['admin_email']);
                if (($data['demo'] ?? '') === '1') {
                    Seeder::run();
                }

                // 5. Bloqueig de l'instal·lador
                if (!is_dir(dirname($lockFile))) {
                    @mkdir(dirname($lockFile), 0775, true);
                }
                @file_put_contents($lockFile, json_encode([
                    'installed_at' => date('c'),
                    'version' => app_version(),
                ], JSON_PRETTY_PRINT));

                $step = 4;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$progress = ['Requisits', 'Base de dades', 'Administració', 'Llest!'];
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instal·lació · Cros Escolar La Granada</title>
<meta name="robots" content="noindex, nofollow">
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
</style>
</head>
<body>
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
        <form method="post">
          <input type="hidden" name="pas" value="2">
          <div class="form-grid form-grid--2">
            <div class="field"><label for="db_host">Servidor</label><input type="text" id="db_host" name="db_host" value="<?= e($data['db_host']) ?>" required></div>
            <div class="field"><label for="db_port">Port</label><input type="number" id="db_port" name="db_port" value="<?= e($data['db_port']) ?>"></div>
            <div class="field"><label for="db_name">Nom de la base de dades</label><input type="text" id="db_name" name="db_name" value="<?= e($data['db_name']) ?>" required></div>
            <div class="field"><label for="db_user">Usuari</label><input type="text" id="db_user" name="db_user" value="<?= e($data['db_user']) ?>" required></div>
            <div class="field"><label for="db_pass">Contrasenya</label><input type="password" id="db_pass" name="db_pass" value="<?= e($data['db_pass']) ?>"></div>
            <div class="field"><label for="base_url">URL del web</label><input type="url" id="base_url" name="base_url" value="<?= e($data['base_url']) ?>"></div>
          </div>
          <div class="form-actions">
            <button class="btn" type="submit">Comprovar i continuar</button>
            <a class="btn btn--ghost" href="?pas=1">Enrere</a>
          </div>
        </form>

      <?php elseif ($step === 3): ?>
        <h2>Compte d'administració i dades bàsiques</h2>
        <form method="post">
          <input type="hidden" name="pas" value="3">
          <?php foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'base_url'] as $hidden): ?>
            <input type="hidden" name="<?= e($hidden) ?>" value="<?= e($data[$hidden]) ?>">
          <?php endforeach; ?>
          <div class="form-grid form-grid--2">
            <div class="field"><label for="site_name">Nom del web</label><input type="text" id="site_name" name="site_name" value="<?= e($data['site_name']) ?>" required></div>
            <div class="field"><label for="event_date">Data de la cursa</label><input type="date" id="event_date" name="event_date" value="<?= e($data['event_date']) ?>" required></div>
            <div class="field"><label for="admin_name">El vostre nom</label><input type="text" id="admin_name" name="admin_name" value="<?= e($data['admin_name']) ?>" required></div>
            <div class="field"><label for="admin_email">Correu electrònic</label><input type="email" id="admin_email" name="admin_email" value="<?= e($data['admin_email']) ?>" required></div>
            <div class="field"><label for="admin_pass">Contrasenya (mín. 8 caràcters)</label><input type="password" id="admin_pass" name="admin_pass" required minlength="8"></div>
          </div>
          <label class="switch mt-2"><input type="checkbox" name="demo" value="1" checked> <span>Crear continguts d'exemple (recorreguts, categories, programa, tiquets…)</span></label>
          <div class="form-actions">
            <button class="btn" type="submit">Instal·lar</button>
            <a class="btn btn--ghost" href="?pas=2">Enrere</a>
          </div>
        </form>

      <?php else: ?>
        <div class="alert alert--success">Instal·lació completada correctament!</div>
        <h2>Últims passos</h2>
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
