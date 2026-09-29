<?php
/**
 * L'estat d'un cobrament, tal com el veu qui paga.
 * @var array<string,mixed> $payment
 * @var array<int,array<string,mixed>> $items
 * @var array<string,mixed>|null $document
 * @var bool $ready
 */
use Cros\Core\View;
use Cros\Models\Billing;
use Cros\Models\Payment;

$status = (string) $payment['status'];
$waiting = $waiting ?? false;
$cancelled = $cancelled ?? false;
?>
<?= View::partial('partials/page-header', ['title' => $title, 'breadcrumb' => ['Pagament' => '']]) ?>
<section class="section">
  <div class="container-narrow">

    <?php if ($status === 'paid'): ?>
      <div class="alert alert--success"><span>Aquest pagament ja està fet. Gràcies!</span></div>
    <?php elseif ($cancelled): ?>
      <div class="alert alert--warning"><span>No s'ha completat el pagament i no s'ha fet cap càrrec.</span></div>
    <?php elseif ($waiting): ?>
      <div class="alert alert--info">
        <span>El pagament s'està comprovant. Si el vostre banc l'ha acceptat, en pocs segons quedarà confirmat;
        podeu tornar a carregar aquesta pàgina d'aquí una estona.</span>
      </div>
    <?php elseif ($status === 'failed'): ?>
      <div class="alert alert--error"><span>El pagament no s'ha pogut fer. No s'ha carregat res.</span></div>
    <?php endif; ?>

    <div class="card">
      <h2 style="margin-top:0">Què es paga</h2>
      <table class="table">
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['description']) ?><?= (int) $item['qty'] > 1 ? ' × ' . (int) $item['qty'] : '' ?></td>
              <td class="text-right"><?= e(money((int) $item['subtotal_cents'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td><strong>Total</strong></td>
            <td class="text-right"><strong><?= e(money((int) $payment['total_cents'])) ?></strong></td>
          </tr>
        </tbody>
      </table>
      <p class="text-soft" style="font-size:.9rem">
        Referència <?= e($payment['code']) ?> ·
        <?= e(Payment::STATUSES[$status] ?? $status) ?>
        <?php if (!empty($payment['paid_at'])): ?> el <?= e(dt($payment['paid_at'])) ?><?php endif; ?>
      </p>
    </div>

    <div class="flex" style="margin-top:1.4rem;gap:.7rem;flex-wrap:wrap">
      <?php if ($status === 'pending' && $ready): ?>
        <a class="btn" href="<?= e(url('/pagament/' . $payment['token'] . '/anar')) ?>">
          <?= $cancelled || $waiting ? 'Tornar-ho a provar' : 'Pagar ara' ?>
        </a>
      <?php elseif ($status === 'pending'): ?>
        <span class="text-soft">Ara mateix no es pot pagar en línia. Poseu-vos en contacte amb l'organització.</span>
      <?php endif; ?>
      <?php if ($document): ?>
        <a class="btn btn--ghost" href="<?= e(url('/pagament/' . $payment['token'] . '/document')) ?>">
          Descarregar el <?= e(mb_strtolower(Billing::TYPES[$document['type']] ?? 'rebut')) ?>
        </a>
      <?php endif; ?>
      <a class="btn btn--ghost" href="<?= e(url('/contacte')) ?>">Contactar</a>
    </div>
  </div>
</section>
