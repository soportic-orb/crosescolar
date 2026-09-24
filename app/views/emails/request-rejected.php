<?php /** Resposta quan una sol·licitud no tira endavant. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Sobre la vostra sol·licitud</h1>
<p style="margin:0 0 16px">Hola, <?= e(explode(' ', trim((string) ($name ?? '')))[0] ?: 'bon dia') ?>,</p>
<p style="margin:0 0 16px">
  Hem mirat la sol·licitud <strong><?= e($code ?? '') ?></strong> del web del cros de
  <strong><?= e($entity ?? '') ?></strong> i de moment no la podem tirar endavant.
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px"><?= nl2br(e($reason ?? '')) ?></td></tr>
</table>
<p style="margin:16px 0 0;font-size:14px">
  Si creieu que hi ha hagut un malentès o voleu tornar-ho a plantejar, escriviu-nos a
  <a href="mailto:<?= e($contact ?? '') ?>"><?= e($contact ?? '') ?></a> i ho mirem.
</p>
