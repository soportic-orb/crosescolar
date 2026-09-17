<?php
/** Llistat de comandes. */
use Cros\Core\Icons;
$badges = ['paid' => 'badge--green', 'pending' => 'badge--amber', 'cancelled' => 'badge--red', 'refunded' => 'badge--blue'];
$labels = ['paid' => 'Pagada', 'pending' => 'Pendent', 'cancelled' => 'Cancel·lada', 'refunded' => 'Retornada'];
?>
<div class="grid-cards mb-2">
  <div class="kpi"><div class="kpi__label">Recaptat</div><div class="kpi__value"><?= e(money((int) $stats['revenue_cents'])) ?></div></div>
  <div class="kpi"><div class="kpi__label">Comandes pagades</div><div class="kpi__value"><?= (int) $stats['orders_paid'] ?></div></div>
  <div class="kpi"><div class="kpi__label">Pendents</div><div class="kpi__value"><?= (int) $stats['orders_pending'] ?></div></div>
  <div class="kpi"><div class="kpi__label">Tiquets</div><div class="kpi__value"><?= (int) $stats['tickets_total'] ?></div><div class="kpi__foot"><?= (int) $stats['tickets_used'] ?> validats</div></div>
</div>

<div class="panel">
  <div class="panel__head">
    <h2>Comandes (<?= (int) $total ?>)</h2>
    <div class="spacer"></div>
    <form method="get" class="filters">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Codi, nom o correu…">
      <select name="estat">
        <option value="">Tots els estats</option>
        <?php foreach ($labels as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--sm" type="submit">Filtrar</button>
    </form>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/comandes/exportar')) ?>"><?= Icons::svg('download', 'icon', 15) ?> CSV</a>
    <a class="btn btn--sm" href="<?= e(url('/admin/comandes/nova')) ?>"><?= Icons::svg('plus', 'icon', 15) ?> Venda manual</a>
  </div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <thead><tr><th>Codi</th><th>Comprador</th><th>Import</th><th>Estat</th><th>Mètode</th><th>Data</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td class="mono"><a href="<?= e(url('/admin/comandes/' . $order['id'])) ?>"><?= e($order['code']) ?></a></td>
            <td><?= e($order['name']) ?><div class="text-soft" style="font-size:.84rem"><?= e($order['email']) ?></div></td>
            <td><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></td>
            <td><span class="badge <?= e($badges[$order['status']] ?? '') ?>"><?= e($labels[$order['status']] ?? $order['status']) ?></span></td>
            <td class="text-soft"><?= e($order['payment_method']) ?></td>
            <td class="text-soft" style="font-size:.85rem"><?= e(dt($order['created_at'])) ?></td>
            <td class="actions"><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/comandes/' . $order['id'])) ?>">Veure</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7" class="text-soft">Cap comanda amb aquests filtres.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="is-active"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= e(url('/admin/comandes', array_filter(['p' => $i, 'q' => $search, 'estat' => $status]))) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
