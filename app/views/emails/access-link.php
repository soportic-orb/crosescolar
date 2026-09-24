<?php /** Enllaç per tornar a entrar al panell d'un cros. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Enllaç per entrar al panell</h1>
<p style="margin:0 0 16px">Hola,</p>
<p style="margin:0 0 16px">
  Aquí teniu un enllaç per entrar al panell de <strong><?= e($site_name ?? '') ?></strong>
  i posar-vos una contrasenya nova.
</p>
<p style="margin:20px 0;text-align:center">
  <a href="<?= e($link ?? '') ?>" style="display:inline-block;background:#2f6b3c;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:700">
    Entrar al panell
  </a>
</p>
<p style="margin:0 0 16px;font-size:13px;color:#5a6b60">
  Serveix una sola vegada i caduca en un parell d'hores. Si no l'heu demanat vosaltres,
  no cal que feu res: sense prémer-lo, no serveix de res.
</p>
