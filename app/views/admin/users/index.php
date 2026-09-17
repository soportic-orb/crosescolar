<?php /** Usuaris del panell. */ ?>
<div class="panel">
  <div class="panel__head">
    <h2>Usuaris</h2>
    <a class="btn btn--sm spacer" href="<?= e(url('/admin/usuaris/nou')) ?>">Afegir usuari</a>
  </div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <thead><tr><th>Nom</th><th>Correu</th><th>Rol</th><th>Estat</th><th>Últim accés</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><strong><?= e($row['name']) ?></strong></td>
            <td><?= e($row['email']) ?></td>
            <td><span class="badge <?= $row['role'] === 'admin' ? 'badge--green' : '' ?>"><?= $row['role'] === 'admin' ? 'Administrador' : 'Editor' ?></span></td>
            <td><?= (int) $row['active'] === 1 ? '<span class="badge badge--green">Actiu</span>' : '<span class="badge badge--red">Desactivat</span>' ?></td>
            <td class="text-soft" style="font-size:.85rem"><?= e($row['last_login_at'] ? dt($row['last_login_at']) : 'Mai') ?></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/usuaris/' . $row['id'])) ?>">Editar</a>
              <form method="post" action="<?= e(url('/admin/usuaris/' . $row['id'] . '/esborrar')) ?>" style="display:inline" data-confirm="Esborrar aquest usuari?">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--sm" type="submit">Esborrar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
