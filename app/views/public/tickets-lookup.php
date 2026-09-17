<?php /** Consulta de tiquets comprats. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => 'Els meus tiquets',
    'subtitle' => 'Consulta els tiquets de l\'esmorzar que ja has comprat.',
    'breadcrumb' => ['Els meus tiquets' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="card">
      <form method="post" action="<?= e(url('/els-meus-tiquets')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
          <label for="code">Codi de la comanda *</label>
          <input type="text" id="code" name="code" value="<?= e(old('code')) ?>" placeholder="CR26-ABCDE" required style="text-transform:uppercase">
          <span class="field__hint">El trobaràs al correu de confirmació (comença per CR).</span>
        </div>
        <div class="field">
          <label for="email">Correu electrònic de la compra *</label>
          <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
        </div>
        <button class="btn" type="submit"><?= \Cros\Core\Icons::svg('search', 'icon', 18) ?> Veure els tiquets</button>
      </form>
    </div>
    <p style="margin-top:1.5rem" class="field__hint">
      Si has perdut el codi, escriu-nos a <a href="mailto:<?= e(setting('contact_email', '')) ?>"><?= e(setting('contact_email', '')) ?></a>
      indicant el nom i el correu de la compra.
    </p>
  </div>
</section>
