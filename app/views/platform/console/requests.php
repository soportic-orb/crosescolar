<?php
/** Llista de sol·licituds. */
use Cros\Platform\Request;

$tabs = ['pending' => 'Pendents', 'info' => 'Esperant resposta', 'approved' => 'Aprovades', 'rejected' => 'Rebutjades', 'all' => 'Totes'];
$tones = ['pending' => 'amber', 'info' => 'blue', 'approved' => 'green', 'rejected' => 'red'];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Sol·licituds</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn--sm <?= $status === $key ? '' : 'btn--ghost' ?>" href="<?= e(url('/sollicituds', $key === 'pending' ? [] : ['estat' => $key])) ?>">
          <?= e($label) ?><?php if (($counts[$key] ?? 0) > 0): ?> (<?= (int) $counts[$key] ?>)<?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel__body table-wrap">
    <?php if (!$requests): ?>
      <p class="text-soft" style="margin:0">Cap sol·licitud en aquest estat.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Codi</th><th>Entitat</th><th>Contacte</th><th>Adreça demanada</th><th>Estat</th><th>Rebuda</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($requests as $request): ?>
          <tr>
            <td><strong><?= e($request['code']) ?></strong></td>
            <td><?= e($request['entity']) ?><?php if ($request['town']): ?><br><small class="text-soft"><?= e($request['town']) ?></small><?php endif; ?></td>
            <td><?= e($request['contact_name']) ?><br><small class="text-soft"><?= e($request['contact_email']) ?></small></td>
            <td><?= $request['slug'] ? '<code>' . e($request['slug']) . '</code>' : '<span class="text-soft">—</span>' ?></td>
            <td><span class="badge badge--<?= e($tones[$request['status']] ?? '') ?>"><?= e(Request::STATUSES[$request['status']] ?? $request['status']) ?></span></td>
            <td><?= e(ca_date(substr((string) $request['created_at'], 0, 10))) ?></td>
            <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/sollicituds/' . (int) $request['id'])) ?>">Obrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
