<?php
/**
 * Una consulta vista pel client.
 * @var array<string,mixed> $ticket
 * @var array<int,array<string,mixed>> $messages
 * @var array<int,array<string,mixed>> $departments
 */
use Cros\Core\View;
use Cros\Platform\Support;

$closed = (string) $ticket['status'] === 'closed';
$names = [];
foreach ($departments as $department) {
    $names[(int) $department['id']] = (string) $department['name'];
}
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($ticket['subject']) ?></h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/suport')) ?>">← Totes les consultes</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      <?= e($ticket['reference']) ?> ·
      <?= e($names[(int) ($ticket['department_id'] ?? 0)] ?? 'Sense departament') ?> ·
      Prioritat <?= e(mb_strtolower(Support::PRIORITIES[$ticket['priority']] ?? 'normal')) ?> ·
      Oberta el <?= e(dt($ticket['created_at'])) ?>
      <span class="badge <?= $closed ? '' : 'badge--amber' ?>"><?= e(Support::STATUSES[$ticket['status']] ?? $ticket['status']) ?></span>
    </p>

    <?= View::partial('admin/partials/support-thread', ['messages' => $messages, 'side' => 'client']) ?>

    <?php if ($closed): ?>
      <div class="alert alert--info" style="margin-top:1.4rem">
        Aquesta consulta està tancada. Si hi torna a haver res, obriu-ne una de nova i
        digueu-hi el número <?= e($ticket['reference']) ?>.
      </div>
    <?php else: ?>
      <form method="post" action="<?= e(url('/admin/suport/' . (int) $ticket['id'] . '/respondre')) ?>" style="margin-top:1.4rem">
        <?= csrf_field() ?>
        <div class="field">
          <label for="body">La vostra resposta</label>
          <?= View::partial('admin/partials/editor', [
              'name' => 'body',
              'value' => '',
              'id' => 'body',
              'label' => 'Resposta',
              'rows' => 7,
              'placeholder' => 'Escriviu aquí…',
          ]) ?>
        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Enviar la resposta</button>
        </div>
      </form>
      <form method="post" action="<?= e(url('/admin/suport/' . (int) $ticket['id'] . '/tancar')) ?>"
            data-confirm="Donar aquesta consulta per resolta?" style="margin-top:.6rem">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--sm" type="submit">Ja està resolta, tanqueu-la</button>
      </form>
    <?php endif; ?>
  </div>
</div>
