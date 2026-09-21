<?php
/** Fitxa d'un enviament: destinataris, prova i enviament per tandes. */
use Cros\Core\Icons;
use Cros\Models\Mailing;

$id = (int) $mailing['id'];
$isDraft = $mailing['status'] === 'draft';
$pending = max(0, (int) $mailing['total'] - (int) $mailing['sent'] - (int) $mailing['failed']);
$names = [];
foreach ($categories as $category) {
    $names[(int) $category['id']] = (string) $category['name'];
}
$chosen = array_values(array_filter(array_map(
    static fn (int $catId): string => $names[$catId] ?? '',
    Mailing::categoryIds($mailing)
)));
?>
<div class="panel">
  <div class="panel__head">
    <h2><?= e($mailing['subject']) ?></h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/enviaments')) ?>">← Tots els enviaments</a>
  </div>
  <div class="panel__body">

    <div class="grid-cards mb-2">
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('users', 'icon', 16) ?> Destinataris</div>
        <div class="kpi__value"><?= $isDraft ? count($audience) : (int) $mailing['total'] ?></div>
        <div class="kpi__foot">adreces diferents</div>
      </div>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('check', 'icon', 16) ?> Enviats</div>
        <div class="kpi__value"><?= (int) $mailing['sent'] ?></div>
        <div class="kpi__foot"><?= $pending ?> per enviar</div>
      </div>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('alert', 'icon', 16) ?> Amb error</div>
        <div class="kpi__value"><?= (int) $mailing['failed'] ?></div>
        <div class="kpi__foot"><?= (int) $mailing['failed'] > 0 ? 'reviseu-ho més avall' : 'cap problema' ?></div>
      </div>
    </div>

    <h3>A qui va</h3>
    <p>
      <?= e(Mailing::AUDIENCES[$mailing['audience']] ?? '') ?>
      <?php if ($chosen): ?>
        — <strong><?= e(implode(', ', $chosen)) ?></strong>
      <?php endif; ?>
      <?php if ($mailing['audience'] !== 'manual'): ?>
        · <?= e(mb_strtolower(Mailing::STATUSES[$mailing['reg_status']] ?? '')) ?>
      <?php endif; ?>
    </p>

    <?php if ($isDraft): ?>
      <?php if (!$audience): ?>
        <div class="alert alert--error">
          No hi ha cap destinatari amb els criteris triats. Editeu l'enviament i reviseu-los.
        </div>
      <?php else: ?>
        <details>
          <summary>Veure les <?= count($audience) ?> adreces</summary>
          <div class="table-wrap" style="margin-top:.8rem">
            <table class="admin-table">
              <thead><tr><th>Adreça</th><th>Contacte</th><th>Participants</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($audience, 0, 200) as $person): ?>
                  <tr>
                    <td class="mono"><?= e($person['email']) ?></td>
                    <td><?= e($person['name']) ?></td>
                    <td class="text-soft"><?= e($person['participants']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (count($audience) > 200): ?>
            <p class="text-soft">…i <?= count($audience) - 200 ?> més.</p>
          <?php endif; ?>
        </details>
      <?php endif; ?>
    <?php endif; ?>

    <h3 style="margin-top:2rem">Abans d'enviar-lo</h3>
    <div class="flex" style="gap:.6rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enviaments/' . $id . '/vista-previa')) ?>" target="_blank">
        <?= Icons::svg('eye', 'icon', 16) ?> Vista prèvia
      </a>
      <form method="post" action="<?= e(url('/admin/enviaments/' . $id . '/prova')) ?>" class="flex" style="gap:.4rem">
        <?= csrf_field() ?>
        <input type="email" name="email" placeholder="<?= e(\Cros\Core\Auth::user()['email'] ?? 'adreça de prova') ?>"
               style="width:16rem">
        <button class="btn btn--ghost btn--sm" type="submit">
          <?= Icons::svg('mail', 'icon', 16) ?> Enviar-ne una prova
        </button>
      </form>
      <?php if ($isDraft): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/enviaments/' . $id . '/editar')) ?>">
          <?= Icons::svg('edit', 'icon', 16) ?> Editar
        </a>
      <?php endif; ?>
    </div>

    <h3 style="margin-top:2rem">Enviar</h3>
    <?php if ($isDraft && $audience): ?>
      <p class="text-soft">
        En preparar-lo es fixa la llista de destinataris i ja no es podrà editar el correu.
      </p>
      <form method="post" action="<?= e(url('/admin/enviaments/' . $id . '/preparar')) ?>"
            data-confirm="Preparar l'enviament per a <?= count($audience) ?> adreces? Després ja no es podrà editar.">
        <?= csrf_field() ?>
        <button class="btn" type="submit">
          <?= Icons::svg('check', 'icon', 18) ?> Preparar l'enviament
        </button>
      </form>
    <?php elseif ($pending > 0): ?>
      <div data-send="<?= e(url('/admin/enviaments/' . $id . '/tanda')) ?>"
           data-token="<?= e(csrf_token()) ?>" data-total="<?= (int) $mailing['total'] ?>">
        <div class="progress" style="margin-bottom:.7rem">
          <div class="progress__bar" data-send-bar
               style="width:<?= (int) $mailing['total'] > 0 ? round((((int) $mailing['sent'] + (int) $mailing['failed']) / (int) $mailing['total']) * 100) : 0 ?>%"></div>
        </div>
        <p class="text-soft" data-send-note>
          <?= (int) $mailing['sent'] + (int) $mailing['failed'] ?> de <?= (int) $mailing['total'] ?> ·
          <?= (int) $mailing['sent'] ?> enviats
        </p>
        <form method="post" action="<?= e(url('/admin/enviaments/' . $id . '/tanda')) ?>">
          <?= csrf_field() ?>
          <button class="btn" type="submit" data-send-start>
            <?= Icons::svg('mail', 'icon', 18) ?>
            <?= (int) $mailing['sent'] + (int) $mailing['failed'] > 0 ? 'Continuar l\'enviament' : 'Enviar ara' ?>
          </button>
        </form>
        <p class="text-soft" style="margin-top:.6rem;font-size:.9rem">
          S'envien de <?= (int) $batch ?> en <?= (int) $batch ?>. No tanqueu aquesta pàgina mentre duri;
          si es talla, podeu continuar des d'aquí mateix i no es repetirà cap correu.
        </p>
      </div>
    <?php elseif ((int) $mailing['total'] > 0): ?>
      <div class="alert alert--success">
        Enviament acabat el <?= e(dt($mailing['finished_at'] ?: $mailing['updated_at'])) ?>:
        <?= (int) $mailing['sent'] ?> correus enviats<?= (int) $mailing['failed'] > 0 ? ' i ' . (int) $mailing['failed'] . ' amb error' : '' ?>.
      </div>
      <form method="post" action="<?= e(url('/admin/enviaments/' . $id . '/preparar')) ?>"
            data-confirm="Buscar destinataris nous (per exemple, inscripcions fetes després)?">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--sm" type="submit">
          <?= Icons::svg('refresh', 'icon', 16) ?> Enviar-lo a les inscripcions noves
        </button>
      </form>
    <?php endif; ?>

    <?php if ($recipients): ?>
      <h3 style="margin-top:2rem">Destinataris</h3>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Adreça</th><th>Participants</th><th>Estat</th></tr></thead>
          <tbody>
            <?php foreach ($recipients as $row): ?>
              <tr>
                <td class="mono"><?= e($row['email']) ?></td>
                <td class="text-soft"><?= e($row['participants']) ?></td>
                <td>
                  <?php if ($row['status'] === 'sent'): ?>
                    <span class="badge badge--green">Enviat</span>
                  <?php elseif ($row['status'] === 'failed'): ?>
                    <span class="badge badge--red">Error</span>
                    <span class="text-soft"><?= e($row['error'] ?? '') ?></span>
                  <?php else: ?>
                    <span class="badge badge--amber">Per enviar</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
