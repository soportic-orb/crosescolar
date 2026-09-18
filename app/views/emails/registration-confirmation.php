<?php /** Confirmació d'inscripció. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Inscripció confirmada</h1>
<p style="margin:0 0 16px">
  Hem registrat la inscripció de <strong><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></strong>
  al <?= e(setting('site_name', 'Cros Escolar La Granada')) ?>.
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <div style="text-align:center;padding:6px 0 14px;border-bottom:1px solid #e3eade;margin-bottom:12px">
      <div style="font-size:12px;color:#5a6b60;text-transform:uppercase;letter-spacing:.08em">Dorsal</div>
      <div style="font-size:40px;font-weight:700;line-height:1.1;color:<?= e(setting('color_primary', '#2f6b3c')) ?>">
        <?= e(\Cros\Models\Bib::number($registration)) ?>
      </div>
    </div>
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:3px 0">Codi</td><td><strong><?= e($registration['code']) ?></strong></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Categoria</td><td><?= e($registration['category_name'] ?? 'Per assignar') ?></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Any</td><td><?= e($registration['birth_year']) ?></td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Data</td><td><?= e(ucfirst(ca_date(setting('event_date', ''), true))) ?>, <?= e(setting('event_time', '')) ?> h</td></tr>
      <tr><td style="color:#5a6b60;padding:3px 0">Lloc</td><td><?= e(setting('event_place', '')) ?></td></tr>
    </table>
  </td></tr>
</table>
<?php $token = (string) ($registration['token'] ?? ''); ?>
<?php if ($token !== ''): ?>
  <p style="text-align:center;margin:24px 0 8px">
    <a href="<?= e(url('/inscripcio/dorsal/' . $token)) ?>"
       style="display:inline-block;background:<?= e(setting('color_primary', '#2f6b3c')) ?>;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700">
      Descarregar el dorsal (PDF)
    </a>
  </p>
  <?php if (($siblings ?? 1) > 1): ?>
    <p style="text-align:center;margin:0 0 8px;font-size:14px">
      <a href="<?= e(url('/inscripcio/dorsals/' . $token)) ?>" style="color:<?= e(setting('color_primary', '#2f6b3c')) ?>">
        Descarregar els <?= (int) $siblings ?> dorsals d'aquest correu en un sol PDF
      </a>
    </p>
  <?php endif; ?>
  <p style="font-size:13px;color:#5a6b60;text-align:center;margin:0 0 8px">
    Imprimiu-lo i porteu-lo posat el dia de la cursa.
  </p>
<?php endif; ?>

<p style="margin:18px 0 0;font-size:14px">
  El dia de la cursa, passeu per la carpa de l'AFA 15 minuts abans de la vostra sortida.
  Si voleu quedar-vos a l'esmorzar popular, en trobareu tota la informació
  <a href="<?= e(url('/esmorzar')) ?>" style="color:<?= e(setting('color_primary', '#2f6b3c')) ?>">en aquesta pàgina</a>.
</p>
