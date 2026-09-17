<?php /** Formulari d'accés. */ ?>
<div class="login-card">
  <h1><?= e(setting('site_name', 'Cros Escolar La Granada')) ?></h1>
  <p class="sub">Accés al panell de gestió</p>

  <?php foreach (flash() as $message): ?>
    <div class="alert alert--<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
  <?php endforeach; ?>

  <form method="post" action="<?= e(url('/admin/acces')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">Correu electrònic</label>
      <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username">
    </div>
    <div class="field">
      <label for="password">Contrasenya</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn" type="submit" style="width:100%">Entrar</button>
  </form>
  <p style="margin:1.2rem 0 0;font-size:.85rem" class="text-soft">
    <a href="<?= e(url('/')) ?>">← Tornar al web</a>
  </p>
</div>
