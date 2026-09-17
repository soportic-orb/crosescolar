<?php /** Preguntes freqüents. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', ['title' => 'Preguntes freqüents', 'breadcrumb' => ['Preguntes freqüents' => '']]) ?>
<section class="section">
  <div class="container-narrow">
    <?php if (!$faqs): ?>
      <div class="notice-box">Encara no hi ha preguntes publicades. Escriviu-nos si teniu qualsevol dubte.</div>
    <?php else: ?>
      <div class="accordion">
        <?php foreach ($faqs as $faq): ?>
          <details>
            <summary><?= e($faq['question']) ?></summary>
            <div class="accordion__body"><?= \Cros\Core\Html::clean((string) $faq['answer']) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p style="margin-top:2rem">Teniu algun altre dubte? <a href="<?= e(url('/contacte')) ?>">Escriviu-nos</a>.</p>
  </div>
</section>
