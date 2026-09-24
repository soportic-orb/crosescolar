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
      <span class="sidebar__mark"><?= Icons::svg('run', 'icon', 22) ?></span>
      <span><strong><?= e(setting('site_name', 'Cros Escolar')) ?></strong><small>Panell de gestió</small></span>
    </a>

    <a class="nav-link<?= $path === '/admin' ? ' is-active' : '' ?>" href="<?= e(url('/admin')) ?>"><?= Icons::svg('chart', 'icon', 18) ?> Tauler</a>
    <a class="nav-link<?= $isActive('/') && false ? ' is-active' : '' ?>" href="<?= e(url('/')) ?>" target="_blank"><?= Icons::svg('eye', 'icon', 18) ?> Veure el web</a>

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
      <a class="nav-link<?= $isActive('/admin/actualitzacions') ? ' is-active' : '' ?>" href="<?= e(url('/admin/actualitzacions')) ?>"><?= Icons::svg('refresh', 'icon', 18) ?> Actualitzacions</a>
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
