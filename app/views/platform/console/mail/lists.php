<?php
/**
 * Les llistes de correu.
 * @var array<int,array<string,mixed>> $lists
 * @var array<string,mixed>|null $edit
 */
use Cros\Core\Icons;

$row = $edit ?? ['id' => 0, 'name' => '', 'description' => ''];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Llistes de correu</h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/enviaments')) ?>">← Els enviaments</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Serveixen per escriure a gent que encara no és clienta: una associació de mestres,
      els contactes d'una fira, les escoles d'una comarca. Els clients i les
      administradores dels webs ja se saben i no cal apuntar-los aquí.
    </p>
    <?php if (!$lists): ?>
      <p class="text-soft">Encara no n'hi ha cap.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Nom</th><th>Adreces</th><th>Creada</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($lists as $list): ?>
              <tr>
                <td>
                  <a href="<?= e(url('/enviaments/llistes/' . (int) $list['id'])) ?>"><strong><?= e($list['name']) ?></strong></a>
                  <?php if (!empty($list['description'])): ?>
                    <br><small class="text-soft"><?= e($list['description']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?= (int) $list['active_contacts'] ?>
                  <?php if ((int) $list['contacts'] > (int) $list['active_contacts']): ?>
                    <small class="text-soft">(<?= (int) $list['contacts'] - (int) $list['active_contacts'] ?> de baixa)</small>
                  <?php endif; ?>
                </td>
                <td class="text-soft"><?= e(ca_date(substr((string) $list['created_at'], 0, 10))) ?></td>
                <td class="text-right">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/llistes/' . (int) $list['id'])) ?>">Obrir</a>
                  <form method="post" style="display:inline"
                        action="<?= e(url('/enviaments/llistes/' . (int) $list['id'] . '/esborrar')) ?>"
                        data-confirm="Esborrar la llista i totes les seves adreces?">
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
  <div class="panel__head"><h2><?= (int) $row['id'] > 0 ? 'Editar la llista' : 'Llista nova' ?></h2></div>
  <form method="post" action="<?= e(url('/enviaments/llistes/desar')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <div class="panel__body form-grid form-grid--2">
      <div class="field">
        <label for="name">Nom *</label>
        <input type="text" id="name" name="name" value="<?= e($row['name']) ?>" maxlength="150" required
               placeholder="Escoles del Penedès">
      </div>
      <div class="field">
        <label for="description">Per a què és</label>
        <input type="text" id="description" name="description" value="<?= e($row['description'] ?? '') ?>" maxlength="255"
               placeholder="Contactes de la fira d'entitats del 2026">
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Desar</button>
      <?php if ((int) $row['id'] > 0): ?>
        <a class="btn btn--ghost" href="<?= e(url('/enviaments/llistes')) ?>">Cancel·lar</a>
      <?php endif; ?>
    </div>
  </form>
</div>
