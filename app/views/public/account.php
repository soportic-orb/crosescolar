<?php /** «Les meves inscripcions»: llista de participants de l'adreça validada. */ ?>
<?php $partial = ($scope ?? 'email') !== 'email'; ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => 'Les meves inscripcions',
    'subtitle' => $partial ? 'Inscripcions que acabeu de fer' : 'Inscripcions fetes amb ' . $email,
    'breadcrumb' => ['Les meves inscripcions' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <?php if ($partial): ?>
      <div class="notice-box" style="margin-bottom:1.4rem">
        Aquí hi teniu el que acabeu d'inscriure des d'aquest navegador i ho podeu corregir.
        Si n'heu fet més en un altre moment o des d'un altre dispositiu,
        <a href="<?= e(url('/les-meves-inscripcions', ['codi' => 1])) ?>">demaneu un codi d'accés</a>
        i les veureu totes.
      </div>
    <?php endif; ?>

    <?php if (!$registrations): ?>
      <div class="card">
        <p style="margin:0">No hi ha cap inscripció feta amb aquesta adreça.</p>
        <div class="flex" style="margin-top:1rem">
          <a class="btn" href="<?= e(url('/inscripcio')) ?>">Inscriure un participant</a>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($registrations as $registration): ?>
        <?php $cancelled = \Cros\Models\Registration::isCancelled($registration); ?>
        <div class="card<?= $cancelled ? ' card--muted' : '' ?>" style="margin-bottom:1.2rem">
          <div class="eyebrow">Dorsal <?= e(\Cros\Models\Bib::number($registration)) ?></div>
          <h3 style="margin:.2rem 0 .9rem">
            <?= e($registration['first_name'] . ' ' . $registration['last_name']) ?>
            <?php if ($cancelled): ?><span class="status-pill">Anul·lada</span><?php endif; ?>
          </h3>
          <table class="data" style="min-width:0">
            <tbody>
              <tr><th>Any de naixement</th><td><?= e($registration['birth_year']) ?></td></tr>
              <tr><th>Categoria</th><td><?= e($registration['category_name'] ?? 'Per assignar') ?></td></tr>
              <?php if (!empty($registration['gender'])): ?>
                <tr><th>Gènere</th><td><?= e(\Cros\Models\Registration::GENDERS[$registration['gender']] ?? $registration['gender']) ?></td></tr>
              <?php endif; ?>
              <?php if (!empty($registration['school'])): ?><tr><th>Escola</th><td><?= e($registration['school']) ?></td></tr><?php endif; ?>
              <tr><th>Contacte</th><td><?= e($registration['tutor_name']) ?> · <?= e($registration['tutor_phone'] ?? '') ?></td></tr>
            </tbody>
          </table>
          <?php if ($cancelled): ?>
            <p class="field__hint" style="margin:.2rem 0 0">
              Aquest participant ja no consta a la sortida. El dorsal
              <?= e(\Cros\Models\Bib::number($registration)) ?> queda reservat i no serà de ningú més.
              Si ho voleu desfer, escriviu-nos a
              <a href="mailto:<?= e(setting('contact_email', '')) ?>"><?= e(setting('contact_email', '')) ?></a>.
            </p>
          <?php else: ?>
            <div class="flex" style="margin-top:1rem">
              <a class="btn btn--sm" href="<?= e(url('/les-meves-inscripcions/' . (int) $registration['id'] . '/modificar')) ?>">
                <?= \Cros\Core\Icons::svg('edit', 'icon', 16) ?> Modificar les dades
              </a>
              <?php if (!empty($registration['token']) && \Cros\Models\Bib::publicDownload()): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/inscripcio/dorsal/' . $registration['token'])) ?>">
                  <?= \Cros\Core\Icons::svg('download', 'icon', 16) ?> Descarregar el dorsal
                </a>
              <?php endif; ?>
              <form method="post" action="<?= e(url('/les-meves-inscripcions/' . (int) $registration['id'] . '/anullar')) ?>"
                    data-confirm="Voleu anul·lar la inscripció de <?= e($registration['first_name']) ?>? El participant no sortirà a la cursa i, per tornar-la a activar, haureu d'escriure a l'organització.">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--sm" type="submit">
                  <?= \Cros\Core\Icons::svg('trash', 'icon', 16) ?> Anul·lar la inscripció
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php $actives = \Cros\Models\Registration::active($registrations); ?>
      <?php if (count($actives) > 1 && !empty($actives[0]['token']) && \Cros\Models\Bib::publicDownload()): ?>
        <a class="btn" href="<?= e(url('/inscripcio/dorsals/' . $actives[0]['token'])) ?>">
          <?= \Cros\Core\Icons::svg('download', 'icon', 18) ?> Tots els dorsals en un PDF
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <div class="flex" style="margin-top:1.5rem;justify-content:space-between">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/inscripcio')) ?>">Inscriure un altre participant</a>
      <form method="post" action="<?= e(url('/les-meves-inscripcions/sortir')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--sm" type="submit">Sortir</button>
      </form>
    </div>
    <p class="field__hint" style="margin-top:1.2rem">
      Les inscripcions anul·lades es queden aquí, marcades, i conserven el número de dorsal.
      Per canviar l'adreça de contacte o recuperar una inscripció anul·lada, escriviu-nos a
      <a href="mailto:<?= e(setting('contact_email', '')) ?>"><?= e(setting('contact_email', '')) ?></a>.
    </p>
  </div>
</section>
