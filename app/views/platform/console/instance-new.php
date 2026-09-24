<?php
/** Alta d'una instància nova. */
$prefill = static function (string $key, string $default = '') use ($request) {
    $old = old($key, null);
    if ($old !== null && $old !== '') {
        return (string) $old;
    }
    return $default;
};
$slug = $prefill('slug', (string) ($request['slug'] ?? ''));
?>
<p><a href="<?= e(url($request ? '/sollicituds/' . (int) $request['id'] : '/instancies')) ?>">← Tornar</a></p>

<?php if ($request): ?>
  <div class="alert alert--info">
    S'està creant la instància de la sol·licitud <strong><?= e($request['code']) ?></strong>
    (<?= e($request['entity']) ?>). En acabar, la sol·licitud queda aprovada.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/instancies/nova')) ?>">
  <?= csrf_field() ?>
  <?php if ($request): ?><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><?php endif; ?>

  <div class="panel">
    <div class="panel__head"><h2>El web</h2></div>
    <div class="panel__body">
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="slug">Adreça</label>
          <div style="display:flex;align-items:center;gap:.4rem">
            <input type="text" id="slug" name="slug" value="<?= e($slug) ?>" required maxlength="40"
                   pattern="[a-z0-9-]+" autocomplete="off" placeholder="lagranada">
            <?php if (count($domains) > 1): ?>
              <?php $chosen = $prefill('domain', (string) ($request['domain'] ?? $domains[0])); ?>
              <select name="domain" style="width:auto">
                <?php foreach ($domains as $option): ?>
                  <option value="<?= e($option) ?>"<?= $chosen === $option ? ' selected' : '' ?>>.<?= e($option) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <span class="text-soft">.<?= e($domains[0] ?? '') ?></span>
              <input type="hidden" name="domain" value="<?= e($domains[0] ?? '') ?>">
            <?php endif; ?>
          </div>
          <p class="hint">Només lletres minúscules, números i guions. No es pot canviar després.</p>
        </div>
        <div class="field">
          <label for="site_name">Nom del cros</label>
          <input type="text" id="site_name" name="site_name" required maxlength="190"
                 value="<?= e($prefill('site_name', (string) ($request['entity'] ?? ''))) ?>" placeholder="Cros Escolar La Granada">
        </div>
        <div class="field">
          <label for="town">Població</label>
          <input type="text" id="town" name="town" maxlength="120" value="<?= e($prefill('town', (string) ($request['town'] ?? ''))) ?>">
        </div>
        <div class="field">
          <label for="event_date">Data de la cursa</label>
          <input type="date" id="event_date" name="event_date" value="<?= e($prefill('event_date', (string) ($request['event_date'] ?? ''))) ?>">
          <p class="hint">Es pot deixar buida i posar-la després des del seu panell.</p>
        </div>
        <div class="field">
          <span class="label">Opcions</span>
          <label class="switch"><input type="checkbox" name="listed" value="1" checked> Surt al llistat de crosescolar.com</label>
          <label class="switch"><input type="checkbox" name="demo" value="1"> Omplir-lo amb dades d'exemple</label>
        </div>
      </div>
    </div>
  </div>

  <div class="panel mt-2">
    <div class="panel__head"><h2>Qui el gestionarà</h2></div>
    <div class="panel__body">
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="admin_name">Nom i cognoms</label>
          <input type="text" id="admin_name" name="admin_name" required maxlength="150"
                 value="<?= e($prefill('admin_name', (string) ($request['contact_name'] ?? ''))) ?>">
        </div>
        <div class="field">
          <label for="admin_email">Correu electrònic</label>
          <input type="email" id="admin_email" name="admin_email" required maxlength="190"
                 value="<?= e($prefill('admin_email', (string) ($request['contact_email'] ?? ''))) ?>">
          <p class="hint">Hi arribaran l'adreça del web i la contrasenya per entrar-hi.</p>
        </div>
        <div class="field">
          <label for="client_id">Client</label>
          <select id="client_id" name="client_id">
            <option value="0"><?= $request ? 'Crear-ne la fitxa amb les dades de la sol·licitud' : 'Cap' ?></option>
            <?php foreach ($clients as $client): ?>
              <option value="<?= (int) $client['id'] ?>"<?= (int) old('client_id', 0) === (int) $client['id'] ? ' selected' : '' ?>>
                <?= e($client['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Crear la instància</button>
        <span class="hint">Es crea la base de dades, s'instal·la el cros i s'envien les claus per correu. Triga uns segons.</span>
      </div>
    </div>
  </div>
</form>
<?php clear_old(); ?>
