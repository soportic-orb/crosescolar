<?php /** Confirmació a qui demana una instància. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Hem rebut la vostra sol·licitud</h1>
<p style="margin:0 0 16px">Hola, <?= e(explode(' ', trim((string) $request['contact_name']))[0] ?: 'bon dia') ?>,</p>
<p style="margin:0 0 16px">
  Hem rebut la sol·licitud del web del cros de <strong><?= e($request['entity']) ?></strong>.
  La revisem i us responem en 48 hores feineres.
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:4px 0;width:45%">Número de sol·licitud</td><td><strong><?= e($request['code']) ?></strong></td></tr>
      <tr><td style="color:#5a6b60;padding:4px 0">Entitat</td><td><?= e($request['entity']) ?></td></tr>
      <?php if (!empty($request['town'])): ?><tr><td style="color:#5a6b60;padding:4px 0">Població</td><td><?= e($request['town']) ?></td></tr><?php endif; ?>
      <?php if (!empty($request['slug'])): ?>
        <tr><td style="color:#5a6b60;padding:4px 0">Adreça demanada</td><td><?= e($request['slug'] . '.' . ($domain ?? '')) ?></td></tr>
      <?php endif; ?>
      <?php if (!empty($request['event_date'])): ?>
        <tr><td style="color:#5a6b60;padding:4px 0">Cursa prevista</td><td><?= e(ca_date($request['event_date'], true)) ?></td></tr>
      <?php endif; ?>
      <tr><td style="color:#5a6b60;padding:4px 0">Contacte</td><td><?= e($request['contact_name']) ?> · <?= e($request['contact_phone'] ?? '') ?></td></tr>
    </table>
  </td></tr>
</table>
<p style="margin:16px 0 0;font-size:14px">Si hi ha res a corregir, responeu aquest correu i ho arreglem.</p>
