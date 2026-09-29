<?php
/**
 * Els pagaments que la plataforma cobra als seus clients.
 * @var array<int,array<string,mixed>> $charges
 * @var array{status:string,search:string} $filters
 * @var array<string,int> $totals
 * @var array<string,mixed> $plan
 * @var array<int,string> $missing
 */
use Cros\Core\Icons;
use Cros\Platform\Charge;

$tones = ['paid' => 'green', 'pending' => 'amber', 'failed' => 'red', 'cancelled' => '', 'refunded' => 'blue'];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Pagaments</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/pagaments/factures')) ?>">
        <?= Icons::svg('file', 'icon', 15) ?> Factures
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/configuracio/plan')) ?>">
        <?= Icons::svg('settings', 'icon', 15) ?> El pla
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/configuracio/stripe')) ?>">Stripe</a>
    </div>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      El que cobrem als clients per tenir el web publicat. El que cobra cada cros als seus
      participants no és aquí: viu a la seva base de dades, amb les seves dades fiscals.
    </p>

    <?php if (!$plan['enabled']): ?>
      <div class="alert alert--info">
        El pagament d'activació està <strong>desactivat</strong>: ara mateix qualsevol client pot
        publicar el seu web sense pagar res. Es tria a
        <a href="<?= e(url('/configuracio/plan')) ?>">Configuració → Pagament d'activació</a>.
      </div>
    <?php elseif (!$plan['stripe']): ?>
      <div class="alert alert--error">
        El pla està actiu (<?= e(money((int) $plan['total'])) ?>) però falten les claus de Stripe, de manera
        que ningú no pot pagar. Poseu-les a <a href="<?= e(url('/configuracio/stripe')) ?>">Stripe de la plataforma</a>.
      </div>
    <?php elseif ($plan['testing']): ?>
      <div class="alert alert--info">
        Stripe va en <strong>mode de proves</strong>: els pagaments no són de debò.
      </div>
    <?php endif; ?>

    <?php if ($missing): ?>
      <div class="alert alert--info">
        Per poder emetre factures falta <?= e(implode(', ', $missing)) ?>, a
        <a href="<?= e(url('/configuracio/platform_billing')) ?>">Configuració → Dades fiscals</a>.
      </div>
    <?php endif; ?>

    <div class="grid-cards mb-2">
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('euro', 'icon', 16) ?> Cobrat</div>
        <div class="kpi__value"><?= e(money((int) $totals['cents'])) ?></div>
        <div class="kpi__foot"><?= (int) $totals['paid'] ?> pagaments</div>
      </div>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('clock', 'icon', 16) ?> Pendents</div>
        <div class="kpi__value"><?= (int) $totals['pending'] ?></div>
        <div class="kpi__foot">començats i sense acabar</div>
      </div>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('card', 'icon', 16) ?> Preu ara</div>
        <div class="kpi__value"><?= e(money((int) $plan['total'])) ?></div>
        <div class="kpi__foot">
          <?= e($plan['name']) ?>
          <?php if ((int) $plan['total'] !== (int) $plan['price']): ?>
            · base <?= e(money((int) $plan['price'])) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <form method="get" class="filters" style="margin-bottom:1rem">
      <select name="estat">
        <option value="">Tots els estats</option>
        <?php foreach (Charge::STATUSES as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Referència, client o subdomini">
      <button class="btn btn--sm" type="submit">Filtrar</button>
    </form>

    <?php if (!$charges): ?>
      <p class="text-soft" style="margin:0">Encara no hi ha cap pagament.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Referència</th><th>Client</th><th>Web</th><th>Import</th><th>Estat</th><th>Data</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($charges as $charge): ?>
              <tr>
                <td><strong><?= e($charge['code']) ?></strong></td>
                <td><?= e($charge['payer_name']) ?><br><small class="text-soft"><?= e($charge['payer_email']) ?></small></td>
                <td class="text-soft"><?= e($charge['slug'] ?? '—') ?></td>
                <td><?= e(money((int) $charge['total_cents'])) ?></td>
                <td><span class="badge badge--<?= e($tones[$charge['status']] ?? '') ?>"><?= e(Charge::STATUSES[$charge['status']] ?? '') ?></span></td>
                <td class="text-soft"><?= e(dt($charge['paid_at'] ?: $charge['created_at'])) ?></td>
                <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/pagaments/' . (int) $charge['id'])) ?>">Obrir</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
