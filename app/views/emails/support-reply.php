<?php
/**
 * La resposta del suport, tal com la rep el client.
 * @var array $ticket
 * @var string $body
 * @var string $link  adreça del tiquet al panell del seu web
 */
?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">Resposta a la consulta <?= e($ticket['reference'] ?? '') ?></h1>
<p style="margin:0 0 14px;font-size:14px;color:#5a6b60"><strong><?= e($ticket['subject'] ?? '') ?></strong></p>
<div style="font-size:15px;line-height:1.6"><?= $body ?></div>
<?php if (($link ?? '') !== ''): ?>
  <p style="text-align:center;margin:24px 0 8px">
    <a href="<?= e($link) ?>" style="display:inline-block;background:#2f6b3c;color:#fff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:700">Veure la conversa</a>
  </p>
  <p style="text-align:center;margin:0;font-size:13px;color:#5a6b60">Podeu contestar-hi des del vostre panell, a Suport.</p>
<?php endif; ?>
