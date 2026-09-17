<?php /** Plantilla base dels correus. */ ?>
<!doctype html>
<html lang="ca">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($subject ?? '') ?></title></head>
<body style="margin:0;padding:0;background:#f2f7ef;font-family:'Segoe UI',system-ui,Arial,sans-serif;color:#17261c">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f7ef;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(18,48,28,.08)">
      <tr>
        <td style="background:<?= e(setting('color_primary', '#2f6b3c')) ?>;padding:24px 28px;color:#ffffff">
          <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.85"><?= e(setting('event_town', 'La Granada')) ?></div>
          <div style="font-size:20px;font-weight:700"><?= e(setting('site_name', 'Cros Escolar La Granada')) ?></div>
        </td>
      </tr>
      <tr><td style="padding:28px 28px 8px;font-size:15px;line-height:1.6"><?= $content ?></td></tr>
      <tr>
        <td style="padding:18px 28px 26px;font-size:12px;line-height:1.6;color:#5a6b60;border-top:1px solid #e3eade">
          <?= e(setting('organizer', '')) ?> · <?= e(ucfirst(ca_date(setting('event_date', ''), true))) ?><br>
          <?= e(setting('event_place', '')) ?> — <?= e(setting('event_address', '')) ?><br>
          <a href="<?= e(url('/')) ?>" style="color:<?= e(setting('color_primary', '#2f6b3c')) ?>"><?= e(preg_replace('#^https?://#', '', base_url())) ?></a>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
