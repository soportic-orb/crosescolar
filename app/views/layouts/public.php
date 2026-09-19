<?php
/** @var string $content */
$siteName = (string) setting('site_name', 'Cros Escolar La Granada');
$pageTitle = isset($title) && $title !== '' ? $title : $siteName;
$fullTitle = $pageTitle === $siteName ? $siteName . ' — ' . setting('site_tagline', '') : $pageTitle . ' · ' . $siteName;
$metaDescription = $description ?? setting('meta_description', '');
$logo = (string) setting('logo', '');
$favicon = (string) setting('favicon', '');
$ogImage = (string) (setting('og_image', '') ?: setting('hero_image', ''));
$eventDate = (string) setting('event_date', '');
$path = $currentPath ?? '/';
$navItems = [
    '/' => 'Inici',
    '/recorreguts' => 'Recorreguts',
    '/categories-i-premis' => 'Categories i premis',
    // La inscripció no surt a la llista: el botó destacat del menú ja hi porta.
    '/contacte' => 'Contacte',
];
if (\Cros\Core\Settings::bool('results_published')) {
    // Un cop publicats, els resultats passen a ser el primer que busca la gent.
    $navItems = ['/' => 'Inici', '/resultats' => 'Resultats'] + $navItems;
}
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="ca_ES">
<?php if ($metaDescription !== ''): ?><meta property="og:description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= e(upload_url($ogImage)) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e(setting('color_primary', '#2f6b3c')) ?>">
<?php if ($favicon !== ''): ?>
<link rel="icon" href="<?= e(upload_url($favicon)) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="' . setting('color_primary', '#2f6b3c') . '"/><text x="16" y="22" font-size="16" font-family="sans-serif" fill="#fff" text-anchor="middle">C</text></svg>') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<style>
:root{
  --green-700: <?= e(setting('color_primary', '#2f6b3c')) ?>;
  --green-300: <?= e(setting('color_secondary', '#7ba05b')) ?>;
  --accent: <?= e(setting('color_accent', '#c8552b')) ?>;
  --overlay: <?= max(0, min(95, (int) setting('hero_overlay', '55'))) / 100 ?>;
}
</style>
</head>
<body>
<a class="visually-hidden" href="#contingut">Salta al contingut principal</a>

<?php if (\Cros\Core\Settings::bool('coming_soon') && \Cros\Core\Auth::check()): ?>
  <div class="preview-bar">
    <span><?= \Cros\Core\Icons::svg('eye', 'icon', 18) ?>
      <strong>Web en preparació:</strong> els visitants veuen l'avís «<?= e(setting('coming_soon_title', 'Aviat publicarem el web')) ?>». Vós el veieu sencer perquè teniu la sessió iniciada.</span>
    <form method="post" action="<?= e(url('/admin/properament')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="enable" value="0">
      <button type="submit">Publicar el web ara</button>
    </form>
  </div>
<?php endif; ?>

<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="<?= e(url('/')) ?>">
      <?php if ($logo !== ''): ?>
        <img src="<?= e(upload_url($logo)) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <span class="brand__mark"><?= \Cros\Core\Icons::svg('run', 'icon', 24) ?></span>
      <?php endif; ?>
      <span class="brand__text">
        <small><?= e(setting('event_town', 'La Granada')) ?></small>
        <span><?= e($siteName) ?></span>
      </span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal" aria-label="Obre el menú">
      <?= \Cros\Core\Icons::svg('menu') ?>
    </button>
    <nav class="nav" id="menu-principal" aria-label="Menú principal">
      <?php foreach ($navItems as $href => $label): ?>
        <a href="<?= e(url($href)) ?>"<?= $path === $href ? ' class="is-active"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--accent btn--sm" href="<?= e(url('/inscripcio')) ?>">
        <?= \Cros\Core\Icons::svg('run', 'icon', 18) ?> Inscripcions al cros
      </a>
    </nav>
  </div>
</header>

<main id="contingut">
  <?php $messages = flash(); ?>
  <?php if ($messages): ?>
    <div class="container" style="margin-top:1.5rem">
      <?php foreach ($messages as $message): ?>
        <div class="alert alert--<?= e($message['type']) ?>">
          <span><?= e($message['message']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <h4><?= e($siteName) ?></h4>
        <p style="font-size:.95rem"><?= nl(setting('footer_text', '')) ?></p>
        <?php
        $social = array_filter([
            'instagram' => setting('social_instagram', ''),
            'facebook' => setting('social_facebook', ''),
            'whatsapp' => setting('social_whatsapp', ''),
        ]);
        ?>
        <?php if ($social): ?>
          <div class="social-links">
            <?php foreach ($social as $network => $link): ?>
              <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($network)) ?>">
                <?= \Cros\Core\Icons::svg($network, 'icon', 20) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <h4>La cursa</h4>
        <ul class="footer-links">
          <li><a href="<?= e(url('/recorreguts')) ?>">Recorreguts</a></li>
          <li><a href="<?= e(url('/categories-i-premis')) ?>">Categories i premis</a></li>
          <li><a href="<?= e(url('/inscripcio')) ?>">Inscripció</a></li>
          <li><a href="<?= e(url('/esmorzar')) ?>">Esmorzar popular</a></li>
          <?php if (\Cros\Controllers\AccountController::enabled()): ?>
            <li><a href="<?= e(url('/les-meves-inscripcions')) ?>">Les meves inscripcions</a></li>
          <?php endif; ?>
          <?php if (\Cros\Controllers\TicketsController::saleMode()): ?>
            <li><a href="<?= e(url('/els-meus-tiquets')) ?>">Els meus tiquets</a></li>
          <?php endif; ?>
          <?php if (\Cros\Core\Settings::bool('results_published')): ?>
            <li><a href="<?= e(url('/resultats')) ?>">Resultats</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div>
        <h4>Quan i on</h4>
        <ul class="footer-links">
          <li><?= e(ca_date($eventDate, true)) ?></li>
          <li><?= e(setting('event_time', '')) ?> h · <?= e(setting('event_place', '')) ?></li>
          <li><?= e(setting('event_address', '')) ?></li>
        </ul>
      </div>
      <div>
        <h4>Contacte</h4>
        <ul class="footer-links">
          <?php if (setting('contact_email', '')): ?>
            <li><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li>
          <?php endif; ?>
          <?php if (setting('contact_phone', '')): ?>
            <li><a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li>
          <?php endif; ?>
          <li><a href="<?= e(url('/contacte')) ?>">Formulari de contacte</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e(setting('legal_entity', setting('organizer', ''))) ?>. Tots els drets reservats.</span>
      <span>
        <a href="<?= e(url('/avis-legal')) ?>">Avís legal</a> ·
        <a href="<?= e(url('/privacitat')) ?>">Privacitat</a> ·
        <a href="<?= e(url('/admin')) ?>">Gestió</a>
      </span>
    </div>
  </div>
</footer>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?php if ($analytics = (string) setting('analytics_id', '')): ?>
  <?php if (preg_match('/^G-[A-Z0-9]+$/i', $analytics)): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($analytics) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($analytics) ?>');</script>
  <?php endif; ?>
<?php endif; ?>
</body>
</html>
