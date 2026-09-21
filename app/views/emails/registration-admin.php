<?php /** Avís intern de nova inscripció. */ ?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">Nova inscripció</h1>
<table role="presentation" width="100%" style="font-size:14px">
  <tr><td style="padding:3px 0;color:#5a6b60">Participant</td><td><strong><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Any</td><td><?= e($registration['birth_year']) ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Categoria</td><td><?= e($registration['category_name'] ?? '—') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Escola</td><td><?= e($registration['school'] ?? '') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Contacte</td><td><?= e($registration['tutor_name'] ?? '') ?> · <?= e($registration['tutor_email'] ?? '') ?> · <?= e($registration['tutor_phone'] ?? '') ?></td></tr>
  <?php if (!empty($registration['notes'])): ?>
    <tr><td style="padding:3px 0;color:#5a6b60">Notes</td><td><?= nl2br(e($registration['notes'])) ?></td></tr>
  <?php endif; ?>
</table>
<p><a href="<?= e(url('/admin/inscripcions/' . $registration['id'])) ?>">Obrir al panell</a></p>
