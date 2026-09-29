<?php
/**
 * Un grup de configuració de la plataforma.
 * @var string $groupKey
 * @var array  $group
 * @var array  $values
 * @var array  $platformFile  el que hi ha a tenants/platform.php
 * @var bool   $perSite       aquest grup té un text per cada pàgina pública
 * @var string $domain        de quina pàgina s'estan tocant els textos
 * @var array  $domains       totes les pàgines públiques
 */
use Cros\Core\View;

$perSite = ($perSite ?? false) && count($domains ?? []) > 0;
$domain = (string) ($domain ?? '');
$accio = url('/configuracio/' . $groupKey, $perSite ? ['domini' => $domain] : []);
?>
<?php if ($perSite): ?>
  <div class="panel mb-2">
    <div class="panel__body">
      <p class="text-soft" style="margin:0 0 .8rem">
        Cada domini té la seva pàgina pública i el seu text. Trieu de quina n'esteu
        canviant les coses; la resta de la plataforma —els clients, la facturació i
        el correu— és la mateixa per a tots.
      </p>
      <div class="site-tabs">
        <?php foreach ($domains as $opcio): ?>
          <a class="site-tabs__tab<?= $opcio === $domain ? ' is-active' : '' ?>"
             href="<?= e(url('/configuracio/' . $groupKey, ['domini' => $opcio])) ?>">
            <?= \Cros\Core\Icons::svg('globe', 'icon', 16) ?>
            <span><?= e($opcio) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<form method="post" action="<?= e($accio) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($group['title']) ?></h2>
    </div>
    <div class="panel__body">
      <?php if (!empty($group['description'])): ?>
        <p class="text-soft" style="margin:0 0 1.2rem"><?= e($group['description']) ?></p>
      <?php endif; ?>

      <div class="form-grid form-grid--2">
        <?php foreach ($group['fields'] as $name => $field): ?>
          <?php $ample = in_array($field['type'] ?? 'text', ['html', 'textarea', 'faqs', 'features'], true); ?>
          <div style="<?= $ample ? 'grid-column:1/-1' : '' ?>">
            <?= View::partial('admin/partials/field', [
                'name' => $name,
                'field' => $field,
                'value' => $values[$name] ?? ($field['default'] ?? ''),
            ]) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Desar</button>
        <?php if ($groupKey === 'mail'): ?>
          <button class="btn btn--ghost" type="submit" name="provar_correu" value="1">Desar i enviar-me una prova</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<?php if ($groupKey === 'general'): ?>
  <div class="panel mt-2">
    <div class="panel__head"><h2>El que no es toca des d'aquí</h2></div>
    <div class="panel__body table-wrap">
      <p class="text-soft" style="margin:0 0 1rem">
        Els dominis i les bases de dades són al fitxer <code>tenants/platform.php</code> del
        servidor: han de funcionar abans que hi hagi res a punt, i canviar-los vol dir tocar
        també l'nginx i el certificat.
      </p>
      <table class="admin-table">
        <tbody>
          <tr><th style="width:230px">Domini principal</th><td><code><?= e((string) ($platformFile['base_domain'] ?? '')) ?></code></td></tr>
          <?php if (!empty($platformFile['domains'])): ?>
            <tr><th>Altres dominis</th><td><code><?= e(implode(', ', (array) $platformFile['domains'])) ?></code></td></tr>
          <?php endif; ?>
          <tr><th>Panell</th><td><code><?= e(implode(', ', (array) ($platformFile['console'] ?? ['admin']))) ?></code></td></tr>
          <tr><th>Base de dades</th><td><code><?= e((string) ($platformFile['db']['name'] ?? '')) ?></code> · usuari <code><?= e((string) ($platformFile['db']['user'] ?? '')) ?></code></td></tr>
          <tr><th>Qui crea les dels clients</th><td><code><?= e((string) ($platformFile['provision']['admin_user'] ?? '')) ?></code> · prefix <code><?= e((string) ($platformFile['provision']['db_prefix'] ?? 'cros_')) ?></code></td></tr>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
