<?php
/** «Les meves inscripcions»: modificar les dades d'un participant. */
?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => 'Modificar la inscripció',
    'subtitle' => 'Dorsal ' . \Cros\Models\Bib::number($registration),
    'breadcrumb' => ['Les meves inscripcions' => '/les-meves-inscripcions', 'Modificar' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="card">
      <form method="post" action="<?= e(url('/les-meves-inscripcions/' . (int) $registration['id'] . '/modificar')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="field<?= isset($errors['first_name']) ? ' field--error' : '' ?>">
            <label for="first_name">Nom *</label>
            <input type="text" id="first_name" name="first_name" value="<?= e($registration['first_name'] ?? '') ?>" required>
            <?php if (isset($errors['first_name'])): ?><span class="field__error"><?= e($errors['first_name']) ?></span><?php endif; ?>
          </div>
          <div class="field<?= isset($errors['last_name']) ? ' field--error' : '' ?>">
            <label for="last_name">Cognoms *</label>
            <input type="text" id="last_name" name="last_name" value="<?= e($registration['last_name'] ?? '') ?>" required>
            <?php if (isset($errors['last_name'])): ?><span class="field__error"><?= e($errors['last_name']) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="field<?= isset($errors['birth_year']) ? ' field--error' : '' ?>">
            <label for="birth_year">Any de naixement *</label>
            <input type="number" id="birth_year" name="birth_year" value="<?= e($registration['birth_year'] ?? '') ?>" min="1930" max="<?= date('Y') ?>" required>
            <span class="field__hint">Si el canvieu, la categoria es torna a assignar sola.</span>
            <?php if (isset($errors['birth_year'])): ?><span class="field__error"><?= e($errors['birth_year']) ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label for="gender">Gènere</label>
            <select id="gender" name="gender">
              <option value="">—</option>
              <?php foreach (\Cros\Models\Registration::GENDERS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string) ($registration['gender'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Categoria</label>
            <input type="text" value="<?= e($registration['category_name'] ?? 'Per assignar') ?>" disabled>
          </div>
        </div>
        <div class="field">
          <label for="school">Escola o club</label>
          <input type="text" id="school" name="school" value="<?= e($registration['school'] ?? '') ?>">
        </div>

        <h2 style="margin-top:1.5rem">Dades de contacte</h2>
        <div class="form-row">
          <div class="field<?= isset($errors['tutor_name']) ? ' field--error' : '' ?>">
            <label for="tutor_name">Nom de la persona de contacte *</label>
            <input type="text" id="tutor_name" name="tutor_name" value="<?= e($registration['tutor_name'] ?? '') ?>" required>
            <?php if (isset($errors['tutor_name'])): ?><span class="field__error"><?= e($errors['tutor_name']) ?></span><?php endif; ?>
          </div>
          <div class="field<?= isset($errors['tutor_phone']) ? ' field--error' : '' ?>">
            <label for="tutor_phone">Telèfon</label>
            <input type="tel" id="tutor_phone" name="tutor_phone" value="<?= e($registration['tutor_phone'] ?? '') ?>">
            <?php if (isset($errors['tutor_phone'])): ?><span class="field__error"><?= e($errors['tutor_phone']) ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label>Correu electrònic</label>
            <input type="email" value="<?= e($registration['tutor_email'] ?? '') ?>" disabled>
            <span class="field__hint">És l'adreça amb què entreu; per canviar-la, escriviu-nos.</span>
          </div>
        </div>
        <div class="field">
          <label for="notes">Observacions</label>
          <textarea id="notes" name="notes" rows="3"><?= e($registration['notes'] ?? '') ?></textarea>
        </div>
        <label class="checkbox">
          <input type="checkbox" name="consent_image" value="1" <?= !empty($registration['consent_image']) ? 'checked' : '' ?>>
          <span><?= e(setting('registrations_image_consent', '')) ?></span>
        </label>

        <div class="flex" style="margin-top:1.4rem">
          <button class="btn" type="submit"><?= \Cros\Core\Icons::svg('check', 'icon', 18) ?> Desar els canvis</button>
          <a class="btn btn--ghost" href="<?= e(url('/les-meves-inscripcions')) ?>">Cancel·lar</a>
        </div>
      </form>
    </div>
  </div>
</section>
