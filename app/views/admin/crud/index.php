<?php
/** Llistat genèric d'un recurs. */
use Cros\Core\Icons;
$currency = (string) setting('payments_currency', 'EUR');
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($resource['title']) ?> <span class="text-soft" style="font-weight:400">(<?= count($rows) ?>)</span></h2>
    <div class="spacer"></div>
    <?php if (!empty($resource['search'])): ?>
      <form method="get" class="filters">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cercar…">
        <button class="btn btn--ghost btn--sm" type="submit"><?= Icons::svg('search', 'icon', 16) ?></button>
      </form>
    <?php endif; ?>
    <a class="btn btn--sm" href="<?= e(url('/admin/contingut/' . $resource['key'] . '/nou')) ?>">
      <?= Icons::svg('plus', 'icon', 16) ?> Afegir
    </a>
  </div>

  <div class="panel__body">
    <?php if (!empty($resource['description'])): ?>
      <p class="text-soft" style="margin-top:0"><?= e($resource['description']) ?></p>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="empty-state">
        <?= Icons::svg((string) $resource['icon'], 'icon', 42) ?>
        <p>Encara no hi ha cap <?= e($resource['singular']) ?>.</p>
        <a class="btn" href="<?= e(url('/admin/contingut/' . $resource['key'] . '/nou')) ?>">Afegir-ne <?= e($resource['singular']) ?></a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table" data-sortable="<?= e(url('/admin/contingut/' . $resource['key'] . '/ordre')) ?>" data-token="<?= e(csrf_token()) ?>">
          <thead>
            <tr>
              <th style="width:28px"></th>
              <?php foreach ($resource['columns'] as $column => $label): ?>
                <th><?= e($label) ?></th>
              <?php endforeach; ?>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr draggable="true" data-id="<?= (int) $row['id'] ?>">
                <td class="drag-handle" title="Arrossegueu per ordenar">⋮⋮</td>
                <?php foreach ($resource['columns'] as $column => $label): ?>
                  <td>
                    <?php
                    $value = $row[$column] ?? '';
                    $fieldType = $resource['fields'][$column]['type'] ?? 'text';
                    if ($column === 'years') {
                        $years = \Cros\Models\Content::years($row);
                        echo $years !== ''
                            ? e($years)
                            : '<span class="text-soft">—</span>';
                    } elseif ($column === 'winners') {
                        // Els premiats només tenen sentit si la categoria mostra medalles.
                        echo (int) ($row['medals'] ?? 1) === 1
                            ? e((string) (int) $value)
                            : '<span class="text-soft">Sense medalles</span>';
                    } elseif ($column === 'sold') {
                        echo (int) ($row['sold'] ?? 0);
                    } elseif ($fieldType === 'image' || ($column === 'logo' || $column === 'file')) {
                        echo $value
                            ? '<img class="thumb" src="' . e(upload_url((string) $value)) . '" alt="">'
                            : '<span class="text-soft">—</span>';
                    } elseif ($fieldType === 'bool') {
                        echo ((int) $value === 1)
                            ? '<span class="badge badge--green">Sí</span>'
                            : '<span class="badge">No</span>';
                    } elseif ($fieldType === 'money') {
                        echo e(money((int) $value, $currency));
                    } elseif ($fieldType === 'icon') {
                        echo Icons::svg((string) ($value ?: 'info'), 'icon', 20);
                    } elseif ($fieldType === 'select') {
                        echo e($resource['fields'][$column]['options'][(string) $value] ?? (string) $value);
                    } elseif ($column === 'distance_m') {
                        echo $value ? e(number_format((int) $value / 1000, 1, ',', '.')) . ' km' : '<span class="text-soft">—</span>';
                    } elseif ($column === 'stock') {
                        echo $value === null || $value === '' ? '<span class="text-soft">Sense límit</span>' : (int) $value;
                    } else {
                        echo $value !== '' && $value !== null ? e(excerpt((string) $value, 70)) : '<span class="text-soft">—</span>';
                    }
                    ?>
                  </td>
                <?php endforeach; ?>
                <td class="actions">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/contingut/' . $resource['key'] . '/' . $row['id'])) ?>">
                    <?= Icons::svg('edit', 'icon', 15) ?> Editar
                  </a>
                  <form method="post" style="display:inline" action="<?= e(url('/admin/contingut/' . $resource['key'] . '/' . $row['id'] . '/esborrar')) ?>"
                        data-confirm="Segur que voleu esborrar aquest element?">
                    <?= csrf_field() ?>
                    <button class="btn btn--danger btn--sm" type="submit"><?= Icons::svg('trash', 'icon', 15) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="text-soft mt-2" style="font-size:.85rem">Consell: arrossegueu les files per canviar l'ordre en què es mostren al web.</p>
    <?php endif; ?>
  </div>
</div>
