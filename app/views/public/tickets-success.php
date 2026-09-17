<?php /** Retorn correcte del pagament. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => $paid ? 'Compra confirmada!' : 'Estem confirmant el pagament',
    'breadcrumb' => ['Esmorzar' => '/esmorzar', 'Confirmació' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <?php if ($paid && $order): ?>
      <div class="alert alert--success">
        <span>Pagament rebut correctament. Comanda <strong><?= e($order['code']) ?></strong>.</span>
      </div>
      <div class="prose"><?= setting_html('tickets_success_text') ?></div>

      <div class="card" style="margin:1.5rem 0">
        <h3>Resum</h3>
        <table class="data" style="min-width:0">
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <th><?= (int) $item['qty'] ?> × <?= e($item['name']) ?></th>
                <td class="text-right"><?= e(money((int) $item['subtotal_cents'], (string) $order['currency'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr><th>Total pagat</th><td class="text-right"><strong><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></strong></td></tr>
          </tbody>
        </table>
      </div>

      <div class="flex">
        <a class="btn" href="<?= e(url('/tiquets/' . $order['token'])) ?>">
          <?= \Cros\Core\Icons::svg('ticket', 'icon', 18) ?> Veure els meus tiquets
        </a>
        <a class="btn btn--ghost" href="<?= e(url('/tiquets/' . $order['token'] . '/imprimir')) ?>" target="_blank">Imprimir</a>
      </div>
      <p class="field__hint" style="margin-top:1.2rem">
        Desa aquest enllaç: també el rebràs per correu a <strong><?= e($order['email']) ?></strong>.
      </p>
    <?php else: ?>
      <div class="alert alert--info">
        <span>Estem esperant la confirmació del pagament. Si l'has completat, en pocs segons rebràs els tiquets per correu electrònic.</span>
      </div>
      <p>Si passats uns minuts no reps res, consulta els teus tiquets amb el codi de comanda i el teu correu:</p>
      <a class="btn" href="<?= e(url('/els-meus-tiquets')) ?>">Consultar els meus tiquets</a>
    <?php endif; ?>
  </div>
</section>
