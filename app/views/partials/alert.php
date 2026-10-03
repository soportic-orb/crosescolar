<?php
/**
 * Avís emergent amb icona. Com el cartell de la portada, sense JavaScript no
 * s'obre: un avís que no es pot tancar val més no ensenyar-lo.
 */
$avis = \Cros\Models\Notice::alert();
if (!$avis) {
    return;
}
// Una adreça de fora s'obre en una pestanya nova; una d'aquest web, no: qui la
// segueix no ha de perdre de vista on era.
$fora = preg_match('#^https?://#i', $avis['url']) === 1;
?>
<dialog class="modal modal--avis" id="avis"
        <?= $avis['title'] !== '' ? 'aria-labelledby="avis-titol"' : 'aria-label="Avís"' ?>
        data-popup data-notice="<?= e($avis['key']) ?>" data-once="<?= $avis['once'] ? '1' : '0' ?>">
  <div class="modal__box avis">
    <button type="button" class="modal__close modal__close--clar" data-popup-close aria-label="Tancar l'avís">&times;</button>
    <span class="avis__icona" style="color:<?= e($avis['color']) ?>">
      <?= \Cros\Core\Icons::svg($avis['icon'], 'icon', 30) ?>
    </span>
    <div class="avis__text">
      <?php if ($avis['title'] !== ''): ?>
        <h2 class="modal__title" id="avis-titol"><?= e($avis['title']) ?></h2>
      <?php endif; ?>
      <?php if ($avis['text'] !== ''): ?>
        <div class="prose"><?= setting_html('alert_text') ?></div>
      <?php endif; ?>
      <?php if ($avis['url'] !== ''): ?>
        <div class="modal__actions">
          <a class="btn" href="<?= e($avis['url']) ?>" data-popup-link
             <?= $fora ? 'target="_blank" rel="noopener"' : '' ?>><?= e($avis['label']) ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</dialog>
