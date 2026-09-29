<?php
/**
 * Renderitza un camp de formulari a partir de la seva definició.
 * @var string $name
 * @var array  $field
 * @var mixed  $value
 */
$type = $field['type'] ?? 'text';
$id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
$error = $error ?? '';
$label = $field['label'] ?? $name;
$hint = $field['help'] ?? '';
$placeholder = $field['placeholder'] ?? '';
$required = str_contains((string) ($field['rules'] ?? ''), 'required');
?>
<div class="field"<?= isset($field['show_if']) ? ' data-show-if="' . e($field['show_if']) . '"' : '' ?>>
  <?php if ($type === 'bool'): ?>
    <label class="switch" for="<?= e($id) ?>">
      <input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1" <?= (string) $value === '1' ? 'checked' : '' ?>>
      <span><?= e($label) ?></span>
    </label>
  <?php else: ?>
    <label for="<?= e($id) ?>"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
  <?php endif; ?>

  <?php if ($hint !== '' && $type === 'course_laps'): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>

  <?php switch ($type):
      case 'textarea': ?>
        <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) ($field['rows'] ?? 4) ?>" placeholder="<?= e($placeholder) ?>"><?= e((string) $value) ?></textarea>
      <?php break; ?>

      <?php case 'html': ?>
        <?= \Cros\Core\View::partial('admin/partials/editor', [
            'name' => $name,
            'value' => (string) $value,
            'id' => $id,
            'label' => $label,
            'rows' => (int) ($field['rows'] ?? 6),
            'placeholder' => $placeholder !== '' ? $placeholder : 'Escriviu aquí el contingut…',
        ]) ?>
        <span class="hint">
          S'escriu tal com quedarà. Amb el botó <strong>&lt;/&gt; HTML</strong> podeu veure i editar el codi.
          <?= $hint !== '' ? e($hint) : '' ?>
        </span>
      <?php break; ?>

      <?php case 'select': ?>
        <select id="<?= e($id) ?>" name="<?= e($name) ?>">
          <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
            <option value="<?= e((string) $optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
          <?php endforeach; ?>
        </select>
      <?php break; ?>

      <?php case 'relation': ?>
        <?php
        $relation = $field['relation'] ?? [];
        $options = \Cros\Core\Db::all('SELECT id, `' . ($relation['label'] ?? 'name') . '` AS label FROM `' . ($relation['table'] ?? '') . '` ORDER BY label ASC');
        ?>
        <select id="<?= e($id) ?>" name="<?= e($name) ?>">
          <option value=""><?= e($field['empty'] ?? '—') ?></option>
          <?php foreach ($options as $option): ?>
            <option value="<?= (int) $option['id'] ?>" <?= (string) $value === (string) $option['id'] ? 'selected' : '' ?>><?= e($option['label']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php break; ?>

      <?php case 'icon': ?>
        <select id="<?= e($id) ?>" name="<?= e($name) ?>">
          <?php foreach (\Cros\Core\Icons::names() as $iconName): ?>
            <option value="<?= e($iconName) ?>" <?= (string) $value === $iconName ? 'selected' : '' ?>><?= e($iconName) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint flex">Actual: <?= \Cros\Core\Icons::svg((string) ($value ?: 'info'), 'icon', 22) ?></span>
      <?php break; ?>

      <?php case 'image': case 'file': ?>
        <div class="image-field">
          <div class="image-field__preview" id="<?= e($id) ?>_preview">
            <?php if ($value): ?>
              <?php if ($type === 'image'): ?>
                <img src="<?= e(upload_url((string) $value)) ?>" alt="">
              <?php else: ?>
                <a href="<?= e(upload_url((string) $value)) ?>" target="_blank" style="font-size:.8rem;text-align:center;padding:.4rem"><?= e(basename((string) $value)) ?></a>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-soft" style="font-size:.8rem">Sense fitxer</span>
            <?php endif; ?>
          </div>
          <div class="image-field__controls">
            <?php $maxima = \Cros\Core\Uploader::serverLimit(); ?>
            <input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>"
                   accept="<?= e($field['accept'] ?? ($type === 'image' ? 'image/*' : '.pdf')) ?>"
                   data-max-bytes="<?= (int) $maxima ?>"
                   data-preview="#<?= e($id) ?>_preview">
            <small class="text-soft">Fins a <?= e(\Cros\Core\Uploader::serverLimitLabel()) ?> per fitxer.</small>
            <?php if ($value): ?>
              <label class="switch"><input type="checkbox" name="<?= e($name) ?>_remove" value="1"> <span>Esborrar el fitxer actual</span></label>
            <?php endif; ?>
          </div>
        </div>
      <?php break; ?>

      <?php case 'course_laps': ?>
        <?php
        $courses = \Cros\Core\Db::all('SELECT id, name FROM courses ORDER BY sort_order ASC, name ASC');
        // Si el formulari torna amb errors, es conserva el que hi havia escrit.
        $rows = [];
        if (isset($_POST[$name]) && is_array($_POST[$name])) {
            foreach ($_POST[$name] as $index => $courseId) {
                $rows[] = ['course_id' => (int) $courseId, 'laps' => (int) ($_POST[$name . '_laps'][$index] ?? 1)];
            }
        } elseif (is_array($value)) {
            $rows = $value;
        }
        $rows[] = ['course_id' => 0, 'laps' => 1]; // fila buida per afegir-ne un més
        ?>
        <div class="laps" data-laps>
          <?php foreach ($rows as $row): ?>
            <div class="laps__row" data-laps-row>
              <select name="<?= e($name) ?>[]" aria-label="Recorregut">
                <option value="">— Cap —</option>
                <?php foreach ($courses as $course): ?>
                  <option value="<?= (int) $course['id'] ?>" <?= (int) ($row['course_id'] ?? 0) === (int) $course['id'] ? 'selected' : '' ?>>
                    <?= e($course['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <label class="laps__laps">
                <input type="number" name="<?= e($name) ?>_laps[]" value="<?= (int) ($row['laps'] ?? 1) ?>" min="1" max="99" aria-label="Voltes">
                <span>voltes</span>
              </label>
              <div class="laps__actions">
                <button type="button" class="btn btn--ghost btn--sm" data-laps-up title="Puja">↑</button>
                <button type="button" class="btn btn--ghost btn--sm" data-laps-down title="Baixa">↓</button>
                <button type="button" class="btn btn--ghost btn--sm" data-laps-remove title="Treure">×</button>
              </div>
            </div>
          <?php endforeach; ?>
          <button type="button" class="btn btn--ghost btn--sm" data-laps-add>+ Afegir un recorregut</button>
        </div>
      <?php break; ?>

      <?php case 'color': ?>
        <div class="flex">
          <input type="color" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) ($value ?: '#2f6b3c')) ?>">
          <span class="mono text-soft"><?= e((string) $value) ?></span>
        </div>
      <?php break; ?>

      <?php case 'money': ?>
        <input type="text" id="<?= e($id) ?>" name="<?= e($name) ?>" inputmode="decimal"
               value="<?= e(number_format(((int) $value) / 100, 2, ',', '')) ?>" placeholder="0,00">
        <span class="hint">Import en euros (p. ex. 3,50).</span>
      <?php break; ?>

      <?php case 'slug': ?>
        <input type="text" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
               data-slug-target="#f_<?= e($field['source'] ?? 'name') ?>">
      <?php break; ?>

      <?php case 'password': ?>
        <input type="password" id="<?= e($id) ?>" name="<?= e($name) ?>" value="" placeholder="<?= e($value ? \Cros\Core\Crypto::mask((string) $value) : ($placeholder ?: '')) ?>" autocomplete="new-password">
        <?php if ($value): ?><span class="hint">Deixeu-ho buit per conservar el valor actual.</span><?php endif; ?>
      <?php break; ?>

      <?php case 'bool': break; ?>

      <?php case 'faqs': ?>
        <?php
        // Una llista de preguntes i respostes. Es desa com un sol valor (JSON)
        // perquè no calgui cap taula per a una llista que sempre és curta.
        $rows = json_decode((string) $value, true);
        $rows = is_array($rows) ? array_values($rows) : [];
        $rows[] = ['q' => '', 'a' => '']; // sempre una de buida per afegir-ne
        ?>
        <div class="faq-rows" data-faq-rows>
          <?php foreach ($rows as $i => $row): ?>
            <div class="faq-row">
              <input type="text" name="<?= e($name) ?>_q[]" value="<?= e((string) ($row['q'] ?? '')) ?>"
                     placeholder="La pregunta">
              <textarea name="<?= e($name) ?>_a[]" rows="3" placeholder="La resposta"><?= e((string) ($row['a'] ?? '')) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>
        <span class="hint">
          Les que deixeu en blanc no es desen. Per treure'n una, buideu-li la pregunta i deseu.
          <?= $hint !== '' ? e($hint) : '' ?>
        </span>
      <?php break; ?>

      <?php case 'features': ?>
        <?php
        // Les funcionalitats que s'ensenyen al web: icona, títol i explicació.
        // Es desen igual que les preguntes freqüents, com un sol valor en JSON:
        // per a una llista que s'edita sencera d'una tirada no cal cap taula.
        $rows = json_decode((string) $value, true);
        $rows = is_array($rows) ? array_values($rows) : [];
        $rows[] = ['icon' => '', 'title' => '', 'text' => ''];
        $icones = \Cros\Core\Icons::names();
        ?>
        <div class="faq-rows" data-feature-rows>
          <?php foreach ($rows as $i => $row): ?>
            <div class="feature-row">
              <select name="<?= e($name) ?>_icon[]" aria-label="Icona">
                <option value="">Sense icona</option>
                <?php foreach ($icones as $icona): ?>
                  <option value="<?= e($icona) ?>" <?= (string) ($row['icon'] ?? '') === $icona ? 'selected' : '' ?>>
                    <?= e($icona) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="<?= e($name) ?>_title[]" value="<?= e((string) ($row['title'] ?? '')) ?>"
                     placeholder="El que sap fer">
              <textarea name="<?= e($name) ?>_text[]" rows="3" placeholder="Una explicació curta"><?= e((string) ($row['text'] ?? '')) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>
        <span class="hint">
          Les que deixeu sense títol no es desen. Per treure'n una, buideu-li el títol i deseu.
          <?= $hint !== '' ? e($hint) : '' ?>
        </span>
      <?php break; ?>

      <?php case 'sport_icon': ?>
        <?php
        // Un mosaic per triar: amb una llista desplegable de noms ningú no
        // sabria quina és quina fins a desar-ho i mirar el web.
        $triada = \Cros\Core\Icons::isSport((string) $value) ? (string) $value : 'run';
        ?>
        <div class="icon-picker">
          <?php foreach (\Cros\Core\Icons::sports() as $clau => $nom): ?>
            <label class="icon-picker__option<?= $triada === $clau ? ' is-chosen' : '' ?>" title="<?= e($nom) ?>">
              <input type="radio" name="<?= e($name) ?>" value="<?= e($clau) ?>" <?= $triada === $clau ? 'checked' : '' ?>>
              <?= \Cros\Core\Icons::svg($clau, 'icon', 26) ?>
              <span><?= e($nom) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php break; ?>

      <?php default: ?>
        <input type="<?= e(in_array($type, ['email', 'url', 'tel', 'number', 'date', 'time'], true) ? $type : 'text') ?>"
               id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
               placeholder="<?= e($placeholder) ?>" <?= $required ? 'required' : '' ?>
               <?= $type === 'number' ? 'step="' . e((string) ($field['step'] ?? '1')) . '"' : '' ?>
               <?= isset($field['min']) ? 'min="' . e((string) $field['min']) . '"' : '' ?>
               <?= isset($field['max']) ? 'max="' . e((string) $field['max']) . '"' : '' ?>>
  <?php endswitch; ?>

  <?php if ($hint !== '' && !in_array($type, ['html', 'money', 'course_laps', 'faqs'], true)): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
  <?php if ($error !== ''): ?><span class="error"><?= e($error) ?></span><?php endif; ?>
</div>
