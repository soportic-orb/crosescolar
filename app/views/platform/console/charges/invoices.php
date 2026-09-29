<?php
/**
 * Les factures que ha emès la plataforma.
 * @var array<int,array<string,mixed>> $invoices
 * @var bool $complete
 * @var array<int,string> $missing
 */
use Cros\Core\Icons;
?>
<div class="panel">
  <div class="panel__head">
    <h2>Factures emeses</h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/pagaments')) ?>">← Els pagaments</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Les factures de la plataforma als seus clients, amb les nostres dades fiscals i la nostra
      numeració. Un cop emeses no canvien mai més.
    </p>
    <?php if (!$complete): ?>
      <div class="alert alert--info">
        Falta <?= e(implode(', ', $missing)) ?> a
        <a href="<?= e(url('/configuracio/platform_billing')) ?>">Configuració → Dades fiscals</a>:
        fins que no hi sigui, les factures sortiran incompletes.
      </div>
    <?php endif; ?>

    <?php if (!$invoices): ?>
      <p class="text-soft">Encara no se n'ha emès cap.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Número</th><th>Client</th><th>Web</th><th>Data</th><th>Import</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($invoices as $invoice): ?>
              <tr>
                <td><strong><?= e($invoice['full_number']) ?></strong></td>
                <td><?= e($invoice['customer_name']) ?>
                  <?php if (!empty($invoice['customer_nif'])): ?><br><small class="text-soft"><?= e($invoice['customer_nif']) ?></small><?php endif; ?>
                </td>
                <td class="text-soft"><?= e($invoice['slug'] ?? '—') ?></td>
                <td class="text-soft"><?= e(ca_date((string) $invoice['issued_on'])) ?></td>
                <td><?= e(money((int) $invoice['total_cents'])) ?></td>
                <td class="text-right">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/pagaments/' . (int) $invoice['payment_id'] . '/factura')) ?>">
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
