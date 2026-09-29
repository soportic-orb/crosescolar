<?php
/**
 * El salt cap al TPV.
 *
 * Redsys no dona cap adreça on anar: vol un formulari amb tres camps signats.
 * S'envia sol amb JavaScript i, si algú el té desactivat, hi ha el botó.
 *
 * @var array<string,mixed> $payment
 * @var string $action
 * @var array<string,string> $fields
 */
use Cros\Core\View;
?>
<?= View::partial('partials/page-header', ['title' => 'Anem a pagar', 'breadcrumb' => ['Pagament' => '']]) ?>
<section class="section">
  <div class="container-narrow" style="text-align:center">
    <p>Us estem enviant a la pàgina segura del banc per pagar
      <strong><?= e(money((int) $payment['total_cents'])) ?></strong>.</p>
    <form method="post" action="<?= e($action) ?>" id="tpv">
      <?php foreach ($fields as $name => $value): ?>
        <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
      <?php endforeach; ?>
      <button class="btn" type="submit">Continuar cap al banc</button>
    </form>
    <p class="text-soft" style="font-size:.9rem;margin-top:1.2rem">
      Si no hi anéssiu sols al cap d'uns segons, premeu el botó.
    </p>
  </div>
</section>
<script>document.getElementById('tpv').submit();</script>
