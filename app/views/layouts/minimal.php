<?php /** Plantilla mínima: accés al panell, manteniment i errors del panell. */ ?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
// El nom del web només té sentit quan la petició és d'una instal·lació:
// a una adreça que no és de ningú no hi ha de sortir el nom d'un altre client.
$owner = in_array(\Cros\Core\Tenancy::mode(), ['single', 'tenant'], true)
    ? ' · ' . setting('site_name', 'EsportWeb')
    : '';
// El fons és el mateix de la portada de la plataforma, amb el vel verd a
// sobre. Si no se sap quin és, el degradat de sempre ja fa la seva feina.
$fons = ['url' => '', 'overlay' => 0.72];
try {
    $fons = \Cros\Models\Backdrop::login();
} catch (\Throwable $e) {
    // Una pantalla d'accés no ha de fallar mai per una imatge.
}
?>
<title><?= e(($title ?? 'Panell') . $owner) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>
  :root{--a-green: <?= e(setting('color_primary', '#2f6b3c')) ?>;}
<?php if ($fons['url'] !== ''): ?>
<?php
  // El vel verd, amb l'opacitat que tingui la portada de la plataforma. Es
  // fa aquí perquè el valor és una opció, no una constant del full d'estils.
  $vel = static fn (string $color, float $part): string => sprintf(
      'rgba(%s, %.2f)', $color, max(0.0, min(1.0, (float) $fons['overlay'] * $part))
  );
?>
  .login-page{
    --login-image: url('<?= e($fons['url']) ?>');
    --login-veil: linear-gradient(160deg,
      <?= e($vel('27, 69, 42', 1.0)) ?>,
      <?= e($vel('47, 107, 60', .92)) ?> 60%,
      <?= e($vel('79, 148, 87', .86)) ?>);
  }
<?php endif; ?>
</style>
</head>
<body class="login-page<?= $fons['url'] !== '' ? ' login-page--image' : '' ?>">
<?= $content ?>
</body>
</html>
