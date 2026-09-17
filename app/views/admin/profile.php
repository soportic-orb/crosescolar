<?php /** Dades del compte propi. */ ?>
<form method="post" action="<?= e(url('/admin/perfil')) ?>">
  <?= csrf_field() ?>
  <div class="panel" style="max-width:640px">
    <div class="panel__head"><h2>El meu compte</h2></div>
    <div class="panel__body">
      <div class="form-grid">
        <div class="field"><label for="name">Nom</label>
          <input type="text" id="name" name="name" value="<?= e(old('name', $user['name'])) ?>" required></div>
        <div class="field"><label for="email">Correu electrònic</label>
          <input type="email" id="email" name="email" value="<?= e(old('email', $user['email'])) ?>" required></div>
        <div class="field"><label for="password">Contrasenya nova</label>
          <input type="password" id="password" name="password" autocomplete="new-password">
          <span class="hint">Deixeu-ho buit per no canviar-la. Mínim 8 caràcters.</span></div>
        <div class="field"><label for="password_confirm">Repetiu la contrasenya</label>
          <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">Desar</button></div>
    </div>
  </div>
</form>
