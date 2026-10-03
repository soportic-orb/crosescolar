<?php
/**
 * Franja d'avís per a qui té la sessió oberta i està veient una pàgina que els
 * visitants encara no veuen.
 *
 * Rep el text («text») i, si es pot publicar des d'aquí mateix, l'adreça del
 * formulari («action») i què hi diu el botó («button»).
 */
$text = (string) ($text ?? '');
$action = (string) ($action ?? '');
$button = (string) ($button ?? 'Publicar-ho ara');
?>
<div class="preview-bar">
  <span><?= \Cros\Core\Icons::svg('eye', 'icon', 18) ?> <?= $text ?></span>
  <?php if ($action !== ''): ?>
    <form method="post" action="<?= e($action) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="enable" value="1">
      <button type="submit"><?= e($button) ?></button>
    </form>
  <?php endif; ?>
</div>
