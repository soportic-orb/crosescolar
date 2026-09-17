<?php /** Confirmació d'inscripció. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Inscripció confirmada</h1>
<p style="margin:0 0 16px">
  Hem registrat la inscripció de <strong><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></strong>
  al <?= e(setting('site_name', 'Cros Escolar La Granada')) ?>.
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:3px 0">Codi</td><td><strong><?= e($registration['code']) ?></strong></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Categoria</td><td><?= e($registration['category_name'] ?? 'Per assignar') ?></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Any</td><td><?= e($registration['birth_year']) ?></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Data</td><td><?= e(ucfirst(ca_date(setting('event_date', ''), true))) ?>, <?= e(setting('event_time', '')) ?> h</td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Lloc</td><td><?= e(setting('event_place', '')) ?></td></tr>
    </table>
  </td></tr>
</table>
<p style="margin:18px 0 0;font-size:14px">
  Recordeu recollir el dorsal a la zona esportiva 15 minuts abans de la sortida.
  Si voleu quedar-vos a l'esmorzar popular, podeu
  <a href="<?= e(url('/esmorzar')) ?>" style="color:<?= e(setting('color_primary', '#2f6b3c')) ?>">comprar els tiquets aquí</a>.
</p>
