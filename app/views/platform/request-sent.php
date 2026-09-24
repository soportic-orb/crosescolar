<?php
/** Pantalla de després d'enviar la sol·licitud. */
use Cros\Core\Icons;
use Cros\Platform\Platform;
?>
<section class="section">
  <div class="container-narrow">
    <div class="card" style="text-align:center">
      <div class="card__icon" style="margin:0 auto 1rem"><?= Icons::svg('check', 'icon', 26) ?></div>
      <h1 style="margin:0 0 .6rem">Hem rebut la vostra sol·licitud</h1>
      <p class="lead" style="margin:0 auto;max-width:48ch">
        Queda apuntada amb el número <strong><?= e($request['code']) ?></strong>.
        Us hem enviat un correu de confirmació a <strong><?= e($request['contact_email']) ?></strong>.
      </p>
      <table class="data" style="min-width:0;margin-top:1.6rem;text-align:left">
        <tbody>
          <tr><th>Entitat</th><td><?= e($request['entity']) ?></td></tr>
          <?php if (!empty($request['town'])): ?><tr><th>Població</th><td><?= e($request['town']) ?></td></tr><?php endif; ?>
          <?php if (!empty($request['slug'])): ?>
            <tr><th>Adreça demanada</th><td><?= e($request['slug'] . '.' . Platform::domain()) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($request['event_date'])): ?>
            <tr><th>Cursa prevista</th><td><?= e(ca_date($request['event_date'], true)) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <p class="field__hint" style="margin-top:1.4rem">
        La revisem i us responem en 48 hores feineres. Si hi ha res a corregir, responeu el correu que us hem enviat.
      </p>
      <div class="flex" style="justify-content:center;margin-top:1.2rem">
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Tornar a la portada</a>
      </div>
    </div>
  </div>
</section>
