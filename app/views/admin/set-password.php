<?php /** Posar-se una contrasenya després d'entrar amb un enllaç. */ ?>
<div class="login-card">
  <h1><?= e(setting('site_name', 'Cros Escolar')) ?></h1>
  <p class="sub"><?= $first ? 'Benvinguts! Poseu-vos una contrasenya' : 'Canviar la contrasenya' ?></p>

  <?php foreach (flash() as $message): ?>
    <div class="alert alert--<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
  <?php endforeach; ?>

  <?php if ($first): ?>
    <p class="text-soft" style="font-size:.9rem;margin:0 0 1rem">
      Ja esteu a dins com a <strong><?= e($user['email']) ?></strong>. Trieu una contrasenya
      i així la propera vegada hi podreu entrar directament.
    </p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/admin/clau')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <div class="field">
      <label for="password">Contrasenya nova</label>
      <input type="password" id="password" name="password" required autofocus minlength="8" autocomplete="new-password">
      <p class="hint">Com a mínim 8 caràcters.</p>
    </div>
    <div class="field">
      <label for="password_confirm">Repetiu-la</label>
      <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
    </div>
    <button class="btn" type="submit" style="width:100%">Desar i entrar</button>
  </form>
  <p style="margin:1.2rem 0 0;font-size:.85rem" class="text-soft">
    <a href="<?= e(url('/admin')) ?>">Ara no, vull anar al panell</a>
  </p>
</div>
