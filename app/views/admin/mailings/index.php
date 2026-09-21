<?php
/** Llistat d'enviaments de correu. */
use Cros\Core\Icons;

$labels = [
    'draft' => ['Esborrany', 'badge--amber'],
    'sending' => ['Enviant-se', 'badge--amber'],
    'sent' => ['Enviat', 'badge--green'],
];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Enviaments de correu</h2>
    <a class="btn btn--sm spacer" href="<?= e(url('/admin/enviaments/nou')) ?>">
      <?= Icons::svg('mail', 'icon', 16) ?> Nou enviament
    </a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Correus a les persones inscrites. Cada adreça en rep un de sol, encara que hi hagi
      més d'un participant a la família, i s'envien per tandes de <?= (int) \Cros\Models\Mailing::batchSize() ?>
      per no saturar el servidor.
    </p>

    <?php if (!$rows): ?>
      <p class="text-soft">Encara no heu fet cap enviament.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr>
            <th>Assumpte</th><th>Destinataris</th><th>Estat</th><th>Data</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php [$label, $class] = $labels[$row['status']] ?? ['—', '']; ?>
              <tr>
                <td>
                  <a href="<?= e(url('/admin/enviaments/' . (int) $row['id'])) ?>">
                    <strong><?= e($row['subject']) ?></strong>
                  </a>
                </td>
                <td>
                  <?php if ($row['status'] === 'draft'): ?>
                    <span class="text-soft">per preparar</span>
                  <?php else: ?>
                    <?= (int) $row['sent'] ?> de <?= (int) $row['total'] ?>
                    <?php if ((int) $row['failed'] > 0): ?>
                      <span class="badge badge--red"><?= (int) $row['failed'] ?> amb error</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td><span class="badge <?= e($class) ?>"><?= e($label) ?></span></td>
                <td class="text-soft"><?= e(dt($row['finished_at'] ?: $row['created_at'])) ?></td>
                <td class="actions">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enviaments/' . (int) $row['id'])) ?>">Obrir</a>
                  <form method="post" style="display:inline"
                        action="<?= e(url('/admin/enviaments/' . (int) $row['id'] . '/esborrar')) ?>"
                        data-confirm="Esborrar aquest enviament i el seu historial?">
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
