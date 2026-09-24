<?php
/** Fitxa d'una instància. */
use Cros\Platform\Instance;

$status = (string) $instance['status'];
?>
<p><a href="<?= e(url('/instancies')) ?>">← Totes les instàncies</a></p>

<?php if ($fresh): ?>
  <div class="alert alert--success">
    <strong>Instància creada.</strong> Aquestes són les claus que s'han enviat a <?= e((string) $instance['admin_email']) ?>;
    només es veuen ara:
    <br>Usuari <code><?= e((string) $instance['admin_email']) ?></code>
    · Contrasenya <code style="font-size:1rem;letter-spacing:1px"><?= e((string) $fresh['password']) ?></code>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel__head">
    <h2><?= e($instance['site_name']) ?></h2>
    <span class="badge badge--<?= e(Instance::tone($status)) ?>"><?= e(Instance::label($status)) ?></span>
    <?php if (!$instance['published']): ?><span class="badge">En preparació</span><?php endif; ?>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e($url) ?>" target="_blank" rel="noopener">Veure el web</a>
      <a class="btn btn--ghost btn--sm" href="<?= e($url . '/admin') ?>" target="_blank" rel="noopener">El seu panell</a>
    </div>
  </div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <tbody>
        <tr><th style="width:230px">Adreça</th><td><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a></td></tr>
        <tr><th>Client</th><td><?= $client ? e($client['name']) . ' · ' . e($client['contact_email']) : '<span class="text-soft">Sense fitxa de client</span>' ?></td></tr>
        <tr><th>Administrador</th><td><?= e((string) ($instance['admin_email'] ?? '—')) ?></td></tr>
        <tr><th>Cursa</th><td><?= $instance['event_date'] ? e(ca_date((string) $instance['event_date'], true)) : '—' ?></td></tr>
        <tr><th>Inscripcions</th><td><?= (int) $instance['registrations'] ?></td></tr>
        <tr><th>Idioma</th><td><?= $instance['language'] === 'es' ? 'Castellà' : 'Català' ?></td></tr>
        <tr><th>Base de dades</th><td><code><?= e($instance['db_name']) ?></code><?= $instance['db_user'] ? ' · usuari <code>' . e($instance['db_user']) . '</code>' : '' ?></td></tr>
        <tr><th>Versió instal·lada</th><td><?= e($instance['version'] ?? '—') ?></td></tr>
        <tr><th>Creada</th><td><?= e(ca_date(substr((string) $instance['created_at'], 0, 10), true)) ?></td></tr>
        <tr><th>Últim repàs</th><td><?= $instance['synced_at'] ? e(substr((string) $instance['synced_at'], 0, 16)) : '<span class="text-soft">mai</span>' ?></td></tr>
        <?php if ($instance['cancelled_at']): ?>
          <tr><th>Baixa</th><td>
            <?= e(substr((string) $instance['cancelled_at'], 0, 10)) ?>
            · les dades s'esborren el <?= e(ca_date((string) $instance['purge_at'])) ?>
          </td></tr>
        <?php endif; ?>
        <?php if ($request): ?>
          <tr><th>Sol·licitud</th><td><a href="<?= e(url('/sollicituds/' . (int) $request['id'])) ?>"><?= e($request['code']) ?></a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Dades i manteniment</h2></div>
  <div class="panel__body">
    <form method="post" action="<?= e(url('/instancies/' . (int) $instance['id'] . '/accio')) ?>" class="form-grid form-grid--2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="field">
        <label for="site_name">Nom del cros</label>
        <input type="text" id="site_name" name="site_name" value="<?= e($instance['site_name']) ?>" maxlength="190">
      </div>
      <div class="field">
        <label for="town">Població</label>
        <input type="text" id="town" name="town" value="<?= e((string) ($instance['town'] ?? '')) ?>" maxlength="120">
      </div>
      <div class="field">
        <label for="admin_email">Correu de contacte</label>
        <input type="email" id="admin_email" name="admin_email" value="<?= e((string) ($instance['admin_email'] ?? '')) ?>" maxlength="190">
      </div>
      <div class="field">
        <span class="label">Llistat públic</span>
        <label class="switch"><input type="checkbox" name="listed" value="1"<?= $instance['listed'] ? ' checked' : '' ?>> Surt a crosescolar.com</label>
        <p class="hint">Només hi surt si, a més, el client ha publicat el seu web.</p>
      </div>
      <div class="form-actions" style="grid-column:1/-1">
        <button class="btn" type="submit">Desar</button>
      </div>
    </form>

    <div class="form-actions" style="gap:.6rem">
      <form method="post" action="<?= e(url('/instancies/' . (int) $instance['id'] . '/accio')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sync">
        <button class="btn btn--ghost" type="submit">Actualitzar les dades des del seu web</button>
      </form>
      <?php if ($status === 'suspended'): ?>
        <form method="post" action="<?= e(url('/instancies/' . (int) $instance['id'] . '/accio')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="resume">
          <button class="btn btn--ghost" type="submit">Tornar a engegar el web</button>
        </form>
      <?php elseif ($status !== 'cancelled'): ?>
        <form method="post" action="<?= e(url('/instancies/' . (int) $instance['id'] . '/accio')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="suspend">
          <button class="btn btn--ghost" type="submit">Aturar el web</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($status !== 'cancelled'): ?>
  <div class="panel mt-2">
    <div class="panel__head"><h2>Donar de baixa</h2></div>
    <div class="panel__body">
      <p class="text-soft">
        El web deixa de servir-se i les dades es guarden <?= Instance::PURGE_DAYS ?> dies abans d'esborrar-se.
        El client pot descarregar-se-les del seu panell mentre hi siguin.
      </p>
      <form method="post" action="<?= e(url('/instancies/' . (int) $instance['id'] . '/accio')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <div class="field">
          <label for="confirm">Escriviu «<?= e($instance['slug']) ?>» per confirmar</label>
          <input type="text" id="confirm" name="confirm" autocomplete="off" style="max-width:280px">
        </div>
        <div class="form-actions">
          <button class="btn btn--danger" type="submit">Donar de baixa la instància</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($activity): ?>
  <div class="panel mt-2">
    <div class="panel__head"><h2>Què s'hi ha fet</h2></div>
    <div class="panel__body table-wrap">
      <table class="admin-table">
        <tbody>
        <?php foreach ($activity as $row): ?>
          <tr>
            <td style="width:150px"><small><?= e(substr((string) $row['created_at'], 0, 16)) ?></small></td>
            <td><code><?= e($row['action']) ?></code></td>
            <td><small class="text-soft"><?= e((string) ($row['context'] ?? '')) ?></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
