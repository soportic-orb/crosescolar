<?php /** Confirmació d'inscripció. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', ['title' => 'Inscripció confirmada', 'breadcrumb' => ['Inscripció' => '/inscripcio', 'Confirmada' => '']]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="alert alert--success">
      <span>Inscripció registrada amb el codi <strong><?= e($registration['code']) ?></strong>.</span>
    </div>
    <div class="prose"><?= setting_html('registrations_success_text') ?></div>
    <div class="card">
      <h3>Dades registrades</h3>
      <table class="data" style="min-width:0">
        <tbody>
          <tr><th>Participant</th><td><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></td></tr>
          <tr><th>Any de naixement</th><td><?= e($registration['birth_year']) ?></td></tr>
          <tr><th>Categoria</th><td><?= e($registration['category_name'] ?? 'Per assignar') ?></td></tr>
          <?php if (!empty($registration['school'])): ?><tr><th>Escola</th><td><?= e($registration['school']) ?></td></tr><?php endif; ?>
          <tr><th>Contacte</th><td><?= e($registration['tutor_name']) ?> · <?= e($registration['tutor_email']) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="flex" style="margin-top:1.5rem">
      <a class="btn" href="<?= e(url('/inscripcio')) ?>">Inscriure un altre participant</a>
      <a class="btn btn--accent" href="<?= e(url('/esmorzar')) ?>">Tiquets de l'esmorzar</a>
    </div>
  </div>
</section>
