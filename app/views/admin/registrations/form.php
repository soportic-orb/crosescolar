<?php /** Alta/edició d'inscripció. */ ?>
<form method="post" action="<?= e(url('/admin/inscripcions' . ($isNew ? '/nova' : '/' . $row['id']))) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($title) ?></h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/inscripcions')) ?>">← Totes les inscripcions</a>
    </div>
    <div class="panel__body">
      <div class="form-grid form-grid--2">
        <div class="field"><label for="first_name">Nom *</label>
          <input type="text" id="first_name" name="first_name" value="<?= e($row['first_name'] ?? '') ?>" required>
          <?php if (isset($errors['first_name'])): ?><span class="error"><?= e($errors['first_name']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label for="last_name">Cognoms *</label>
          <input type="text" id="last_name" name="last_name" value="<?= e($row['last_name'] ?? '') ?>" required>
          <?php if (isset($errors['last_name'])): ?><span class="error"><?= e($errors['last_name']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label for="birth_year">Any de naixement</label>
          <input type="number" id="birth_year" name="birth_year" value="<?= e($row['birth_year'] ?? '') ?>" min="1930" max="<?= date('Y') ?>">
        </div>
        <div class="field"><label for="gender">Gènere</label>
          <select id="gender" name="gender">
            <?php foreach (['' => '—', 'femeni' => 'Femení', 'masculi' => 'Masculí', 'altre' => 'Altre'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string) ($row['gender'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="category_id">Categoria</label>
          <select id="category_id" name="category_id">
            <option value="">Sense assignar</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id'] ?>" <?= (int) ($row['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="status">Estat</label>
          <select id="status" name="status">
            <?php foreach (['confirmed' => 'Confirmada', 'pending' => 'Pendent', 'cancelled' => 'Cancel·lada'] as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string) ($row['status'] ?? 'confirmed') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="school">Escola o club</label>
          <input type="text" id="school" name="school" value="<?= e($row['school'] ?? '') ?>"></div>
        <div class="field"><label for="class_group">Curs</label>
          <input type="text" id="class_group" name="class_group" value="<?= e($row['class_group'] ?? '') ?>"></div>
        <div class="field"><label for="shirt_size">Talla</label>
          <input type="text" id="shirt_size" name="shirt_size" value="<?= e($row['shirt_size'] ?? '') ?>"></div>
        <div class="field"><label for="tutor_name">Persona de contacte</label>
          <input type="text" id="tutor_name" name="tutor_name" value="<?= e($row['tutor_name'] ?? '') ?>"></div>
        <div class="field"><label for="tutor_email">Correu</label>
          <input type="email" id="tutor_email" name="tutor_email" value="<?= e($row['tutor_email'] ?? '') ?>">
          <?php if (isset($errors['tutor_email'])): ?><span class="error"><?= e($errors['tutor_email']) ?></span><?php endif; ?>
        </div>
        <div class="field"><label for="tutor_phone">Telèfon</label>
          <input type="tel" id="tutor_phone" name="tutor_phone" value="<?= e($row['tutor_phone'] ?? '') ?>"></div>
        <div class="field" style="grid-column:1/-1"><label for="notes">Observacions</label>
          <textarea id="notes" name="notes" rows="3"><?= e($row['notes'] ?? '') ?></textarea></div>
      </div>

      <div class="flex mt-2">
        <label class="switch"><input type="checkbox" name="consent_data" value="1" <?= (int) ($row['consent_data'] ?? 0) === 1 ? 'checked' : '' ?>> <span>Consentiment de dades</span></label>
        <label class="switch"><input type="checkbox" name="consent_image" value="1" <?= (int) ($row['consent_image'] ?? 0) === 1 ? 'checked' : '' ?>> <span>Consentiment d'imatge</span></label>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit"><?= $isNew ? 'Crear' : 'Desar' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/admin/inscripcions')) ?>">Cancel·lar</a>
        <?php if (!$isNew): ?>
          <span class="spacer"></span>
          <button class="btn btn--danger" type="submit" form="delete-registration">Esborrar</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<?php if (!$isNew): ?>
  <form id="delete-registration" method="post" action="<?= e(url('/admin/inscripcions/' . $row['id'] . '/esborrar')) ?>" data-confirm="Esborrar aquesta inscripció?">
    <?= csrf_field() ?>
  </form>
<?php endif; ?>
