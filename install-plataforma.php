<?php
/**
 * Assistent d'instal·lació de la plataforma.
 *
 * L'script del servidor (tools/instalar-vps.sh) deixa el VPS a punt: programari,
 * bases de dades, codi, nginx i certificat. Aquí s'acaba la part d'aplicació —
 * dominis, correu, superadministrador— i es posa tot en marxa.
 *
 * No cal cap permís especial: tot el que fa aquest fitxer ho pot fer l'usuari
 * del servidor web.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Platform\Console;
use Cros\Platform\Platform;
use Cros\Platform\Setup;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
@set_time_limit(300);
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = __DIR__;
$hints = Setup::hints($root);
$token = (string) ($_POST['clau'] ?? $_GET['clau'] ?? '');
// Mentre hi hagi el testimoni, la instal·lació encara s'està fent: la
// configuració ja pot existir i faltar-hi l'últim pas.
$done = !Setup::pending($root) && Setup::token($root) === '';
$allowed = Setup::allowed($token, $root);

/** Apunta al registre què va passant. */
function setup_log(string $message, array $context = []): void
{
    $dir = storage_path('logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(
        $dir . '/install-plataforma-' . date('Y-m-d') . '.log',
        sprintf("[%s] %s %s\n", date('H:i:s'), $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''),
        FILE_APPEND | LOCK_EX
    );
}

$progress = ['Comprovacions', 'Dominis', 'Base de dades', 'Correu', 'Superadministració', 'En marxa'];
$step = (int) ($_POST['pas'] ?? $_GET['pas'] ?? 1);
$errors = [];
$notices = [];
$results = [];

/** Valors que viatgen d'un pas a l'altre. */
$v = static fn (string $key, $default = '') => $_POST[$key] ?? $default;
$data = [
    'name' => (string) $v('name', 'Cros Escolar'),
    'domains' => (string) $v('domains', implode(', ', (array) ($hints['dominis'] ?? []))),
    'console' => (string) $v('console', 'admin'),
    'db_host' => (string) $v('db_host', $hints['db']['host'] ?? 'localhost'),
    'db_port' => (string) $v('db_port', (string) ($hints['db']['port'] ?? '3306')),
    'db_name' => (string) $v('db_name', $hints['db']['name'] ?? 'cros_platform'),
    'db_user' => (string) $v('db_user', $hints['db']['user'] ?? 'cros_platform'),
    'db_pass' => (string) $v('db_pass', $hints['db']['pass'] ?? ''),
    'admin_user' => (string) $v('admin_user', $hints['provision']['admin_user'] ?? 'cros_admin'),
    'admin_pass' => (string) $v('admin_pass', $hints['provision']['admin_pass'] ?? ''),
    'db_prefix' => (string) $v('db_prefix', 'cros_'),
    'mail_from_name' => (string) $v('mail_from_name', 'Cros Escolar'),
    'mail_from_email' => (string) $v('mail_from_email', ''),
    'mail_notify' => (string) $v('mail_notify', ''),
    'mail_transport' => (string) $v('mail_transport', 'mail'),
    'smtp_host' => (string) $v('smtp_host', ''),
    'smtp_port' => (string) $v('smtp_port', '587'),
    'smtp_user' => (string) $v('smtp_user', ''),
    'smtp_pass' => (string) $v('smtp_pass', ''),
    'smtp_secure' => (string) $v('smtp_secure', 'tls'),
    'super_name' => (string) $v('super_name', ''),
    'super_email' => (string) $v('super_email', ''),
    'super_pass' => (string) $v('super_pass', ''),
    'super_pass2' => (string) $v('super_pass2', ''),
];

/** La configuració tal com quedarà, a partir del formulari. */
function setup_values(array $data): array
{
    return [
        'name' => $data['name'],
        'domains' => Setup::domains($data['domains']),
        'console' => $data['console'],
        'db' => [
            'host' => $data['db_host'], 'port' => (int) $data['db_port'], 'name' => $data['db_name'],
            'user' => $data['db_user'], 'pass' => $data['db_pass'], 'socket' => '',
        ],
        'provision' => [
            'db_host' => $data['db_host'], 'db_port' => (int) $data['db_port'],
            'admin_user' => $data['admin_user'], 'admin_pass' => $data['admin_pass'],
            'db_prefix' => $data['db_prefix'],
        ],
        'mail' => [
            'from_name' => $data['mail_from_name'], 'from_email' => $data['mail_from_email'],
            'notify' => $data['mail_notify'], 'transport' => $data['mail_transport'],
            'smtp_host' => $data['smtp_host'], 'smtp_port' => (int) $data['smtp_port'],
            'smtp_user' => $data['smtp_user'], 'smtp_pass' => $data['smtp_pass'],
            'smtp_secure' => $data['smtp_secure'],
        ],
    ];
}

/* ----------------------------------------------------------------- Processos */

if (!$done && $allowed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $domains = Setup::domains($data['domains']);

    if ($step === 2) {
        if ($domains === []) {
            $errors[] = 'Indiqueu com a mínim un domini.';
        }
        foreach ($domains as $domain) {
            if (!Setup::validDomain($domain)) {
                $errors[] = '«' . $domain . '» no sembla un domini.';
            }
        }
        if (!preg_match('/^[a-z0-9-]{2,30}$/', $data['console'])) {
            $errors[] = 'El subdomini del panell només pot portar lletres, números i guions.';
        }
        if (!$errors) {
            $step = 3;
        }
    } elseif ($step === 3) {
        $values = setup_values($data);
        $db = Setup::testDb($values['db']);
        if (!$db['ok']) {
            $errors[] = 'Base de dades de la plataforma: ' . $db['error'];
        } else {
            $notices[] = 'Connectat amb ' . $data['db_name'] . ' (' . $db['version'] . ').';
        }
        $provision = Setup::testProvision($values['provision']);
        if (!$provision['ok']) {
            $errors[] = 'Usuari que crearà les bases de dades dels clients: ' . $provision['error'];
        } else {
            $notices[] = 'L\'usuari «' . $data['admin_user'] . '» pot crear bases de dades.';
        }
        if (!$errors) {
            $step = 4;
        }
    } elseif ($step === 4) {
        if ($data['mail_from_email'] !== '' && !filter_var($data['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adreça de qui envia no és vàlida.';
        }
        if (!filter_var($data['mail_notify'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Indiqueu una adreça vàlida per als avisos.';
        }
        if ($data['mail_transport'] === 'smtp' && trim($data['smtp_host']) === '') {
            $errors[] = 'Amb SMTP cal indicar el servidor de correu.';
        }
        if (!$errors && ($_POST['provar'] ?? '') === '1') {
            $mail = setup_values($data)['mail'];
            Settings::prime([
                'site_name' => $data['name'],
                'mail_from_name' => $mail['from_name'],
                'mail_from_email' => $mail['from_email'] ?: ('no-reply@' . ($domains[0] ?? 'localhost')),
                'mail_transport' => $mail['transport'],
                'smtp_host' => $mail['smtp_host'], 'smtp_port' => (string) $mail['smtp_port'],
                'smtp_user' => $mail['smtp_user'], 'smtp_pass' => $mail['smtp_pass'],
                'smtp_secure' => $mail['smtp_secure'],
            ]);
            $sent = Mailer::send(
                $data['mail_notify'],
                'Prova del correu de la plataforma',
                '<p>Si llegiu això, el correu de la plataforma funciona.</p>'
            );
            $sent
                ? $notices[] = 'Prova enviada a ' . $data['mail_notify'] . '. Mireu la safata d\'entrada.'
                : $errors[] = 'No s\'ha pogut enviar la prova. Reviseu les dades del servidor de correu.';
            $step = 4;
        } elseif (!$errors) {
            $step = 5;
        }
    } elseif ($step === 5) {
        if (trim($data['super_name']) === '') {
            $errors[] = 'Indiqueu el vostre nom.';
        }
        if (!filter_var($data['super_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adreça electrònica no és vàlida.';
        }
        if (mb_strlen($data['super_pass']) < 8) {
            $errors[] = 'La contrasenya ha de tenir com a mínim 8 caràcters.';
        } elseif ($data['super_pass'] !== $data['super_pass2']) {
            $errors[] = 'Les dues contrasenyes no coincideixen.';
        }
        if (!$errors) {
            $step = 6;
        }
    } elseif ($step === 6 && ($_POST['instalar'] ?? '') === '1') {
        // Tot en un: escriure la configuració, crear les taules i el compte.
        try {
            $values = setup_values($data);
            setup_log('Comença la instal·lació', ['dominis' => $values['domains']]);

            Setup::save($values, $root);
            $results[] = ['ok' => true, 'text' => 'Configuració desada a tenants/platform.php.'];

            Platform::boot($root);
            $migrations = Platform::migrate();
            $results[] = ['ok' => true, 'text' => $migrations
                ? count($migrations) . ' taules de la plataforma creades.'
                : 'La base de dades de la plataforma ja estava al dia.'];

            $userId = Console::save($data['super_name'], $data['super_email'], $data['super_pass']);
            $results[] = ['ok' => true, 'text' => 'Superadministrador creat (' . $data['super_email'] . ').'];
            Platform::log('setup', 'user', $userId, ['dominis' => $values['domains']], $userId);

            setup_log('Instal·lació acabada', ['usuari' => $userId]);
            $step = 7;
        } catch (Throwable $e) {
            setup_log('ERROR', ['error' => $e->getMessage()]);
            $results[] = ['ok' => false, 'text' => $e->getMessage()];
            $errors[] = 'La instal·lació s\'ha aturat: ' . $e->getMessage();
        }
    } elseif ($step === 7 && ($_POST['tancar'] ?? '') === '1') {
        Setup::finish($root);
        $principal = $domains[0] ?? '';
        $console = $data['console'] . '.' . $principal;
        header('Location: ' . ($principal !== '' ? 'https://' . $console . '/acces' : '/'));
        exit;
    }
}

$requirements = Setup::requirements($root);
$domains = Setup::domains($data['domains']);
$principal = $domains[0] ?? '';

/** Els camps que s'arrosseguen d'un pas a l'altre. */
function setup_hidden(array $data, string $token, int $step): string
{
    $html = '<input type="hidden" name="clau" value="' . e($token) . '">'
        . '<input type="hidden" name="pas" value="' . $step . '">';
    foreach ($data as $key => $value) {
        if (in_array($key, ['super_pass2'], true)) {
            continue;
        }
        $html .= '<input type="hidden" name="' . e($key) . '" value="' . e((string) $value) . '">';
    }

    return $html;
}
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instal·lació de la plataforma · Cros Escolar</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
  body{background:linear-gradient(160deg,#16303d,#1f4f5f 60%,#2f7d84);min-height:100vh;padding:2rem 1rem;margin:0}
  .wizard{max-width:780px;margin:0 auto;background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.25);overflow:hidden}
  .wizard__head{padding:1.6rem 2rem;border-bottom:1px solid #e3eade}
  .wizard__head h1{margin:0 0 .2rem;font-size:1.4rem}
  .wizard__body{padding:1.8rem 2rem 2rem}
  .steps{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:1rem}
  .steps span{padding:.25rem .8rem;border-radius:999px;background:#eef3ec;color:#5a6b60;font-size:.82rem;font-weight:600}
  .steps span.is-active{background:#1f4f5f;color:#fff}
  .steps span.is-done{background:#d7e8ea;color:#1f4f5f}
  .req{display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid #eef1ec;font-size:.92rem}
  .req:last-child{border-bottom:0}
  .ok{color:#2a6b35;font-weight:700}
  .ko{color:#a5401d;font-weight:700}
  .warn{color:#8a6114;font-weight:700}
  .done{display:flex;gap:.7rem;align-items:flex-start;padding:.45rem 0;font-size:.93rem}
  .done__mark{width:22px;height:22px;border-radius:50%;flex:none;display:grid;place-items:center;font-size:.75rem;font-weight:700;background:#d7ead7;color:#2a6b35}
  .done__mark--ko{background:#fbe8e2;color:#a5401d}
  .env{font-size:.82rem;color:#5a6b60;margin-top:1rem;line-height:1.7}
  code{background:#f2f5f1;padding:.1rem .35rem;border-radius:5px}
</style>
</head>
<body class="admin">
<div class="wizard">
  <div class="wizard__head">
    <h1>Instal·lació de la plataforma</h1>
    <p class="text-soft" style="margin:0">Cros Escolar · versió <?= e(app_version()) ?></p>
    <?php if (!$done && $allowed): ?>
      <div class="steps">
        <?php foreach ($progress as $i => $label): ?>
          <span class="<?= $i + 1 === min($step, 6) ? 'is-active' : ($i + 1 < $step ? 'is-done' : '') ?>"><?= ($i + 1) . '. ' . e($label) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="wizard__body">
  <?php if ($done): ?>
    <div class="alert alert--info">
      La plataforma ja està instal·lada: hi ha el fitxer <code>tenants/platform.php</code>.
    </div>
    <p>
      Per seguretat, esborreu aquest assistent del servidor:<br>
      <code>rm <?= e($root) ?>/install-plataforma.php</code>
    </p>
    <p class="text-soft">Si el que voleu és tornar a començar de zero, traieu abans
      <code>tenants/platform.php</code>.</p>

  <?php elseif (!$allowed): ?>
    <div class="alert alert--error">
      Aquesta adreça necessita la clau que us ha donat l'script d'instal·lació.
    </div>
    <p>
      Executeu al servidor <code>sudo bash tools/instalar-vps.sh</code>: en acabar us
      dirà l'adreça sencera, amb la clau inclosa.
    </p>
    <p class="text-soft">
      Si l'heu perduda, la trobareu a <code>storage/<?= e(Setup::TOKEN) ?></code> del servidor.
    </p>

  <?php else: ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert alert--error"><?= e($error) ?></div>
    <?php endforeach; ?>
    <?php foreach ($notices as $notice): ?>
      <div class="alert alert--success"><?= e($notice) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 1): ?>
      <h2>Com està el servidor</h2>
      <div>
        <?php foreach ($requirements as [$label, $ok, $value, $required]): ?>
          <div class="req">
            <span><?= e($label) ?> <span class="text-soft mono"><?= e($value) ?></span></span>
            <span class="<?= $ok ? 'ok' : ($required ? 'ko' : 'warn') ?>"><?= $ok ? 'Correcte' : ($required ? 'Falta' : 'Recomanat') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($hints): ?>
        <p class="env">
          L'script d'instal·lació ha deixat el servidor a punt:
          codi a <code><?= e((string) ($hints['arrel'] ?? '')) ?></code> ·
          PHP <?= e((string) ($hints['php'] ?? '')) ?> ·
          base de dades <code><?= e((string) ($hints['db']['name'] ?? '')) ?></code>
          <?php if (!empty($hints['dominis'])): ?>
            · dominis <?= e(implode(', ', (array) $hints['dominis'])) ?>
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="alert alert--warning mt-2">
          No trobo <code><?= e(Setup::HINTS) ?></code>. Podeu continuar, però haureu
          d'escriure a mà les dades de la base de dades.
        </div>
      <?php endif; ?>
      <?php if (!Setup::ready($root)): ?>
        <div class="alert alert--error mt-2">Cal resoldre el que falta abans de continuar.</div>
      <?php endif; ?>
      <div class="form-actions">
        <?php if (Setup::ready($root)): ?>
          <a class="btn" href="?pas=2&amp;clau=<?= e(urlencode($token)) ?>">Continuar</a>
        <?php else: ?>
          <span class="btn btn--ghost" style="pointer-events:none;opacity:.6">Continuar</span>
        <?php endif; ?>
        <a class="btn btn--ghost" href="?clau=<?= e(urlencode($token)) ?>">Tornar a comprovar</a>
      </div>

    <?php elseif ($step === 2): ?>
      <h2>Els dominis</h2>
      <p class="text-soft">
        El primer de la llista és el principal: hi viuran la pàgina pública i el panell.
        Els altres també serveixen, però cada web té una adreça bona i prou i la resta hi mena.
      </p>
      <form method="post">
        <?= setup_hidden(array_diff_key($data, ['domains' => 1, 'console' => 1, 'name' => 1]), $token, 2) ?>
        <div class="form-grid">
          <div class="field">
            <label for="name">Nom del servei</label>
            <input type="text" id="name" name="name" value="<?= e($data['name']) ?>" required>
            <span class="hint">Surt als correus que envia la plataforma.</span>
          </div>
          <div class="field">
            <label for="domains">Dominis</label>
            <input type="text" id="domains" name="domains" value="<?= e($data['domains']) ?>" required
                   placeholder="crosescolar.cat, crosescolar.com">
            <span class="hint">Separats per comes. Cadascun necessita un registre comodí al DNS.</span>
          </div>
          <div class="field">
            <label for="console">Subdomini del panell</label>
            <input type="text" id="console" name="console" value="<?= e($data['console']) ?>" required style="max-width:220px">
            <span class="hint">El panell de superadministració serà <code><?= e($data['console']) ?>.<?= e($principal ?: 'el-vostre-domini') ?></code>.</span>
          </div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Continuar</button></div>
      </form>

    <?php elseif ($step === 3): ?>
      <h2>Les bases de dades</h2>
      <p class="text-soft">
        La primera és la de la plataforma: qui són els clients i quines instàncies tenen.
        La segona és l'usuari que crearà la base de dades de cada client nou.
      </p>
      <form method="post">
        <?= setup_hidden(array_diff_key($data, array_flip([
            'db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'admin_user', 'admin_pass', 'db_prefix',
        ])), $token, 3) ?>
        <div class="form-grid form-grid--2">
          <div class="field"><label for="db_host">Servidor</label><input type="text" id="db_host" name="db_host" value="<?= e($data['db_host']) ?>" required></div>
          <div class="field"><label for="db_port">Port</label><input type="number" id="db_port" name="db_port" value="<?= e($data['db_port']) ?>"></div>
          <div class="field"><label for="db_name">Base de dades de la plataforma</label><input type="text" id="db_name" name="db_name" value="<?= e($data['db_name']) ?>" required></div>
          <div class="field"><label for="db_user">Usuari</label><input type="text" id="db_user" name="db_user" value="<?= e($data['db_user']) ?>" required></div>
          <div class="field"><label for="db_pass">Contrasenya</label><input type="password" id="db_pass" name="db_pass" value="<?= e($data['db_pass']) ?>"></div>
          <div class="field"></div>
          <div class="field"><label for="admin_user">Usuari que crea bases de dades</label><input type="text" id="admin_user" name="admin_user" value="<?= e($data['admin_user']) ?>" required></div>
          <div class="field"><label for="admin_pass">La seva contrasenya</label><input type="password" id="admin_pass" name="admin_pass" value="<?= e($data['admin_pass']) ?>"></div>
          <div class="field"><label for="db_prefix">Prefix de les bases de dades</label><input type="text" id="db_prefix" name="db_prefix" value="<?= e($data['db_prefix']) ?>">
            <span class="hint">El cros «granada» tindrà <code><?= e($data['db_prefix']) ?>granada</code>.</span></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Provar i continuar</button></div>
      </form>

    <?php elseif ($step === 4): ?>
      <h2>El correu</h2>
      <p class="text-soft">
        D'aquí surten els avisos de les sol·licituds, les claus dels clients nous i les
        alertes si un web deixa de respondre.
      </p>
      <form method="post">
        <?= setup_hidden(array_diff_key($data, array_flip([
            'mail_from_name', 'mail_from_email', 'mail_notify', 'mail_transport',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure',
        ])), $token, 4) ?>
        <div class="form-grid form-grid--2">
          <div class="field"><label for="mail_from_name">Nom de qui envia</label><input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($data['mail_from_name']) ?>"></div>
          <div class="field"><label for="mail_from_email">Adreça de qui envia</label><input type="email" id="mail_from_email" name="mail_from_email" value="<?= e($data['mail_from_email'] ?: 'no-reply@' . ($principal ?: '')) ?>"></div>
          <div class="field"><label for="mail_notify">On arriben els avisos</label><input type="email" id="mail_notify" name="mail_notify" value="<?= e($data['mail_notify']) ?>" required></div>
          <div class="field"><label for="mail_transport">Com s'envia</label>
            <select id="mail_transport" name="mail_transport">
              <option value="mail"<?= $data['mail_transport'] === 'mail' ? ' selected' : '' ?>>Funció mail() del servidor</option>
              <option value="smtp"<?= $data['mail_transport'] === 'smtp' ? ' selected' : '' ?>>Servidor SMTP</option>
              <option value="log"<?= $data['mail_transport'] === 'log' ? ' selected' : '' ?>>Assaig: no enviar res</option>
            </select>
          </div>
          <div class="field"><label for="smtp_host">Servidor SMTP</label><input type="text" id="smtp_host" name="smtp_host" value="<?= e($data['smtp_host']) ?>" placeholder="smtp.elproveidor.cat"></div>
          <div class="field"><label for="smtp_port">Port</label><input type="number" id="smtp_port" name="smtp_port" value="<?= e($data['smtp_port']) ?>"></div>
          <div class="field"><label for="smtp_user">Usuari SMTP</label><input type="text" id="smtp_user" name="smtp_user" value="<?= e($data['smtp_user']) ?>"></div>
          <div class="field"><label for="smtp_pass">Contrasenya SMTP</label><input type="password" id="smtp_pass" name="smtp_pass" value="<?= e($data['smtp_pass']) ?>"></div>
          <div class="field"><label for="smtp_secure">Xifratge</label>
            <select id="smtp_secure" name="smtp_secure">
              <option value="tls"<?= $data['smtp_secure'] === 'tls' ? ' selected' : '' ?>>TLS (587)</option>
              <option value="ssl"<?= $data['smtp_secure'] === 'ssl' ? ' selected' : '' ?>>SSL (465)</option>
              <option value="none"<?= $data['smtp_secure'] === 'none' ? ' selected' : '' ?>>Cap</option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Continuar</button>
          <button class="btn btn--ghost" type="submit" name="provar" value="1">Enviar-me una prova</button>
        </div>
      </form>

    <?php elseif ($step === 5): ?>
      <h2>El vostre compte</h2>
      <p class="text-soft">
        És el compte del panell de superadministració, a part dels administradors de
        cada cros. Amb ell donareu d'alta els clients.
      </p>
      <form method="post">
        <?= setup_hidden(array_diff_key($data, array_flip(['super_name', 'super_email', 'super_pass'])), $token, 5) ?>
        <div class="form-grid form-grid--2">
          <div class="field"><label for="super_name">Nom i cognoms</label><input type="text" id="super_name" name="super_name" value="<?= e($data['super_name']) ?>" required></div>
          <div class="field"><label for="super_email">Correu electrònic</label><input type="email" id="super_email" name="super_email" value="<?= e($data['super_email']) ?>" required></div>
          <div class="field"><label for="super_pass">Contrasenya</label><input type="password" id="super_pass" name="super_pass" required minlength="8"></div>
          <div class="field"><label for="super_pass2">Repetiu-la</label><input type="password" id="super_pass2" name="super_pass2" required minlength="8"></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Continuar</button></div>
      </form>

    <?php elseif ($step === 6): ?>
      <h2>Tot a punt per engegar</h2>
      <div class="req"><span>Servei</span><span><strong><?= e($data['name']) ?></strong></span></div>
      <div class="req"><span>Pàgina pública</span><span><strong><?= e($principal) ?></strong></span></div>
      <div class="req"><span>Panell</span><span><strong><?= e($data['console'] . '.' . $principal) ?></strong></span></div>
      <?php if (count($domains) > 1): ?>
        <div class="req"><span>Altres dominis</span><span><?= e(implode(', ', array_slice($domains, 1))) ?></span></div>
      <?php endif; ?>
      <div class="req"><span>Base de dades</span><span><?= e($data['db_name']) ?> · <?= e($data['db_user']) ?></span></div>
      <div class="req"><span>Correu</span><span><?= e($data['mail_notify']) ?> · <?= e($data['mail_transport']) ?></span></div>
      <div class="req"><span>Superadministrador</span><span><?= e($data['super_email']) ?></span></div>

      <?php foreach ($results as $result): ?>
        <div class="done">
          <span class="done__mark <?= $result['ok'] ? '' : 'done__mark--ko' ?>"><?= $result['ok'] ? '✓' : '!' ?></span>
          <span><?= e($result['text']) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post">
        <?= setup_hidden($data, $token, 6) ?>
        <input type="hidden" name="instalar" value="1">
        <div class="form-actions">
          <button class="btn" type="submit">Engegar la plataforma</button>
          <span class="hint">S'escriu la configuració, es creen les taules i el vostre compte.</span>
        </div>
      </form>

    <?php elseif ($step === 7): ?>
      <h2>Ja està en marxa</h2>
      <?php foreach ($results as $result): ?>
        <div class="done">
          <span class="done__mark <?= $result['ok'] ? '' : 'done__mark--ko' ?>"><?= $result['ok'] ? '✓' : '!' ?></span>
          <span><?= e($result['text']) ?></span>
        </div>
      <?php endforeach; ?>

      <h3 style="margin-top:1.6rem">Què queda per fer</h3>
      <ol style="line-height:1.9">
        <li>Comproveu que <code>https://<?= e($principal) ?></code> ensenya la pàgina pública
          i <code>https://<?= e($data['console'] . '.' . $principal) ?></code> demana les credencials.</li>
        <li>Si encara no teniu el certificat, demaneu-lo al servidor:<br>
          <code>sudo certbot certonly --manual --preferred-challenges dns <?php foreach ($domains as $d): ?>-d <?= e($d) ?> -d '*.<?= e($d) ?>' <?php endforeach; ?></code></li>
        <li>Doneu d'alta el primer cros des del panell. Si ve d'un web que ja existia,
          pugeu-hi el seu fitxer de migració.</li>
      </ol>

      <form method="post">
        <?= setup_hidden($data, $token, 7) ?>
        <input type="hidden" name="tancar" value="1">
        <div class="form-actions">
          <button class="btn" type="submit">Tancar l'assistent i anar al panell</button>
          <span class="hint">S'esborren la clau i les dades que va deixar l'script.</span>
        </div>
      </form>
      <p class="env">
        En acabar, esborreu aquest fitxer del servidor:
        <code>rm <?= e($root) ?>/install-plataforma.php</code>
      </p>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>
</body>
</html>
