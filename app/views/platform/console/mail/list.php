<?php
/**
 * Una llista i les seves adreces.
 * @var array<string,mixed> $list
 * @var array<int,array<string,mixed>> $contacts
 */
use Cros\Core\Icons;

$id = (int) $list['id'];
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($list['name']) ?></h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/llistes', ['editar' => $id])) ?>">
        <?= Icons::svg('edit', 'icon', 15) ?> Canviar-ne el nom
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/llistes')) ?>">← Totes les llistes</a>
    </div>
  </div>
  <div class="panel__body">
    <?php if (!empty($list['description'])): ?>
      <p class="text-soft" style="margin-top:0"><?= e($list['description']) ?></p>
    <?php endif; ?>
    <?php if (!$contacts): ?>
      <p class="text-soft">Aquesta llista encara és buida. Enganxeu-hi adreces aquí sota.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Adreça</th><th>Nom</th><th>Entitat</th><th>Estat</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($contacts as $contact): ?>
              <tr>
                <td class="mono"><?= e($contact['email']) ?></td>
                <td><?= e($contact['name'] ?? '') ?></td>
                <td class="text-soft"><?= e($contact['entity'] ?? '') ?></td>
                <td>
                  <span class="badge badge--<?= !empty($contact['active']) ? 'green' : '' ?>">
                    <?= !empty($contact['active']) ? 'Rep correus' : 'De baixa' ?>
                  </span>
                </td>
                <td class="text-right">
                  <form method="post" style="display:inline"
                        action="<?= e(url('/enviaments/contactes/' . (int) $contact['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="list_id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="toggle">
                    <button class="btn btn--ghost btn--sm" type="submit">
                      <?= !empty($contact['active']) ? 'Donar de baixa' : 'Tornar a donar d\'alta' ?>
                    </button>
                  </form>
                  <form method="post" style="display:inline"
                        action="<?= e(url('/enviaments/contactes/' . (int) $contact['id'])) ?>"
                        data-confirm="Treure aquesta adreça de la llista?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="list_id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="remove">
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
  <div class="panel__head"><h2>Afegir adreces</h2></div>
  <form method="post" action="<?= e(url('/enviaments/llistes/' . $id . '/contactes')) ?>">
    <?= csrf_field() ?>
    <div class="panel__body">
      <div class="field">
        <label for="contacts">Enganxeu-les aquí, una per línia</label>
        <textarea id="contacts" name="contacts" rows="8"
                  placeholder="anna@example.cat&#10;Pau Soler &lt;pau@example.cat&gt;&#10;marta@example.cat; Marta Vila; AFA Sant Jordi"></textarea>
        <span class="hint">
          S'accepta l'adreça sola, la forma <code>Nom &lt;adreça&gt;</code> que dona el correu i les
          columnes d'un full de càlcul separades per comes, punts i comes o tabuladors: la que sigui
          una adreça és l'adreça, i les altres dues el nom i l'entitat. Les repetides no es dupliquen.
        </span>
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Afegir-les</button>
    </div>
  </form>
</div>
