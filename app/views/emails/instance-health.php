<?php /** Avís de la vigilància de les instàncies. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:<?= !empty($recovered) ? '#1b452a' : '#a5401d' ?>">
  <?= !empty($recovered) ? 'Torna a funcionar' : 'Un web no respon' ?>
</h1>
<p style="margin:0 0 16px">
  <?php if (!empty($recovered)): ?>
    El web de <strong><?= e($instance['site_name']) ?></strong> torna a estar en marxa.
  <?php else: ?>
    El web de <strong><?= e($instance['site_name']) ?></strong> no respon.
  <?php endif; ?>
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:4px 0;width:35%">Adreça</td><td><a href="<?= e($url ?? '') ?>"><?= e($url ?? '') ?></a></td></tr>
      <tr><td style="color:#5a6b60;padding:4px 0">Client</td><td><?= e($instance['site_name']) ?><?= !empty($instance['town']) ? ' · ' . e($instance['town']) : '' ?></td></tr>
      <?php if (!empty($instance['admin_email'])): ?>
        <tr><td style="color:#5a6b60;padding:4px 0">Qui el gestiona</td><td><?= e($instance['admin_email']) ?></td></tr>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <tr><td style="color:#5a6b60;padding:4px 0">Què passa</td><td><?= e($error) ?></td></tr>
      <?php endif; ?>
      <tr><td style="color:#5a6b60;padding:4px 0">Hora</td><td><?= e(date('d/m/Y H:i')) ?></td></tr>
    </table>
  </td></tr>
</table>
<?php if (empty($recovered)): ?>
  <p style="margin:16px 0 0;font-size:14px">
    Si la cursa és aviat, val més mirar-s'ho de seguida. Quan torni a respondre, rebreu un altre avís.
  </p>
<?php endif; ?>
