<?php
/** Ordre i visibilitat dels apartats del menú del web públic. */
use Cros\Core\Icons;
?>
<form method="post" action="<?= e(url('/admin/menu')) ?>">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2>Menú del web</h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/')) ?>" target="_blank">
        <?= Icons::svg('eye', 'icon', 16) ?> Veure el web
      </a>
    </div>
    <div class="panel__body">
      <p class="text-soft" style="margin-top:0">
        Trieu quins apartats surten al menú del web i en quin ordre. El botó
        <strong>«Inscriu-te!»</strong> hi és sempre, al final, i no forma part de la llista.
      </p>

      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr>
            <th style="width:90px">Ordre</th>
            <th style="width:110px">Visible</th>
            <th>Apartat</th>
            <th>Nom al menú</th>
            <th>Adreça</th>
          </tr></thead>
          <tbody>
            <?php foreach ($items as $index => $item): ?>
              <tr<?= $item['shown'] ? '' : ' class="is-muted"' ?>>
                <td class="actions">
                  <button class="btn btn--ghost btn--sm" type="submit" title="Puja"
                          formaction="<?= e(url('/admin/menu/' . $item['key'] . '/moure')) ?>"
                          name="direccio" value="puja" <?= $index === 0 ? 'disabled' : '' ?>>↑</button>
                  <button class="btn btn--ghost btn--sm" type="submit" title="Baixa"
                          formaction="<?= e(url('/admin/menu/' . $item['key'] . '/moure')) ?>"
                          name="direccio" value="baixa" <?= $index === count($items) - 1 ? 'disabled' : '' ?>>↓</button>
                </td>
                <td>
                  <label class="switch">
                    <input type="checkbox" name="active[]" value="<?= e($item['key']) ?>"
                           <?= $item['active'] ? 'checked' : '' ?>>
                    <span class="visually-hidden">Mostrar <?= e($item['label']) ?> al menú</span>
                  </label>
                </td>
                <td>
                  <strong><?= e($item['label']) ?></strong>
                  <?php if (!$item['shown']): ?>
                    <span class="badge badge--amber">ara no es veu</span>
                  <?php endif; ?>
                  <?php if ($item['note'] !== ''): ?>
                    <div class="text-soft" style="font-size:.85rem"><?= e($item['note']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <input type="text" name="label[<?= e($item['key']) ?>]" maxlength="120"
                         value="<?= e($item['label_custom']) ?>" placeholder="<?= e($item['label']) ?>">
                </td>
                <td class="mono text-soft"><?= e($item['url']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="text-soft" style="font-size:.9rem;margin-bottom:0">
        Els apartats marcats com a «ara no es veu» no surten al web fins que la seva
        funció estigui activa, encara que aquí els marqueu com a visibles.
      </p>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Desar el menú</button>
    </div>
  </div>
</form>
