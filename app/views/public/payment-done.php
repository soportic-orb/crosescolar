<?php
/**
 * El pagament ha anat bé.
 * @var array<string,mixed> $payment
 * @var array<int,array<string,mixed>> $items
 * @var array<string,mixed>|null $document
 * @var array<string,mixed>|null $order
 * @var array<string,mixed>|null $registration
 */
use Cros\Core\View;
use Cros\Models\Billing;
?>
<?= View::partial('partials/page-header', ['title' => 'Pagament fet', 'breadcrumb' => ['Pagament' => '']]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="alert alert--success">
      <span>Pagament rebut. Us n'hem enviat el comprovant a <strong><?= e($payment['payer_email']) ?></strong>.</span>
    </div>

    <div class="card">
      <table class="data data--tight">
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['description']) ?><?= (int) $item['qty'] > 1 ? ' × ' . (int) $item['qty'] : '' ?></td>
              <td class="text-right"><?= e(money((int) $item['subtotal_cents'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr><td><strong>Total pagat</strong></td>
              <td class="text-right"><strong><?= e(money((int) $payment['total_cents'])) ?></strong></td></tr>
        </tbody>
      </table>
      <p class="text-soft" style="font-size:.9rem">Referència <?= e($payment['code']) ?></p>
    </div>

    <div class="flex" style="margin-top:1.4rem;gap:.7rem;flex-wrap:wrap">
      <?php if ($document): ?>
        <a class="btn" href="<?= e(url('/pagament/' . $payment['token'] . '/document')) ?>">
          Descarregar <?= e(mb_strtolower(Billing::TYPES[$document['type']] ?? 'el rebut')) ?>
          <?= e($document['full_number']) ?>
        </a>
      <?php endif; ?>
      <?php if ($order): ?>
        <a class="btn btn--ghost" href="<?= e(url('/tiquets/' . $order['token'])) ?>">Veure els tiquets</a>
      <?php endif; ?>
      <?php if ($registration): ?>
        <a class="btn btn--ghost" href="<?= e(url('/inscripcio/confirmada/' . $registration['code'])) ?>">Veure la inscripció</a>
      <?php endif; ?>
      <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Tornar a l'inici</a>
    </div>
  </div>
</section>
