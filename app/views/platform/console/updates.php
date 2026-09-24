<?php
/**
 * Actualitzacions del sistema.
 * @var string $current
 * @var array|null $result
 */
use Cros\Core\Icons;

$mida = static function (int $bytes): string {
    $units = ['B', 'kB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
};
$nova = $result && !empty($result['available']);
?>
<div class="grid-cards">
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('refresh', 'icon', 16) ?> Versió instal·lada</div>
    <div class="kpi__value"><?= e($current) ?></div>
    <div class="kpi__foot"><?= $lastUpdate !== '' ? 'des del ' . e(substr($lastUpdate, 0, 10)) : 'la de sempre' ?></div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('chart', 'icon', 16) ?> Instàncies per actualitzar</div>
    <div class="kpi__value"><?= (int) $outdated ?></div>
    <div class="kpi__foot"><?= $outdated > 0 ? 'des d\'«Instàncies»' : 'totes al dia' ?></div>
  </div>
</div>

<?php if (!$writable): ?>
  <div class="alert alert--error mt-2">
    La carpeta del codi no es pot escriure: l'actualització automàtica no funcionarà.
    Reviseu que <code><?= e(CROS_ROOT) ?></code> sigui de l'usuari del servidor web.
  </div>
<?php endif; ?>
<?php if (!$zipAvailable): ?>
  <div class="alert alert--error mt-2">Aquest servidor no té l'extensió ZIP de PHP.</div>
<?php endif; ?>
<?php if ($pending): ?>
  <div class="alert alert--warning mt-2">
    La base de dades de la plataforma té canvis pendents: <?= e(implode(', ', $pending)) ?>.
    S'apliquen sols en entrar al panell.
  </div>
<?php endif; ?>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Versions noves</h2>
    <form class="spacer" method="post" action="<?= e(url('/actualitzacions/comprovar')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit">Comprovar ara</button>
    </form>
  </div>
  <div class="panel__body">
    <?php if ($manifestUrl === ''): ?>
      <div class="alert alert--info" style="margin:0">
        Encara no heu dit d'on surten les versions.
        <a href="<?= e(url('/configuracio/updates')) ?>">Indiqueu l'adreça del manifest</a> i torneu-hi.
      </div>
    <?php elseif ($result === null): ?>
      <p class="text-soft" style="margin:0">Encara no s'ha comprovat res. Premeu «Comprovar ara».</p>
    <?php elseif (!empty($result['error'])): ?>
      <div class="alert alert--error" style="margin:0">No s'ha pogut comprovar: <?= e((string) $result['error']) ?></div>
    <?php elseif ($nova): ?>
      <p>
        Hi ha la versió <strong><?= e((string) $result['latest']) ?></strong>
        (ara teniu la <?= e($current) ?>).
      </p>
      <?php if (!empty($result['notes'])): ?>
        <div class="alert alert--info" style="white-space:pre-line"><?= e((string) $result['notes']) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/actualitzacions/instalar')) ?>">
        <?= csrf_field() ?>
        <div class="form-actions">
          <button class="btn" type="submit">Actualitzar el sistema</button>
          <span class="hint">
            Es fa una còpia abans, es canvien els fitxers i es posa al dia la base de dades de
            la plataforma. Mentre dura, els webs dels clients diuen que tornen de seguida.
          </span>
        </div>
      </form>
    <?php else: ?>
      <p style="margin:0">Teniu l'última versió (<?= e($current) ?>).</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Instal·lar un paquet a mà</h2></div>
  <div class="panel__body">
    <form method="post" action="<?= e(url('/actualitzacions/pujar')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label for="package">Paquet (.zip)</label>
        <input type="file" id="package" name="package" accept=".zip" required>
        <p class="hint">El mateix fitxer que es descarrega de la pàgina de versions.</p>
      </div>
      <div class="form-actions"><button class="btn btn--ghost" type="submit">Instal·lar aquest paquet</button></div>
    </form>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Còpies del sistema</h2>
    <form class="spacer" method="post" action="<?= e(url('/actualitzacions/copia')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--sm" type="submit">Fer-ne una ara</button>
    </form>
  </div>
  <div class="panel__body table-wrap">
    <p class="text-soft" style="margin:0 0 1rem">
      Aquestes són del codi i de la base de dades de la plataforma. Les dels clients es fan a
      part, cada nit, i surten a la fitxa de cada instància.
    </p>
    <?php if (!$backups): ?>
      <p class="text-soft" style="margin:0">Encara no n'hi ha cap.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Quan</th><th>Fitxer</th><th>Mida</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($backups as $copy): ?>
          <tr>
            <td><?= e($copy['date']) ?></td>
            <td><code><?= e($copy['name']) ?></code></td>
            <td><?= e($mida((int) $copy['size'])) ?></td>
            <td class="text-right">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/actualitzacions/copia/' . rawurlencode($copy['name']))) ?>">Descarregar</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
