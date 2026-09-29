<?php
/**
 * Avís intern: un client ha obert una consulta o n'ha respost una.
 * @var array $ticket
 * @var string $body
 * @var bool $isReply
 * @var string $link
 */
use Cros\Platform\Support;
?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">
  <?= $isReply ? 'Resposta del client' : 'Consulta nova' ?>
</h1>
<table role="presentation" width="100%" style="font-size:14px">
  <tr><td style="padding:3px 0;color:#5a6b60;width:34%">Número</td><td><strong><?= e($ticket['reference'] ?? '') ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Assumpte</td><td><strong><?= e($ticket['subject'] ?? '') ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Web</td><td><?= e(($ticket['site_name'] ?? '') !== '' ? $ticket['site_name'] : ($ticket['slug'] ?? '—')) ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Qui escriu</td><td><?= e(trim((string) ($ticket['author_name'] ?? ''))) ?> &lt;<?= e($ticket['author_email'] ?? '') ?>&gt;</td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Prioritat</td><td><?= e(Support::PRIORITIES[$ticket['priority'] ?? 'normal'] ?? 'Normal') ?></td></tr>
</table>
<div style="margin:14px 0 0;padding:12px 14px;background:#f4f7f3;border-radius:10px;font-size:14px;line-height:1.6"><?= $body ?></div>
<p style="text-align:center;margin:22px 0 8px">
  <a href="<?= e($link) ?>" style="display:inline-block;background:#2f6b3c;color:#fff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:700">Obrir-la al panell</a>
</p>
