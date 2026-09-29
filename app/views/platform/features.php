<?php
/**
 * Què sap fer la plataforma.
 * @var string $intro
 * @var string $closing
 * @var array<int,array{icon:string,title:string,text:string}> $features
 */
use Cros\Core\Icons;
use Cros\Platform\Platform;
?>
<section class="platform-page platform-page--wide">
  <div class="container">
    <header class="page-head">
      <h1><?= e($title) ?></h1>
      <?php if (trim($intro) !== ''): ?>
        <p class="lead"><?= nl2br(e($intro)) ?></p>
      <?php endif; ?>
    </header>

    <?php if ($features): ?>
      <div class="feature-grid">
        <?php foreach ($features as $feature): ?>
          <article class="feature-card">
            <?php if (($feature['icon'] ?? '') !== ''): ?>
              <span class="feature-card__icon"><?= Icons::svg((string) $feature['icon'], 'icon', 24) ?></span>
            <?php endif; ?>
            <h2><?= e((string) ($feature['title'] ?? '')) ?></h2>
            <p><?= nl2br(e((string) ($feature['text'] ?? ''))) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (trim($closing) !== ''): ?>
      <p class="feature-closing"><?= nl2br(e($closing)) ?></p>
    <?php endif; ?>

    <div class="feature-cta">
      <a class="btn btn--accent" href="<?= e(url('/')) ?>#formulari">Crea la web per al teu cros</a>
      <a class="btn btn--ghost" href="mailto:<?= e(Platform::notifyEmail()) ?>">Pregunta'ns el que et calgui</a>
    </div>
  </div>
</section>
