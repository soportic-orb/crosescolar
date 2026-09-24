<?php
/** Llista d'instàncies. */
use Cros\Platform\Instance;
use Cros\Platform\Platform;

$tabs = ['' => 'Totes'] + Instance::STATUSES;
?>
<?php if ($outdated > 0): ?>
  <div class="alert alert--warning flex-between">
    <span>
      <strong><?= $outdated === 1 ? 'Una instància' : $outdated . ' instàncies' ?></strong>
      no <?= $outdated === 1 ? 'té' : 'tenen' ?> la versió <?= e(app_version()) ?>.
      Actualitzar-les repassa la seva base de dades; no els toca cap dada.
    </span>
    <form method="post" action="<?= e(url('/instancies/actualitzar')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--sm" type="submit">Actualitzar-les totes</button>
    </form>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel__head">
    <h2>Instàncies</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn--sm <?= $status === $key ? '' : 'btn--ghost' ?>" href="<?= e(url('/instancies', $key === '' ? [] : ['estat' => $key])) ?>">
          <?= e($label) ?><?php $n = (int) ($counts[$key === '' ? 'all' : $key] ?? 0); ?><?php if ($n > 0): ?> (<?= $n ?>)<?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="btn btn--sm" href="<?= e(url('/instancies/nova')) ?>">Nova instància</a>
    </div>
  </div>
  <div class="panel__body table-wrap">
    <?php if (!$instances): ?>
      <p class="text-soft" style="margin:0">Cap instància en aquest estat.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Cros</th><th>Adreça</th><th>Cursa</th><th>Inscrits</th><th>Estat</th><th>Versió</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($instances as $row): ?>
          <tr class="<?= $row['status'] === 'cancelled' ? 'is-cancelled' : '' ?>">
            <td>
              <strong><?= e($row['site_name']) ?></strong>
              <?php if ($row['town']): ?><br><small class="text-soft"><?= e($row['town']) ?></small><?php endif; ?>
            </td>
            <td>
              <a href="<?= e(Platform::url((string) $row['slug'])) ?>" target="_blank" rel="noopener"><?= e($row['slug']) ?></a>
              <?php if (!$row['published']): ?><br><small class="text-soft">en preparació</small><?php endif; ?>
            </td>
            <td><?= $row['event_date'] ? e(ca_date((string) $row['event_date'])) : '<span class="text-soft">—</span>' ?></td>
            <td><?= (int) $row['registrations'] ?></td>
            <td><span class="badge badge--<?= e(Instance::tone((string) $row['status'])) ?>"><?= e(Instance::label((string) $row['status'])) ?></span></td>
            <td><small class="text-soft"><?= e($row['version'] ?? '—') ?></small></td>
            <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/instancies/' . (int) $row['id'])) ?>">Fitxa</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
