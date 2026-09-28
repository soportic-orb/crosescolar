<?php /** Avís que el certificat del servidor s'acaba. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:<?= (int) ($days ?? 0) <= 3 ? '#a5401d' : '#8a6d1f' ?>">
  <?= (int) ($days ?? 0) <= 0 ? 'El certificat ha caducat' : 'El certificat s\'acaba' ?>
</h1>
<p style="margin:0 0 16px">
  <?php if ((int) ($days ?? 0) <= 0): ?>
    El certificat de <strong><?= e($host ?? '') ?></strong> ha caducat: qui entri als webs
    veurà un avís de seguretat del navegador i molta gent no hi passarà.
  <?php else: ?>
    El certificat de <strong><?= e($host ?? '') ?></strong> caduca d'aquí a
    <strong><?= (int) ($days ?? 0) ?> <?= (int) ($days ?? 0) === 1 ? 'dia' : 'dies' ?></strong>.
    Quan caduqui, els navegadors deixaran de deixar entrar als webs sense un avís de seguretat.
  <?php endif; ?>
</p>
<table role="presentation" width="100%" style="font-size:14px;border:1px solid #e3eade;border-radius:12px">
  <tr><td style="padding:14px 16px">
    <table role="presentation" width="100%" style="font-size:14px">
      <tr><td style="color:#5a6b60;padding:4px 0;width:35%">Caduca</td><td><?= e(date('d/m/Y H:i', strtotime((string) ($expires ?? 'now')))) ?></td></tr>
      <tr><td style="color:#5a6b60;padding:4px 0">Servidor</td><td><?= e($host ?? '') ?></td></tr>
    </table>
  </td></tr>
</table>
<p style="margin:16px 0 8px;font-size:14px">
  Per renovar-lo, entreu al servidor per SSH com a root i executeu:
</p>
<p style="margin:0;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;background:#f4f7f2;border:1px solid #e3eade;border-radius:10px;padding:12px 14px;word-break:break-all">
  <?= e($command ?? '') ?>
</p>
<p style="margin:16px 0 0;font-size:13px;color:#5a6b60">
  Aquest avís s'envia als 21, 7, 3 i 1 dies. Quan el certificat estigui renovat, deixaran d'arribar.
</p>
