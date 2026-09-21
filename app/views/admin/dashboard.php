<?php
/** Tauler del panell. */
use Cros\Core\Icons;
$currency = (string) setting('payments_currency', 'EUR');
?>
<div class="grid-cards">
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('euro', 'icon', 16) ?> Recaptat</div>
    <div class="kpi__value"><?= e(money((int) $stats['revenue_cents'], $currency)) ?></div>
    <div class="kpi__foot"><?= (int) $stats['orders_paid'] ?> comandes pagades</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('ticket', 'icon', 16) ?> Tiquets venuts</div>
    <div class="kpi__value"><?= (int) $stats['tickets_total'] ?></div>
    <div class="kpi__foot"><?= (int) $stats['tickets_used'] ?> validats el dia de la cursa</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('users', 'icon', 16) ?> Inscripcions</div>
    <div class="kpi__value"><?= (int) $stats['registrations'] ?></div>
    <div class="kpi__foot"><?= (int) $counts['categories'] ?> categories actives</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('calendar', 'icon', 16) ?> Compte enrere</div>
    <div class="kpi__value"><?= $daysLeft === null ? '—' : ($daysLeft > 0 ? $daysLeft . ' dies' : ($daysLeft === 0 ? 'Avui!' : 'Finalitzat')) ?></div>
    <div class="kpi__foot"><?= e(ucfirst(ca_date(setting('event_date', ''), true))) ?></div>
  </div>
</div>

<?php if (\Cros\Core\Settings::bool('coming_soon')): ?>
  <div class="alert alert--warning mt-2 flex-between">
    <span>
      <strong>El web està amagat al públic.</strong>
      Els visitants veuen l'avís «<?= e(setting('coming_soon_title', 'Aviat publicarem el web')) ?>».
      <a href="<?= e(url('/admin/configuracio/coming_soon')) ?>">Editar l'avís</a>
    </span>
    <form method="post" action="<?= e(url('/admin/properament')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="enable" value="0">
      <button class="btn btn--sm" type="submit">Publicar el web ara</button>
    </form>
  </div>
<?php endif; ?>

<?php if (!$stripeReady): ?>
  <div class="alert alert--warning mt-2">
    Encara no heu configurat les credencials de Stripe: la venda en línia està desactivada.
    <a href="<?= e(url('/admin/configuracio/payments')) ?>">Configurar-les ara</a>.
  </div>
<?php elseif ($stripeMode === 'test'): ?>
  <div class="alert alert--info mt-2">
    Stripe està en <strong>mode de proves</strong>. Quan tot estigui a punt, canvieu-ho a producció a
    <a href="<?= e(url('/admin/configuracio/payments')) ?>">Pagaments</a>.
  </div>
<?php endif; ?>
<?php if (!$salesOpen): ?>
  <div class="alert alert--info mt-2">La venda de tiquets està tancada (per configuració o per data límit).</div>
<?php endif; ?>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Tiquets per tipus</h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/contingut/tipus-tiquet')) ?>">Gestionar</a>
  </div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <thead><tr><th>Tiquet</th><th>Preu</th><th>Venuts</th><th>Existències</th><th>Estat</th></tr></thead>
      <tbody>
        <?php foreach ($ticketTypes as $type): ?>
          <tr>
            <td><strong><?= e($type['name']) ?></strong></td>
            <td><?= e(money((int) $type['price_cents'], $currency)) ?></td>
            <td><?= (int) $type['sold'] ?></td>
            <td><?= $type['stock'] === null ? '<span class="text-soft">Sense límit</span>' : (int) $type['available'] . ' / ' . (int) $type['stock'] ?></td>
            <td><?= (int) $type['active'] === 1 ? '<span class="badge badge--green">A la venda</span>' : '<span class="badge">Aturat</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ticketTypes): ?>
          <tr><td colspan="5" class="text-soft">Encara no heu creat cap tipus de tiquet.
            <a href="<?= e(url('/admin/contingut/tipus-tiquet/nou')) ?>">Crear-ne un</a>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-cards mt-2" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr))">
  <div class="panel">
    <div class="panel__head">
      <h2>Últimes comandes</h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/comandes')) ?>">Totes</a>
    </div>
    <div class="panel__body table-wrap">
      <table class="admin-table">
        <tbody>
          <?php foreach ($recentOrders as $order): ?>
            <tr>
              <td><a class="mono" href="<?= e(url('/admin/comandes/' . $order['id'])) ?>"><?= e($order['code']) ?></a></td>
              <td><?= e($order['name']) ?></td>
              <td><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></td>
              <td>
                <?php
                $badges = ['paid' => 'badge--green', 'pending' => 'badge--amber', 'cancelled' => 'badge--red', 'refunded' => 'badge--blue'];
                $labels = ['paid' => 'Pagada', 'pending' => 'Pendent', 'cancelled' => 'Cancel·lada', 'refunded' => 'Retornada'];
                ?>
                <span class="badge <?= e($badges[$order['status']] ?? '') ?>"><?= e($labels[$order['status']] ?? $order['status']) ?></span>
              </td>
              <td class="text-soft" style="font-size:.85rem"><?= e(dt($order['created_at'], 'd/m H:i')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentOrders): ?><tr><td class="text-soft">Encara no hi ha comandes.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <h2>Últimes inscripcions</h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/inscripcions')) ?>">Totes</a>
    </div>
    <div class="panel__body table-wrap">
      <table class="admin-table">
        <tbody>
          <?php foreach ($recentRegistrations as $registration): ?>
            <tr>
              <td><a href="<?= e(url('/admin/inscripcions/' . $registration['id'])) ?>"><?= e($registration['first_name'] . ' ' . $registration['last_name']) ?></a>
                <?php if (\Cros\Models\Registration::isCancelled($registration)): ?>
                  <span class="badge badge--red">Anul·lada</span>
                <?php endif; ?>
              </td>
              <td><?= e($registration['category_name'] ?? '—') ?></td>
              <td class="text-soft" style="font-size:.85rem"><?= e(dt($registration['created_at'], 'd/m H:i')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentRegistrations): ?><tr><td class="text-soft">Encara no hi ha inscripcions.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Accions ràpides</h2></div>
  <div class="panel__body flex">
    <a class="btn btn--ghost" href="<?= e(url('/admin/configuracio/home')) ?>"><?= Icons::svg('image', 'icon', 16) ?> Canviar el banner</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/contingut/patrocinadors/nou')) ?>"><?= Icons::svg('heart', 'icon', 16) ?> Afegir patrocinador</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/contingut/recorreguts/nou')) ?>"><?= Icons::svg('map', 'icon', 16) ?> Afegir recorregut</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/comandes/nova')) ?>"><?= Icons::svg('plus', 'icon', 16) ?> Venda manual</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/resultats')) ?>"><?= Icons::svg('trophy', 'icon', 16) ?> Resultats de la cursa</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/inscripcions/dorsals')) ?>"><?= Icons::svg('flag', 'icon', 16) ?> Dorsals en PDF</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/validacio')) ?>"><?= Icons::svg('qr', 'icon', 16) ?> Validar tiquets</a>
  </div>
</div>
