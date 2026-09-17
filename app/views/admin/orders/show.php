<?php
/** Detall d'una comanda. */
use Cros\Core\Icons;
$labels = ['paid' => 'Pagada', 'pending' => 'Pendent', 'cancelled' => 'Cancel·lada', 'refunded' => 'Retornada'];
$badges = ['paid' => 'badge--green', 'pending' => 'badge--amber', 'cancelled' => 'badge--red', 'refunded' => 'badge--blue'];
$ticketLabels = ['valid' => 'Vàlid', 'used' => 'Validat', 'void' => 'Anul·lat'];
?>
<div class="flex-between mb-2">
  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/comandes')) ?>">← Totes les comandes</a>
  <span class="badge <?= e($badges[$order['status']] ?? '') ?>"><?= e($labels[$order['status']] ?? $order['status']) ?></span>
</div>

<div class="grid-cards" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr))">
  <div class="panel">
    <div class="panel__head"><h2>Comanda <?= e($order['code']) ?></h2></div>
    <div class="panel__body">
      <table class="admin-table">
        <tbody>
          <tr><th>Comprador</th><td><?= e($order['name']) ?></td></tr>
          <tr><th>Correu</th><td><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></td></tr>
          <tr><th>Telèfon</th><td><?= e($order['phone'] ?: '—') ?></td></tr>
          <tr><th>Creada</th><td><?= e(dt($order['created_at'])) ?></td></tr>
          <tr><th>Pagament</th><td><?= e($order['paid_at'] ? dt($order['paid_at']) : '—') ?> (<?= e($order['payment_method']) ?>)</td></tr>
          <?php if ($order['stripe_payment_intent']): ?>
            <tr><th>Stripe</th><td class="mono" style="font-size:.8rem"><?= e($order['stripe_payment_intent']) ?></td></tr>
          <?php endif; ?>
          <?php if ($order['notes']): ?><tr><th>Notes</th><td><?= nl($order['notes']) ?></td></tr><?php endif; ?>
          <tr><th>Enllaç públic</th><td><a href="<?= e(url('/tiquets/' . $order['token'])) ?>" target="_blank">Veure tiquets</a></td></tr>
        </tbody>
      </table>

      <h3 class="mt-3">Articles</h3>
      <table class="admin-table">
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr><td><?= (int) $item['qty'] ?> × <?= e($item['name']) ?></td>
                <td class="text-right"><?= e(money((int) $item['subtotal_cents'], (string) $order['currency'])) ?></td></tr>
          <?php endforeach; ?>
          <tr><th>Total</th><th class="text-right"><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></th></tr>
          <?php if ((int) $order['refunded_cents'] > 0): ?>
            <tr><td>Retornat</td><td class="text-right"><?= e(money((int) $order['refunded_cents'], (string) $order['currency'])) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head"><h2>Tiquets (<?= count($tickets) ?>)</h2></div>
    <div class="panel__body">
      <?php if (!$tickets): ?>
        <p class="text-soft">Encara no s'han generat tiquets (la comanda no consta pagada).</p>
      <?php else: ?>
        <table class="admin-table">
          <tbody>
            <?php foreach ($tickets as $ticket): ?>
              <tr>
                <td class="mono"><?= e($ticket['code']) ?></td>
                <td><?= e($ticket['type_name'] ?? '—') ?></td>
                <td>
                  <span class="badge <?= $ticket['status'] === 'valid' ? 'badge--green' : ($ticket['status'] === 'used' ? '' : 'badge--red') ?>">
                    <?= e($ticketLabels[$ticket['status']] ?? $ticket['status']) ?>
                  </span>
                  <?php if ($ticket['used_at']): ?><div class="text-soft" style="font-size:.8rem"><?= e(dt($ticket['used_at'])) ?></div><?php endif; ?>
                </td>
                <td class="actions">
                  <?php if ($ticket['status'] === 'used'): ?>
                    <form method="post" action="<?= e(url('/admin/tiquets/' . $ticket['id'] . '/restablir')) ?>" style="display:inline">
                      <?= csrf_field() ?>
                      <button class="btn btn--ghost btn--sm" type="submit">Restablir</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Accions</h2></div>
  <div class="panel__body flex">
    <?php if ($order['status'] !== 'paid'): ?>
      <form method="post" action="<?= e(url('/admin/comandes/' . $order['id'] . '/pagada')) ?>" data-confirm="Marcar la comanda com a pagada i enviar els tiquets?">
        <?= csrf_field() ?>
        <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Marcar com a pagada</button>
      </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/comandes/' . $order['id'] . '/reenviar')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--ghost" type="submit"><?= Icons::svg('mail', 'icon', 16) ?> Reenviar el correu</button>
    </form>
    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'refunded'): ?>
      <form method="post" action="<?= e(url('/admin/comandes/' . $order['id'] . '/cancellar')) ?>" data-confirm="Cancel·lar la comanda i anul·lar-ne els tiquets?">
        <?= csrf_field() ?>
        <button class="btn btn--ghost" type="submit">Cancel·lar</button>
      </form>
      <?php if ($order['status'] === 'paid' && $order['stripe_payment_intent']): ?>
        <form method="post" action="<?= e(url('/admin/comandes/' . $order['id'] . '/cancellar')) ?>" data-confirm="Fer la devolució de l'import a través de Stripe?">
          <?= csrf_field() ?>
          <input type="hidden" name="refund" value="1">
          <button class="btn btn--danger" type="submit"><?= Icons::svg('euro', 'icon', 16) ?> Devolució per Stripe</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (\Cros\Core\Auth::isAdmin()): ?>
      <form method="post" action="<?= e(url('/admin/comandes/' . $order['id'] . '/esborrar')) ?>" data-confirm="Esborrar definitivament la comanda i els seus tiquets?" style="margin-left:auto">
        <?= csrf_field() ?>
        <button class="btn btn--danger" type="submit"><?= Icons::svg('trash', 'icon', 16) ?> Esborrar</button>
      </form>
    <?php endif; ?>
  </div>
</div>
