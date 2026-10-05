<?php
/**
 * La pàgina de contacte de la plataforma.
 *
 * @var array<string,string> $errors
 * @var bool $captchaImage    si el captcha és una imatge (o una suma en text)
 * @var string $captchaQuestion
 */
use Cros\Core\Icons;

$errors = $errors ?? [];
$old = static fn (string $key, string $default = ''): string => (string) old($key, $default);
$error = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<span class="field__error">' . e($errors[$field]) . '</span>' : '';
};
$errorClass = static fn (string $field): string => isset($errors[$field]) ? ' field--error' : '';
?>
<section class="platform-page">
  <div class="container-narrow">
    <h1><?= e((string) setting('contact_title', 'Parlem-ne')) ?></h1>
    <?php if (($intro = trim((string) setting('contact_intro', ''))) !== ''): ?>
      <p class="lead"><?= nl2br(e($intro)) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/contacte')) ?>" class="card" style="margin-top:1.6rem" novalidate>
      <?= csrf_field() ?>
      <p class="visually-hidden" aria-hidden="true">
        <label for="website_url">No ompliu aquest camp</label>
        <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
      </p>

      <div class="form-row">
        <div class="field<?= $errorClass('name') ?>">
          <label for="name">Nom i cognoms *</label>
          <input type="text" id="name" name="name" value="<?= e($old('name')) ?>" autocomplete="name" required maxlength="150">
          <?= $error('name') ?>
        </div>
        <div class="field<?= $errorClass('entity') ?>">
          <label for="entity">Entitat</label>
          <input type="text" id="entity" name="entity" value="<?= e($old('entity')) ?>" autocomplete="organization"
                 maxlength="190" placeholder="Club, AFA, ajuntament…">
          <?= $error('entity') ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= $errorClass('email') ?>">
          <label for="email">Correu electrònic *</label>
          <input type="email" id="email" name="email" value="<?= e($old('email')) ?>" autocomplete="email" required maxlength="190">
          <?= $error('email') ?>
        </div>
        <div class="field<?= $errorClass('phone') ?>">
          <label for="phone">Telèfon de contacte</label>
          <input type="tel" id="phone" name="phone" value="<?= e($old('phone')) ?>" autocomplete="tel" maxlength="40">
          <?= $error('phone') ?>
        </div>
      </div>

      <div class="field<?= $errorClass('message') ?>">
        <label for="message">Missatge *</label>
        <textarea id="message" name="message" rows="6" required maxlength="5000"><?= e($old('message')) ?></textarea>
        <?= $error('message') ?>
      </div>

      <div class="field<?= $errorClass('captcha') ?>">
        <?php if ($captchaImage): ?>
          <label for="captcha">Escriviu els caràcters de la imatge *</label>
          <div class="captcha">
            <img class="captcha__image" src="<?= e(url('/contacte/captcha')) ?>?<?= e(bin2hex(random_bytes(4))) ?>"
                 width="190" height="64" alt="Codi de seguretat: cinc lletres i xifres">
            <input type="text" id="captcha" name="captcha" required autocomplete="off" autocapitalize="characters"
                   spellcheck="false" maxlength="10" inputmode="text">
          </div>
          <span class="field__hint">
            No cal distingir majúscules de minúscules. No es llegeix bé?
            <a href="<?= e(url('/contacte')) ?>" data-captcha-refresh>Doneu-me'n un altre</a>.
          </span>
        <?php else: ?>
          <label for="captcha">Quant fa <?= e($captchaQuestion) ?>? *</label>
          <input type="text" id="captcha" name="captcha" required autocomplete="off" inputmode="numeric" maxlength="4"
                 style="max-width:8rem">
        <?php endif; ?>
        <?= $error('captcha') ?>
      </div>

      <label class="checkbox<?= $errorClass('privacy') ?>">
        <input type="checkbox" name="privacy" value="1" <?= $old('privacy') === '1' ? 'checked' : '' ?> required>
        <span>
          He llegit la <a href="<?= e(url('/privadesa')) ?>" target="_blank" rel="noopener">política de privadesa</a>
          i accepto que tracteu les meves dades per respondre aquest missatge. *
        </span>
      </label>
      <?= $error('privacy') ?>

      <label class="checkbox">
        <input type="checkbox" name="news" value="1" <?= $old('news') === '1' ? 'checked' : '' ?>>
        <span>Vull rebre de tant en tant notícies i novetats de la plataforma. Me'n puc donar de baixa quan vulgui.</span>
      </label>

      <div class="flex" style="margin-top:1.4rem">
        <button class="btn" type="submit"><?= Icons::svg('mail', 'icon', 18) ?> Enviar el missatge</button>
        <span class="field__hint">* Camps obligatoris.</span>
      </div>
    </form>
  </div>
</section>
<script>
  // Un captcha nou sense tornar a carregar la pàgina ni perdre el que s'ha escrit.
  // El servidor en prepara un altre en demanar la imatge amb «?nou».
  document.querySelectorAll('[data-captcha-refresh]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      var image = document.querySelector('.captcha__image');
      if (!image) { return; }
      event.preventDefault();
      image.src = '<?= e(url('/contacte/captcha')) ?>?nou=' + Date.now();
      var field = document.getElementById('captcha');
      if (field) { field.value = ''; field.focus(); }
    });
  });
</script>
