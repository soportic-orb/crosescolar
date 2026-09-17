<?php /** Capçalera de les pàgines interiors. */ ?>
<section class="page-header">
  <div class="container">
    <?php if (!empty($breadcrumb)): ?>
      <nav class="breadcrumb" aria-label="Ruta de navegació">
        <a href="<?= e(url('/')) ?>">Inici</a>
        <?php foreach ($breadcrumb as $label => $href): ?>
          <span>/</span>
          <?php if (is_string($href) && $href !== ''): ?>
            <a href="<?= e(url($href)) ?>"><?= e($label) ?></a>
          <?php else: ?>
            <span><?= e(is_int($label) ? $href : $label) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <h1><?= e($heading ?? $title ?? '') ?></h1>
    <?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif; ?>
  </div>
  <?= \Cros\Core\Icons::svg('vine', 'page-header__vines', 220) ?>
</section>
