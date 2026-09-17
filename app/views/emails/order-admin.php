<?php /** Avís intern de nova comanda. */ ?>
<h1 style="font-size:18px;margin:0 0 12px;color:#1b452a">Nova comanda pagada</h1>
<table role="presentation" width="100%" style="font-size:14px">
  <tr><td style="padding:3px 0;color:#5a6b60">Codi</td><td><strong><?= e($order['code']) ?></strong></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Nom</td><td><?= e($order['name']) ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Correu</td><td><?= e($order['email']) ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Telèfon</td><td><?= e($order['phone'] ?? '') ?></td></tr>
  <tr><td style="padding:3px 0;color:#5a6b60">Total</td><td><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></td></tr>
  <?php if (!empty($order['notes'])): ?>
    <tr><td style="padding:3px 0;color:#5a6b60">Notes</td><td><?= nl2br(e($order['notes'])) ?></td></tr>
  <?php endif; ?>
</table>
<ul style="font-size:14px">
  <?php foreach ($items as $item): ?>
    <li><?= (int) $item['qty'] ?> × <?= e($item['name']) ?></li>
  <?php endforeach; ?>
</ul>
<p><a href="<?= e(url('/admin/comandes/' . $order['id'])) ?>">Obrir al panell</a></p>
