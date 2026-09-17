<?php /** Pàgina de text legal. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', ['title' => $title, 'breadcrumb' => [$title => '']]) ?>
<section class="section">
  <div class="container-narrow prose">
    <?= $body !== '' ? $body : '<p>Aquest text encara no s\'ha publicat.</p>' ?>
  </div>
</section>
