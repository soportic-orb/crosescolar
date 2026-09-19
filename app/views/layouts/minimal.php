<?php /** Plantilla mínima: accés al panell, manteniment i errors del panell. */ ?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Panell') . ' · ' . setting('site_name', 'Cros Escolar La Granada')) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>:root{--a-green: <?= e(setting('color_primary', '#2f6b3c')) ?>;}</style>
</head>
<body class="login-page">
<?= $content ?>
</body>
</html>
