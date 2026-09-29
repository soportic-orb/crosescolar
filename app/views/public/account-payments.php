<?php
/**
 * Els pagaments que ha fet una adreça a aquesta organització.
 * @var string $email
 * @var array<int,array<string,mixed>> $payments
 * @var array<int,array<string,mixed>> $documents
 */
use Cros\Core\View;
use Cros\Models\Billing;
use Cros\Models\Payment;
?>
<?= View::partial('partials/page-header', [
    'title' => 'Els meus pagaments',
    'breadcrumb' => ['Les meves inscripcions' => '/les-meves-inscripcions', 'Pagaments' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <p class="text-soft">
      Pagaments fets amb l'adreça <strong><?= e($email) ?></strong> a
      <strong><?= e(setting('site_name', '')) ?></strong>.
    </p>

    <?php if (!$payments): ?>
      <div class="notice-box">Amb aquesta adreça no hi consta cap pagament.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Data</th><th>Concepte</th><th>Import</th><th>Comprovant</th></tr></thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
              <?php $document = $documents[(int) $payment['id']] ?? null; ?>
              <tr>
                <td><?= e(ca_date(substr((string) ($payment['paid_at'] ?: $payment['created_at']), 0, 10))) ?></td>
                <td>
                  <?= e(Payment::CONCEPTS[$payment['concept']] ?? '') ?>
                  <br><small class="text-soft"><?= e($payment['code']) ?></small>
                </td>
                <td>
                  <?= e(money((int) $payment['total_cents'])) ?>
                  <?php if ((string) $payment['status'] === 'refunded'): ?>
                    <br><small class="text-soft">retornat</small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($document): ?>
                    <a href="<?= e(url('/pagament/' . $payment['token'] . '/document')) ?>">
                      <?= e(Billing::TYPES[$document['type']] ?? 'Rebut') ?> <?= e($document['full_number']) ?>
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
    <?php endif; ?>

    <div class="flex" style="margin-top:1.4rem;gap:.7rem;flex-wrap:wrap">
      <a class="btn btn--ghost" href="<?= e(url('/les-meves-inscripcions')) ?>">Les meves inscripcions</a>
    </div>
  </div>
</section>
