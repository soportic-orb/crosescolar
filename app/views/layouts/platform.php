<?php
/**
 * Plantilla de la pàgina pública de la plataforma.
 * @var string $content
 */
use Cros\Platform\Platform;

$domain = Platform::domain();
$name = (string) setting('site_name', 'Cros Escolar');
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? $name) . ' · ' . $domain) ?></title>
<?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/platform.css')) ?>">
<?php $favicon = (string) setting('platform_favicon', ''); ?>
<?php if ($favicon !== ''): ?>
  <link rel="icon" href="<?= e(upload_url($favicon)) ?>">
<?php else: ?>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#2f6b3c"/><text x="16" y="22" font-size="16" font-family="sans-serif" fill="#fff" text-anchor="middle">C</text></svg>') ?>">
<?php endif; ?>
<style>
  :root{
    --platform-dark: <?= e(setting('platform_color_dark', '#16303d')) ?>;
    --platform-main: <?= e(setting('color_primary', '#1f4f5f')) ?>;
    --platform-accent: <?= e(setting('platform_color_accent', '#2f7d84')) ?>;
  }
</style>
</head>
<body>
<a class="visually-hidden" href="#contingut">Salta al contingut principal</a>

<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="<?= e(url('/')) ?>">
      <?php $logo = (string) setting('platform_logo', ''); ?>
      <span class="brand__mark">
        <?php if ($logo !== ''): ?>
          <img src="<?= e(upload_url($logo)) ?>" alt="<?= e($name) ?>" style="max-width:28px;max-height:28px">
        <?php else: ?>
          <?= \Cros\Core\Icons::svg('run', 'icon', 24) ?>
        <?php endif; ?>
      </span>
      <span class="brand__text">
        <small>Plataforma</small>
        <span><?= e($name) ?></span>
      </span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-plataforma" aria-label="Obre el menú">
      <?= \Cros\Core\Icons::svg('menu') ?>
    </button>
    <nav class="nav" id="menu-plataforma" aria-label="Menú principal">
      <a href="<?= e(url('/')) ?>#cros">Cros escolars</a>
      <a href="<?= e(url('/')) ?>#com-va">Com funciona</a>
      <a class="btn btn--accent btn--sm" href="<?= e(url('/')) ?>#formulari">Crea la web per al teu cros</a>
    </nav>
  </div>
</header>

<main id="contingut">
  <?php $messages = flash(); ?>
  <?php if ($messages): ?>
    <div class="container" style="margin-top:1.5rem">
      <?php foreach ($messages as $message): ?>
        <div class="alert alert--<?= e($message['type']) ?>"><span><?= e($message['message']) ?></span></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <h4><?= e($name) ?></h4>
        <p style="font-size:.95rem">La plataforma per als cros escolars de les escoles, AFA i clubs: inscripcions, dorsals i resultats, cadascú a la seva adreça.</p>
      </div>
      <div>
        <h4>La plataforma</h4>
        <ul class="footer-links">
          <li><a href="<?= e(url('/')) ?>#cros">Cros escolars</a></li>
          <li><a href="<?= e(url('/')) ?>#com-va">Com funciona</a></li>
          <li><a href="<?= e(url('/')) ?>#formulari">Crea la web per al teu cros</a></li>
        </ul>
      </div>
      <div>
        <h4>Contacte</h4>
        <ul class="footer-links">
          <li><a href="mailto:<?= e(Platform::notifyEmail()) ?>"><?= e(Platform::notifyEmail()) ?></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e($name) ?></span>
      <span>Desenvolupat per <a href="mailto:octavi@soportic.es">Octavi Rodríguez</a></span>
    </div>
  </div>
</footer>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
