<?php
/**
 * L'editor de blocs d'un correu (o d'una plantilla base).
 *
 * @var array<string,mixed> $mailing
 * @var array{settings:array,blocks:array} $doc
 * @var array<string,string> $images
 * @var array<string,array<string,mixed>> $brands
 * @var array<int,array<string,mixed>> $lists
 * @var array<string,mixed> $me
 * @var array<string,mixed> $ai
 * @var int $batch
 */
use Cros\Platform\Mail\Ai;
use Cros\Platform\Mail\Doc;
use Cros\Platform\Mailout;

$id = (int) $mailing['id'];
$isTemplate = Mailout::kind($mailing) === Mailout::TEMPLATE;
$domain = Mailout::domain($mailing);
$types = Doc::TYPES;
$defaults = [];
foreach (array_keys($types) as $t) {
    $defaults[$t] = ['type' => $t] + Doc::defaults($t);
}
$icons = [
    'header' => '<path d="M4 4h16v6h-16z"/><path d="M4 14h10"/><path d="M4 18h7"/>',
    'heading' => '<path d="M7 12h10"/><path d="M7 5v14"/><path d="M17 5v14"/>',
    'text' => '<path d="M4 6h16"/><path d="M4 10h16"/><path d="M4 14h16"/><path d="M4 18h10"/>',
    'image' => '<path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"/><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/>',
    'button' => '<path d="M3 9a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v6a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M8 12h8"/>',
    'link' => '<path d="M9 15l6 -6"/><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"/><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"/>',
    'box' => '<path d="M4 7h16v10h-16z"/><path d="M8 11h8"/>',
    'columns' => '<path d="M4 4h6v16h-6z"/><path d="M14 4h6v16h-6z"/>',
    'imagetext' => '<path d="M3 5h8v8h-8z"/><path d="M14 6h7"/><path d="M14 10h7"/><path d="M3 18h18"/>',
    'divider' => '<path d="M3 12h18"/>',
    'spacer' => '<path d="M8 7l4 -4l4 4"/><path d="M8 17l4 4l4 -4"/><path d="M12 3v18"/>',
    'footer' => '<path d="M4 20h16"/><path d="M4 15h16"/><path d="M8 10h8"/>',
    'race' => '<path d="M5 20l5 -10l3 5l2 -3l4 8z"/><path d="M16 6m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>',
    'news' => '<path d="M12 3l2.6 5.6l6 .7l-4.5 4.1l1.2 6l-5.3 -3l-5.3 3l1.2 -6l-4.5 -4.1l6 -.7z"/>',
];
$svg = static fn (string $paths, int $size = 20): string => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
$aiText = (array) ($ai['text'] ?? []);
$aiImage = (bool) ($ai['image'] ?? false);
$aiShow = !empty($ai['show']);
$when = !empty($mailing['scheduled_at']) ? strtotime((string) $mailing['scheduled_at']) : null;

$cfg = [
    'id' => $id,
    'isTemplate' => $isTemplate,
    'doc' => $doc,
    'subject' => (string) $mailing['subject'],
    'preheader' => (string) ($mailing['preheader'] ?? ''),
    'templateName' => (string) ($mailing['template_name'] ?? ''),
    'domain' => $domain,
    'status' => (string) $mailing['status'],
    'version' => (int) $mailing['version'],
    'types' => $types,
    'defaults' => $defaults,
    'fonts' => Doc::FONTS,
    'fields' => Doc::FIELDS,
    'images' => (object) $images,
    'brands' => $brands,
    'delivery' => [
        'audience' => (string) $mailing['audience'],
        'list_id' => (int) ($mailing['list_id'] ?? 0),
        'instance_status' => (string) $mailing['instance_status'],
        'manual_emails' => (string) ($mailing['manual_emails'] ?? ''),
        'all_domains' => (int) ($mailing['all_domains'] ?? 0),
        'date' => $when ? date('Y-m-d', $when) : date('Y-m-d', strtotime('+1 day')),
        'time' => $when ? date('H:i', $when) : '09:00',
    ],
    'me' => ['email' => (string) ($me['email'] ?? ''), 'name' => (string) ($me['name'] ?? '')],
    'batch' => $batch,
    'ai' => [
        'show' => $aiShow,
        'text' => (bool) $aiText,
        'image' => $aiImage,
        'provider' => $ai['provider'] ?? null,
        'maxRefs' => Ai::MAX_REFS,
    ],
    'csrf' => csrf_token(),
    'urls' => [
        'save' => url('/enviaments/' . $id . '/desar'),
        'preview' => url('/enviaments/vista-previa'),
        'upload' => url('/enviaments/imatge'),
        'versions' => url('/enviaments/' . $id . '/versions'),
        'test' => url('/enviaments/' . $id . '/prova'),
        'audience' => url('/enviaments/' . $id . '/destinataris'),
        'send' => url('/enviaments/' . $id . '/envia'),
        'templates' => url('/enviaments/plantilles'),
        'races' => url('/enviaments/curses'),
        'race' => url('/enviaments/curses/__SLUG__'),
        'aiText' => url('/enviaments/ia/text'),
        'aiImage' => url('/enviaments/ia/imatge'),
        'aiConfig' => url('/configuracio/ai'),
    ],
];
?>
<div class="me" id="me">
  <div class="me-bar">
    <a class="me-back" href="<?= e(url($isTemplate ? '/enviaments/plantilles' : '/enviaments')) ?>">← <?= $isTemplate ? 'Plantilles' : 'Enviaments' ?></a>
    <?php if ($isTemplate): ?>
      <input class="me-title" id="me-title" type="text" maxlength="190" value="<?= e((string) $mailing['template_name']) ?>" aria-label="Nom de la plantilla">
      <span class="ml-status draft">Plantilla base</span>
    <?php else: ?>
      <input class="me-title" id="me-title" type="text" maxlength="190" value="<?= e((string) $mailing['subject']) ?>" aria-label="Assumpte del correu" placeholder="Assumpte del correu">
      <button type="button" class="btn btn--ghost btn--sm" id="me-subject-fields" title="Posa un camp personal a l'assumpte" aria-label="Camps personals de l'assumpte">{ }</button>
      <span class="ml-status <?= e((string) $mailing['status']) ?>" id="me-status"><?= e(Mailout::STATUSES[$mailing['status']] ?? '') ?></span>
    <?php endif; ?>
    <button type="button" class="btn btn--ghost me-ico" id="me-undo" aria-label="Desfés" title="Desfés (Ctrl+Z)" disabled><?= $svg('<path d="M9 14l-4 -4l4 -4"/><path d="M5 10h11a4 4 0 1 1 0 8h-1"/>', 16) ?></button>
    <button type="button" class="btn btn--ghost me-ico" id="me-redo" aria-label="Refés" title="Refés (Ctrl+Y)" disabled><?= $svg('<path d="M15 14l4 -4l-4 -4"/><path d="M19 10h-11a4 4 0 1 0 0 8h1"/>', 16) ?></button>
    <?php if ($aiShow): ?>
      <button type="button" class="btn btn--ghost" id="me-ai" title="Escriviu textos o creeu imatges amb IA">✨ IA</button>
    <?php endif; ?>
    <button type="button" class="btn btn--ghost" id="me-preview">Previsualitza</button>
    <details class="me-menu" id="me-more">
      <summary class="btn btn--ghost me-ico" aria-label="Més opcions" title="Més opcions"><?= $svg('<path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/>', 16) ?></summary>
      <div class="me-menu-pop">
        <button type="button" id="me-history">Historial de versions<small>Mireu i recupereu versions anteriors</small></button>
        <?php if (!$isTemplate): ?>
          <button type="button" id="me-test">Envia'm una prova<small>A <?= e((string) ($me['email'] ?? 'la vostra adreça')) ?>, des del domini triat</small></button>
        <?php endif; ?>
        <button type="button" id="me-astemplate"><?= $isTemplate ? 'Fes-ne una còpia' : 'Desa com a plantilla base' ?><small>Una còpia a «Plantilles base»</small></button>
        <form method="post" action="<?= e(url('/enviaments/' . $id . '/accio')) ?>" data-leave>
          <?= csrf_field() ?><input type="hidden" name="action" value="duplicate">
          <button type="submit">Duplica<small>Un esborrany nou amb el mateix contingut</small></button>
        </form>
        <form method="post" action="<?= e(url('/enviaments/' . $id . '/accio')) ?>" id="me-delete-form" data-leave>
          <?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <button type="submit" style="color:#a5401d">Elimina<small><?= $isTemplate ? 'Els correus que ja se n\'han fet no canvien' : 'El correu i totes les seves versions' ?></small></button>
        </form>
      </div>
    </details>
    <button type="button" class="btn btn--ghost" id="me-save">Desa</button>
    <?php if (!$isTemplate): ?>
      <button type="button" class="btn me-send" id="me-send">Envia o programa…</button>
    <?php endif; ?>
  </div>

  <?php if (!$isTemplate && $mailing['status'] === 'scheduled' && $when): ?>
    <div class="me-banner<?= !empty($cronStale) ? ' warn' : '' ?>">
      <span class="me-banner__text">
        <strong>Programat per al <?= e(dt((string) $mailing['scheduled_at'])) ?>.</strong>
        <?php if (!empty($cronStale)): ?>
          Però el cron dels enviaments no passa, i <b>no sortirà sol</b>. Envieu-lo ara, o feu que root
          executi <code><?= e(Mailout::cronCommand()) ?></code> al servidor.
        <?php else: ?>
          Sortirà sol a l'hora. Fins llavors el podeu continuar retocant: s'enviarà l'última versió desada.
        <?php endif; ?>
      </span>
      <form method="post" action="<?= e(url('/enviaments/' . $id . '/accio')) ?>" data-confirm="Enviar-lo ara mateix, sense esperar l'hora programada?">
        <?= csrf_field() ?><input type="hidden" name="action" value="start_now">
        <button class="btn btn--sm" type="submit">Envia'l ara</button>
      </form>
      <form method="post" action="<?= e(url('/enviaments/' . $id . '/accio')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="unschedule">
        <button class="btn btn--ghost btn--sm" type="submit">Desprograma</button>
      </form>
    </div>
  <?php elseif (!$isTemplate && !empty($mailing['paused_reason'])): ?>
    <div class="me-banner warn"><strong>Atenció:</strong> <?= e((string) $mailing['paused_reason']) ?></div>
  <?php endif; ?>

  <div class="me-meta">
    <?php if ($isTemplate): ?>
      <label class="pre">Assumpte suggerit
        <input type="text" id="me-subject" maxlength="190" value="<?= e((string) $mailing['subject']) ?>" placeholder="El que proposa als correus que se'n facin">
      </label>
    <?php endif; ?>
    <label class="pre">Text de la safata d'entrada
      <input type="text" id="me-pre" maxlength="255" value="<?= e((string) ($mailing['preheader'] ?? '')) ?>" placeholder="El resum que es veu al costat de l'assumpte">
    </label>
    <?php if (!$isTemplate): ?>
      <label>Surt de
        <select id="me-domain" aria-label="Domini des d'on surt el correu">
          <?php foreach ($brands as $d => $brand): ?>
            <option value="<?= e($d) ?>"<?= $d === $domain ? ' selected' : '' ?>><?= e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <span>Remitent: <b id="me-sender"><?= e((string) ($brands[$domain]['sender'] ?? '')) ?></b></span>
    <?php else: ?>
      <label>Colors de
        <select id="me-domain" aria-label="Domini amb què es previsualitza">
          <?php foreach ($brands as $d => $brand): ?>
            <option value="<?= e($d) ?>"<?= $d === $domain ? ' selected' : '' ?>><?= e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <span><span id="me-saved">Desat <?= e(dt((string) ($mailing['updated_at'] ?: $mailing['created_at']))) ?></span> · <b id="me-ver">versió <?= (int) $mailing['version'] ?></b>
      <span id="me-dirty" style="color:#8a5a06;font-weight:700" hidden>· canvis sense desar</span></span>
  </div>

  <div class="me-grid">
    <aside class="me-side me-left" aria-label="Blocs i estils">
      <div class="me-tabs" role="tablist">
        <button type="button" role="tab" aria-selected="true" data-tab="blocks">Blocs</button>
        <button type="button" role="tab" aria-selected="false" data-tab="styles">Estils</button>
      </div>
      <div data-pane="blocks">
        <span class="me-kicker">Arrossegueu al correu</span>
        <div class="me-pal" id="me-pal">
          <?php foreach ($types as $key => $name): ?>
            <?php if (in_array($key, Doc::PLATFORM, true)) { continue; } ?>
            <button type="button" draggable="true" data-type="<?= e($key) ?>" title="Arrossegueu-lo al correu o cliqueu per afegir-lo"><?= $svg($icons[$key]) ?><?= e($name) ?></button>
          <?php endforeach; ?>
        </div>
        <span class="me-kicker" style="margin-top:14px">De la plataforma</span>
        <div class="me-pal own" id="me-pal-own">
          <?php foreach (Doc::PLATFORM as $key): ?>
            <button type="button" draggable="true" data-type="<?= e($key) ?>" title="<?= $key === 'race' ? 'Un web del directori de curses' : 'Una novetat de la plataforma' ?>"><?= $svg($icons[$key]) ?><?= e($types[$key]) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="me-tip">Arrossegueu un bloc on vulgueu, o cliqueu-lo i s'afegeix sota el seleccionat. Per moure un bloc, agafeu-lo per <b>⠿</b> o feu servir ↑ ↓. El text s'escriu directament al correu.</div>
      </div>
      <div data-pane="styles" hidden id="me-styles"></div>
    </aside>

    <section class="me-canvas" aria-label="Correu">
      <div class="me-mail" id="me-mail"></div>
    </section>

    <aside class="me-side me-props" id="me-props" aria-label="Opcions del bloc"></aside>
  </div>
</div>

<div class="me-rtbar" id="me-rtbar" role="toolbar" aria-label="Format del text">
  <button type="button" data-cmd="bold" title="Negreta (Ctrl+B)"><b>B</b></button>
  <button type="button" data-cmd="italic" title="Cursiva (Ctrl+I)"><i>I</i></button>
  <button type="button" data-cmd="underline" title="Subratllat (Ctrl+U)"><u>U</u></button>
  <button type="button" data-cmd="link" title="Enllaç">Enllaç</button>
  <button type="button" data-cmd="unlink" title="Treu l'enllaç">✕ enllaç</button>
  <button type="button" data-cmd="insertUnorderedList" title="Llista">• Llista</button>
  <button type="button" data-cmd="insertOrderedList" title="Llista numerada">1.</button>
  <button type="button" data-cmd="h3" title="Subtítol dins del text">Subtítol</button>
  <button type="button" data-cmd="removeFormat" title="Treu el format">Neteja</button>
  <button type="button" data-cmd="fields" class="fields" title="Camps personals: el nom, l'entitat…">{ } Camps ▾</button>
</div>
<div class="me-fields-pop" id="me-fields-pop" role="menu" aria-label="Camps personals">
  <?php foreach (Doc::FIELDS as $tag => $label): ?>
    <button type="button" role="menuitem" data-field="<?= e($tag) ?>"><span><?= e($label) ?></span><code><?= e($tag) ?></code></button>
  <?php endforeach; ?>
</div>

<dialog class="me-dlg me-prev" id="me-prev" aria-label="Previsualització">
  <div class="in" style="display:flex;flex-direction:column">
    <div class="me-prev-bar">
      <b style="flex:1;font-size:.95rem" id="me-prev-title">Previsualització</b>
      <span class="me-seg" role="group" aria-label="Mida" id="me-prev-mode">
        <button type="button" data-mode="desk" aria-pressed="false">Ordinador</button>
        <button type="button" data-mode="phone" aria-pressed="false">Mòbil</button>
        <button type="button" data-mode="both" aria-pressed="true">Tots dos</button>
      </span>
      <label class="me-chk" style="margin:0"><input type="checkbox" id="me-prev-noimg"> Sense imatges</label>
      <button type="button" class="btn btn--ghost" data-close>Tanca</button>
    </div>
    <p class="me-note">Amb les vostres dades com a destinatari d'exemple. Cada persona el rep amb les seves.</p>
    <div class="me-prev-body">
      <div class="me-pane desk"><span>ORDINADOR</span><iframe title="Correu a l'ordinador" id="me-if-desk" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe></div>
      <div class="me-pane ph"><span>MÒBIL · 375 PX</span><div class="me-phone"><iframe title="Correu al mòbil" id="me-if-phone" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe></div></div>
    </div>
  </div>
</dialog>

<dialog class="me-dlg" id="me-hist" aria-labelledby="me-hist-h">
  <div class="in">
    <div class="head"><h2 id="me-hist-h">Historial de versions</h2><button type="button" class="btn btn--ghost" data-close>Tanca</button></div>
    <p class="me-small" style="margin:0">Cada «Desa» guarda una versió. Recuperar-ne una la desa com a versió nova: no s'esborra res.</p>
    <div class="me-hist" id="me-hist-list"><span class="me-busy">Carregant…</span></div>
  </div>
</dialog>

<?php if (!$isTemplate): ?>
<dialog class="me-dlg me-send-dlg" id="me-send-dlg" aria-labelledby="me-send-h">
  <form class="in" id="me-send-form" method="dialog">
    <div class="head"><h2 id="me-send-h">Envia o programa</h2><button type="button" class="btn btn--ghost btn--sm" data-close aria-label="Tanca">✕</button></div>
    <div class="me-step"><span class="n">1</span><div>
      <h3>A qui va</h3>
      <div class="me-opts">
        <label class="me-opt"><input type="radio" name="audience" value="instances"><span><b>Administradores dels webs</b>
          <small>L'adreça d'accés de cada web</small><br>
          <select name="instance_status" aria-label="Quins webs">
            <?php foreach (Mailout::SCOPES as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select></span></label>
        <label class="me-opt"><input type="radio" name="audience" value="clients"><span><b>Contacte dels clients</b><small>La persona de cada fitxa de client</small></span></label>
        <label class="me-opt"><input type="radio" name="audience" value="list"><span><b>Una llista de correu</b>
          <?php if ($lists): ?>
            <select name="list_id" aria-label="Llista">
              <?php foreach ($lists as $list): ?><option value="<?= (int) $list['id'] ?>"><?= e($list['name']) ?> (<?= (int) ($list['active_contacts'] ?? 0) ?>)</option><?php endforeach; ?>
            </select>
          <?php else: ?>
            <small>Encara no n'hi ha cap: <a href="<?= e(url('/enviaments/llistes')) ?>">feu-ne una</a></small>
          <?php endif; ?></span></label>
        <label class="me-opt"><input type="radio" name="audience" value="manual"><span style="flex:1"><b>Adreces escrites a mà</b><small>Una per línia: «Nom &lt;adreça&gt;» o només l'adreça</small>
          <textarea name="manual_emails" aria-label="Adreces"></textarea></span></label>
      </div>
    </div></div>
    <div class="me-step"><span class="n">2</span><div>
      <h3>Des de quin domini</h3>
      <div class="me-opts">
        <?php foreach ($brands as $d => $brand): ?>
          <label class="me-opt"><input type="radio" name="domain" value="<?= e($d) ?>"><span><b><?= e($d) ?></b><small><?= e((string) $brand['sender']) ?><?= (int) $brand['limit'] > 0 ? ' · ' . (int) $brand['limit'] . ' correus per hora com a molt' : '' ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <label class="me-chk" style="margin:8px 0 0" id="me-only-wrap"><input type="checkbox" id="me-only-domain" checked> Només als clients i webs d'aquest domini</label>
      <p class="me-small" id="me-only-note" hidden style="margin:6px 0 0">Les llistes i les adreces escrites a mà no es filtren pel domini: les heu triat vosaltres.</p>
    </div></div>
    <div class="me-step"><span class="n">3</span><div>
      <h3>Quan</h3>
      <div class="me-opts">
        <label class="me-opt"><input type="radio" name="when" value="now"><span><b>Ara</b><small>La primera tanda surt de seguida; la resta, cada cinc minuts</small></span></label>
        <label class="me-opt"><input type="radio" name="when" value="later"><span><b>Programat</b><br>
          <input type="date" name="date" aria-label="Dia"> <input type="time" name="time" aria-label="Hora"></span></label>
      </div>
    </div></div>
    <div class="me-sum" id="me-sum"><span class="me-busy">Comptant…</span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <label class="me-chk" style="margin:0"><input type="checkbox" id="me-test-first"> Envia'm una prova abans a <?= e((string) ($me['email'] ?? '')) ?></label>
      <span style="flex:1"></span>
      <button type="button" class="btn btn--ghost" data-close>Cancel·la</button>
      <button type="submit" class="btn me-send" id="me-send-go">Envia</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php if ($aiShow): ?>
<dialog class="me-dlg me-ai" id="me-ai-dlg" aria-labelledby="me-ai-h">
  <div class="in">
    <div class="head">
      <h2 id="me-ai-h">✨ Crea amb IA</h2>
      <span class="me-seg" role="group" aria-label="Què voleu crear" id="me-ai-mode">
        <button type="button" data-mode="text" aria-pressed="true">Text</button>
        <button type="button" data-mode="image" aria-pressed="false">Imatge</button>
      </span>
      <button type="button" class="btn btn--ghost btn--sm" data-close aria-label="Tanca">✕</button>
    </div>
    <div class="me-ai-target" id="me-ai-target"></div>
    <?php if (!$aiText && !$aiImage): ?>
      <p style="margin:0;font-size:.9rem;line-height:1.5">Encara no hi ha cap servei d'IA configurat. <a href="<?= e(url('/configuracio/ai')) ?>">Poseu-hi la clau de Claude o la d'OpenAI</a>.</p>
    <?php endif; ?>

    <div data-ai-pane="text"<?= $aiText ? '' : ' hidden' ?>>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php if (count($aiText) > 1): ?>
          <div class="me-f" style="margin:0"><span>Amb</span>
            <div class="seg" role="group" aria-label="Servei d'IA per al text" id="me-ai-provider">
              <?php foreach ($aiText as $k => $label): ?>
                <button type="button" data-provider="<?= e($k) ?>" aria-pressed="<?= $k === ($ai['provider'] ?? '') ? 'true' : 'false' ?>"><?= e($label) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <label class="me-f" style="margin:0">Què voleu que escrigui?
          <textarea id="me-ai-prompt" maxlength="4000" placeholder="Ex.: Expliqueu que ja es poden obrir les inscripcions del cros des del panell del web, i que les famílies reben el dorsal per correu."></textarea></label>
        <div class="opts">
          <label class="me-f">Tipus<select id="me-ai-format"><?php foreach (Ai::FORMATS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
          <label class="me-f">To<select id="me-ai-tone"><?php foreach (Ai::TONES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
          <label class="me-f">Llargada<select id="me-ai-length"><?php foreach (Ai::LENGTHS as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === 'mitja' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="me-chk" style="margin:0"><input type="checkbox" id="me-ai-context" checked> Té en compte el que ja diu el correu</label>
        <label class="me-chk" style="margin:0" id="me-ai-rewrite-wrap" hidden><input type="checkbox" id="me-ai-rewrite"> Parteix del text del bloc (millora'l o reescriu-lo)</label>
      </div>
    </div>

    <div data-ai-pane="image" hidden>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php if ($aiImage): ?>
          <label class="me-f" style="margin:0">Descriviu la imatge
            <textarea id="me-ai-iprompt" maxlength="3000" placeholder="Ex.: Sortida d'un cros escolar en un parc a la tardor, vista de lluny, amb molts infants corrent amb samarretes de colors."></textarea></label>
          <div class="opts" style="grid-template-columns:1fr 1fr">
            <div class="me-f"><span>Forma</span><div class="seg" role="group" aria-label="Forma" id="me-ai-shape"><?php $first = true; foreach (Ai::SHAPES as $k => $v): ?><button type="button" data-v="<?= e($k) ?>" aria-pressed="<?= $first ? 'true' : 'false' ?>"><?= e($v['label']) ?></button><?php $first = false; endforeach; ?></div></div>
            <div class="me-f"><span>Estil</span><div class="seg" role="group" aria-label="Estil" id="me-ai-style"><?php $first = true; foreach (Ai::STYLES as $k => $v): ?><button type="button" data-v="<?= e($k) ?>" aria-pressed="<?= $first ? 'true' : 'false' ?>"><?= e($v['label']) ?></button><?php $first = false; endforeach; ?></div></div>
          </div>
          <div class="me-f" style="margin:0">
            <span>Imatges de referència (opcional)</span>
            <div class="me-refs" id="me-ai-refs" aria-label="Imatges de referència">
              <button type="button" class="me-ref-add" id="me-ai-refs-add"><span aria-hidden="true">＋</span>Afegeix</button>
              <input type="file" id="me-ai-refs-file" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden>
            </div>
            <small class="me-small" style="margin:0">Fotos, captures o logotips perquè la IA s'hi basi. Arrossegueu-les aquí o enganxeu una captura amb <b>Ctrl+V</b>. <?= Ai::MAX_REFS ?> com a molt. Expliqueu a la descripció què n'ha de fer: «feu servir aquesta foto de fons», «seguiu l'estil d'aquesta il·lustració»…</small>
          </div>
          <label class="me-chk" style="margin:0" id="me-ai-usecur-wrap" hidden><input type="checkbox" id="me-ai-usecur"> Feu servir també la imatge que ja hi ha al bloc</label>
          <label class="me-chk" style="margin:0" id="me-ai-keep-wrap" hidden><input type="checkbox" id="me-ai-keep"> Conserva fidelment els detalls de les referències (cares, logotips, textos)</label>
          <label class="me-chk" style="margin:0"><input type="checkbox" id="me-ai-notext" checked> Sense text ni logotips dins de la imatge (la IA els escriu malament)</label>
          <p class="me-note">Les imatges les crea OpenAI (<?= e((string) ($ai['imageModel'] ?? '')) ?>): Claude no en fa. Pot trigar fins a un minut.</p>
        <?php else: ?>
          <p style="margin:0;font-size:.9rem;line-height:1.5">Per crear imatges cal la clau d'OpenAI (Claude no en fa). <a href="<?= e(url('/configuracio/ai')) ?>">Poseu-la a la configuració</a>.</p>
        <?php endif; ?>
      </div>
    </div>

    <p class="me-note">El que escriviu aquí (i el text del correu, si ho marqueu) s'envia al servei d'IA. No hi poseu dades personals de ningú i reviseu sempre el resultat abans d'enviar el correu.</p>
    <div class="me-ai-out" id="me-ai-out" hidden></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
      <button type="button" class="btn btn--ghost" id="me-ai-new" hidden>Afegeix com a bloc nou</button>
      <button type="button" class="btn btn--ghost" id="me-ai-replace" hidden>Substitueix</button>
      <button type="button" class="btn btn--ghost" id="me-ai-subject" hidden>Fes-ho servir d'assumpte</button>
      <button type="button" class="btn" id="me-ai-go">Genera</button>
    </div>
  </div>
</dialog>
<?php endif; ?>

<dialog class="me-dlg" id="me-src-dlg" aria-labelledby="me-src-h" style="width:min(620px, calc(100vw - 24px))">
  <div class="in">
    <div class="head"><h2 id="me-src-h">Trieu un web del directori</h2><button type="button" class="btn btn--ghost btn--sm" data-close aria-label="Tanca">✕</button></div>
    <input class="input" type="search" id="me-src-q" placeholder="Cerqueu pel nom o la població" aria-label="Cerca">
    <div id="me-src-list" class="me-hist" role="listbox" aria-label="Resultats" style="min-height:120px"></div>
    <p class="me-note">Els webs publicats que surten al directori de curses. Les dades es copien al correu: després les podeu retocar.</p>
  </div>
</dialog>

<script type="application/json" id="me-cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
