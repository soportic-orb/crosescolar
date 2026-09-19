<?php /** Separador decoratiu amb fulles de parra i un carràs de raïm. */ ?>
<div class="container">
  <div class="divider-vines" aria-hidden="true">
    <span class="divider-vines__line"></span>
    <svg viewBox="0 0 100 100" width="26" height="26" fill="currentColor" style="transform:rotate(-16deg)"><?= \Cros\Core\Vines::leaf() ?></svg>
    <svg viewBox="0 0 100 100" width="30" height="30" fill="currentColor"><?= \Cros\Core\Vines::grapes() ?></svg>
    <svg viewBox="0 0 100 100" width="26" height="26" fill="currentColor" style="transform:rotate(16deg)"><?= \Cros\Core\Vines::leaf() ?></svg>
    <span class="divider-vines__line"></span>
  </div>
</div>
