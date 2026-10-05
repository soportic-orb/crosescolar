<?php
/**
 * Un missatge de contacte.
 *
 * @var array<string,mixed> $contact
 */
use Cros\Platform\Contact;

$id = (int) $contact['id'];
$status = (string) $contact['status'];
$accio = url('/contacte/' . $id . '/accio');
$assumpte = 'Re: el vostre missatge a ' . ($contact['domain'] ?? 'la plataforma');
?>
<p><a href="<?= e(url('/contacte')) ?>">← Tots els missatges</a></p>

<div class="panel">
  <div class="panel__head">
    <h2><?= e($contact['name']) ?></h2>
    <span class="badge badge--<?= e(['new' => 'amber', 'read' => 'blue', 'answered' => 'green'][$status] ?? '') ?>">
      <?= e(Contact::STATUSES[$status] ?? $status) ?>
    </span>
    <a class="btn btn--sm spacer" href="mailto:<?= e($contact['email']) ?>?subject=<?= e(rawurlencode($assumpte)) ?>">
      Respondre per correu
    </a>
  </div>
  <div class="panel__body">
    <table class="data">
      <tbody>
        <tr><th style="width:30%">Nom i cognoms</th><td><?= e($contact['name']) ?></td></tr>
        <tr><th>Entitat</th><td><?= !empty($contact['entity']) ? e($contact['entity']) : '<span class="text-soft">—</span>' ?></td></tr>
        <tr><th>Correu</th><td><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></td></tr>
        <tr><th>Telèfon</th><td><?= !empty($contact['phone'])
            ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', (string) $contact['phone'])) . '">' . e($contact['phone']) . '</a>'
            : '<span class="text-soft">—</span>' ?></td></tr>
        <tr><th>Des de</th><td><?= e((string) ($contact['domain'] ?? '—')) ?></td></tr>
        <tr><th>Rebut</th><td><?= e(dt((string) $contact['created_at'])) ?></td></tr>
        <tr><th>Privadesa</th><td>Acceptada el <?= e(dt((string) $contact['privacy_at'])) ?></td></tr>
        <tr><th>Novetats</th><td><?= (int) $contact['news'] === 1 ? 'Vol rebre\'n' : 'No n\'ha demanat' ?></td></tr>
      </tbody>
    </table>

    <h3 style="margin:1.6rem 0 .6rem">Missatge</h3>
    <div class="card" style="white-space:pre-wrap;line-height:1.6"><?= e($contact['message']) ?></div>

    <div class="form-actions" style="gap:.6rem;margin-top:1.4rem">
      <?php foreach (['answered' => 'Marcar com a respost', 'new' => 'Tornar a «per llegir»', 'archived' => 'Arxivar'] as $estat => $etiqueta): ?>
        <?php if ($status !== $estat): ?>
          <form method="post" action="<?= e($accio) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= e($estat) ?>">
            <button class="btn btn--ghost" type="submit"><?= e($etiqueta) ?></button>
          </form>
        <?php endif; ?>
      <?php endforeach; ?>
      <form method="post" action="<?= e($accio) ?>" data-confirm="Esborrar aquest missatge per sempre?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="btn btn--danger" type="submit">Esborrar</button>
      </form>
    </div>
  </div>
</div>
