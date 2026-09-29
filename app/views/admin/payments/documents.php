<?php
/**
 * Tots els rebuts i factures emesos.
 * @var array<int,array<string,mixed>> $documents
 * @var bool $complete
 * @var array<int,string> $missing
 */
use Cros\Core\Icons;
use Cros\Models\Billing;
?>
<div class="panel">
  <div class="panel__head">
    <h2>Rebuts i factures</h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/pagaments')) ?>">← Els cobraments</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Els documents que heu emès de les vostres vendes, amb les vostres dades fiscals i la vostra
      numeració. Un cop emesos no canvien mai més, encara que després toqueu les dades de l'entitat.
    </p>
    <?php if (!$complete): ?>
      <div class="alert alert--info">
        Ara s'emeten <strong>rebuts</strong>. Per poder emetre factures falta <?= e(implode(', ', $missing)) ?>,
        a <a href="<?= e(url('/admin/configuracio/billing')) ?>">Configuració → Facturació</a>.
      </div>
    <?php endif; ?>

    <?php if (!$documents): ?>
      <p class="text-soft">Encara no se n'ha emès cap.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Número</th><th>Tipus</th><th>A nom de</th><th>Data</th><th>Import</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($documents as $document): ?>
              <tr>
                <td><strong><?= e($document['full_number']) ?></strong></td>
                <td class="text-soft"><?= e(Billing::TYPES[$document['type']] ?? '') ?></td>
                <td><?= e($document['customer_name']) ?>
                  <?php if (!empty($document['customer_nif'])): ?><br><small class="text-soft"><?= e($document['customer_nif']) ?></small><?php endif; ?>
                </td>
                <td class="text-soft"><?= e(ca_date((string) $document['issued_on'])) ?></td>
                <td><?= e(money((int) $document['total_cents'])) ?></td>
                <td class="text-right">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/pagaments/' . (int) $document['payment_id'] . '/document')) ?>">
                    <?= Icons::svg('download', 'icon', 14) ?> PDF
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
