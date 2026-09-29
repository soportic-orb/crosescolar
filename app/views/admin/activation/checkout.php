<?php
/**
 * El pagament per publicar el web, sense sortir del panell.
 *
 * El formulari de la targeta el dibuixa Stripe dins d'una finestreta seva
 * (Stripe.js). Ni el número ni el CVC no passen mai pel nostre servidor:
 * nosaltres només sabem si ha anat bé.
 *
 * @var array<string,mixed> $status
 * @var array{client_secret:string,publishable:string,intent:string,code:string} $pagament
 * @var array<string,string> $payer
 */
use Cros\Core\Icons;

$imports = $status['amounts'];
$teImpostos = (float) $imports['vat_rate'] > 0 || (float) $imports['irpf_rate'] > 0;
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($status['name'] ?: 'Publicar el web') ?></h2>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/activacio')) ?>">Deixar-ho per després</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      És un pagament únic. Un cop fet, el web queda activat per sempre i el podeu
      publicar i despublicar les vegades que calgui sense tornar a pagar res.
    </p>

    <div class="checkout">
      <div class="checkout__summary">
        <h3 style="margin-top:0"><?= e($status['name'] ?: 'Activació del web') ?></h3>
        <table class="data data--tight" style="min-width:0">
          <tbody>
            <?php if ($teImpostos): ?>
              <tr><th>Base imposable</th><td class="text-right"><?= e(money((int) $imports['base'])) ?></td></tr>
              <?php if ((float) $imports['vat_rate'] > 0): ?>
                <tr><th>IVA <?= e(rtrim(rtrim(number_format((float) $imports['vat_rate'], 2, ',', '.'), '0'), ',')) ?> %</th>
                    <td class="text-right"><?= e(money((int) $imports['vat'])) ?></td></tr>
              <?php endif; ?>
              <?php if ((float) $imports['irpf_rate'] > 0): ?>
                <tr><th>Retenció IRPF <?= e(rtrim(rtrim(number_format((float) $imports['irpf_rate'], 2, ',', '.'), '0'), ',')) ?> %</th>
                    <td class="text-right">−<?= e(money((int) $imports['irpf'])) ?></td></tr>
              <?php endif; ?>
            <?php endif; ?>
            <tr class="is-total"><th>Total a pagar</th><td class="text-right"><strong><?= e(money((int) $imports['total'])) ?></strong></td></tr>
          </tbody>
        </table>
        <p class="text-soft" style="font-size:.85rem;margin-bottom:0">
          Referència <code><?= e((string) ($pagament['code'] ?? '')) ?></code>.
          La factura la tindreu al mateix panell quan el pagament quedi confirmat.
        </p>
        <?php if (trim((string) ($payer['name'] ?? '')) !== ''): ?>
          <p class="text-soft" style="font-size:.85rem">
            S'emetrà a nom de <strong><?= e($payer['name']) ?></strong><?= trim((string) ($payer['nif'] ?? '')) !== '' ? ' (' . e($payer['nif']) . ')' : '' ?>.
            Ho podeu canviar a <a href="<?= e(url('/admin/configuracio/billing')) ?>">Facturació</a>.
          </p>
        <?php endif; ?>
      </div>

      <div class="checkout__card">
        <h3 style="margin-top:0"><?= Icons::svg('card', 'icon', 18) ?> Dades de la targeta</h3>
        <form id="pagament-targeta">
          <div id="pagament-camp"><p class="text-soft">Carregant el formulari segur…</p></div>
          <div id="pagament-error" class="alert alert--error" style="display:none;margin-top:1rem"></div>
          <button class="btn mt-2" id="pagament-boto" type="submit" disabled>
            Pagar <?= e(money((int) $imports['total'])) ?>
          </button>
        </form>
        <p class="text-soft" style="font-size:.82rem;margin-bottom:0">
          El número de la targeta va directament al nostre proveïdor de pagaments.
          Aquest web no el veu ni el desa.
        </p>
      </div>
    </div>
  </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  var clau = <?= json_encode((string) $pagament['publishable'], JSON_UNESCAPED_SLASHES) ?>;
  var secret = <?= json_encode((string) $pagament['client_secret'], JSON_UNESCAPED_SLASHES) ?>;
  var tornada = <?= json_encode(url('/admin/activacio/tornada'), JSON_UNESCAPED_SLASHES) ?>;
  var camp = document.getElementById('pagament-camp');
  var avis = document.getElementById('pagament-error');
  var boto = document.getElementById('pagament-boto');
  var form = document.getElementById('pagament-targeta');

  function malament(text) {
    avis.textContent = text;
    avis.style.display = '';
    boto.disabled = false;
    boto.textContent = boto.dataset.text;
  }

  boto.dataset.text = boto.textContent.trim();

  if (typeof Stripe !== 'function') {
    camp.innerHTML = '';
    malament('No s\'ha pogut carregar el formulari de pagament. Comproveu la connexió i torneu-ho a provar.');
    boto.disabled = true;
    return;
  }

  var stripe = Stripe(clau);
  var elements = stripe.elements({ clientSecret: secret, locale: 'ca' });
  var payment = elements.create('payment', { layout: 'tabs' });
  camp.innerHTML = '';
  payment.mount(camp);
  payment.on('ready', function () { boto.disabled = false; });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    avis.style.display = 'none';
    boto.disabled = true;
    boto.textContent = 'Un moment…';
    stripe.confirmPayment({
      elements: elements,
      confirmParams: { return_url: tornada },
    }).then(function (resultat) {
      // Si arriba aquí és que alguna cosa ha fallat: quan va bé, Stripe
      // se'n porta el navegador a l'adreça de tornada.
      if (resultat.error) {
        malament(resultat.error.message || 'No s\'ha pogut fer el pagament. No s\'ha carregat res.');
      }
    });
  });
})();
</script>
