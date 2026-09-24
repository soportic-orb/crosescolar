<?php
$platform = 'https://' . (\Cros\Core\Tenancy::settings(CROS_ROOT)['base_domain'] ?? 'crosescolar.com');
/** Un subdomini que no és de cap cros. */ ?>
<div class="login-card" style="text-align:center">
  <h1>Aquesta adreça no existeix</h1>
  <p class="sub">
    No hi ha cap cros escolar a <strong><?= e($host ?? '') ?></strong>.
    Comproveu que l'adreça estigui ben escrita.
  </p>
  <p style="margin-top:1.2rem">
    <a class="btn" href="<?= e($platform) ?>">Veure tots els cros escolars</a>
  </p>
  <p class="text-soft" style="font-size:.9rem;margin-top:1rem">
    Organitzeu un cros i encara no teniu web?
    <a href="<?= e($platform) ?>#formulari">Creeu la vostra</a>.
  </p>
</div>
