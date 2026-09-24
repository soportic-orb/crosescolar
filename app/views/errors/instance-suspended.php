<?php
$platform = 'https://' . (\Cros\Core\Tenancy::settings(CROS_ROOT)['base_domain'] ?? 'crosescolar.com');
/** El web d'un client aturat temporalment. */ ?>
<div class="login-card" style="text-align:center">
  <h1>Aquest web no està disponible</h1>
  <p class="sub">
    El cros de <strong><?= e($host ?? '') ?></strong> està aturat temporalment.
    Si en sou l'organització, escriviu-nos i ho mirem.
  </p>
  <p style="margin-top:1.2rem">
    <a class="btn btn--ghost" href="<?= e($platform) ?>">Anar a la plataforma</a>
  </p>
</div>
