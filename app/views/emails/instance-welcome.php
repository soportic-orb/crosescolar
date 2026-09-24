<?php /** Les claus del web acabat de crear. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Ja teniu el web del vostre cros</h1>
<p style="margin:0 0 16px">Hola, <?= e(explode(' ', trim((string) ($name ?? '')))[0] ?: 'bon dia') ?>,</p>
<p style="margin:0 0 16px">
  El web de <strong><?= e($site_name ?? '') ?></strong> ja està creat i us n'hem fet administrador.
  De moment només el veieu vosaltres: quan el tingueu a punt, el publiqueu des del panell.
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:4px 0;width:35%">Adreça del web</td><td><a href="<?= e($url ?? '') ?>"><?= e($url ?? '') ?></a></td></tr>
      <tr><td style="color:#5a6b60;padding:4px 0">Panell de gestió</td><td><a href="<?= e(($url ?? '') . '/admin') ?>"><?= e(($url ?? '') . '/admin') ?></a></td></tr>
      <tr><td style="color:#5a6b60;padding:4px 0">El vostre usuari</td><td><?= e($email ?? '') ?></td></tr>
    </table>
  </td></tr>
</table>
<?php if (!empty($link)): ?>
  <p style="margin:20px 0;text-align:center">
    <a href="<?= e($link) ?>" style="display:inline-block;background:#2f6b3c;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:700">
      Entrar i triar la contrasenya
    </a>
  </p>
  <p style="margin:0 0 16px;font-size:13px;color:#5a6b60">
    Aquest enllaç serveix una sola vegada i val set dies. En prémer-lo entrareu al panell i
    us podreu posar la contrasenya que vulgueu. Si caduca, demaneu-nos-en un altre.
  </p>
<?php endif; ?>
<p style="margin:12px 0 0;font-size:14px">
  Per començar, ompliu les dades de la cursa (dia, hora, lloc i categories) a «Configuració»
  i obriu les inscripcions quan vulgueu.
</p>
