<?php
/**
 * Barra d'avís de dalt de tot. Els colors els tria l'organització al panell,
 * de manera que van a l'atribut «style» del mateix element.
 */
$bar = \Cros\Models\Notice::bar();
if (!$bar) {
    return;
}
?>
<div class="topbar" id="topbar" data-notice="<?= e($bar['key']) ?>"
     style="background:<?= e($bar['bg']) ?>;color:<?= e($bar['color']) ?>">
  <div class="container topbar__inner">
    <p class="topbar__text"><?= e($bar['text']) ?></p>
    <?php if ($bar['url'] !== ''): ?>
      <a class="topbar__link" href="<?= e($bar['url']) ?>" target="_blank" rel="noopener">
        <?= e($bar['label']) ?>
      </a>
    <?php endif; ?>
    <?php if ($bar['dismissible']): ?>
      <button type="button" class="topbar__close" data-topbar-close aria-label="Tancar l'avís">&times;</button>
    <?php endif; ?>
  </div>
</div>
<?php if ($bar['dismissible']): ?>
  <script>
    // Es mira abans de pintar res: així una barra ja tancada no pampallugueja.
    try {
      if (sessionStorage.getItem('cros_topbar_<?= e($bar['key']) ?>') === '1') {
        document.getElementById('topbar').remove();
      }
    } catch (e) {}
  </script>
<?php endif; ?>
