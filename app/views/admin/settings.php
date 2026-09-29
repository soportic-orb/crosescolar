<?php
/** Formulari de configuració d'un grup. */
use Cros\Core\Settings;
?>
<nav class="settings-nav">
  <?php foreach (Settings::schema() as $key => $item): ?>
    <?php if (!empty($item['admin_only']) && !\Cros\Core\Auth::isAdmin()) { continue; } ?>
    <a href="<?= e(url('/admin/configuracio/' . $key)) ?>" class="<?= $key === $groupKey ? 'is-active' : '' ?>"><?= e($item['title']) ?></a>
  <?php endforeach; ?>
</nav>

<form method="post" action="<?= e(url('/admin/configuracio/' . $groupKey)) ?>" enctype="multipart/form-data" data-dirty-check>
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($group['title']) ?></h2>
      <?php if ($groupKey === 'payments'): ?>
        <?php $passarela = \Cros\Payments\Gateways::chosen(); ?>
        <span class="spacer"></span>
        <?php if ($passarela === ''): ?>
          <span class="badge">Sense passarel·la</span>
        <?php else: ?>
          <span class="badge <?= \Cros\Payments\Gateways::module($passarela)::testing() ? 'badge--amber' : 'badge--green' ?>">
            <?= e(\Cros\Payments\Gateways::label($passarela)) ?>
            <?= \Cros\Payments\Gateways::module($passarela)::testing() ? ' · proves' : '' ?>
          </span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <div class="panel__body">
      <?php if (!empty($group['description'])): ?>
        <p class="text-soft" style="margin-top:0"><?= e($group['description']) ?></p>
      <?php endif; ?>

      <?php if ($groupKey === 'payments' && $passarela === 'stripe'): ?>
        <div class="alert alert--info">
          <strong>Avís de Stripe:</strong> configureu aquesta adreça al vostre tauler de Stripe
          (esdeveniments <span class="mono">checkout.session.completed</span>, <span class="mono">checkout.session.expired</span> i <span class="mono">charge.refunded</span>):<br>
          <span class="mono"><?= e(url('/stripe/webhook')) ?></span>
        </div>
      <?php elseif ($groupKey === 'payments' && $passarela !== ''): ?>
        <div class="alert alert--info">
          <strong>Avís de la passarel·la:</strong> si us demana una adreça on avisar dels pagaments
          (la «notificació en línia», al TPV de Redsys), poseu-hi aquesta:<br>
          <span class="mono"><?= e(url('/pagament/avis/' . $passarela)) ?></span>
        </div>
      <?php endif; ?>
      <?php if ($groupKey === 'plan'): ?>
        <?php
        // El compte fet, perquè qui posa el preu vegi de seguida què cobrarà
        // i què dirà la factura, que amb la retenció pel mig no és el mateix.
        $imports = \Cros\Platform\Plan::amounts();
        $tant = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',') . ' %';
        ?>
        <div class="alert alert--info">
          <strong>Com queda el pagament:</strong>
          base <?= e(money($imports['base'])) ?>
          <?php if ($imports['vat_rate'] > 0): ?>
            · IVA <?= e($tant($imports['vat_rate'])) ?> <?= e(money($imports['vat'])) ?>
          <?php endif; ?>
          <?php if ($imports['irpf_rate'] > 0): ?>
            · retenció IRPF <?= e($tant($imports['irpf_rate'])) ?> −<?= e(money($imports['irpf'])) ?>
          <?php endif; ?>
          → <strong>el client paga <?= e(money($imports['total'])) ?></strong> amb targeta.
          <?php if ($imports['irpf_rate'] > 0): ?>
            <br>Els <?= e(money($imports['irpf'])) ?> de la retenció els ingressa ell a Hisenda en nom vostre.
            <?php $sense = \Cros\Platform\Plan::amounts(null, false); ?>
            <br>Això és el que paga <strong>una entitat</strong>. Un <strong>particular</strong> no reté:
            ell en paga <strong><?= e(money($sense['total'])) ?></strong>.
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($groupKey === 'bibs'): ?>
        <?php
          $bib = \Cros\Models\Bib::describe();
          $shape = fn (array $size) => sprintf('%.0f×%.0f mm (%s)', $size[0], $size[1],
              $size[0] > $size[1] ? 'horitzontal' : 'vertical');
          $bibSheet = \Cros\Models\Bib::familySheet();
          // El disseny és apaïsat però el dorsal surt vertical (o al revés):
          // vol dir que el gir o l'orientació el capgiren, i sol ser un
          // encàrrec vell que s'ha quedat d'un disseny anterior.
          $bibFlipped = $bib['template'] !== null
              && ($bib['template'][0] > $bib['template'][1]) !== ($bib['page'][0] > $bib['page'][1]);
        ?>
        <div class="alert alert--info">
          Les posicions es compten en mil·límetres des de la cantonada <strong>superior esquerra</strong> del dorsal,
          i la Y marca la línia de base del text. Deseu els canvis i premeu
          <strong>«Veure un dorsal de prova»</strong> per comprovar com queda.
          <br>
          <?php if ($bib['template'] !== null): ?>
            La maqueta que hi ha pujada és de <strong><?= e($shape($bib['template'])) ?></strong>
            i el dorsal sortirà de <strong><?= e($shape($bib['page'])) ?></strong><?php
              ?><?= $bib['rotate'] ? ', amb la maqueta girada ' . (int) $bib['rotate'] . '°' : '' ?>.
            <br>
            El PDF que es descarrega la família portarà
            <?php if ($bibSheet['per_sheet'] > 1): ?>
              <strong>dos dorsals per full A4 <?= $bibSheet['upright'] ? 'vertical' : 'apaïsat' ?></strong>,
              <?= $bibSheet['axis'] === 'y' ? 'un a dalt i un a baix' : 'un a cada banda' ?>,
              amb la línia per retallar.
            <?php else: ?>
              <strong>un dorsal per full</strong>: amb aquesta mida no n'hi caben dos en un A4,
              que admet fins a 210×148 mm si el dorsal és apaïsat o 148×210 mm si és vertical.
            <?php endif; ?>
            <?php if ($bibFlipped): ?>
              <br>
              <strong>Compte:</strong> la maqueta és
              <?= $bib['template'][0] > $bib['template'][1] ? 'apaïsada' : 'vertical' ?>
              però el dorsal surt <?= $bib['page'][0] > $bib['page'][1] ? 'apaïsat' : 'vertical' ?>.
              Ho fan «Orientació del dorsal» i «Gir de la maqueta»: si heu canviat el disseny i
              abans en teníeu un de l'altra manera, deixeu-les totes dues com estaven
              («la mateixa que la maqueta» i «cap gir»).
            <?php endif; ?>
          <?php elseif ($bib['error'] !== ''): ?>
            No s'ha pogut llegir la maqueta: <?= e($bib['error']) ?>
          <?php else: ?>
            Sense maqueta, el dorsal sortirà de <strong><?= e($shape($bib['page'])) ?></strong>.
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($groupKey === 'updates'): ?>
        <div class="alert alert--info">Versió instal·lada: <strong><?= e(app_version()) ?></strong> ·
          <a href="<?= e(url('/admin/actualitzacions')) ?>">Comprovar actualitzacions</a></div>
      <?php endif; ?>

      <div class="form-grid form-grid--2">
        <?php foreach ($group['fields'] as $name => $field): ?>
          <?php if (!empty($field['section'])): ?>
            <h3 class="form-section"><?= e($field['section']) ?></h3>
          <?php endif; ?>
          <?php $fullWidth = in_array($field['type'] ?? 'text', ['html', 'textarea', 'sport_icon'], true); ?>
          <div style="<?= $fullWidth ? 'grid-column:1/-1' : '' ?>">
            <?= \Cros\Core\View::partial('admin/partials/field', [
                'name' => $name,
                'field' => $field,
                'value' => $values[$name] ?? ($field['default'] ?? ''),
            ]) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Desar els canvis</button>
        <?php if ($groupKey === 'bibs'): ?>
          <a class="btn btn--ghost" href="<?= e(url('/admin/inscripcions/dorsal-de-prova')) ?>" target="_blank">
            Veure un dorsal de prova
          </a>
          <a class="btn btn--ghost" href="<?= e(url('/admin/inscripcions/dorsals')) ?>">Descarregar tots els dorsals</a>
        <?php endif; ?>
        <?php if ($groupKey === 'results'): ?>
          <a class="btn btn--ghost" href="<?= e(url('/admin/resultats')) ?>">Anar als resultats</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<?php if ($groupKey === 'payments' && \Cros\Payments\Gateways::chosen() === 'stripe'): ?>
  <form method="post" action="<?= e(url('/admin/stripe/prova')) ?>" class="mt-2">
    <?= csrf_field() ?>
    <button class="btn btn--ghost" type="submit">Provar la connexió amb Stripe</button>
  </form>
<?php endif; ?>
