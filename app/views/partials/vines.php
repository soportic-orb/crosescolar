<?php
/** Il·lustració decorativa de vinyes del Penedès. */
$height = $height ?? 150;
$opacity = $opacity ?? 1;
?>
<svg class="hero__vines" viewBox="0 0 1440 150" preserveAspectRatio="none" aria-hidden="true"
     style="height:<?= (int) $height ?>px;width:100%;opacity:<?= e((string) $opacity) ?>">
  <path d="M0 96c120-26 220-8 340 6s210 10 330-10 236-30 356-14 300 40 414 26v46H0z" fill="rgba(18,48,28,.35)"/>
  <path d="M0 118c140-22 250 6 372 14s230-4 348-20 250-22 372-6 244 34 348 26v18H0z" fill="rgba(18,48,28,.6)"/>
  <g stroke="rgba(255,255,255,.22)" stroke-width="2" fill="none">
    <?php for ($x = 30; $x < 1440; $x += 72): ?>
      <path d="M<?= $x ?> 150v-34M<?= $x - 12 ?> 122h24M<?= $x - 9 ?> 110h18"/>
      <circle cx="<?= $x ?>" cy="104" r="3.4" fill="rgba(255,255,255,.16)"/>
    <?php endfor; ?>
  </g>
</svg>
