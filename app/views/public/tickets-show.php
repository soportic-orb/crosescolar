<?php
/** Tiquets d'una comanda (accés per enllaç privat). */
$statusLabels = ['valid' => 'Vàlid', 'used' => 'Validat', 'void' => 'Anul·lat'];
?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => 'Comanda ' . $order['code'],
    'subtitle' => 'Presenta el codi QR de cada tiquet a la carpa de l\'AFA.',
    'breadcrumb' => ['Els meus tiquets' => '/els-meus-tiquets', $order['code'] => ''],
]) ?>

<section class="section">
  <div class="container">
    <?php if ($order['status'] === 'pending'): ?>
      <div class="alert alert--warning">
        <span>Aquesta comanda encara consta com a <strong>pendent de pagament</strong>. Si acabes de pagar, actualitza la pàgina d'aquí a un minut.</span>
      </div>
    <?php elseif ($order['status'] === 'cancelled'): ?>
      <div class="alert alert--error"><span>Aquesta comanda està cancel·lada.</span></div>
    <?php elseif ($order['status'] === 'refunded'): ?>
      <div class="alert alert--info"><span>L'import d'aquesta comanda s'ha retornat.</span></div>
    <?php endif; ?>

    <div class="split">
      <div>
        <div class="flex-between" style="margin-bottom:1.2rem">
          <h2 style="margin:0">Els teus tiquets (<?= count($tickets) ?>)</h2>
          <?php if ($tickets): ?>
            <a class="btn btn--ghost btn--sm no-print" href="<?= e(url('/tiquets/' . $order['token'] . '/imprimir')) ?>" target="_blank">
              <?= \Cros\Core\Icons::svg('download', 'icon', 16) ?> Imprimir o desar en PDF
            </a>
          <?php endif; ?>
        </div>

        <?php if (!$tickets): ?>
          <div class="notice-box">Els tiquets es generaran automàticament quan es confirmi el pagament.</div>
        <?php else: ?>
          <div class="ticket-list">
            <?php foreach ($tickets as $index => $ticket): ?>
              <div class="ticket-card<?= $ticket['status'] !== 'valid' ? ' ticket-card--used' : '' ?>">
                <img class="ticket-card__qr" src="<?= e(url('/qr/' . $ticket['code'])) ?>" alt="Codi QR del tiquet <?= e($ticket['code']) ?>" loading="lazy">
                <div>
                  <strong><?= e($ticket['type_name'] ?? 'Tiquet') ?></strong>
                  <div class="ticket-card__code"><?= e($ticket['code']) ?></div>
                  <div style="margin-top:.4rem">
                    <span class="ticket-status ticket-status--<?= e($ticket['status']) ?>">
                      <?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?>
                      <?= $ticket['used_at'] ? ' · ' . e(dt($ticket['used_at'], 'd/m H:i')) : '' ?>
                    </span>
                  </div>
                  <?php if (!empty($ticket['type_description'])): ?>
                    <div class="field__hint" style="margin-top:.4rem"><?= e($ticket['type_description']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside>
        <div class="card">
          <h3>Detalls de la comanda</h3>
          <table class="data" style="min-width:0">
            <tbody>
              <tr><th>Codi</th><td class="mono"><?= e($order['code']) ?></td></tr>
              <tr><th>Nom</th><td><?= e($order['name']) ?></td></tr>
              <tr><th>Correu</th><td><?= e($order['email']) ?></td></tr>
              <tr><th>Data</th><td><?= e(dt($order['created_at'])) ?></td></tr>
              <?php foreach ($items as $item): ?>
                <tr><th><?= (int) $item['qty'] ?> × <?= e($item['name']) ?></th><td><?= e(money((int) $item['subtotal_cents'], (string) $order['currency'])) ?></td></tr>
              <?php endforeach; ?>
              <tr><th>Total</th><td><strong><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></strong></td></tr>
            </tbody>
          </table>
        </div>
        <?php if (setting('tickets_info', '')): ?>
          <div class="card" style="margin-top:1.2rem">
            <h3><?= \Cros\Core\Icons::svg('coffee', 'icon', 20) ?> Com funciona</h3>
            <div style="font-size:.95rem;color:var(--ink-soft)"><?= setting_html('tickets_info') ?></div>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</section>
