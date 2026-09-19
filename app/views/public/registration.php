<?php
/**
 * Pàgina d'inscripció.
 * Amb el formulari en línia actiu es mostra el formulari complet; si està
 * desactivat (o s'ha passat la data límit), només s'hi mostra el text informatiu.
 */
$errors = $errors ?? [];
$sizes = ['4', '6', '8', '10', '12', '14', 'S', 'M', 'L', 'XL'];
$linkLabel = trim((string) setting('registrations_closed_link_label', ''));
$linkUrl = trim((string) setting('registrations_closed_link_url', ''));
$externalLink = (bool) preg_match('#^https?://#i', $linkUrl);
?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => setting('registrations_title', 'Inscripció a la cursa'),
    'subtitle' => setting('event_date', '') ? ucfirst(ca_date(setting('event_date'), true)) : '',
    'breadcrumb' => ['Inscripció' => ''],
]) ?>

<?php if (!$open): ?>

  <section class="section">
    <div class="container-narrow">
      <div class="prose"><?= setting_html('registrations_closed_text') ?></div>
      <?php if ($linkLabel !== '' && $linkUrl !== ''): ?>
        <p style="margin-top:1.8rem">
          <a class="btn" href="<?= e($externalLink || preg_match('#^(mailto:|tel:)#i', $linkUrl) ? $linkUrl : url($linkUrl)) ?>"
             <?= $externalLink ? 'target="_blank" rel="noopener"' : '' ?>>
            <?= e($linkLabel) ?>
            <?= $externalLink ? \Cros\Core\Icons::svg('external', 'icon', 16) : \Cros\Core\Icons::svg('arrow', 'icon', 18) ?>
          </a>
        </p>
      <?php endif; ?>
    </div>
  </section>

<?php else: ?>

<section class="section">
  <div class="container split">
    <div>
      <div class="prose"><?= setting_html('registrations_intro') ?></div>

        <form method="post" action="<?= e(url('/inscripcio')) ?>" class="form" style="margin-top:1.5rem">
          <?= csrf_field() ?>
          <div class="honeypot"><label>No omplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

          <h2>Dades del participant</h2>
          <div class="form-row">
            <div class="field<?= isset($errors['first_name']) ? ' field--error' : '' ?>">
              <label for="first_name">Nom *</label>
              <input type="text" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required>
              <?php if (isset($errors['first_name'])): ?><span class="field__error"><?= e($errors['first_name']) ?></span><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['last_name']) ? ' field--error' : '' ?>">
              <label for="last_name">Cognoms *</label>
              <input type="text" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required>
              <?php if (isset($errors['last_name'])): ?><span class="field__error"><?= e($errors['last_name']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="form-row">
            <div class="field<?= isset($errors['birth_year']) ? ' field--error' : '' ?>">
              <label for="birth_year">Any de naixement *</label>
              <input type="number" id="birth_year" name="birth_year" value="<?= e(old('birth_year')) ?>" min="1930" max="<?= date('Y') ?>" required>
              <?php if (isset($errors['birth_year'])): ?><span class="field__error"><?= e($errors['birth_year']) ?></span><?php endif; ?>
            </div>
            <div class="field">
              <label for="gender">Gènere</label>
              <select id="gender" name="gender">
                <option value="">—</option>
                <?php foreach (\Cros\Models\Registration::GENDERS as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= old('gender') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="category_id">Categoria</label>
              <select id="category_id" name="category_id">
                <option value="">Assignar automàticament</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= (int) $category['id'] ?>" <?= (string) old('category_id') === (string) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?><?= $category['year_from'] ? ' (' . e(min((int) $category['year_from'], (int) $category['year_to'])) . '–' . e(max((int) $category['year_from'], (int) $category['year_to'])) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="field">
              <label for="school">Escola o club</label>
              <input type="text" id="school" name="school" value="<?= e(old('school', 'Escola La Granada')) ?>">
            </div>
            <div class="field">
              <label for="class_group">Curs</label>
              <select id="class_group" name="class_group">
                <option value="">—</option>
                <?php foreach (\Cros\Models\Registration::COURSES as $course): ?>
                  <option value="<?= e($course) ?>" <?= old('class_group') === $course ? 'selected' : '' ?>><?= e($course) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="shirt_size">Talla de samarreta</label>
              <select id="shirt_size" name="shirt_size">
                <option value="">—</option>
                <?php foreach ($sizes as $size): ?>
                  <option value="<?= e($size) ?>" <?= old('shirt_size') === $size ? 'selected' : '' ?>><?= e($size) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <h2 style="margin-top:1.5rem">Dades de contacte</h2>
          <div class="form-row">
            <div class="field<?= isset($errors['tutor_name']) ? ' field--error' : '' ?>">
              <label for="tutor_name">Nom de la persona de contacte *</label>
              <input type="text" id="tutor_name" name="tutor_name" value="<?= e(old('tutor_name')) ?>" required>
              <span class="field__hint">Mare, pare o tutor/a en el cas de menors.</span>
              <?php if (isset($errors['tutor_name'])): ?><span class="field__error"><?= e($errors['tutor_name']) ?></span><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['tutor_email']) ? ' field--error' : '' ?>">
              <label for="tutor_email">Correu electrònic *</label>
              <input type="email" id="tutor_email" name="tutor_email" value="<?= e(old('tutor_email')) ?>" required>
              <?php if (isset($errors['tutor_email'])): ?><span class="field__error"><?= e($errors['tutor_email']) ?></span><?php endif; ?>
            </div>
            <div class="field">
              <label for="tutor_phone">Telèfon</label>
              <input type="tel" id="tutor_phone" name="tutor_phone" value="<?= e(old('tutor_phone')) ?>">
            </div>
          </div>
          <div class="field">
            <label for="notes">Observacions (al·lèrgies, necessitats especials…)</label>
            <textarea id="notes" name="notes" rows="3"><?= e(old('notes')) ?></textarea>
          </div>

          <label class="checkbox<?= isset($errors['consent_data']) ? ' field--error' : '' ?>">
            <input type="checkbox" name="consent_data" value="1" required>
            <span><?= e(setting('registrations_consent', '')) ?> *</span>
          </label>
          <?php if (isset($errors['consent_data'])): ?><span class="field__error"><?= e($errors['consent_data']) ?></span><?php endif; ?>
          <label class="checkbox">
            <input type="checkbox" name="consent_image" value="1">
            <span><?= e(setting('registrations_image_consent', '')) ?></span>
          </label>

          <button class="btn" type="submit" style="justify-self:start">
            <?= \Cros\Core\Icons::svg('check', 'icon', 18) ?> Enviar la inscripció
          </button>
        </form>
    </div>

    <aside>
      <div class="card">
        <h3><?= \Cros\Core\Icons::svg('info', 'icon', 20) ?> Recorda</h3>
        <ul style="padding-left:1.1rem;color:var(--ink-soft);font-size:.95rem">
          <li>Cal omplir un formulari per cada participant.</li>
          <li>Els dorsals es recullen el mateix dia a la zona esportiva.</li>
          <li>Arribeu 15 minuts abans de la vostra sortida.</li>
          <li>El punt de recàrrega té tiquet a part: <a href="<?= e(url('/punt-de-recarrega')) ?>">mira-ho aquí</a>.</li>
        </ul>
      </div>
      <div class="card" style="margin-top:1.2rem">
        <h3><?= \Cros\Core\Icons::svg('clock', 'icon', 20) ?> Termini</h3>
        <p style="margin:0">
          <?php $close = (string) setting('registrations_close_at', ''); ?>
          <?= $close !== '' ? 'Inscripcions en línia fins al ' . e(ca_date($close)) . '.' : 'Inscripcions obertes.' ?>
        </p>
      </div>
    </aside>
  </div>
</section>

<?php endif; ?>
