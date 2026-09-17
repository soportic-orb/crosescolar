<?php /** Formulari genèric d'alta/edició. */ ?>
<form method="post" enctype="multipart/form-data" data-dirty-check
      action="<?= e(url('/admin/contingut/' . $resource['key'] . ($isNew ? '/nou' : '/' . $row['id']))) ?>">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($title) ?></h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/contingut/' . $resource['key'])) ?>">← Tornar al llistat</a>
    </div>
    <div class="panel__body">
      <div class="form-grid form-grid--2">
        <?php foreach ($resource['fields'] as $name => $field): ?>
          <?php $fullWidth = in_array($field['type'] ?? 'text', ['html', 'textarea'], true); ?>
          <div style="<?= $fullWidth ? 'grid-column:1/-1' : '' ?>">
            <?= \Cros\Core\View::partial('admin/partials/field', [
                'name' => $name,
                'field' => $field,
                'value' => $row[$name] ?? ($field['default'] ?? ''),
                'error' => $errors[$name] ?? '',
            ]) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="form-actions">
        <button class="btn" type="submit"><?= $isNew ? 'Crear' : 'Desar els canvis' ?></button>
        <a class="btn btn--ghost" href="<?= e(url('/admin/contingut/' . $resource['key'])) ?>">Cancel·lar</a>
        <span class="spacer"></span>
        <?php if (!$isNew): ?>
          <button class="btn btn--ghost" type="submit" form="duplicate-form">Duplicar</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<?php if (!$isNew): ?>
  <form id="duplicate-form" method="post" action="<?= e(url('/admin/contingut/' . $resource['key'] . '/' . $row['id'] . '/duplicar')) ?>">
    <?= csrf_field() ?>
  </form>
<?php endif; ?>
