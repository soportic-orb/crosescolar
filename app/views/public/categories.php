<?php /** Categories, horaris i premis. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => setting('categories_title', 'Categories i premis'),
    'subtitle' => setting('event_date', '') ? ucfirst(ca_date(setting('event_date'), true)) . ' · ' . setting('event_place', '') : '',
    'breadcrumb' => ['Categories i premis' => ''],
]) ?>

<section class="section">
  <div class="container">
    <div class="prose" style="max-width:70ch"><?= setting_html('categories_intro') ?></div>

    <?php if ($categories): ?>
      <div class="table-wrap" style="margin-top:2rem">
        <table class="data">
          <thead>
            <tr>
              <th>Categoria</th>
              <th>Anys de naixement</th>
              <th>Sortida</th>
              <th>Distància</th>
              <th>Recorregut</th>
              <th>Premis</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $category): ?>
              <tr>
                <td>
                  <strong><?= e($category['name']) ?></strong>
                  <?php // El codi de la categoria és intern: només surt al panell. ?>
                  <?php if (($category['gender'] ?? 'mixt') !== 'mixt'): ?>
                    <span class="chip chip--muted"><?= $category['gender'] === 'femeni' ? 'Femenina' : 'Masculina' ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($category['year_from'] && $category['year_to']): ?>
                    <?= e(min((int) $category['year_from'], (int) $category['year_to'])) ?>–<?= e(max((int) $category['year_from'], (int) $category['year_to'])) ?>
                  <?php else: ?>
                    <span class="text-soft">—</span>
                  <?php endif; ?>
                </td>
                <td><strong><?= e($category['start_time']) ?></strong></td>
                <td>
                  <?php if (trim((string) $category['distance_label']) !== ''): ?>
                    <?= e($category['distance_label']) ?>
                  <?php elseif (!empty($category['distance_total'])): ?>
                    <?= e(number_format((int) $category['distance_total'], 0, ',', '.')) ?> m
                  <?php else: ?>
                    <span class="text-soft">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($category['courses'])): ?>
                    <?php foreach ($category['courses'] as $index => $course): ?>
                      <?php if ($index > 0): ?><span class="text-soft"> + </span><?php endif; ?>
                      <span style="white-space:nowrap">
                        <strong><?= (int) $course['laps'] ?> <?= (int) $course['laps'] === 1 ? 'volta' : 'voltes' ?></strong> a
                        <a href="<?= e(url('/recorreguts/' . $course['slug'])) ?>"><?= e($course['name']) ?></a>
                      </span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-soft">—</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:.92rem"><?= nl($category['prizes'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (setting('categories_notes', '')): ?>
      <div class="notice-box" style="margin-top:1.6rem"><?= setting_html('categories_notes') ?></div>
    <?php endif; ?>
  </div>
</section>

<?php if ($prizes): ?>
<section class="section section--tint">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('trophy', 'icon', 16) ?> Premis</span>
      <h2><?= e(setting('prizes_title', 'Premis')) ?></h2>
      <p class="lead"><?= nl(setting('prizes_intro', '')) ?></p>
    </div>
    <div class="grid grid--3">
      <?php foreach ($prizes as $prize): ?>
        <div class="card card--hover">
          <div class="card__icon"><?= \Cros\Core\Icons::svg((string) ($prize['icon'] ?: 'trophy'), 'icon', 24) ?></div>
          <h3><?= e($prize['title']) ?></h3>
          <div style="color:var(--ink-soft);font-size:.95rem"><?= \Cros\Core\Html::clean((string) $prize['description']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container" style="text-align:center">
    <h2>Ja ho tens clar?</h2>
    <p class="lead" style="margin-inline:auto">Inscriu-te a la cursa i mira què trobaràs al punt de recàrrega en acabar.</p>
    <div class="flex" style="justify-content:center;margin-top:1.2rem">
      <a class="btn" href="<?= e(url('/inscripcio')) ?>">Inscripció</a>
      <a class="btn btn--accent" href="<?= e(url('/punt-de-recarrega')) ?>">Punt de recàrrega</a>
    </div>
  </div>
</section>
