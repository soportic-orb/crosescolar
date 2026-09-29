<?php
/**
 * Una consulta, vista pel suport.
 * @var array<string,mixed> $ticket
 * @var array<int,array<string,mixed>> $messages
 * @var array<int,array<string,mixed>> $departments
 * @var array<string,mixed>|null $instance
 */
use Cros\Core\Icons;
use Cros\Core\View;
use Cros\Platform\Instance;
use Cros\Platform\Support;

$tones = ['open' => 'amber', 'answered' => 'green', 'waiting' => 'blue', 'closed' => ''];
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($ticket['subject']) ?></h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/suport')) ?>">← La safata</a>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      <strong><?= e($ticket['reference']) ?></strong> ·
      <?= e(trim((string) ($ticket['author_name'] ?? '')) ?: 'Sense nom') ?>
      &lt;<a href="mailto:<?= e($ticket['author_email']) ?>"><?= e($ticket['author_email']) ?></a>&gt; ·
      oberta el <?= e(dt($ticket['created_at'])) ?>
      <span class="badge badge--<?= e($tones[$ticket['status']] ?? '') ?>"><?= e(Support::STATUSES[$ticket['status']] ?? $ticket['status']) ?></span>
    </p>
    <?php if ($instance): ?>
      <p class="text-soft" style="margin-top:-.4rem">
        Web: <a href="<?= e(Instance::url($instance)) ?>" target="_blank" rel="noopener"><?= e($instance['site_name']) ?></a>
        · <a href="<?= e(url('/instancies/' . (int) $instance['id'])) ?>">la seva fitxa</a>
        · versió <?= e($instance['version'] ?: '—') ?>
      </p>
    <?php elseif (!empty($ticket['slug'])): ?>
      <p class="text-soft" style="margin-top:-.4rem">Web: <?= e($ticket['slug']) ?> (ja no hi és a les instàncies)</p>
    <?php endif; ?>

    <?= View::partial('admin/partials/support-thread', ['messages' => $messages, 'side' => 'support']) ?>
  </div>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Contestar</h2></div>
  <form method="post" action="<?= e(url('/suport/' . (int) $ticket['id'] . '/respondre')) ?>">
    <?= csrf_field() ?>
    <div class="panel__body">
      <?= View::partial('admin/partials/editor', [
          'name' => 'body',
          'value' => '',
          'id' => 'body',
          'label' => 'Resposta',
          'rows' => 9,
          'placeholder' => 'Escriviu aquí la resposta…',
      ]) ?>
      <label class="switch" style="margin-top:.8rem">
        <input type="checkbox" name="internal" value="1">
        <span>Nota interna: desar-ho sense enviar-ho al client</span>
      </label>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('mail', 'icon', 16) ?> Enviar</button>
      <span class="text-soft">La resposta li arriba per correu i li surt al seu panell.</span>
    </div>
  </form>
</div>

<div class="panel" style="margin-top:1.2rem">
  <div class="panel__head"><h2>Com es classifica</h2></div>
  <form method="post" action="<?= e(url('/suport/' . (int) $ticket['id'] . '/estat')) ?>">
    <?= csrf_field() ?>
    <div class="panel__body form-grid form-grid--2">
      <div class="field">
        <label for="status">Estat</label>
        <select id="status" name="status">
          <?php foreach (Support::STATUSES as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= (string) $ticket['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="priority">Prioritat</label>
        <select id="priority" name="priority">
          <?php foreach (Support::PRIORITIES as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= (string) $ticket['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="department_id">Departament</label>
        <select id="department_id" name="department_id">
          <option value="0">Sense departament</option>
          <?php foreach ($departments as $department): ?>
            <option value="<?= (int) $department['id'] ?>" <?= (int) ($ticket['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
              <?= e($department['name']) ?><?= empty($department['active']) ? ' (desactivat)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn btn--ghost" type="submit">Desar</button>
      <span class="spacer"></span>
    </div>
  </form>
  <div class="panel__foot">
    <form method="post" action="<?= e(url('/suport/' . (int) $ticket['id'] . '/esborrar')) ?>"
          data-confirm="Esborrar la consulta i tots els seus missatges? No es pot desfer.">
      <?= csrf_field() ?>
      <button class="btn btn--danger btn--sm" type="submit"><?= Icons::svg('trash', 'icon', 14) ?> Esborrar la consulta</button>
    </form>
  </div>
</div>
