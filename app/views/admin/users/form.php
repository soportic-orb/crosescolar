<?php /** Alta/edició d'usuari. */ ?>
<form method="post" action="<?= e(url('/admin/usuaris' . ($isNew ? '/nou' : '/' . $row['id']))) ?>">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($title) ?></h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/usuaris')) ?>">← Usuaris</a>
    </div>
    <div class="panel__body">
      <div class="form-grid form-grid--2">
        <div class="field"><label for="name">Nom *</label>
          <input type="text" id="name" name="name" value="<?= e($row['name'] ?? '') ?>" required>
          <?php if (isset($errors['name'])): ?><span class="error"><?= e($errors['name']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label for="email">Correu electrònic *</label>
          <input type="email" id="email" name="email" value="<?= e($row['email'] ?? '') ?>" required>
          <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label for="password">Contrasenya <?= $isNew ? '*' : '' ?></label>
          <input type="password" id="password" name="password" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
          <span class="hint"><?= $isNew ? 'Mínim 8 caràcters.' : 'Deixeu-ho buit per no canviar-la.' ?></span>
          <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label for="role">Rol</label>
          <select id="role" name="role">
            <option value="editor" <?= ($row['role'] ?? 'editor') === 'editor' ? 'selected' : '' ?>>Editor (continguts i comandes)</option>
            <option value="admin" <?= ($row['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador (tot)</option>
          </select>
          <?php if (isset($errors['role'])): ?><span class="error"><?= e($errors['role']) ?></span><?php endif; ?>
        </div>
      </div>
      <label class="switch mt-2"><input type="checkbox" name="active" value="1" <?= (int) ($row['active'] ?? 1) === 1 ? 'checked' : '' ?>> <span>Compte actiu</span></label>
      <div class="form-actions">
        <button class="btn" type="submit"><?= $isNew ? 'Crear' : 'Desar' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/admin/usuaris')) ?>">Cancel·lar</a>
      </div>
    </div>
  </div>
</form>
