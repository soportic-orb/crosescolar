<?php
/**
 * Les consultes de suport d'aquest web.
 * @var array<int,array<string,mixed>> $tickets
 * @var array<int,array<string,mixed>> $departments
 * @var int $unread
 * @var bool $canOpen
 */
use Cros\Core\Icons;
use Cros\Platform\Support;

$badges = [
    'open' => 'badge--amber',
    'answered' => 'badge--green',
    'waiting' => 'badge--amber',
    'closed' => '',
];
$names = [];
foreach ($departments as $department) {
    $names[(int) $department['id']] = (string) $department['name'];
}
?>
<div class="panel">
  <div class="panel__head">
    <h2>Consultes al suport</h2>
    <?php if ($canOpen): ?>
      <a class="btn btn--sm spacer" href="<?= e(url('/admin/suport/nou')) ?>">
        <?= Icons::svg('mail', 'icon', 16) ?> Nova consulta
      </a>
    <?php endif; ?>
  </div>
  <div class="panel__body">
    <?php if (!$canOpen): ?>
      <div class="alert alert--info">Ara mateix no s'accepten consultes noves. Les que ja hi ha continuen obertes.</div>
    <?php endif; ?>

    <?php if (!$tickets): ?>
      <p class="text-soft">Encara no heu obert cap consulta. Quan en tingueu una, escriviu-nos: ho veurem tot des d'aquí.</p>
    <?php else: ?>
      <?php if ($unread > 0): ?>
        <div class="alert alert--info">
          Hi ha <?= (int) $unread ?> <?= $unread === 1 ? 'resposta nova' : 'respostes noves' ?> que encara no heu llegit.
        </div>
      <?php endif; ?>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Número</th><th>Assumpte</th><th>Departament</th><th>Estat</th><th>Últim moviment</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($tickets as $ticket): ?>
              <?php
              $new = (string) $ticket['last_sender'] === 'support'
                  && (empty($ticket['client_read_at']) || $ticket['client_read_at'] < $ticket['last_message_at']);
              ?>
              <tr>
                <td class="text-soft"><?= e($ticket['reference']) ?></td>
                <td>
                  <a href="<?= e(url('/admin/suport/' . (int) $ticket['id'])) ?>"><strong><?= e($ticket['subject']) ?></strong></a>
                  <?php if ($new): ?><span class="badge badge--green">Resposta nova</span><?php endif; ?>
                </td>
                <td class="text-soft"><?= e($names[(int) ($ticket['department_id'] ?? 0)] ?? '—') ?></td>
                <td><span class="badge <?= e($badges[$ticket['status']] ?? '') ?>"><?= e(Support::STATUSES[$ticket['status']] ?? $ticket['status']) ?></span></td>
                <td class="text-soft"><?= e(dt($ticket['last_message_at'] ?: $ticket['created_at'])) ?></td>
                <td class="actions">
                  <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/suport/' . (int) $ticket['id'])) ?>">Obrir</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
