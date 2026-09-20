<?php /** Detall d'un recorregut. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => $course['name'],
    'subtitle' => $course['surface'] ?? '',
    'breadcrumb' => ['Recorreguts' => '/recorreguts', $course['name'] => ''],
]) ?>

<section class="section">
  <div class="container split">
    <div>
      <div class="course-card__stats" style="margin-bottom:1.2rem">
        <?php if ((int) $course['distance_m'] > 0): ?>
          <span class="chip"><?= \Cros\Core\Icons::svg('run', 'icon', 15) ?> <?= e(number_format((int) $course['distance_m'] / 1000, 1, ',', '.')) ?> km</span>
        <?php endif; ?>
        <?php if (!empty($course['elevation_m'])): ?>
          <span class="chip chip--muted"><?= \Cros\Core\Icons::svg('mountain', 'icon', 15) ?> +<?= (int) $course['elevation_m'] ?> m</span>
        <?php endif; ?>
        <?php if (!empty($course['surface'])): ?><span class="chip chip--grape"><?= e($course['surface']) ?></span><?php endif; ?>
      </div>

      <?php if (!empty($course['image'])): ?>
        <img src="<?= e(upload_url($course['image'])) ?>" alt="<?= e($course['name']) ?>" style="border-radius:var(--radius);margin-bottom:1.4rem">
      <?php endif; ?>

      <div class="prose"><?= \Cros\Core\Html::clean((string) $course['description']) ?></div>
      <?= \Cros\Core\View::partial('partials/wikiloc', ['course' => $course]) ?>
    </div>

    <aside>
      <?php if ($categories): ?>
        <div class="card">
          <h3><?= \Cros\Core\Icons::svg('users', 'icon', 20) ?> Categories que hi corren</h3>
          <table class="data" style="min-width:0">
            <tbody>
              <?php foreach ($categories as $category): ?>
                <?php
                  // Voltes que fa aquesta categoria en aquest recorregut.
                  $laps = 0;
                  foreach ($category['courses'] ?? [] as $leg) {
                      if ((int) $leg['course_id'] === (int) $course['id']) {
                          $laps = (int) $leg['laps'];
                          break;
                      }
                  }
                ?>
                <tr>
                  <th><?= e($category['name']) ?></th>
                  <td>
                    <?php if ($laps > 0): ?><?= $laps ?> <?= $laps === 1 ? 'volta' : 'voltes' ?> · <?php endif; ?>
                    <?= e($category['start_time']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($otherCourses): ?>
        <div class="card" style="margin-top:1.2rem">
          <h3>Altres recorreguts</h3>
          <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.5rem">
            <?php foreach ($otherCourses as $other): ?>
              <li><a href="<?= e(url('/recorreguts/' . $other['slug'])) ?>"><?= e($other['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</section>
