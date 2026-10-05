<?php /** Avís intern: ha arribat un missatge pel formulari de contacte. */ ?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">Missatge nou des de <?= e($domain ?? 'la web') ?></h1>
<table role="presentation" width="100%" style="font-size:14px">
  <tr><td style="padding:3px 0;color:#5a6b60;width:38%">Nom</td><td><strong><?= e($contact['name']) ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Entitat</td><td><?= e($contact['entity'] ?? '') !== '' ? e($contact['entity']) : '—' ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Correu</td><td><?= e($contact['email']) ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Telèfon</td><td><?= e($contact['phone'] ?? '') !== '' ? e($contact['phone']) : '—' ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Novetats</td><td><?= !empty($contact['news']) ? 'Vol rebre\'n' : 'No n\'ha demanat' ?></td></tr>
</table>
<p style="margin:14px 0 0;padding:12px 14px;background:#f4f7f3;border-radius:10px;font-size:14px;line-height:1.6"><?= nl2br(e($contact['message'])) ?></p>
<p style="margin:14px 0 0;font-size:13px;color:#5a6b60">Si responeu aquest correu, la resposta li arriba directament.</p>
<p style="text-align:center;margin:22px 0 8px">
  <a href="https://admin.<?= e($domain ?? '') ?>/contacte/<?= (int) $contact['id'] ?>" style="display:inline-block;background:#2f6b3c;color:#fff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:700">Obrir-lo al panell</a>
</p>
