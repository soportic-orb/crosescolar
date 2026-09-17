<?php
/** Actualitzacions OTA. */
use Cros\Core\Icons;
?>
<div class="grid-cards mb-2">
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('refresh', 'icon', 16) ?> Versió instal·lada</div>
    <div class="kpi__value"><?= e($current) ?></div>
    <div class="kpi__foot">PHP <?= e(PHP_VERSION) ?></div>
  </div>
  <div class="kpi">
    <div class="kpi__label">Última comprovació</div>
    <div class="kpi__value" style="font-size:1.1rem"><?= e($result['checked_at'] ?? 'Mai') ?></div>
    <div class="kpi__foot">
      <?php if (!$result): ?>
        Encara no s'ha comprovat
      <?php elseif (!empty($result['error'])): ?>
        No s'ha pogut comprovar
      <?php elseif (!empty($result['notice'])): ?>
        Cap versió publicada a l'origen
      <?php elseif (!empty($result['available'])): ?>
        Versió <?= e($result['latest']) ?> disponible
      <?php else: ?>
        Esteu al dia
      <?php endif; ?>
    </div>
  </div>
  <div class="kpi">
    <div class="kpi__label">Permisos d'escriptura</div>
    <div class="kpi__value" style="font-size:1.1rem"><?= $writable ? 'Correctes' : 'Insuficients' ?></div>
    <div class="kpi__foot"><?= $zipAvailable ? 'Extensió ZIP activa' : 'Falta l\'extensió ZIP' ?></div>
  </div>
</div>

<?php if (!$writable): ?>
  <div class="alert alert--error">La carpeta de l'aplicació no té permisos d'escriptura: l'actualització automàtica no funcionarà.</div>
<?php endif; ?>
<?php if ($pendingMigrations): ?>
  <div class="alert alert--warning">Hi ha migracions de base de dades pendents: <?= e(implode(', ', $pendingMigrations)) ?>.
    S'aplicaran automàticament en la propera actualització.</div>
<?php endif; ?>

<div class="panel">
  <div class="panel__head">
    <h2>Actualització automàtica</h2>
    <form method="post" action="<?= e(url('/admin/actualitzacions/comprovar')) ?>" class="spacer">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit"><?= Icons::svg('refresh', 'icon', 15) ?> Comprovar ara</button>
    </form>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Origen configurat:
      <?php if ($manifestUrl !== ''): ?>
        <span class="mono"><?= e($manifestUrl) ?></span>
      <?php else: ?>
        <em>cap</em>
      <?php endif; ?>
      · <a href="<?= e(url('/admin/configuracio/updates')) ?>">Canviar-lo</a>
    </p>

    <?php if (!$result): ?>
      <p class="text-soft">Encara no s'ha comprovat cap actualització. Premeu «Comprovar ara».</p>
    <?php elseif (!empty($result['error'])): ?>
      <div class="alert alert--error"><?= e($result['error']) ?></div>
      <p class="text-soft">Reviseu l'URL del manifest a <a href="<?= e(url('/admin/configuracio/updates')) ?>">Configuració → Actualitzacions</a>.</p>
    <?php elseif (!empty($result['notice'])): ?>
      <div class="alert alert--info"><?= e($result['notice']) ?></div>
      <p class="text-soft" style="margin-bottom:.4rem">Això és normal fins que es publiqui la primera versió. Hi ha dues maneres de publicar-ne una:</p>
      <ul class="help-list">
        <li><strong>Amb GitHub:</strong> creeu una versió («release») amb l'etiqueta <span class="mono">v<?= e($current) ?></span>
          i adjunteu-hi el fitxer ZIP generat amb <span class="mono">php tools/build-release.php</span>.</li>
        <li><strong>Sense GitHub:</strong> pugeu <span class="mono">manifest.json</span> i el ZIP a una carpeta del web
          (per exemple <span class="mono"><?= e(rtrim(base_url(), '/')) ?>/actualitzacions/</span>) i poseu l'adreça del
          manifest a Configuració → Actualitzacions.</li>
      </ul>
      <p class="text-soft">Mentrestant podeu instal·lar qualsevol paquet a mà des del bloc de sota.</p>
    <?php elseif (!empty($result['available'])): ?>
      <div class="alert alert--success">
        Hi ha disponible la versió <strong><?= e($result['latest']) ?></strong>
        <?= !empty($result['published']) ? '(' . e(dt($result['published'])) . ')' : '' ?>
      </div>
      <?php if (!empty($result['notes'])): ?>
        <div class="panel" style="box-shadow:none"><div class="panel__body" style="white-space:pre-wrap;font-size:.9rem"><?= e($result['notes']) ?></div></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/admin/actualitzacions/instalar')) ?>" class="mt-2"
            data-confirm="S'actualitzarà el web a la versió <?= e($result['latest']) ?>. Es farà una còpia de seguretat abans. Voleu continuar?">
        <?= csrf_field() ?>
        <button class="btn" type="submit" <?= $writable && $zipAvailable ? '' : 'disabled' ?>>
          <?= Icons::svg('download', 'icon', 16) ?> Instal·lar la versió <?= e($result['latest']) ?>
        </button>
      </form>
    <?php else: ?>
      <div class="alert alert--success">El web està actualitzat (versió <?= e($current) ?>).</div>
    <?php endif; ?>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Instal·lar un paquet manualment</h2></div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">Pugeu un fitxer ZIP generat amb <span class="mono">tools/build-release.php</span>.</p>
    <form method="post" action="<?= e(url('/admin/actualitzacions/pujar')) ?>" enctype="multipart/form-data" class="flex"
          data-confirm="Instal·lar el paquet pujat?">
      <?= csrf_field() ?>
      <input type="file" name="package" accept=".zip" required>
      <button class="btn" type="submit">Instal·lar</button>
    </form>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Còpies de seguretat</h2>
    <form method="post" action="<?= e(url('/admin/actualitzacions/copia')) ?>" class="spacer">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit">Crear còpia ara</button>
    </form>
  </div>
  <div class="panel__body table-wrap">
    <?php if (!$backups): ?>
      <p class="text-soft">Encara no hi ha cap còpia de seguretat.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Fitxer</th><th>Data</th><th>Mida</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($backups as $backup): ?>
            <tr>
              <td class="mono" style="font-size:.85rem"><?= e($backup['name']) ?></td>
              <td><?= e($backup['date']) ?></td>
              <td><?= e(number_format($backup['size'] / 1048576, 1, ',', '.')) ?> MB</td>
              <td class="actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/actualitzacions/descarregar/' . $backup['name'])) ?>">Descarregar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="text-soft mt-2" style="font-size:.85rem">Es conserven les 5 còpies més recents (fitxers + base de dades).</p>
    <?php endif; ?>
  </div>
</div>
