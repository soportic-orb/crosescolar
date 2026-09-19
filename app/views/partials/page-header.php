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
  <svg class="page-header__vines" viewBox="0 0 600 140" width="600" height="140" aria-hidden="true" fill="currentColor">
    <?= \Cros\Core\Vines::band(600, 96, ['step' => 96, 'size' => 62]) ?>
  </svg>
</section>
