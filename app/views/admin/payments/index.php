<?php
/**
 * Els cobraments del cros.
 * @var array<int,array<string,mixed>> $payments
 * @var array{status:string,concept:string,search:string} $filters
 * @var array<string,int> $totals
 * @var array<int,string> $billing
 */
use Cros\Core\Icons;
use Cros\Models\Billing;
use Cros\Models\Payment;
use Cros\Payments\Gateways;

$tones = ['paid' => 'green', 'pending' => 'amber', 'failed' => 'red', 'cancelled' => '', 'refunded' => 'blue'];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Cobraments</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/pagaments/documents')) ?>">
        <?= Icons::svg('file', 'icon', 15) ?> Rebuts i factures
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/configuracio/payments')) ?>">
        <?= Icons::svg('settings', 'icon', 15) ?> Passarel·la
      </a>
    </div>
  </div>
  <div class="panel__body">

    <?php if (!$ready): ?>
      <div class="alert alert--<?= $gateway === '' ? 'info' : 'error' ?>">
        <?php if ($gateway === ''): ?>
          Encara no heu triat cap passarel·la de pagament, de manera que el web no cobra res en línia.
          Es tria a <a href="<?= e(url('/admin/configuracio/payments')) ?>">Configuració → Cobraments</a>.
        <?php else: ?>
          La passarel·la triada (<?= e(Gateways::label($gateway)) ?>) encara no té totes les dades.
          Reviseu-les a <a href="<?= e(url('/admin/configuracio/payments')) ?>">Configuració → Cobraments</a>.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($billing): ?>
      <div class="alert alert--info">
        Ara mateix s'emeten <strong>rebuts</strong>. Per poder emetre factures falta
        <?= e(implode(', ', $billing)) ?>, a
        <a href="<?= e(url('/admin/configuracio/billing')) ?>">Configuració → Facturació</a>.
      </div>
    <?php endif; ?>

    <div class="grid-cards mb-2">
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('euro', 'icon', 16) ?> Cobrat</div>
        <div class="kpi__value"><?= e(money((int) $totals['cents'])) ?></div>
        <div class="kpi__foot"><?= (int) $totals['paid'] ?> cobraments</div>
      </div>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('clock', 'icon', 16) ?> Pendents</div>
        <div class="kpi__value"><?= (int) $totals['pending'] ?></div>
        <div class="kpi__foot">començats i sense acabar</div>
      </div>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('refresh', 'icon', 16) ?> Retornat</div>
        <div class="kpi__value"><?= e(money((int) $totals['refunded'])) ?></div>
        <div class="kpi__foot">
          <?= e(mb_strtolower(Billing::TYPES[$documentType] ?? 'rebut')) ?> de cada venda
        </div>
      </div>
    </div>

    <form method="get" class="filters" style="margin-bottom:1rem">
      <select name="estat">
        <option value="">Tots els estats</option>
        <?php foreach (Payment::STATUSES as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="concepte">
        <option value="">Tot</option>
        <?php foreach (Payment::CONCEPTS as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $filters['concept'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Referència, nom o correu">
      <button class="btn btn--sm" type="submit">Filtrar</button>
    </form>

    <?php if (!$payments): ?>
      <p class="text-soft" style="margin:0">Encara no hi ha cap cobrament.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Referència</th><th>Qui paga</th><th>Concepte</th><th>Import</th><th>Estat</th><th>Data</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
              <tr>
                <td><strong><?= e($payment['code']) ?></strong></td>
                <td><?= e($payment['payer_name']) ?><br><small class="text-soft"><?= e($payment['payer_email']) ?></small></td>
                <td class="text-soft"><?= e(Payment::CONCEPTS[$payment['concept']] ?? '') ?></td>
                <td><?= e(money((int) $payment['total_cents'])) ?>
                  <?php if ((int) $payment['refunded_cents'] > 0): ?>
                    <br><small class="text-soft">−<?= e(money((int) $payment['refunded_cents'])) ?></small>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge--<?= e($tones[$payment['status']] ?? '') ?>"><?= e(Payment::STATUSES[$payment['status']] ?? '') ?></span></td>
                <td class="text-soft"><?= e(dt($payment['paid_at'] ?: $payment['created_at'])) ?></td>
                <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/pagaments/' . (int) $payment['id'])) ?>">Obrir</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
