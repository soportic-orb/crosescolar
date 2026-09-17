<?php /** Llistat de recorreguts. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => setting('courses_title', 'Els recorreguts'),
    'subtitle' => setting('courses_intro', ''),
    'breadcrumb' => ['Recorreguts' => ''],
]) ?>

<section class="section">
  <div class="container">
    <?php if (!$courses): ?>
      <div class="notice-box">Els recorreguts es publicaran ben aviat.</div>
    <?php else: ?>
      <div class="grid grid--3">
        <?php foreach ($courses as $course): ?>
          <?= \Cros\Core\View::partial('partials/course-card', ['course' => $course]) ?>
        <?php endforeach; ?>
      </div>

      <?php foreach ($courses as $course): ?>
        <div style="margin-top:3rem">
          <h2 id="<?= e($course['slug']) ?>"><?= e($course['name']) ?></h2>
          <div class="course-card__stats" style="margin-bottom:1rem">
            <?php if ((int) $course['distance_m'] > 0): ?>
              <span class="chip"><?= e(number_format((int) $course['distance_m'] / 1000, 1, ',', '.')) ?> km</span>
            <?php endif; ?>
            <?php if (!empty($course['elevation_m'])): ?><span class="chip chip--muted">+<?= (int) $course['elevation_m'] ?> m</span><?php endif; ?>
            <?php if (!empty($course['surface'])): ?><span class="chip chip--grape"><?= e($course['surface']) ?></span><?php endif; ?>
          </div>
          <?php if (!empty($course['description'])): ?>
            <div class="prose" style="max-width:70ch"><?= \Cros\Core\Html::clean((string) $course['description']) ?></div>
          <?php endif; ?>
          <?= \Cros\Core\View::partial('partials/wikiloc', ['course' => $course]) ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
