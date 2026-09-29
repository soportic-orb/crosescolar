<?php
/** Plantilla del panell d'administració. */
use Cros\Core\Auth;
use Cros\Core\Icons;
use Cros\Core\Settings;

$user = Auth::user();
$path = $currentPath ?? '/admin';
$resources = require CROS_APP . '/resources.php';
$isActive = fn (string $href): bool => $path === $href || ($href !== '/admin' && str_starts_with($path, $href));
$pending = (int) \Cros\Core\Db::val('SELECT COUNT(*) FROM orders WHERE status = \'pending\'', [], 0);

// L'avís de dalt de tot: mentre el web estigui en preparació, no hi ha res
// més important a la pantalla. Qui el munta ha de veure sempre que encara no
// el veu ningú i què li falta per publicar-lo.
$amagat = Settings::bool('coming_soon');
$activacio = $amagat ? \Cros\Models\Activation::status() : ['applies' => false, 'paid' => false, 'ready' => false, 'amounts' => ['total' => 0]];
$calPagar = $amagat && !empty($activacio['applies']) && empty($activacio['paid']);
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Panell') . ' · ' . setting('site_name', 'Cros Escolar')) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>:root{--a-green: <?= e(setting('color_primary', '#2f6b3c')) ?>;}</style>
</head>
<body class="admin">
<div class="admin-layout">
  <aside class="sidebar">
    <a class="sidebar__brand" href="<?= e(url('/admin')) ?>">
      <span class="sidebar__mark"><?= \Cros\Core\Brand::mark(22) ?></span>
      <span><strong><?= e(setting('site_name', 'Cros Escolar')) ?></strong><small>Panell de gestió</small></span>
    </a>

    <a class="nav-link<?= $path === '/admin' ? ' is-active' : '' ?>" href="<?= e(url('/admin')) ?>"><?= Icons::svg('chart', 'icon', 18) ?> Tauler</a>
    <a class="nav-link<?= $isActive('/') && false ? ' is-active' : '' ?>" href="<?= e(url('/')) ?>" target="_blank"><?= Icons::svg('eye', 'icon', 18) ?> Veure el web</a>
    <?php if (\Cros\Platform\Support::available() && \Cros\Core\Tenancy::slugOf() !== ''): ?>
      <?php $respostes = \Cros\Platform\Support::badge(\Cros\Core\Tenancy::slugOf()); ?>
      <a class="nav-link<?= $isActive('/admin/suport') ? ' is-active' : '' ?>" href="<?= e(url('/admin/suport')) ?>">
        <?= Icons::svg('help', 'icon', 18) ?> Suport
        <?php if ($respostes > 0): ?><span class="badge badge--green"><?= $respostes ?></span><?php endif; ?>
      </a>
    <?php endif; ?>

    <div class="sidebar__section">Punt de recàrrega</div>
    <a class="nav-link<?= $isActive('/admin/comandes') ? ' is-active' : '' ?>" href="<?= e(url('/admin/comandes')) ?>">
      <?= Icons::svg('euro', 'icon', 18) ?> Comandes
      <?php if ($pending > 0): ?><span class="badge badge--amber"><?= $pending ?></span><?php endif; ?>
    </a>
    <a class="nav-link<?= $isActive('/admin/contingut/tipus-tiquet') ? ' is-active' : '' ?>" href="<?= e(url('/admin/contingut/tipus-tiquet')) ?>"><?= Icons::svg('ticket', 'icon', 18) ?> Tipus de tiquet</a>
    <a class="nav-link<?= $isActive('/admin/validacio') ? ' is-active' : '' ?>" href="<?= e(url('/admin/validacio')) ?>"><?= Icons::svg('qr', 'icon', 18) ?> Validar tiquets</a>

    <div class="sidebar__section">Participants</div>
    <a class="nav-link<?= $isActive('/admin/inscripcions') ? ' is-active' : '' ?>" href="<?= e(url('/admin/inscripcions')) ?>"><?= Icons::svg('users', 'icon', 18) ?> Inscripcions</a>
    <a class="nav-link<?= $isActive('/admin/resultats') ? ' is-active' : '' ?>" href="<?= e(url('/admin/resultats')) ?>">
      <?= Icons::svg('trophy', 'icon', 18) ?> Resultats
      <?php if (\Cros\Core\Settings::bool('results_published')): ?><span class="badge badge--green">Publicats</span><?php endif; ?>
    </a>

    <a class="nav-link<?= $isActive('/admin/enviaments') ? ' is-active' : '' ?>" href="<?= e(url('/admin/enviaments')) ?>"><?= Icons::svg('mail', 'icon', 18) ?> Enviaments</a>

    <?php if (\Cros\Platform\Bridge::available() && \Cros\Core\Tenancy::slugOf() !== ''): ?>
      <a class="nav-link<?= $isActive('/admin/activacio') ? ' is-active' : '' ?>" href="<?= e(url('/admin/activacio')) ?>">
        <?= Icons::svg('check', 'icon', 18) ?> Activació del web
      </a>
    <?php endif; ?>

    <div class="sidebar__section">Cobraments</div>
    <a class="nav-link<?= $path === '/admin/pagaments' || preg_match('#^/admin/pagaments/\d+#', $path) ? ' is-active' : '' ?>" href="<?= e(url('/admin/pagaments')) ?>"><?= Icons::svg('euro', 'icon', 18) ?> Cobraments</a>
    <a class="nav-link<?= $isActive('/admin/pagaments/documents') ? ' is-active' : '' ?>" href="<?= e(url('/admin/pagaments/documents')) ?>"><?= Icons::svg('file', 'icon', 18) ?> Rebuts i factures</a>

    <div class="sidebar__section">Continguts</div>
    <a class="nav-link<?= $isActive('/admin/menu') ? ' is-active' : '' ?>" href="<?= e(url('/admin/menu')) ?>"><?= Icons::svg('menu', 'icon', 18) ?> Menú del web</a>
    <?php foreach ($resources as $key => $resource): ?>
      <?php if ($key === 'tipus-tiquet') { continue; } ?>
      <a class="nav-link<?= $isActive('/admin/contingut/' . $key) ? ' is-active' : '' ?>" href="<?= e(url('/admin/contingut/' . $key)) ?>">
        <?= Icons::svg((string) $resource['icon'], 'icon', 18) ?> <?= e($resource['title']) ?>
      </a>
    <?php endforeach; ?>

    <div class="sidebar__section">Configuració</div>
    <?php foreach (Settings::schema() as $groupKey => $group): ?>
      <?php if (!empty($group['admin_only']) && !Auth::isAdmin()) { continue; } ?>
      <a class="nav-link<?= $path === '/admin/configuracio/' . $groupKey ? ' is-active' : '' ?>" href="<?= e(url('/admin/configuracio/' . $groupKey)) ?>">
        <?= Icons::svg((string) $group['icon'], 'icon', 18) ?> <?= e($group['title']) ?>
      </a>
    <?php endforeach; ?>

    <div class="sidebar__section">Sistema</div>
    <?php if (Auth::isAdmin()): ?>
      <a class="nav-link<?= $isActive('/admin/usuaris') ? ' is-active' : '' ?>" href="<?= e(url('/admin/usuaris')) ?>"><?= Icons::svg('users', 'icon', 18) ?> Usuaris</a>
      <?php if (\Cros\Core\Tenancy::mode() !== 'tenant'): ?>
        <a class="nav-link<?= $isActive('/admin/actualitzacions') ? ' is-active' : '' ?>" href="<?= e(url('/admin/actualitzacions')) ?>"><?= Icons::svg('refresh', 'icon', 18) ?> Actualitzacions</a>
      <?php endif; ?>
    <?php endif; ?>
    <a class="nav-link<?= $isActive('/admin/correus') ? ' is-active' : '' ?>" href="<?= e(url('/admin/correus')) ?>"><?= Icons::svg('mail', 'icon', 18) ?> Correus</a>
    <?php if (Auth::isAdmin()): ?>
      <a class="nav-link<?= $isActive('/admin/dades') ? ' is-active' : '' ?>" href="<?= e(url('/admin/dades')) ?>"><?= Icons::svg('download', 'icon', 18) ?> Les meves dades</a>
    <?php endif; ?>
    <a class="nav-link<?= $isActive('/admin/registre') ? ' is-active' : '' ?>" href="<?= e(url('/admin/registre')) ?>"><?= Icons::svg('file', 'icon', 18) ?> Registre</a>
    <div style="padding:1rem 1.3rem;font-size:.75rem;color:rgba(255,255,255,.4)">Versió <?= e(app_version()) ?></div>
  </aside>

  <div class="admin-main">
    <header class="topbar">
      <button class="menu-toggle" type="button" aria-label="Menú"><?= Icons::svg('menu', 'icon', 20) ?></button>
      <h1><?= e($title ?? 'Panell') ?></h1>
      <div class="topbar__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/perfil')) ?>"><?= Icons::svg('settings', 'icon', 16) ?> <?= e($user['name'] ?? '') ?></a>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/sortir')) ?>"><?= Icons::svg('logout', 'icon', 16) ?> Sortir</a>
      </div>
    </header>

    <?php if ($amagat && !str_starts_with($path, '/admin/activacio')): ?>
      <div class="publish-bar">
        <span class="publish-bar__mark"><?= Icons::svg('eye', 'icon', 18) ?></span>
        <div class="publish-bar__text">
          <strong>El web encara no està publicat</strong>
          <span>
            De moment només el veieu vosaltres: qui hi arribi trobarà l'avís de «<?= e(setting('coming_soon_title', 'Aviat publicarem el web')) ?>».
            <?php if ($calPagar && !empty($activacio['ready'])): ?>
              Per obrir-lo al públic hi ha un pagament únic de <strong><?= e(money((int) $activacio['amounts']['total'])) ?></strong>.
            <?php endif; ?>
          </span>
        </div>
        <?php if ($calPagar): ?>
          <a class="btn btn--accent btn--sm" href="<?= e(url(!empty($activacio['ready']) ? '/admin/activacio/publicar' : '/admin/activacio')) ?>">
            <?= Icons::svg('check', 'icon', 16) ?> Publicar web
          </a>
        <?php else: ?>
          <form method="post" action="<?= e(url('/admin/properament')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="enable" value="0">
            <button class="btn btn--accent btn--sm" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Publicar web</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <main class="content">
      <?php foreach (flash() as $message): ?>
        <div class="alert alert--<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
