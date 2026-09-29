<?php
/**
 * Llistat d'enviaments de la plataforma.
 * @var array<int,array<string,mixed>> $rows
 * @var array<int,array<string,mixed>> $lists
 */
use Cros\Core\Icons;
use Cros\Platform\Mailout;

$labels = [
    'draft' => ['Esborrany', 'badge--amber'],
    'sending' => ['Enviant-se', 'badge--amber'],
    'sent' => ['Enviat', 'badge--green'],
];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Enviaments</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/llistes')) ?>">
        <?= Icons::svg('users', 'icon', 15) ?> Llistes (<?= count($lists) ?>)
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/plantilla')) ?>">
        <?= Icons::svg('file', 'icon', 15) ?> Plantilla
      </a>
      <a class="btn btn--sm" href="<?= e(url('/enviaments/nou')) ?>">
        <?= Icons::svg('mail', 'icon', 15) ?> Nou enviament
      </a>
    </div>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      Correus als clients, a les administradores de cada web o a una llista feta a mà.
      S'envien per tandes de <?= (int) Mailout::batchSize() ?> per no saturar el servidor, i
      cada adreça en rep un de sol encara que surti a més d'un lloc.
    </p>

    <?php if (!$rows): ?>
      <p class="text-soft">Encara no heu fet cap enviament.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Assumpte</th><th>A qui</th><th>Destinataris</th><th>Estat</th><th>Data</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php [$label, $class] = $labels[$row['status']] ?? ['—', '']; ?>
              <tr>
                <td><a href="<?= e(url('/enviaments/' . (int) $row['id'])) ?>"><strong><?= e($row['subject']) ?></strong></a></td>
                <td class="text-soft"><?= e(Mailout::AUDIENCES[$row['audience']] ?? '') ?></td>
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
                <td class="text-right">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/' . (int) $row['id'])) ?>">Obrir</a>
                  <form method="post" style="display:inline"
                        action="<?= e(url('/enviaments/' . (int) $row['id'] . '/esborrar')) ?>"
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
