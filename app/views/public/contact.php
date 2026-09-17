<?php /** Formulari de contacte. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', ['title' => 'Contacte', 'breadcrumb' => ['Contacte' => '']]) ?>
<section class="section">
  <div class="container split">
    <div>
      <h2>Parlem-ne</h2>
      <p class="lead">Si teniu dubtes sobre la cursa, les inscripcions o voleu col·laborar com a patrocinador o voluntariat, escriviu-nos.</p>
      <ul style="list-style:none;padding:0;display:grid;gap:.8rem">
        <?php if (setting('contact_email', '')): ?>
          <li class="flex"><?= \Cros\Core\Icons::svg('mail', 'icon', 20) ?> <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li>
        <?php endif; ?>
        <?php if (setting('contact_phone', '')): ?>
          <li class="flex"><?= \Cros\Core\Icons::svg('phone', 'icon', 20) ?> <?= e(setting('contact_phone')) ?></li>
        <?php endif; ?>
        <li class="flex"><?= \Cros\Core\Icons::svg('location', 'icon', 20) ?> <?= e(setting('event_address', '')) ?></li>
      </ul>
    </div>

    <div class="card">
      <?php if (!empty($sent)): ?>
        <div class="alert alert--success">Hem rebut el vostre missatge. Gràcies!</div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/contacte')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="honeypot"><label>No omplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field<?= isset($errors['name']) ? ' field--error' : '' ?>">
          <label for="name">Nom i cognoms *</label>
          <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" required>
          <?php if (isset($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['email']) ? ' field--error' : '' ?>">
          <label for="email">Correu electrònic *</label>
          <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
          <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['message']) ? ' field--error' : '' ?>">
          <label for="message">Missatge *</label>
          <textarea id="message" name="message" required><?= e(old('message')) ?></textarea>
          <?php if (isset($errors['message'])): ?><span class="field__error"><?= e($errors['message']) ?></span><?php endif; ?>
        </div>
        <p class="field__hint">En enviar el formulari accepteu la <a href="<?= e(url('/privacitat')) ?>">política de privacitat</a>.</p>
        <button class="btn" type="submit">Envia el missatge</button>
      </form>
    </div>
  </div>
</section>
