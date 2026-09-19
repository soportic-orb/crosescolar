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
        <span class="spacer"></span>
        <span class="badge <?= \Cros\Core\Stripe::mode() === 'live' ? 'badge--green' : 'badge--amber' ?>">
          Mode <?= e(\Cros\Core\Stripe::mode()) ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="panel__body">
      <?php if (!empty($group['description'])): ?>
        <p class="text-soft" style="margin-top:0"><?= e($group['description']) ?></p>
      <?php endif; ?>

      <?php if ($groupKey === 'payments'): ?>
        <div class="alert alert--info">
          <strong>Webhook de Stripe:</strong> configureu aquesta adreça al vostre tauler de Stripe
          (esdeveniments <span class="mono">checkout.session.completed</span>, <span class="mono">checkout.session.expired</span> i <span class="mono">charge.refunded</span>):<br>
          <span class="mono"><?= e(url('/stripe/webhook')) ?></span>
        </div>
      <?php endif; ?>
      <?php if ($groupKey === 'bibs'): ?>
        <?php
          $bib = \Cros\Models\Bib::describe();
          $shape = fn (array $size) => sprintf('%.0f×%.0f mm (%s)', $size[0], $size[1],
              $size[0] > $size[1] ? 'horitzontal' : 'vertical');
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
          <?php $fullWidth = in_array($field['type'] ?? 'text', ['html', 'textarea'], true); ?>
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

<?php if ($groupKey === 'payments'): ?>
  <form method="post" action="<?= e(url('/admin/stripe/prova')) ?>" class="mt-2">
    <?= csrf_field() ?>
    <button class="btn btn--ghost" type="submit">Provar la connexió amb Stripe</button>
  </form>
<?php endif; ?>
