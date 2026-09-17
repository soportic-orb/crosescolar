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
  <svg class="page-header__vines" viewBox="0 0 600 120" width="600" height="120" aria-hidden="true" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round">
    <?php for ($i = 0; $i < 12; $i++): ?>
      <g transform="translate(<?= $i * 52 ?>,0)">
        <path d="M26 120V78M14 84h24M17 70h18"/>
        <circle cx="26" cy="62" r="5"/>
      </g>
    <?php endfor; ?>
  </svg>
</section>
