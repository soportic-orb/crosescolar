<?php /** Accés al panell de superadministració. */ ?>
<div class="login-card">
  <h1>Plataforma</h1>
  <p class="sub">Panell de superadministració</p>

  <?php foreach (flash() as $message): ?>
    <div class="alert alert--<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
  <?php endforeach; ?>

  <form method="post" action="<?= e(url('/acces')) ?>" class="form-grid">
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
</div>
<?php clear_old(); ?>
