<?php
/**
 * Avís que surt abans de descarregar el dorsal: com s'ha d'imprimir el
 * document i què cal fer-hi després.
 *
 * El text explica el que el PDF porta de debò, que depèn de com estigui
 * configurat el disseny del dorsal al panell.
 */
$sheet = \Cros\Models\Bib::familySheet();
$two = $sheet['per_sheet'] > 1;
?>
<dialog class="modal" id="bib-notice" aria-labelledby="bib-notice-title">
  <form method="dialog" class="modal__box">
    <h2 class="modal__title" id="bib-notice-title">Abans d'imprimir el dorsal</h2>
    <?php if ($two): ?>
      <p>
        El document s'ha d'imprimir en un full <strong>DIN A4</strong> vertical, a mida real
        (sense «ajustar a la pàgina»).
      </p>
      <p>
        Cada full porta <strong>dos dorsals iguals</strong>, un a dalt i un a baix.
        <strong>Retalleu el full per la línia de punts</strong>, que va marcada amb unes tisores,
        i poseu-vos-en un al pit: l'altre és de recanvi.
      </p>
    <?php else: ?>
      <p>
        El document s'ha d'imprimir a mida real (sense «ajustar a la pàgina») en fulls de mida
        <strong><?= e($sheet['name']) ?></strong>.
      </p>
      <p>
        Hi trobareu <strong>dues còpies del dorsal</strong>, una a cada full: poseu-vos-en una
        al pit i guardeu l'altra de recanvi.
      </p>
    <?php endif; ?>
    <div class="modal__actions">
      <a class="btn" data-bib-notice-go href="#">
        <?= \Cros\Core\Icons::svg('download', 'icon', 18) ?> Descarregar el PDF
      </a>
      <button class="btn btn--ghost" value="cancel" type="submit">Tornar</button>
    </div>
  </form>
</dialog>
