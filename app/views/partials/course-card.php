<?php
/** Targeta d'un recorregut. */
$wikilocId = \Cros\Models\Content::wikilocId($course);
?>
<article class="card card--hover course-card">
  <div class="course-card__media">
    <?php if (!empty($course['image'])): ?>
      <img src="<?= e(upload_url($course['image'])) ?>" alt="<?= e($course['name']) ?>" loading="lazy">
    <?php else: ?>
      <div class="course-card__placeholder"><?= \Cros\Core\Icons::svg('map', 'icon', 48) ?></div>
    <?php endif; ?>
  </div>
  <div class="course-card__body">
    <h3><a href="<?= e(url('/recorreguts/' . $course['slug'])) ?>" style="text-decoration:none"><?= e($course['name']) ?></a></h3>
    <?php if (!empty($course['description'])): ?>
      <p style="color:var(--ink-soft);font-size:.95rem"><?= e(excerpt(strip_tags((string) $course['description']), 120)) ?></p>
    <?php endif; ?>
    <div class="course-card__stats">
      <?php if ((int) $course['distance_m'] > 0): ?>
        <span class="chip"><?= \Cros\Core\Icons::svg('run', 'icon', 15) ?> <?= e(number_format((int) $course['distance_m'] / 1000, 1, ',', '.')) ?> km</span>
      <?php endif; ?>
      <?php if (!empty($course['elevation_m'])): ?>
        <span class="chip chip--muted"><?= \Cros\Core\Icons::svg('mountain', 'icon', 15) ?> +<?= (int) $course['elevation_m'] ?> m</span>
      <?php endif; ?>
      <?php if (!empty($course['surface'])): ?>
        <span class="chip chip--grape"><?= e($course['surface']) ?></span>
      <?php endif; ?>
      <?php if ($wikilocId !== ''): ?>
        <span class="chip chip--accent"><?= \Cros\Core\Icons::svg('map', 'icon', 15) ?> Wikiloc</span>
      <?php endif; ?>
    </div>
  </div>
</article>
