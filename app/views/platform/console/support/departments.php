<?php
/**
 * Els departaments del suport.
 * @var array<int,array<string,mixed>> $departments
 * @var array<string,mixed>|null $edit
 */
use Cros\Core\Icons;

$row = $edit ?? ['id' => 0, 'name' => '', 'description' => '', 'email' => '', 'sort_order' => 0, 'active' => 1];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Departaments</h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/suport')) ?>">← La safata</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Són les caselles que tria el client quan obre una consulta. Cadascun pot tenir la
      seva adreça d'avís; si no en té, els avisos van a la del correu de la plataforma.
      Un departament que ja no es fa servir val més desactivar-lo que esborrar-lo: així
      les consultes que hi havia es queden on eren.
    </p>
    <?php if (!$departments): ?>
      <p class="text-soft">Encara no n'hi ha cap.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Ordre</th><th>Nom</th><th>Avisos a</th><th>Estat</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($departments as $department): ?>
              <tr>
                <td class="text-soft"><?= (int) $department['sort_order'] ?></td>
                <td>
                  <strong><?= e($department['name']) ?></strong>
                  <?php if (!empty($department['description'])): ?>
                    <br><small class="text-soft"><?= e($department['description']) ?></small>
                  <?php endif; ?>
                </td>
                <td class="text-soft"><?= e($department['email'] ?: '—') ?></td>
                <td>
                  <span class="badge badge--<?= !empty($department['active']) ? 'green' : '' ?>">
                    <?= !empty($department['active']) ? 'Actiu' : 'Desactivat' ?>
                  </span>
                </td>
                <td class="text-right">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/suport/departaments', ['editar' => (int) $department['id']])) ?>">Editar</a>
                  <form method="post" style="display:inline"
                        action="<?= e(url('/suport/departaments/' . (int) $department['id'] . '/esborrar')) ?>"
                        data-confirm="Esborrar aquest departament? Les consultes que hi havia es quedaran sense departament.">
                    <?= csrf_field() ?>
                    <button class="btn btn--danger btn--sm" type="submit"><?= Icons::svg('trash', 'icon', 14) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2><?= (int) $row['id'] > 0 ? 'Editar el departament' : 'Departament nou' ?></h2></div>
  <form method="post" action="<?= e(url('/suport/departaments/desar')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <div class="panel__body form-grid form-grid--2">
      <div class="field">
        <label for="name">Nom *</label>
        <input type="text" id="name" name="name" value="<?= e($row['name']) ?>" maxlength="120" required
               placeholder="Inscripcions i dorsals">
      </div>
      <div class="field">
        <label for="email">Avisos a</label>
        <input type="email" id="email" name="email" value="<?= e($row['email'] ?? '') ?>" maxlength="190"
               placeholder="suport@crosescolar.cat">
        <span class="hint">On arriba el correu quan s'obre una consulta d'aquest departament.</span>
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="description">Per a què serveix</label>
        <input type="text" id="description" name="description" value="<?= e($row['description'] ?? '') ?>" maxlength="255"
               placeholder="Dubtes sobre les inscripcions, els dorsals i els resultats.">
        <span class="hint">Es llegeix sota la casella quan el client el tria.</span>
      </div>
      <div class="field">
        <label for="sort_order">Ordre</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= (int) $row['sort_order'] ?>">
      </div>
      <div class="field">
        <label class="switch">
          <input type="checkbox" name="active" value="1" <?= !empty($row['active']) ? 'checked' : '' ?>>
          <span>Els clients el poden triar</span>
        </label>
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Desar</button>
      <?php if ((int) $row['id'] > 0): ?>
        <a class="btn btn--ghost" href="<?= e(url('/suport/departaments')) ?>">Cancel·lar</a>
      <?php endif; ?>
    </div>
  </form>
</div>
