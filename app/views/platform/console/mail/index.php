<?php
/**
 * La llista d'enviaments: pestanyes per estat, miniatura, a qui va i quan.
 *
 * @var array<int,array<string,mixed>> $rows
 * @var string $filter
 * @var string $search
 * @var array<string,int> $counts
 * @var array<int,array<string,mixed>> $templates
 * @var array<int,array<string,mixed>> $lists
 * @var array<int,string> $domains
 * @var bool $cronStale
 */
use Cros\Core\Icons;
use Cros\Core\View;
use Cros\Platform\Mail\Brand;
use Cros\Platform\Mailout;

$brands = [];
foreach ($domains as $d) {
    $brands[$d] = Brand::of($d);
}
$listNames = array_column($lists, 'name', 'id');
$who = [
    'clients' => 'Contacte dels clients',
    'instances' => 'Administradores dels webs',
    'list' => 'Llista',
    'manual' => 'Adreces escrites a mà',
];
$action = static fn (int $id, string $what, string $label, string $confirm = '', string $class = 'btn btn--ghost btn--sm'): string =>
    '<form method="post" action="' . e(url('/enviaments/' . $id . '/accio')) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
    . csrf_field() . '<input type="hidden" name="action" value="' . e($what) . '"><button class="' . e($class) . '" type="submit">' . e($label) . '</button></form>';
?>
<div class="ml">
  <div class="panel__head" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <p class="text-soft" style="margin:0;flex:1 1 320px">
      Correus de la plataforma amb l'editor de blocs. Es poden enviar ara o programar, i surten sols pel servidor de correu del domini triat.
    </p>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/plantilles')) ?>"><?= Icons::svg('file', 'icon', 15) ?> Plantilles base (<?= count($templates) ?>)</a>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/llistes')) ?>"><?= Icons::svg('users', 'icon', 15) ?> Llistes (<?= count($lists) ?>)</a>
    <button class="btn btn--sm" type="button" onclick="document.getElementById('ml-new').showModal()"><?= Icons::svg('mail', 'icon', 15) ?> Nou enviament</button>
  </div>

  <?php if ($cronStale): ?>
    <div class="alert alert--warning">
      <strong>El cron dels enviaments no passa.</strong> Els programats i els que s'estan enviant no surten sols.
      Per arreglar-ho, root ha d'executar una vegada al servidor <code><?= e(Mailout::cronCommand()) ?></code>,
      que posa les feines al cron de <code>www-data</code>. Mentrestant, obriu cada enviament: els programats
      tenen el botó <b>Envia'l ara</b>, i els que s'estan enviant, <b>Envia una tanda ara</b>.
    </div>
  <?php endif; ?>

  <nav class="ml-tabs" aria-label="Estat">
    <?php foreach (Mailout::FILTERS as $key => $name): ?>
      <a href="<?= e(url('/enviaments' . ($key !== 'tots' || $search !== '' ? '?' . http_build_query(array_filter(['filtre' => $key !== 'tots' ? $key : null, 'q' => $search ?: null])) : ''))) ?>"
         <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($name) ?> · <?= (int) ($counts[$key] ?? 0) ?></a>
    <?php endforeach; ?>
    <form method="get" action="<?= e(url('/enviaments')) ?>">
      <?php if ($filter !== 'tots'): ?><input type="hidden" name="filtre" value="<?= e($filter) ?>"><?php endif; ?>
      <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Cerqueu per l'assumpte" aria-label="Cerqueu per l'assumpte">
    </form>
  </nav>

  <section class="ml-list">
    <?php if ($rows): ?>
      <div class="ml-head"><span></span><span>Correu</span><span>A qui va</span><span>Quan</span><span>Estat</span><span></span></div>
    <?php endif; ?>
    <?php foreach ($rows as $m): ?>
      <?php
      $id = (int) $m['id'];
      $status = (string) $m['status'];
      $archived = !empty($m['archived_at']);
      $domain = Mailout::domain($m);
      $editable = Mailout::editable($m);
      $href = url('/enviaments/' . $id . ($editable ? '/editar' : ''));
      $total = (int) $m['total'];
      $done = (int) $m['sent'] + (int) $m['failed'];
      $audience = $who[$m['audience']] ?? '';
      if ($m['audience'] === 'list') {
          $audience .= ' «' . ($listNames[$m['list_id']] ?? '?') . '»';
      }
      ?>
      <div class="ml-row">
        <a href="<?= e($href) ?>" tabindex="-1" aria-hidden="true"><?= View::partial('platform/console/mail/_thumb', ['doc' => Mailout::doc($m), 'brand' => $brands[$domain] ?? []]) ?></a>
        <div style="min-width:0">
          <a class="t" href="<?= e($href) ?>"><?= e($m['subject'] ?: 'Sense assumpte') ?></a>
          <span class="s"><?= $m['template_name'] ? 'Plantilla «' . e($m['template_name']) . '»' : 'En blanc' ?> · versió <?= (int) $m['version'] ?></span>
        </div>
        <div class="c-to" style="min-width:0">
          <div style="font-size:.88rem"><?= e($audience) ?></div>
          <div class="s"><?= e($domain) ?><?= !empty($m['all_domains']) && in_array($m['audience'], ['clients', 'instances'], true) ? ' · tots els dominis' : '' ?><?= $total > 0 ? ' · ' . $total . ' persones' : '' ?></div>
        </div>
        <div class="c-when" style="font-size:.88rem">
          <?php if ($status === 'scheduled' && $m['scheduled_at']): ?>
            <b><?= e(dt((string) $m['scheduled_at'])) ?></b>
            <div class="s">Sortirà sol</div>
          <?php elseif (in_array($status, ['sending', 'paused', 'sent', 'cancelled'], true)): ?>
            <b><?= e(dt((string) ($m['started_at'] ?: $m['updated_at']))) ?></b>
            <div class="ml-prog<?= $status === 'sent' ? ' done' : '' ?>">
              <span style="width:<?= $total ? round((int) $m['sent'] * 100 / $total, 1) : 0 ?>%"></span>
              <span class="ko" style="width:<?= $total ? round((int) $m['failed'] * 100 / $total, 1) : 0 ?>%"></span>
            </div>
            <div class="s"><?= (int) $m['sent'] ?> de <?= $total ?><?= (int) $m['failed'] ? ' · ' . (int) $m['failed'] . ' errors' : '' ?></div>
          <?php else: ?>
            <span class="s">Sense data · desat <?= e(dt((string) ($m['updated_at'] ?: $m['created_at']))) ?></span>
          <?php endif; ?>
        </div>
        <span class="ml-status <?= $archived ? 'archived' : e($status) ?>"><?= $archived ? 'Arxivat' : e(Mailout::STATUSES[$status] ?? $status) ?></span>
        <div class="ml-actions">
          <?php if ($editable): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e($href) ?>">Edita</a>
            <?php if ($status === 'scheduled'): ?><?= $action($id, 'unschedule', 'Desprograma') ?><?php endif; ?>
          <?php else: ?>
            <a class="btn btn--ghost btn--sm" href="<?= e($href) ?>"><?= in_array($status, ['sending', 'paused'], true) ? 'Segueix-lo' : 'Resultat' ?></a>
            <?php if ($status === 'sending'): ?><?= $action($id, 'pause', 'Pausa') ?><?php endif; ?>
            <?php if ($status === 'paused'): ?><?= $action($id, 'resume', 'Reprèn') ?><?php endif; ?>
          <?php endif; ?>
          <?php if (in_array($status, ['draft', 'sent', 'cancelled'], true)): ?>
            <?= $archived ? $action($id, 'unarchive', 'Desarxiva') : '' ?>
            <?= !$archived && $status !== 'draft' ? $action($id, 'archive', 'Arxiva') : '' ?>
          <?php endif; ?>
          <?= $action($id, 'duplicate', 'Duplica') ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <div class="ml-empty">
        <?php if ($search !== ''): ?>
          Cap correu no conté «<?= e($search) ?>».
        <?php elseif ($filter !== 'tots'): ?>
          No n'hi ha cap en aquest apartat.
        <?php else: ?>
          Encara no hi ha cap enviament. Feu-ne un amb <b>Nou enviament</b>, a partir d'una plantilla base.
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<dialog class="ml-dialog ml" id="ml-new" aria-labelledby="ml-new-h">
  <form method="post" action="<?= e(url('/enviaments/nou')) ?>">
    <?= csrf_field() ?>
    <div style="display:flex;align-items:center;gap:12px">
      <h2 id="ml-new-h" style="margin:0;font-size:1.1rem;flex:1">Nou enviament</h2>
      <button type="button" class="btn btn--ghost btn--sm" onclick="this.closest('dialog').close()" aria-label="Tanca">✕</button>
    </div>
    <div style="display:grid;grid-template-columns:minmax(0,1fr) 200px;gap:12px" class="ml-new-grid">
      <label class="field" style="margin:0"><span>Assumpte</span>
        <input class="input" type="text" name="subject" maxlength="190" placeholder="Ex.: Les inscripcions del curs 2026-27 ja són obertes" autofocus></label>
      <label class="field" style="margin:0"><span>Surt de</span>
        <select class="input" name="domain">
          <?php foreach ($domains as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
        </select></label>
    </div>
    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
      <b style="font-size:.86rem">Plantilla base</b>
      <span class="text-soft" style="font-size:.82rem;flex:1">El correu en fa una còpia: si després canvieu la plantilla, aquest correu no canvia. Els colors i el logotip són els del domini triat.</span>
      <a href="<?= e(url('/enviaments/plantilles')) ?>" style="font-size:.82rem">Gestioneu les plantilles base</a>
    </div>
    <div class="ml-tpls" role="radiogroup" aria-label="Plantilla base">
      <label class="ml-tpl"><input type="radio" name="template_id" value=""<?= $templates ? '' : ' checked' ?>><span class="blank">+</span><b>En blanc<span class="text-soft" style="display:block;font-weight:400;font-size:.78rem">Capçalera, text i peu</span></b></label>
      <?php foreach ($templates as $i => $t): ?>
        <label class="ml-tpl"><input type="radio" name="template_id" value="<?= (int) $t['id'] ?>"<?= $i === 0 ? ' checked' : '' ?>>
          <?= View::partial('platform/console/mail/_thumb', ['doc' => Mailout::doc($t), 'size' => 'lg', 'brand' => $brands[$domains[0] ?? ''] ?? []]) ?>
          <b><?= e($t['template_name']) ?></b></label>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px">
      <button type="button" class="btn btn--ghost" onclick="this.closest('dialog').close()">Cancel·la</button>
      <button type="submit" class="btn">Crea'l i obre l'editor</button>
    </div>
  </form>
</dialog>
<style>@media (max-width: 640px) { .ml-new-grid { grid-template-columns: 1fr !important; } }</style>
