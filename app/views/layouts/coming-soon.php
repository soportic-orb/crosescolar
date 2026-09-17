<?php
/** Plantilla de la pàgina «Aviat publicarem el web». */
$siteName = (string) setting('site_name', 'Cros Escolar La Granada');
$favicon = (string) setting('favicon', '');
$background = (string) (setting('coming_soon_image', '') ?: setting('hero_image', ''));
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Aviat publicarem el web') . ' · ' . $siteName) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="<?= e(excerpt(strip_tags((string) setting('coming_soon_text', '')), 150)) ?>">
<meta name="theme-color" content="<?= e(setting('color_primary', '#2f6b3c')) ?>">
<?php if ($favicon !== ''): ?>
<link rel="icon" href="<?= e(upload_url($favicon)) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<style>
:root{
  --green-700: <?= e(setting('color_primary', '#2f6b3c')) ?>;
  --green-300: <?= e(setting('color_secondary', '#7ba05b')) ?>;
  --accent: <?= e(setting('color_accent', '#c8552b')) ?>;
  --overlay: <?= max(0, min(95, (int) setting('hero_overlay', '55'))) / 100 ?>;
}
.soon{min-height:100vh;display:grid;place-items:center;padding:clamp(2rem,6vw,4rem) 0}
.soon__card{width:min(100% - 2*var(--gutter),720px);margin-inline:auto;text-align:center;color:#fff}
.soon__logo{max-height:96px;width:auto;margin:0 auto 1.6rem}
.soon__mark{width:76px;height:76px;margin:0 auto 1.4rem;display:grid;place-items:center;border-radius:24px;
  background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.3);color:#fff}
.soon__card h1{color:#fff;max-width:none;margin:0 auto .6rem;text-shadow:0 4px 24px rgba(0,0,0,.28)}
.soon__text{font-size:clamp(1.02rem,.98rem + .3vw,1.2rem);color:rgba(255,255,255,.92)}
.soon__text p:last-child{margin-bottom:0}
.soon .countdown{justify-content:center;margin-top:2rem}
.soon__meta{display:flex;flex-wrap:wrap;gap:1rem 1.6rem;justify-content:center;margin-top:1.8rem;font-weight:600}
.soon__meta div{display:flex;align-items:center;gap:.5rem}
.soon__contact{margin-top:2.2rem;padding-top:1.6rem;border-top:1px solid rgba(255,255,255,.2);font-size:.95rem;color:rgba(255,255,255,.85)}
.soon__contact a{color:#fff}
.soon .social-links{justify-content:center;margin-top:1rem}
.soon__admin{position:fixed;inset:auto 0 0;padding:.6rem 1rem;text-align:center;font-size:.85rem;
  background:rgba(18,48,28,.9);color:rgba(255,255,255,.8)}
.soon__admin a{color:#fff;font-weight:700}
</style>
</head>
<body>
<section class="hero<?= $background === '' ? ' hero--plain' : '' ?> soon">
  <?php if ($background !== ''): ?>
    <div class="hero__bg"><img src="<?= e(upload_url($background)) ?>" alt=""></div>
  <?php endif; ?>
  <div class="hero__overlay"></div>
  <?php if (setting('vine_pattern', '1') === '1'): ?>
    <?= \Cros\Core\View::partial('partials/vines', ['height' => 130]) ?>
  <?php endif; ?>
  <?= $content ?>
</section>
<div class="soon__admin">
  Sou de l'organització? <a href="<?= e(url('/admin')) ?>">Accediu al panell de gestió</a>
</div>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
