<?php
/**
 * La safata dels missatges de contacte.
 *
 * @var string $status
 * @var string $search
 * @var array<int,array<string,mixed>> $contacts
 */
use Cros\Platform\Contact;

$tabs = ['' => 'Safata', 'new' => 'Per llegir', 'answered' => 'Responsos', 'archived' => 'Arxivats'];
$tones = ['new' => 'amber', 'read' => 'blue', 'answered' => 'green', 'archived' => ''];
?>
<div class="panel">
  <div class="panel__head">
    <h2>Contacte</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn--sm <?= $status === $key ? '' : 'btn--ghost' ?>"
           href="<?= e(url('/contacte', $key === '' ? [] : ['estat' => $key])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel__body">
    <form method="get" action="<?= e(url('/contacte')) ?>" class="filters" style="margin-bottom:1rem">
      <?php if ($status !== ''): ?><input type="hidden" name="estat" value="<?= e($status) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Nom, entitat, correu o text del missatge">
      <button class="btn btn--ghost btn--sm" type="submit">Cercar</button>
    </form>

    <div class="table-wrap">
      <?php if (!$contacts): ?>
        <p class="text-soft" style="margin:0">
          <?= $search !== '' ? 'Cap missatge no coincideix amb la cerca.' : 'No hi ha cap missatge aquí.' ?>
        </p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Qui</th><th>Missatge</th><th>Des de</th><th>Estat</th><th>Rebut</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($contacts as $contact): ?>
            <?php $nou = (string) $contact['status'] === 'new'; ?>
            <tr>
              <td>
                <?= $nou ? '<strong>' . e($contact['name']) . '</strong>' : e($contact['name']) ?>
                <?php if (!empty($contact['entity'])): ?><br><small class="text-soft"><?= e($contact['entity']) ?></small><?php endif; ?>
                <br><small class="text-soft"><?= e($contact['email']) ?></small>
              </td>
              <td><?= e(excerpt((string) $contact['message'], 110)) ?></td>
              <td><small><?= e((string) ($contact['domain'] ?? '—')) ?></small></td>
              <td><span class="badge badge--<?= e($tones[$contact['status']] ?? '') ?>"><?= e(Contact::STATUSES[$contact['status']] ?? $contact['status']) ?></span></td>
              <td><?= e(dt((string) $contact['created_at'])) ?></td>
              <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/contacte/' . (int) $contact['id'])) ?>">Obrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
