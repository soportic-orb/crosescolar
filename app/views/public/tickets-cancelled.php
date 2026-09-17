<?php /** Pagament cancel·lat. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', ['title' => 'Pagament cancel·lat', 'breadcrumb' => ['Esmorzar' => '/esmorzar', 'Cancel·lat' => '']]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="alert alert--warning"><span>No s'ha completat el pagament i no s'ha fet cap càrrec.</span></div>
    <p>Pots tornar a provar-ho quan vulguis. Si has tingut algun problema, escriu-nos i t'ajudem.</p>
    <div class="flex">
      <a class="btn" href="<?= e(url('/esmorzar')) ?>">Tornar als tiquets</a>
      <a class="btn btn--ghost" href="<?= e(url('/contacte')) ?>">Contactar</a>
    </div>
  </div>
</section>
