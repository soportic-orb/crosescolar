<?php /** Avís intern que una família ha anul·lat una inscripció. */ ?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">Inscripció anul·lada</h1>
<table role="presentation" width="100%" style="font-size:14px">
  <tr><td style="padding:3px 0;color:#5a6b60">Participant</td><td><strong><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Dorsal</td><td><?= e(\Cros\Models\Bib::number($registration)) ?> (queda reservat)</td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Categoria</td><td><?= e($registration['category_name'] ?? '—') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Contacte</td><td><?= e($registration['tutor_name'] ?? '') ?> · <?= e($registration['tutor_email'] ?? '') ?> · <?= e($registration['tutor_phone'] ?? '') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Anul·lada per</td><td><?= e(\Cros\Models\Registration::CANCELLED_BY[$registration['cancelled_by'] ?? 'familia'] ?? 'La família') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Data</td><td><?= e(dt($registration['cancelled_at'] ?? '', 'd/m/Y H:i')) ?></td></tr>
</table>
<p><a href="<?= e(url('/admin/inscripcions/' . $registration['id'])) ?>">Obrir al panell</a></p>
