<?php /** «Les meves inscripcions»: pas 1, demanar el codi d'accés. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => 'Les meves inscripcions',
    'subtitle' => 'Consulta i modifica les dades dels participants que has inscrit.',
    'breadcrumb' => ['Les meves inscripcions' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="card">
      <form method="post" action="<?= e(url('/les-meves-inscripcions')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Correu electrònic de la inscripció *</label>
          <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
          <span class="field__hint">És l'adreça de la persona de contacte que vau posar en inscriure el participant.</span>
        </div>
        <button class="btn" type="submit">
          <?= \Cros\Core\Icons::svg('mail', 'icon', 18) ?> Enviar-me el codi d'accés
        </button>
      </form>
    </div>
    <p style="margin-top:1.5rem" class="field__hint">
      Us enviarem un codi de sis xifres que val <?= (int) \Cros\Models\AccessCode::TTL_MINUTES ?> minuts. No cal recordar cap contrasenya.
      Si no trobeu el correu, mireu la carpeta de correu brossa o escriviu-nos a
      <a href="mailto:<?= e(setting('contact_email', '')) ?>"><?= e(setting('contact_email', '')) ?></a>.
    </p>
  </div>
</section>
