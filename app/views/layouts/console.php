<?php
/** Plantilla del panell de superadministració. */
use Cros\Core\Icons;
use Cros\Platform\Platform;
use Cros\Platform\Request;

$user = \Cros\Platform\Console::user();
$path = \Cros\Core\Router::currentPath();
$isActive = static fn (string $href): bool => $path === $href || ($href !== '/' && str_starts_with($path, $href));
$pending = Request::pending();
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Panell') . ' · Plataforma') ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>:root{--a-green:#1f4f5f;}</style>
</head>
<body class="admin">
<div class="admin-layout">
  <aside class="sidebar">
    <a class="sidebar__brand" href="<?= e(url('/')) ?>">
      <span class="sidebar__mark"><?= Icons::svg('run', 'icon', 22) ?></span>
      <span><strong>Plataforma</strong><small><?= e(Platform::domain()) ?></small></span>
    </a>

    <a class="nav-link<?= $path === '/' ? ' is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= Icons::svg('chart', 'icon', 18) ?> Tauler</a>
    <a class="nav-link<?= $isActive('/sollicituds') ? ' is-active' : '' ?>" href="<?= e(url('/sollicituds')) ?>">
      <?= Icons::svg('mail', 'icon', 18) ?> Sol·licituds
      <?php if ($pending > 0): ?><span class="badge badge--amber"><?= $pending ?></span><?php endif; ?>
    </a>
    <a class="nav-link<?= $isActive('/instancies') ? ' is-active' : '' ?>" href="<?= e(url('/instancies')) ?>"><?= Icons::svg('run', 'icon', 18) ?> Instàncies</a>
    <a class="nav-link<?= $isActive('/clients') ? ' is-active' : '' ?>" href="<?= e(url('/clients')) ?>"><?= Icons::svg('users', 'icon', 18) ?> Clients</a>

    <div class="sidebar__section">Sistema</div>
    <a class="nav-link<?= $isActive('/registre') ? ' is-active' : '' ?>" href="<?= e(url('/registre')) ?>"><?= Icons::svg('file', 'icon', 18) ?> Registre</a>
    <a class="nav-link" href="https://<?= e(Platform::domain()) ?>" target="_blank" rel="noopener"><?= Icons::svg('eye', 'icon', 18) ?> Veure el web</a>
    <div style="padding:1rem 1.3rem;font-size:.75rem;color:rgba(255,255,255,.4)">Versió <?= e(app_version()) ?></div>
  </aside>

  <div class="admin-main">
    <header class="topbar">
      <button class="menu-toggle" type="button" aria-label="Menú"><?= Icons::svg('menu', 'icon', 20) ?></button>
      <h1><?= e($title ?? 'Panell') ?></h1>
      <div class="topbar__actions">
        <span class="text-soft" style="font-size:.85rem"><?= e($user['name'] ?? '') ?></span>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/sortir')) ?>"><?= Icons::svg('logout', 'icon', 16) ?> Sortir</a>
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
