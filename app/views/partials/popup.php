<?php
/**
 * Cartell emergent de la portada. Sense JavaScript no s'obre: és un avís
 * puntual i no ha de tapar el web a qui no el pugui tancar.
 */
$popup = \Cros\Models\Notice::popup();
if (!$popup) {
    return;
}
$image = '<img src="' . e(upload_url($popup['image'])) . '" alt="' . e($popup['alt']) . '">';
?>
<dialog class="modal modal--popup" id="popup" aria-label="<?= e($popup['alt'] !== '' ? $popup['alt'] : 'Avís') ?>"
        data-popup data-notice="<?= e($popup['key']) ?>" data-once="<?= $popup['once'] ? '1' : '0' ?>">
  <div class="modal__box modal__box--bare">
    <button type="button" class="modal__close" data-popup-close aria-label="Tancar">&times;</button>
    <?php if ($popup['url'] !== ''): ?>
      <a href="<?= e($popup['url']) ?>" target="_blank" rel="noopener" data-popup-link><?= $image ?></a>
    <?php else: ?>
      <?= $image ?>
    <?php endif; ?>
  </div>
</dialog>
