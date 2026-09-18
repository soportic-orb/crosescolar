<?php
/** Llistat d'inscripcions. */
use Cros\Core\Icons;
?>
<div class="panel mb-2">
  <div class="panel__head">
    <h2>Inscripcions (<?= (int) $total ?>)</h2>
    <div class="spacer"></div>
    <form method="get" class="filters">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Nom, correu, escola…">
      <select name="categoria">
        <option value="0">Totes les categories</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--sm" type="submit">Filtrar</button>
    </form>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/inscripcions/exportar')) ?>"><?= Icons::svg('download', 'icon', 15) ?> CSV</a>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/inscripcions/dorsals', array_filter(['categoria' => $categoryId]))) ?>">
      <?= Icons::svg('flag', 'icon', 15) ?> Dorsals en PDF
    </a>
    <form method="post" action="<?= e(url('/admin/inscripcions/assignar-dorsals')) ?>" style="display:inline">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit" title="Assigna número als qui encara no en tenen">Assignar dorsals</button>
    </form>
    <a class="btn btn--sm" href="<?= e(url('/admin/inscripcions/nova')) ?>"><?= Icons::svg('plus', 'icon', 15) ?> Afegir</a>
  </div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <thead><tr><th>Dorsal</th><th>Participant</th><th>Any</th><th>Categoria</th><th>Escola</th><th>Contacte</th><th>Data</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="mono"><strong><?= e(\Cros\Models\Bib::number($row)) ?></strong>
              <div class="text-soft" style="font-size:.78rem"><?= e($row['code']) ?></div></td>
            <td><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong>
              <?php if ((int) $row['consent_image'] === 0): ?><div class="badge badge--amber">Sense dret d'imatge</div><?php endif; ?>
            </td>
            <td><?= e($row['birth_year']) ?></td>
            <td><?= e($row['category_name'] ?? '—') ?></td>
            <td><?= e($row['school'] ?? '') ?> <span class="text-soft"><?= e($row['class_group'] ?? '') ?></span></td>
            <td style="font-size:.85rem"><?= e($row['tutor_name'] ?? '') ?><div class="text-soft"><?= e($row['tutor_email'] ?? '') ?></div></td>
            <td class="text-soft" style="font-size:.84rem"><?= e(dt($row['created_at'], 'd/m H:i')) ?></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/inscripcions/' . $row['id'] . '/dorsal')) ?>" title="Descarregar el dorsal">
                <?= Icons::svg('flag', 'icon', 14) ?>
              </a>
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/inscripcions/' . $row['id'])) ?>">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-soft">Cap inscripció amb aquests filtres.</td></tr><?php endif; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === $page): ?><span class="is-active"><?= $i ?></span>
          <?php else: ?><a href="<?= e(url('/admin/inscripcions', array_filter(['p' => $i, 'q' => $search, 'categoria' => $categoryId]))) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel__head"><h2>Participants per categoria</h2></div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <tbody>
        <?php foreach ($byCategory as $item): ?>
          <tr><td><?= e($item['name']) ?></td><td class="text-right"><strong><?= (int) $item['total'] ?></strong></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
