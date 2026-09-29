<?php
/**
 * Plantilla base dels correus.
 *
 * La fan servir tant els cros com la plataforma, i el que se sap de cadascun
 * no és el mateix: un cros té població, data i lloc de la cursa, i la
 * plataforma no té res de tot això. Per això cada línia del peu només surt si
 * hi ha què dir-hi; si no, el correu quedaria amb dades inventades.
 */
$eyebrow = trim((string) setting('event_town', ''));
$date = trim((string) setting('event_date', ''));
$place = trim((string) setting('event_place', ''));
$address = trim((string) setting('event_address', ''));
$organizer = trim((string) setting('organizer', ''));
$primary = (string) setting('color_primary', '#2f6b3c');
$foot = array_values(array_filter([
    trim($organizer . ($organizer !== '' && $date !== '' ? ' · ' : '') . ($date !== '' ? ucfirst(ca_date($date, true)) : '')),
    trim($place . ($place !== '' && $address !== '' ? ' — ' : '') . $address),
]));
?>
<!doctype html>
<html lang="ca">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($subject ?? '') ?></title></head>
<body style="margin:0;padding:0;background:#f2f7ef;font-family:'Segoe UI',system-ui,Arial,sans-serif;color:#17261c">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f7ef;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(18,48,28,.08)">
      <tr>
        <td style="background:<?= e($primary) ?>;padding:24px 28px;color:#ffffff">
          <?php if ($eyebrow !== ''): ?>
            <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.85"><?= e($eyebrow) ?></div>
          <?php endif; ?>
          <div style="font-size:20px;font-weight:700"><?= e(setting('site_name', 'Cros Escolar')) ?></div>
        </td>
      </tr>
      <tr><td style="padding:28px 28px 8px;font-size:15px;line-height:1.6"><?= $content ?></td></tr>
      <tr>
        <td style="padding:18px 28px 26px;font-size:12px;line-height:1.6;color:#5a6b60;border-top:1px solid #e3eade">
          <?php foreach ($foot as $line): ?><?= e($line) ?><br><?php endforeach; ?>
          <a href="<?= e(url('/')) ?>" style="color:<?= e($primary) ?>"><?= e(preg_replace('#^https?://#', '', base_url())) ?></a>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
