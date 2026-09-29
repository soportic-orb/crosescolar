<?php
/**
 * La fitxa d'un cobrament.
 * @var array<string,mixed> $payment
 * @var array<int,array<string,mixed>> $items
 * @var array<string,mixed>|null $document
 * @var array<string,mixed>|null $order
 * @var array<string,mixed>|null $registration
 */
use Cros\Core\Icons;
use Cros\Models\Billing;
use Cros\Models\Payment;
use Cros\Payments\Gateways;

$id = (int) $payment['id'];
$status = (string) $payment['status'];
$tones = ['paid' => 'green', 'pending' => 'amber', 'failed' => 'red', 'cancelled' => '', 'refunded' => 'blue'];
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($payment['code']) ?></h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/pagaments')) ?>">← Tots els cobraments</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      <span class="badge badge--<?= e($tones[$status] ?? '') ?>"><?= e(Payment::STATUSES[$status] ?? $status) ?></span>
      <?= e(Payment::CONCEPTS[$payment['concept']] ?? '') ?> ·
      creat el <?= e(dt($payment['created_at'])) ?>
      <?php if (!empty($payment['paid_at'])): ?> · cobrat el <?= e(dt($payment['paid_at'])) ?><?php endif; ?>
      <?php if (!empty($payment['gateway'])): ?>
        · <?= e($payment['gateway'] === 'manual' ? 'cobrat a mà' : Gateways::label((string) $payment['gateway'])) ?>
      <?php endif; ?>
    </p>

    <div class="form-grid form-grid--2">
      <div>
        <h3>Qui paga</h3>
        <p>
          <strong><?= e($payment['payer_name']) ?></strong><br>
          <a href="mailto:<?= e($payment['payer_email']) ?>"><?= e($payment['payer_email']) ?></a>
          <?php if (!empty($payment['payer_phone'])): ?><br><?= e($payment['payer_phone']) ?><?php endif; ?>
          <?php if (!empty($payment['payer_nif'])): ?><br>NIF <?= e($payment['payer_nif']) ?><?php endif; ?>
        </p>
      </div>
      <div>
        <h3>Què es cobra</h3>
        <table class="admin-table">
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= e($item['description']) ?><?= (int) $item['qty'] > 1 ? ' × ' . (int) $item['qty'] : '' ?></td>
                <td class="text-right"><?= e(money((int) $item['subtotal_cents'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ((float) $payment['tax_rate'] > 0): ?>
              <tr><td class="text-soft">Base imposable</td><td class="text-right text-soft"><?= e(money((int) $payment['subtotal_cents'])) ?></td></tr>
              <tr><td class="text-soft">IVA <?= e(rtrim(rtrim(number_format((float) $payment['tax_rate'], 2, ',', '.'), '0'), ',')) ?> %</td>
                  <td class="text-right text-soft"><?= e(money((int) $payment['tax_cents'])) ?></td></tr>
            <?php endif; ?>
            <tr><td><strong>Total</strong></td><td class="text-right"><strong><?= e(money((int) $payment['total_cents'])) ?></strong></td></tr>
            <?php if ((int) $payment['refunded_cents'] > 0): ?>
              <tr><td class="text-soft">Retornat</td><td class="text-right text-soft">−<?= e(money((int) $payment['refunded_cents'])) ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($order || $registration): ?>
      <h3 style="margin-top:1.6rem">A què correspon</h3>
      <p>
        <?php if ($order): ?>
          Comanda <strong><?= e($order['code']) ?></strong> ·
          <a href="<?= e(url('/admin/comandes/' . (int) $order['id'])) ?>">obrir-la</a>
        <?php endif; ?>
        <?php if ($registration): ?>
          Inscripció <strong><?= e($registration['code']) ?></strong> de
          <?= e(trim($registration['first_name'] . ' ' . $registration['last_name'])) ?> ·
          <a href="<?= e(url('/admin/inscripcions/' . (int) $registration['id'])) ?>">obrir-la</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if (!empty($payment['gateway_detail'])): ?>
      <p class="text-soft" style="font-size:.9rem"><?= e($payment['gateway_detail']) ?></p>
    <?php endif; ?>
    <?php if (!empty($payment['gateway_ref']) || !empty($payment['gateway_payment'])): ?>
      <p class="text-soft mono" style="font-size:.82rem">
        <?= e((string) ($payment['gateway_ref'] ?? '')) ?>
        <?php if (!empty($payment['gateway_payment'])): ?> · <?= e((string) $payment['gateway_payment']) ?><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Rebut o factura</h2></div>
  <div class="panel__body">
    <?php if ($document): ?>
      <p style="margin-top:0">
        <strong><?= e(Billing::TYPES[$document['type']] ?? '') ?> <?= e($document['full_number']) ?></strong>,
        emès el <?= e(ca_date((string) $document['issued_on'])) ?> a nom de <?= e($document['customer_name']) ?>.
      </p>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/pagaments/' . $id . '/document')) ?>">
        <?= Icons::svg('download', 'icon', 15) ?> Descarregar el PDF
      </a>
    <?php elseif ($status === 'paid'): ?>
      <p style="margin-top:0" class="text-soft">Aquest cobrament encara no té document.</p>
      <form method="post" action="<?= e(url('/admin/pagaments/' . $id . '/accio')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="document">
        <button class="btn btn--ghost btn--sm" type="submit">Emetre'l ara</button>
      </form>
    <?php else: ?>
      <p style="margin:0" class="text-soft">El document s'emet sol quan el cobrament es dona per pagat.</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Accions</h2></div>
  <div class="panel__body">
    <?php if ($status === 'pending'): ?>
      <form method="post" action="<?= e(url('/admin/pagaments/' . $id . '/accio')) ?>" style="display:inline"
            data-confirm="Donar aquest cobrament per pagat? S'emetrà el document i s'entregarà el que s'hagi comprat.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="paid">
        <button class="btn btn--sm" type="submit">Cobrat en mà o per transferència</button>
      </form>
      <form method="post" action="<?= e(url('/admin/pagaments/' . $id . '/accio')) ?>" style="display:inline"
            data-confirm="Anul·lar aquest cobrament?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn--ghost btn--sm" type="submit">Anul·lar</button>
      </form>
    <?php elseif ($status === 'paid'): ?>
      <form method="post" action="<?= e(url('/admin/pagaments/' . $id . '/accio')) ?>" class="flex" style="gap:.5rem;align-items:center"
            data-confirm="Tornar els diners per la mateixa passarel·la?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="refund">
        <label for="amount" class="text-soft">Import a tornar</label>
        <input type="text" id="amount" name="amount" style="width:7rem"
               placeholder="<?= e(number_format((int) $payment['total_cents'] / 100, 2, ',', '')) ?>">
        <button class="btn btn--danger btn--sm" type="submit">Tornar els diners</button>
        <span class="text-soft" style="font-size:.88rem">Buit vol dir tot.</span>
      </form>
      <?php if ((string) ($payment['gateway'] ?? '') === 'redsys'): ?>
        <p class="text-soft" style="font-size:.9rem;margin-bottom:0">
          Les devolucions del TPV de Redsys es fan des del portal del banc: aquí no s'hi pot arribar.
        </p>
      <?php endif; ?>
    <?php else: ?>
      <p class="text-soft" style="margin:0">No hi ha res a fer amb un cobrament <?= e(mb_strtolower(Payment::STATUSES[$status] ?? '')) ?>.</p>
    <?php endif; ?>
  </div>
</div>
