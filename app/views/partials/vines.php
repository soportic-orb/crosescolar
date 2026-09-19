<?php
/** Il·lustració decorativa de vinyes del Penedès (turons + fulles i raïms). */
$height = $height ?? 150;
$opacity = $opacity ?? 1;
?>
<div class="hero__vines" style="height:<?= (int) $height ?>px;opacity:<?= e((string) $opacity) ?>" aria-hidden="true">
  <svg class="hero__hills" viewBox="0 0 1440 150" preserveAspectRatio="none">
    <path d="M0 96c120-26 220-8 340 6s210 10 330-10 236-30 356-14 300 40 414 26v46H0z" fill="rgba(18,48,28,.35)"/>
    <path d="M0 118c140-22 250 6 372 14s230-4 348-20 250-22 372-6 244 34 348 26v18H0z" fill="rgba(18,48,28,.6)"/>
  </svg>
  <?php // Els motius van en un SVG a part perquè no s'estirin amb l'amplada de la pantalla. ?>
  <svg class="hero__motifs" viewBox="0 0 1920 70" preserveAspectRatio="xMidYMid slice"
       fill="rgba(255,255,255,.2)" color="rgba(255,255,255,.2)">
    <?= \Cros\Core\Vines::band(1920, 40, ['step' => 104, 'size' => 54]) ?>
  </svg>
</div>
