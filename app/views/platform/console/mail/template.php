<?php
/**
 * L'editor de la plantilla dels correus.
 * @var string $header
 * @var string $footer
 * @var bool $custom
 */
use Cros\Core\Icons;
use Cros\Platform\MailTemplate;
?>
<div class="panel">
  <div class="panel__head">
    <h2>Plantilla del correu</h2>
    <div class="spacer" style="display:flex;gap:.4rem;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/plantilla/vista-previa')) ?>" target="_blank">
        <?= Icons::svg('eye', 'icon', 15) ?> Vista prèvia
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments')) ?>">← Els enviaments</a>
    </div>
  </div>
  <div class="panel__body">
    <p class="text-soft" style="margin-top:0">
      És el que embolica cada enviament: el que hi ha a dalt i el que hi ha a baix del text
      que s'escriu. El <strong>marc</strong> —la taula que fa que el correu es vegi centrat i
      s'adapti al mòbil— no s'edita a posta: el codi d'un correu que es vegi bé a l'Outlook,
      al Gmail i a l'iPhone és d'una altra època, i n'hi ha prou amb una etiqueta mal tancada
      perquè mig món el vegi escapçat.
    </p>
    <p class="text-soft">
      Marcadors que s'hi poden escriure:
      <?php foreach (MailTemplate::PLACEHOLDERS as $tag => $what): ?>
        <code><?= e($tag) ?></code> (<?= e(mb_strtolower($what)) ?>)<?= $tag === array_key_last(MailTemplate::PLACEHOLDERS) ? '.' : ', ' ?>
      <?php endforeach; ?>
    </p>
    <?php if (!$custom): ?>
      <div class="alert alert--info">Ara mateix hi ha la plantilla de fàbrica. El que deseu aquí la substituirà.</div>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e(url('/enviaments/plantilla')) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="panel" style="margin-top:1.2rem">
    <div class="panel__head"><h2>Capçalera</h2></div>
    <div class="panel__body">
      <div class="field">
        <label for="header">HTML de dalt del correu</label>
        <textarea id="header" name="header" rows="10" class="mono" spellcheck="false"><?= e($header) ?></textarea>
        <span class="hint">
          Hi sol anar el logotip i el nom del servei. Feu servir estils a dins de cada etiqueta
          (<code>style="…"</code>): la majoria de gestors de correu no llegeixen els fulls d'estil.
        </span>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-top:1.2rem">
    <div class="panel__head"><h2>Peu</h2></div>
    <div class="panel__body">
      <div class="field">
        <label for="footer">HTML de baix del correu</label>
        <textarea id="footer" name="footer" rows="10" class="mono" spellcheck="false"><?= e($footer) ?></textarea>
        <span class="hint">
          Hi sol anar qui envia el correu, per què el rep qui el rep i com posar-s'hi en contacte.
          És on la normativa espera trobar la identificació de qui escriu.
        </span>
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Desar la plantilla</button>
      <a class="btn btn--ghost" href="<?= e(url('/enviaments/plantilla/vista-previa')) ?>" target="_blank">Veure com queda</a>
    </div>
  </div>
</form>

<?php if ($custom): ?>
  <div class="panel" style="margin-top:1.2rem">
    <div class="panel__head"><h2>Tornar enrere</h2></div>
    <form method="post" action="<?= e(url('/enviaments/plantilla')) ?>"
          data-confirm="Tornar a la capçalera i el peu de fàbrica? Es perdrà el que hi heu escrit.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset">
      <div class="panel__body">
        <p class="text-soft" style="margin:0">
          Torna la plantilla a com venia, feta amb el nom, el logotip i el color de la plataforma.
        </p>
      </div>
      <div class="panel__foot">
        <button class="btn btn--ghost" type="submit">Tornar a la de fàbrica</button>
      </div>
    </form>
  </div>
<?php endif; ?>
