<?php /** Confirmació d'inscripció. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', ['title' => 'Inscripció confirmada', 'breadcrumb' => ['Inscripció' => '/inscripcio', 'Confirmada' => '']]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="alert alert--success">
      <span>Inscripció registrada amb el codi <strong><?= e($registration['code']) ?></strong>.</span>
    </div>
    <div class="prose"><?= setting_html('registrations_success_text') ?></div>
    <div class="card" style="text-align:center;margin-bottom:1.4rem">
      <div class="eyebrow" style="justify-content:center">El teu dorsal</div>
      <div style="font-size:3.4rem;font-weight:800;line-height:1.1;color:var(--green-700)">
        <?= e(\Cros\Models\Bib::number($registration)) ?>
      </div>
      <?php if (!empty($registration['token']) && \Cros\Models\Bib::publicDownload()): ?>
        <div class="flex" style="justify-content:center;margin-top:1rem">
          <a class="btn" href="<?= e(url('/inscripcio/dorsal/' . $registration['token'])) ?>">
            <?= \Cros\Core\Icons::svg('download', 'icon', 18) ?> Descarregar el dorsal (PDF)
          </a>
          <?php $siblings = \Cros\Models\Registration::forEmail((string) $registration['tutor_email']); ?>
          <?php if (count($siblings) > 1): ?>
            <a class="btn btn--ghost" href="<?= e(url('/inscripcio/dorsals/' . $registration['token'])) ?>">
              Tots els dorsals (<?= count($siblings) ?>)
            </a>
          <?php endif; ?>
        </div>
        <p class="field__hint" style="margin:.9rem 0 0">Imprimiu-lo i porteu-lo posat el dia de la cursa.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Dades registrades</h3>
      <table class="data" style="min-width:0">
        <tbody>
          <tr><th>Participant</th><td><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></td></tr>
          <tr><th>Dorsal</th><td><?= e(\Cros\Models\Bib::number($registration)) ?></td></tr>
          <tr><th>Any de naixement</th><td><?= e($registration['birth_year']) ?></td></tr>
          <tr><th>Categoria</th><td><?= e($registration['category_name'] ?? 'Per assignar') ?></td></tr>
          <?php if (!empty($registration['school'])): ?><tr><th>Escola</th><td><?= e($registration['school']) ?></td></tr><?php endif; ?>
          <tr><th>Contacte</th><td><?= e($registration['tutor_name']) ?> · <?= e($registration['tutor_email']) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="flex" style="margin-top:1.5rem">
      <a class="btn" href="<?= e(url('/inscripcio')) ?>">Inscriure un altre participant</a>
      <?php if (\Cros\Controllers\AccountController::enabled()): ?>
        <a class="btn btn--ghost" href="<?= e(url('/les-meves-inscripcions')) ?>">
          <?= \Cros\Core\Icons::svg('edit', 'icon', 18) ?> Les meves inscripcions
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
