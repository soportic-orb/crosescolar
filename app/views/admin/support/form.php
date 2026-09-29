<?php
/**
 * Obrir una consulta al suport.
 * @var array<string,mixed> $row
 * @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $departments
 * @var string $intro
 * @var string $hours
 */
use Cros\Platform\Support;
?>
<form method="post" action="<?= e(url('/admin/suport/nou')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2>Nova consulta</h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/suport')) ?>">← Totes les consultes</a>
    </div>
    <div class="panel__body form-stack">
      <?php if (trim($intro) !== ''): ?>
        <div class="alert alert--info"><?= nl2br(e($intro)) ?></div>
      <?php endif; ?>

      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="department_id">Departament</label>
          <select id="department_id" name="department_id">
            <option value="0">El que toqui</option>
            <?php foreach ($departments as $department): ?>
              <option value="<?= (int) $department['id'] ?>" <?= (int) ($row['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
                <?= e($department['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php
          $chosen = null;
          foreach ($departments as $department) {
              if ((int) $department['id'] === (int) ($row['department_id'] ?? 0)) {
                  $chosen = $department;
              }
          }
          ?>
          <span class="hint"><?= e($chosen['description'] ?? 'Si no ho teniu clar, deixeu-ho com està i ja ho encaminarem nosaltres.') ?></span>
        </div>
        <div class="field">
          <label for="priority">Quina pressa corre</label>
          <select id="priority" name="priority">
            <?php foreach (Support::PRIORITIES as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string) ($row['priority'] ?? 'normal') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">«Urgent» és per al que impedeix fer anar el web, no per al que corre pressa a tothom.</span>
        </div>
      </div>

      <div class="field">
        <label for="subject">Assumpte *</label>
        <input type="text" id="subject" name="subject" value="<?= e($row['subject'] ?? '') ?>"
               maxlength="190" required placeholder="No puc canviar la data de la cursa">
        <?php if (isset($errors['subject'])): ?><span class="error"><?= e($errors['subject']) ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="body">Què us passa *</label>
        <?= \Cros\Core\View::partial('admin/partials/editor', [
            'name' => 'body',
            'value' => (string) ($row['body'] ?? ''),
            'id' => 'body',
            'label' => 'Explicació de la consulta',
            'rows' => 10,
            'placeholder' => 'Expliqueu què volíeu fer, què heu fet i què ha passat…',
        ]) ?>
        <span class="hint">Com més concret, abans us podrem contestar: on éreu, què heu clicat i què us ha sortit.</span>
        <?php if (isset($errors['body'])): ?><span class="error"><?= e($errors['body']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="panel__foot">
      <?php if (trim($hours) !== ''): ?><span class="text-soft"><?= e($hours) ?></span><?php endif; ?>
      <button class="btn spacer" type="submit">Enviar la consulta</button>
    </div>
  </div>
</form>
