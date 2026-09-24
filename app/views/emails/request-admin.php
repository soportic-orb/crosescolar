<?php /** Avís intern: ha entrat una sol·licitud nova. */ ?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">Nova sol·licitud</h1>
<table role="presentation" width="100%" style="font-size:14px">
  <tr><td style="padding:3px 0;color:#5a6b60;width:38%">Número</td><td><strong><?= e($request['code']) ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Entitat</td><td><strong><?= e($request['entity']) ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">NIF</td><td><?= e($request['nif'] ?? '—') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Població</td><td><?= e($request['town'] ?? '—') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Contacte</td><td><?= e($request['contact_name']) ?><?= !empty($request['contact_role']) ? ' · ' . e($request['contact_role']) : '' ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Correu</td><td><?= e($request['contact_email']) ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Telèfon</td><td><?= e($request['contact_phone'] ?? '—') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Subdomini</td><td><?= e(($request['slug'] ?? '') !== '' ? $request['slug'] . '.' . ($domain ?? '') : 'no n\'ha demanat cap') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Idioma</td><td><?= e(($request['language'] ?? 'ca') === 'es' ? 'Castellà' : 'Català') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Cursa</td><td><?= !empty($request['event_date']) ? e(ca_date($request['event_date'], true)) : '—' ?><?= !empty($request['participants']) ? ' · ~' . (int) $request['participants'] . ' participants' : '' ?></td></tr>
</table>
<?php if (!empty($request['message'])): ?>
  <p style="margin:14px 0 0;padding:12px 14px;background:#f4f7f3;border-radius:10px;font-size:14px;line-height:1.6"><?= nl2br(e($request['message'])) ?></p>
<?php endif; ?>
<p style="text-align:center;margin:22px 0 8px">
  <a href="https://admin.<?= e($domain ?? '') ?>/sollicituds" style="display:inline-block;background:#2f6b3c;color:#fff;text-decoration:none;padding:12px 24px;border-radius:999px;font-weight:700">Obrir-la al panell</a>
</p>
<p style="text-align:center;margin:0;font-size:13px;color:#5a6b60">Pendents ara mateix: <?= (int) ($pending ?? 0) ?>.</p>
