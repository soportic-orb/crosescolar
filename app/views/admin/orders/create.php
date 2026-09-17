<?php /** Venda manual de tiquets. */ ?>
<form method="post" action="<?= e(url('/admin/comandes/nova')) ?>">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head"><h2>Venda manual (taquilla)</h2></div>
    <div class="panel__body">
      <p class="text-soft" style="margin-top:0">
        Feu servir aquest formulari per registrar vendes en efectiu o per transferència.
        Si marqueu la comanda com a pagada es generaran els tiquets i s'enviarà el correu (si hi ha adreça).
      </p>

      <h3>Tiquets</h3>
      <table class="admin-table">
        <thead><tr><th>Tiquet</th><th>Preu</th><th style="width:140px">Unitats</th></tr></thead>
        <tbody>
          <?php foreach ($types as $type): ?>
            <tr>
              <td><?= e($type['name']) ?> <?= (int) $type['active'] === 0 ? '<span class="badge">Aturat</span>' : '' ?></td>
              <td><?= e(money((int) $type['price_cents'])) ?></td>
              <td><input type="number" name="qty[<?= (int) $type['id'] ?>]" value="0" min="0" max="500"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="form-grid form-grid--2 mt-3">
        <div class="field">
          <label for="name">Nom del comprador *</label>
          <input type="text" id="name" name="name" required>
        </div>
        <div class="field">
          <label for="email">Correu electrònic</label>
          <input type="email" id="email" name="email">
          <span class="hint">Opcional. Si s'informa, s'hi enviaran els tiquets.</span>
        </div>
        <div class="field">
          <label for="phone">Telèfon</label>
          <input type="tel" id="phone" name="phone">
        </div>
        <div class="field">
          <label for="method">Forma de pagament</label>
          <select id="method" name="method">
            <option value="cash">Efectiu</option>
            <option value="transfer">Transferència</option>
            <option value="manual">Altres</option>
          </select>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label for="notes">Notes</label>
          <input type="text" id="notes" name="notes">
        </div>
      </div>

      <label class="switch mt-2"><input type="checkbox" name="mark_paid" value="1" checked> <span>Marcar com a pagada i generar els tiquets</span></label>

      <div class="form-actions">
        <button class="btn" type="submit">Crear la comanda</button>
        <a class="btn btn--ghost" href="<?= e(url('/admin/comandes')) ?>">Cancel·lar</a>
      </div>
    </div>
  </div>
</form>
