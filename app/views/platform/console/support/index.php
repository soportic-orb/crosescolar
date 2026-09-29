<?php
/**
 * La safata del suport.
 * @var array<int,array<string,mixed>> $tickets
 * @var array<int,array<string,mixed>> $departments
 * @var array<string,int> $counts
 * @var array{status:string,department:int,search:string} $filters
 */
use Cros\Core\Icons;
use Cros\Platform\Support;

$tabs = ['active' => 'Sense tancar'] + Support::STATUSES + ['' => 'Totes'];
$tones = ['open' => 'amber', 'answered' => 'green', 'waiting' => 'blue', 'closed' => ''];
$priorities = ['urgent' => 'red', 'high' => 'amber', 'normal' => '', 'low' => ''];
$names = [];
foreach ($departments as $department) {
    $names[(int) $department['id']] = (string) $department['name'];
}
?>
<div class="panel">
  <div class="panel__head">
    <h2>Consultes</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn--sm <?= $filters['status'] === $key ? '' : 'btn--ghost' ?>"
           href="<?= e(url('/suport', $key === 'active' ? [] : ['estat' => $key])) ?>">
          <?= e($label) ?><?php if (($counts[$key] ?? 0) > 0): ?> (<?= (int) $counts[$key] ?>)<?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/suport/departaments')) ?>">
        <?= Icons::svg('settings', 'icon', 15) ?> Departaments
      </a>
    </div>
  </div>
  <div class="panel__body">
    <form method="get" class="filters" style="margin-bottom:1rem">
      <input type="hidden" name="estat" value="<?= e($filters['status']) ?>">
      <select name="departament">
        <option value="0">Tots els departaments</option>
        <?php foreach ($departments as $department): ?>
          <option value="<?= (int) $department['id'] ?>" <?= $filters['department'] === (int) $department['id'] ? 'selected' : '' ?>>
            <?= e($department['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Número, assumpte, web o correu">
      <button class="btn btn--sm" type="submit">Filtrar</button>
      <?php if ($filters['department'] > 0 || $filters['search'] !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/suport', ['estat' => $filters['status']])) ?>">Treure els filtres</a>
      <?php endif; ?>
    </form>

    <?php if (!$tickets): ?>
      <p class="text-soft" style="margin:0">No hi ha cap consulta que hi encaixi.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr>
            <th>Número</th><th>Assumpte</th><th>Web</th><th>Departament</th>
            <th>Prioritat</th><th>Estat</th><th>Últim moviment</th>
          </tr></thead>
          <tbody>
            <?php foreach ($tickets as $ticket): ?>
              <?php $waiting = (string) $ticket['last_sender'] === 'client' && (string) $ticket['status'] !== 'closed'; ?>
              <tr>
                <td><strong><?= e($ticket['reference']) ?></strong></td>
                <td>
                  <a href="<?= e(url('/suport/' . (int) $ticket['id'])) ?>"><?= e($ticket['subject']) ?></a>
                  <?php if ($waiting): ?><br><small class="badge badge--amber">Espera resposta</small><?php endif; ?>
                </td>
                <td><?= e(($ticket['site_name'] ?? '') !== '' ? $ticket['site_name'] : ($ticket['slug'] ?? '—')) ?>
                  <?php if (!empty($ticket['slug'])): ?><br><small class="text-soft"><?= e($ticket['slug']) ?></small><?php endif; ?>
                </td>
                <td class="text-soft"><?= e($names[(int) ($ticket['department_id'] ?? 0)] ?? '—') ?></td>
                <td><span class="badge badge--<?= e($priorities[$ticket['priority']] ?? '') ?>"><?= e(Support::PRIORITIES[$ticket['priority']] ?? '') ?></span></td>
                <td><span class="badge badge--<?= e($tones[$ticket['status']] ?? '') ?>"><?= e(Support::STATUSES[$ticket['status']] ?? $ticket['status']) ?></span></td>
                <td class="text-soft"><?= e(dt($ticket['last_message_at'] ?: $ticket['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
