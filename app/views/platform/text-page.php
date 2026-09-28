<?php
/**
 * Una pàgina de text de la plataforma: condicions, privadesa o galetes.
 * @var string $title
 * @var string $body
 * @var string|null $updated
 */
?>
<section class="platform-page">
  <div class="container-narrow">
    <h1><?= e($title) ?></h1>
    <?php if ($body === ''): ?>
      <div class="notice-box">
        Aquest text encara no s'ha escrit. Qui porti la plataforma el pot redactar des del
        panell, a <strong>Configuració → Legal i galetes</strong>.
      </div>
    <?php else: ?>
      <div class="prose"><?= $body ?></div>
      <?php if (!empty($updated)): ?>
        <p class="text-soft" style="margin-top:2rem;font-size:.9rem">
          Última actualització: <?= e(date('d/m/Y', strtotime((string) $updated))) ?>.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
