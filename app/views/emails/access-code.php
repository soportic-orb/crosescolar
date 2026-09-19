<?php /** Codi d'un sol ús per entrar a «Les meves inscripcions». */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">El vostre codi d'accés</h1>
<p style="margin:0 0 16px">
  Escriviu aquest codi al web per veure i modificar
  <?= ($count ?? 1) > 1 ? 'les vostres ' . (int) $count . ' inscripcions' : 'la vostra inscripció' ?>
  al <?= e(setting('site_name', 'Cros Escolar La Granada')) ?>.
</p>
<table role="presentation" width="100%" style="border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:22px 16px;text-align:center">
    <div style="font-size:38px;font-weight:700;letter-spacing:.35em;color:<?= e(setting('color_primary', '#2f6b3c')) ?>">
      <?= e($code) ?>
    </div>
    <div style="font-size:13px;color:#5a6b60;margin-top:10px">
      Val <?= (int) ($minutes ?? 15) ?> minuts i només es pot fer servir un cop.
    </div>
  </td></tr>
</table>
<p style="text-align:center;margin:24px 0 8px">
  <a href="<?= e(url('/les-meves-inscripcions')) ?>"
     style="display:inline-block;background:<?= e(setting('color_primary', '#2f6b3c')) ?>;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700">
    Anar a «Les meves inscripcions»
  </a>
</p>
<p style="font-size:13px;color:#5a6b60;margin:16px 0 0">
  Si no heu demanat aquest codi, podeu oblidar-vos d'aquest correu: sense el codi ningú no pot entrar.
</p>
