<?php /** Pàgina d'error. */ ?>
<section class="section" style="min-height:52vh;display:grid;place-items:center;text-align:center">
  <div class="container-narrow">
    <p class="eyebrow">Error <?= (int) $status ?></p>
    <h1><?= e($message) ?></h1>
    <p class="lead">Comprova l'adreça o torna a la portada.</p>
    <?php if (!empty($details)): ?>
      <pre style="text-align:left;white-space:pre-wrap;background:#fff;border:1px solid var(--line);padding:1rem;border-radius:12px;font-size:.82rem;overflow:auto"><?= e($details) ?></pre>
    <?php endif; ?>
    <div class="flex" style="justify-content:center;margin-top:1.2rem">
      <a class="btn" href="<?= e(url('/')) ?>">Anar a la portada</a>
      <a class="btn btn--ghost" href="<?= e(url('/contacte')) ?>">Contactar</a>
    </div>
  </div>
</section>
