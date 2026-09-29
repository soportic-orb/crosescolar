<?php
/**
 * La fitxa d'un pagament de la plataforma.
 * @var array<string,mixed> $charge
 * @var array<string,mixed>|null $invoice
 * @var array<string,mixed>|null $instance
 */
use Cros\Core\Icons;
use Cros\Platform\Charge;
use Cros\Platform\Instance;

$id = (int) $charge['id'];
$status = (string) $charge['status'];
$tones = ['paid' => 'green', 'pending' => 'amber', 'failed' => 'red', 'cancelled' => '', 'refunded' => 'blue'];
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($charge['code']) ?></h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/pagaments')) ?>">← Tots els pagaments</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      <span class="badge badge--<?= e($tones[$status] ?? '') ?>"><?= e(Charge::STATUSES[$status] ?? $status) ?></span>
      <?= e($charge['description']) ?> · creat el <?= e(dt($charge['created_at'])) ?>
      <?php if (!empty($charge['paid_at'])): ?> · pagat el <?= e(dt($charge['paid_at'])) ?><?php endif; ?>
    </p>

    <div class="form-grid form-grid--2">
      <div>
        <h3>Qui paga</h3>
        <p>
          <strong><?= e($charge['payer_name']) ?></strong><br>
          <a href="mailto:<?= e($charge['payer_email']) ?>"><?= e($charge['payer_email']) ?></a>
          <?php if (!empty($charge['payer_nif'])): ?><br>NIF <?= e($charge['payer_nif']) ?><?php endif; ?>
          <?php if (!empty($charge['payer_address'])): ?><br><?= e($charge['payer_address']) ?><?php endif; ?>
          <?php if (!empty($charge['payer_town'])): ?><br><?= e(trim(($charge['payer_postcode'] ?? '') . ' ' . $charge['payer_town'])) ?><?php endif; ?>
        </p>
      </div>
      <div>
        <h3>Import</h3>
        <table class="admin-table">
          <tbody>
            <?php if ((float) $charge['tax_rate'] > 0): ?>
              <tr><td class="text-soft">Base imposable</td><td class="text-right text-soft"><?= e(money((int) $charge['subtotal_cents'])) ?></td></tr>
              <tr><td class="text-soft">IVA <?= e(rtrim(rtrim(number_format((float) $charge['tax_rate'], 2, ',', '.'), '0'), ',')) ?> %</td>
                  <td class="text-right text-soft"><?= e(money((int) $charge['tax_cents'])) ?></td></tr>
            <?php endif; ?>
            <tr><td><strong>Total</strong></td><td class="text-right"><strong><?= e(money((int) $charge['total_cents'])) ?></strong></td></tr>
            <?php if ((int) $charge['refunded_cents'] > 0): ?>
              <tr><td class="text-soft">Retornat</td><td class="text-right text-soft">−<?= e(money((int) $charge['refunded_cents'])) ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($instance): ?>
      <h3 style="margin-top:1.6rem">El web</h3>
      <p>
        <a href="<?= e(Instance::url($instance)) ?>" target="_blank" rel="noopener"><?= e($instance['site_name']) ?></a>
        · <a href="<?= e(url('/instancies/' . (int) $instance['id'])) ?>">la seva fitxa</a>
        · <?= !empty($instance['activated_at'])
            ? 'activat el ' . e(dt($instance['activated_at']))
            : '<strong>encara no activat</strong>' ?>
      </p>
    <?php endif; ?>

    <?php if (!empty($charge['detail'])): ?>
      <p class="text-soft" style="font-size:.9rem"><?= e($charge['detail']) ?></p>
    <?php endif; ?>
    <?php if (!empty($charge['stripe_session_id']) || !empty($charge['stripe_payment_intent'])): ?>
      <p class="text-soft mono" style="font-size:.82rem">
        <?= e((string) ($charge['stripe_session_id'] ?? '')) ?>
        <?php if (!empty($charge['stripe_payment_intent'])): ?> · <?= e((string) $charge['stripe_payment_intent']) ?><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Factura</h2></div>
  <div class="panel__body">
    <?php if ($invoice): ?>
      <p style="margin-top:0">
        <strong><?= e($invoice['full_number']) ?></strong>, emesa el <?= e(ca_date((string) $invoice['issued_on'])) ?>
        a nom de <?= e($invoice['customer_name']) ?>.
      </p>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/pagaments/' . $id . '/factura')) ?>">
        <?= Icons::svg('download', 'icon', 15) ?> Descarregar el PDF
      </a>
    <?php elseif ($status === 'paid'): ?>
      <p style="margin-top:0" class="text-soft">Aquest pagament encara no té factura.</p>
      <form method="post" action="<?= e(url('/pagaments/' . $id . '/accio')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invoice">
        <button class="btn btn--ghost btn--sm" type="submit">Emetre-la ara</button>
      </form>
    <?php else: ?>
      <p style="margin:0" class="text-soft">La factura s'emet sola quan el pagament es dona per fet.</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Accions</h2></div>
  <div class="panel__body">
    <?php if ($status === 'pending'): ?>
      <form method="post" action="<?= e(url('/pagaments/' . $id . '/accio')) ?>" style="display:inline"
            data-confirm="Donar aquest pagament per fet? S'activarà el web i s'emetrà la factura.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="paid">
        <button class="btn btn--sm" type="submit">Cobrat per transferència</button>
      </form>
      <form method="post" action="<?= e(url('/pagaments/' . $id . '/accio')) ?>" style="display:inline"
            data-confirm="Anul·lar aquest pagament?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn--ghost btn--sm" type="submit">Anul·lar</button>
      </form>
    <?php elseif ($status === 'paid'): ?>
      <form method="post" action="<?= e(url('/pagaments/' . $id . '/accio')) ?>" class="flex" style="gap:.5rem;align-items:center"
            data-confirm="Tornar els diners per Stripe?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="refund">
        <label for="amount" class="text-soft">Import a tornar</label>
        <input type="text" id="amount" name="amount" style="width:7rem"
               placeholder="<?= e(number_format((int) $charge['total_cents'] / 100, 2, ',', '')) ?>">
        <button class="btn btn--danger btn--sm" type="submit">Tornar els diners</button>
        <span class="text-soft" style="font-size:.88rem">Buit vol dir tot.</span>
      </form>
    <?php else: ?>
      <p class="text-soft" style="margin:0">No hi ha res a fer amb un pagament <?= e(mb_strtolower(Charge::STATUSES[$status] ?? '')) ?>.</p>
    <?php endif; ?>
  </div>
</div>
