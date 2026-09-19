<?php /** Versió imprimible dels tiquets. */ ?>
<div class="print-actions">
  <button type="button" onclick="window.print()">Imprimir</button>
  <a href="<?= e(url('/tiquets/' . $order['token'])) ?>">Tornar</a>
</div>

<div class="head">
  <h1><?= e(setting('site_name', 'Cros Escolar La Granada')) ?> — tiquets del punt de recàrrega</h1>
  <p>
    Comanda <strong><?= e($order['code']) ?></strong> · <?= e($order['name']) ?> ·
    <?= e(ucfirst(ca_date(setting('event_date', ''), true))) ?> · <?= e(setting('event_place', '')) ?>
  </p>
</div>

<div class="tickets">
  <?php foreach ($tickets as $ticket): ?>
    <div class="tk">
      <img src="<?= e(\Cros\Core\Qr::dataUri(\Cros\Models\Ticket::qrPayload($ticket), 5, 2)) ?>" alt="QR <?= e($ticket['code']) ?>">
      <div>
        <h2><?= e($ticket['type_name'] ?? 'Tiquet') ?></h2>
        <div class="code"><?= e($ticket['code']) ?></div>
        <small>
          <?= e($ticket['type_description'] ?? '') ?><br>
          Comanda <?= e($order['code']) ?> · <?= e($order['name']) ?><br>
          <?= $ticket['status'] === 'used' ? 'JA VALIDAT el ' . e(dt($ticket['used_at'])) : 'Presenteu aquest codi a la carpa de l\'AFA' ?>
        </small>
      </div>
    </div>
  <?php endforeach; ?>
</div>
