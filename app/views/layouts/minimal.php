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
    ? ' · ' . setting('site_name', 'Cros Escolar La Granada')
    : '';
?>
<title><?= e(($title ?? 'Panell') . $owner) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>:root{--a-green: <?= e(setting('color_primary', '#2f6b3c')) ?>;}</style>
</head>
<body class="login-page">
<?= $content ?>
</body>
</html>
