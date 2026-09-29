<?php
/**
 * L'activació del web, al panell del client.
 * @var array<string,mixed> $status
 * @var array<int,array<string,mixed>> $payments
 * @var array<int,array<string,mixed>> $invoices
 */
use Cros\Core\Icons;
use Cros\Platform\Charge;

$applies = (bool) $status['applies'];
$paid = (bool) $status['paid'];
$due = (bool) $status['due'];
$hidden = \Cros\Core\Settings::bool('coming_soon');
?>
<div class="panel">
  <div class="panel__head"><h2><?= e($status['name'] ?: 'Activació del web') ?></h2></div>
  <div class="panel__body">

    <?php if (!$applies): ?>
      <div class="alert alert--success">
        El vostre web no té cap pagament pendent: el podeu publicar quan vulgueu, des de
        <a href="<?= e(url('/admin/configuracio/coming_soon')) ?>">Configuració → Web en preparació</a>.
      </div>
    <?php elseif ($paid): ?>
      <div class="alert alert--success">
        El web està <strong>activat</strong>. Ja el podeu publicar i despublicar les vegades que calgui,
        sense tornar a pagar res.
      </div>
    <?php else: ?>
      <div class="alert alert--info">
        Podeu preparar tot el cros —categories, recorreguts, dorsals, textos— sense pagar res.
        El pagament només cal el dia que vulgueu que el web es vegi al públic.
      </div>
    <?php endif; ?>

    <?php if ($applies && trim((string) $status['description']) !== ''): ?>
      <div class="prose"><?= $status['description'] ?></div>
    <?php endif; ?>

    <?php if ($applies && !$paid): ?>
      <div class="grid-cards mb-2" style="margin-top:1.2rem">
        <div class="kpi">
          <div class="kpi__label"><?= Icons::svg('euro', 'icon', 16) ?> Import</div>
          <div class="kpi__value"><?= e(money((int) $status['price'])) ?></div>
          <div class="kpi__foot">pagament únic, IVA inclòs</div>
        </div>
        <div class="kpi">
          <div class="kpi__label"><?= Icons::svg('eye', 'icon', 16) ?> Estat del web</div>
          <div class="kpi__value" style="font-size:1.1rem"><?= $hidden ? 'En preparació' : 'Publicat' ?></div>
          <div class="kpi__foot"><?= $hidden ? 'només el veieu vosaltres' : 'visible per a tothom' ?></div>
        </div>
      </div>

      <?php if ($status['ready']): ?>
        <form method="post" action="<?= e(url('/admin/activacio/pagar')) ?>">
          <?= csrf_field() ?>
          <button class="btn" type="submit"><?= Icons::svg('card', 'icon', 18) ?> Pagar i activar el web</button>
          <span class="text-soft" style="margin-left:.6rem">El pagament es fa a la pàgina segura de Stripe.</span>
        </form>
      <?php else: ?>
        <div class="alert alert--info">
          El pagament en línia encara no està disponible. Escriviu-nos des de
          <a href="<?= e(url('/admin/suport')) ?>">Suport</a> i ho resolem.
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($payments): ?>
  <div class="panel" style="margin-top:1.2rem">
    <div class="panel__head"><h2>Els vostres pagaments</h2></div>
    <div class="panel__body table-wrap">
      <table class="admin-table">
        <thead><tr><th>Referència</th><th>Concepte</th><th>Import</th><th>Estat</th><th>Data</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <?php $invoice = $invoices[(int) $payment['id']] ?? null; ?>
            <tr>
              <td><strong><?= e($payment['code']) ?></strong></td>
              <td><?= e($payment['description']) ?></td>
              <td><?= e(money((int) $payment['total_cents'])) ?></td>
              <td>
                <span class="badge badge--<?= (string) $payment['status'] === 'paid' ? 'green' : ((string) $payment['status'] === 'pending' ? 'amber' : '') ?>">
                  <?= e(Charge::STATUSES[$payment['status']] ?? $payment['status']) ?>
                </span>
              </td>
              <td class="text-soft"><?= e(dt($payment['paid_at'] ?: $payment['created_at'])) ?></td>
              <td class="text-right">
                <?php if ($invoice): ?>
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/activacio/factura/' . (int) $payment['id'])) ?>">
                    <?= Icons::svg('download', 'icon', 14) ?> <?= e($invoice['full_number']) ?>
                  </a>
                <?php else: ?>
                  <span class="text-soft">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Això no són els vostres cobraments</h2></div>
  <div class="panel__body">
    <p style="margin:0" class="text-soft">
      Aquesta pantalla és el que pagueu <strong>vosaltres a la plataforma</strong> per tenir el web publicat.
      El que cobreu vosaltres als participants —inscripcions i tiquets— és una altra cosa i la teniu a
      <a href="<?= e(url('/admin/pagaments')) ?>">Cobraments</a>, amb les vostres dades fiscals i la vostra
      numeració de rebuts i factures.
    </p>
  </div>
</div>
