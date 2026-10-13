<?php
/**
 * El seguiment d'un enviament que ja ha començat: com va, a qui ha arribat i a
 * qui no, com ha anat, i el correu tal com ha sortit.
 *
 * @var array<string,mixed> $mailing
 * @var array<string,int> $counts
 * @var array<int,array<string,mixed>> $recipients
 * @var array<string,mixed>|null $list
 * @var array<int,array{at:string,text:string}> $timeline
 * @var int $limit
 * @var int $batch
 * @var bool $cronStale
 */
use Cros\Platform\Mailout;

$id = (int) $mailing['id'];
$status = (string) $mailing['status'];
$total = max(1, array_sum($counts));
$pending = $counts['pending'] + $counts['sending'];
$filter = isset($_GET['estat']) ? (string) $_GET['estat'] : '';
$who = [
    'clients' => 'Contacte dels clients',
    'instances' => 'Administradores dels webs',
    'list' => 'La llista «' . ($list['name'] ?? '?') . '»',
    'manual' => 'Adreces escrites a mà',
];
$labels = ['pending' => 'Pendent', 'sending' => 'S\'està enviant', 'sent' => 'Enviat', 'failed' => 'No ha sortit', 'skipped' => 'No s\'ha enviat'];
$tones = ['pending' => 'draft', 'sending' => 'sending', 'sent' => 'sent', 'failed' => 'paused', 'skipped' => 'cancelled'];
$button = static fn (string $action, string $label, string $class = 'btn btn--ghost btn--sm', string $confirm = ''): string =>
    '<form method="post" action="' . e(url('/enviaments/' . $id . '/accio')) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . ' style="margin:0">'
    . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><button class="' . e($class) . '" type="submit">' . e($label) . '</button></form>';
// Quant falta, a ull: les tandes del cron cada cinc minuts, i el límit per hora si n'hi ha.
$perHour = $limit > 0 ? min($limit, $batch * 12 * 4) : $batch * 12 * 4;
$eta = $pending > 0 ? (int) ceil($pending / max(1, $perHour) * 60) : 0;
?>
<div class="ml">
  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments')) ?>">← Enviaments</a>
    <h2 style="margin:0;font-size:1.15rem;flex:1 1 320px"><?= e($mailing['subject']) ?></h2>
    <span class="ml-status <?= !empty($mailing['archived_at']) ? 'archived' : e($status) ?>"><?= !empty($mailing['archived_at']) ? 'Arxivat' : e(Mailout::STATUSES[$status] ?? $status) ?></span>
    <?php if ($status === 'sending'): ?>
      <?= $button('batch', 'Envia una tanda ara') ?>
      <?= $button('pause', 'Pausa') ?>
    <?php elseif ($status === 'paused'): ?>
      <?= $button('resume', 'Reprèn', 'btn btn--sm') ?>
    <?php endif; ?>
    <?php if (in_array($status, ['sending', 'paused'], true)): ?>
      <?= $button('stop', 'Atura del tot', 'btn btn--danger btn--sm', 'Aturar-lo del tot? Els ' . $pending . ' que encara no l\'han rebut ja no el rebran.') ?>
    <?php endif; ?>
    <?php if ($counts['failed'] > 0 && in_array($status, ['sent', 'sending', 'paused'], true)): ?>
      <?= $button('retry', 'Torna a provar els que han fallat') ?>
    <?php endif; ?>
    <?= $button('duplicate', 'Duplica') ?>
    <?php if (in_array($status, ['sent', 'cancelled'], true)): ?>
      <?= !empty($mailing['archived_at']) ? $button('unarchive', 'Desarxiva') : $button('archive', 'Arxiva') ?>
      <?= $button('delete', 'Esborra', 'btn btn--danger btn--sm', 'Esborrar aquest enviament i tot el seu historial?') ?>
    <?php endif; ?>
  </div>

  <?php if ($status === 'paused' && !empty($mailing['paused_reason'])): ?>
    <div class="alert alert--error"><strong>En pausa.</strong> <?= e((string) $mailing['paused_reason']) ?>
      Quan estigui arreglat, cliqueu <b>Reprèn</b>.</div>
  <?php elseif ($status === 'sending' && $cronStale): ?>
    <div class="alert alert--warning"><strong>El cron dels enviaments no passa</strong>, i les tandes no surten soles.
      Envieu-les a mà amb <b>Envia una tanda ara</b>, o feu que root executi <code><?= e(Mailout::cronCommand()) ?></code> al servidor.</div>
  <?php elseif ($status === 'sending'): ?>
    <div class="alert alert--info">No cal tenir aquesta pàgina oberta: les tandes surten soles cada cinc minuts.</div>
  <?php endif; ?>

  <div class="mt-grid">
    <div class="mt-card">
      <div class="mt-big" role="img" aria-label="<?= (int) $counts['sent'] ?> enviats de <?= array_sum($counts) ?>">
        <span style="width:<?= round($counts['sent'] * 100 / $total, 2) ?>%;background:var(--a-green)"></span>
        <span style="width:<?= round($counts['failed'] * 100 / $total, 2) ?>%;background:#c0492b"></span>
        <span style="width:<?= round($counts['skipped'] * 100 / $total, 2) ?>%;background:#c9b8ae"></span>
      </div>
      <div class="mt-counts">
        <a href="<?= e(url('/enviaments/' . $id . '?estat=sent')) ?>"<?= $filter === 'sent' ? ' aria-current="true"' : '' ?>><b><?= (int) $counts['sent'] ?></b>enviats</a>
        <a href="<?= e(url('/enviaments/' . $id . '?estat=failed')) ?>"<?= $filter === 'failed' ? ' aria-current="true"' : '' ?>><b><?= (int) $counts['failed'] ?></b>no han sortit</a>
        <a href="<?= e(url('/enviaments/' . $id . '?estat=pending')) ?>"<?= $filter === 'pending' ? ' aria-current="true"' : '' ?>><b><?= $pending ?></b><?= $counts['skipped'] ? 'pendents · ' . (int) $counts['skipped'] . ' aturats' : 'pendents' ?></a>
        <div><b><?= $pending > 0 ? ($eta < 60 ? '~' . max(5, $eta) . ' min' : '~' . round($eta / 60, 1) . ' h') : '—' ?></b><?= $pending > 0 ? 'per acabar' : 'acabat' ?></div>
      </div>
      <p class="text-soft" style="margin:0;font-size:.86rem">
        <?= e($who[$mailing['audience']] ?? '') ?> · <?= e(\Cros\Platform\Controllers\MailoutController::from(Mailout::domain($mailing))) ?>
        <?= !empty($mailing['all_domains']) && in_array($mailing['audience'], ['clients', 'instances'], true) ? '(a tots els dominis)' : '' ?>
        · tandes de <?= $batch ?><?= $limit > 0 ? ', com a molt ' . $limit . ' per hora' : '' ?>.
        <?php if ($filter !== ''): ?><a href="<?= e(url('/enviaments/' . $id)) ?>">Mostra'ls tots</a><?php endif; ?>
      </p>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Destinatari</th><th>Entitat</th><th>Estat</th></tr></thead>
          <tbody>
            <?php foreach ($recipients as $r): ?>
              <tr>
                <td><?= $r['name'] ? '<b>' . e($r['name']) . '</b> · ' : '' ?><?= e($r['email']) ?></td>
                <td class="text-soft"><?= e((string) $r['entity']) ?></td>
                <td>
                  <span class="ml-status <?= e($tones[$r['status']] ?? 'draft') ?>"><?= e($labels[$r['status']] ?? $r['status']) ?><?= $r['sent_at'] && $r['status'] === 'sent' ? ' ' . e(date('H:i', (int) strtotime((string) $r['sent_at']))) : '' ?></span>
                  <?php if ($r['status'] === 'failed' && $r['error']): ?><div class="text-soft" style="font-size:.78rem;margin-top:3px"><?= e((string) $r['error']) ?></div><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recipients): ?><tr><td colspan="3" class="text-soft">Cap destinatari en aquest estat.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
      <div class="mt-card">
        <h3 style="margin:0;font-size:.98rem">Com ha anat</h3>
        <div class="mt-tl">
          <?php foreach (array_slice($timeline, -40) as $row): ?>
            <div><span><?= e(dt($row['at'], 'd/m H:i')) ?></span><span><?= e($row['text']) ?></span></div>
          <?php endforeach; ?>
          <?php if (!$timeline): ?><p class="text-soft" style="margin:0">Encara no ha passat res.</p><?php endif; ?>
        </div>
      </div>
      <div class="mt-card">
        <h3 style="margin:0;font-size:.98rem">El correu</h3>
        <iframe class="mt-frame" title="El correu enviat" src="<?= e(url('/enviaments/' . $id . '/correu')) ?>" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" loading="lazy"></iframe>
      </div>
    </div>
  </div>
</div>
