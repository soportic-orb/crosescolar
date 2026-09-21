<?php /** Avís a la família que una inscripció s'ha anul·lat. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Inscripció anul·lada</h1>
<p style="margin:0 0 16px">
  Hem anul·lat la inscripció de <strong><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></strong>
  al <?= e(setting('site_name', 'Cros Escolar La Granada')) ?>. Aquest participant ja no consta a la sortida.
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:3px 0">Dorsal</td><td><strong><?= e(\Cros\Models\Bib::number($registration)) ?></strong></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Codi</td><td><?= e($registration['code']) ?></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Categoria</td><td><?= e($registration['category_name'] ?? '—') ?></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Anul·lada el</td><td><?= e(dt($registration['cancelled_at'] ?? '', 'd/m/Y H:i')) ?></td></tr>
    </table>
  </td></tr>
</table>
<p style="margin:16px 0 0;font-size:14px">
  El número de dorsal es conserva i no es donarà a ningú més, de manera que un dorsal
  imprès no pot acabar en dues mans diferents.
</p>
<?php if ((int) ($remaining ?? 0) > 0): ?>
  <p style="margin:12px 0 0;font-size:14px">
    Amb aquesta adreça hi continuen havent-hi <strong><?= (int) $remaining ?></strong>
    inscripcions actives, que podeu consultar a
    <a href="<?= e(url('/les-meves-inscripcions')) ?>" style="color:<?= e(setting('color_primary', '#2f6b3c')) ?>">Les meves inscripcions</a>.
  </p>
<?php endif; ?>
<p style="margin:16px 0 0;font-size:14px;color:#5a6b60">
  Si ho heu anul·lat sense voler, escriviu-nos a
  <a href="mailto:<?= e(setting('contact_email', '')) ?>" style="color:<?= e(setting('color_primary', '#2f6b3c')) ?>"><?= e(setting('contact_email', '')) ?></a>
  i ho tornem a activar.
</p>
