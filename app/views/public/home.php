<?php
/** Portada. */
$heroImage = (string) setting('hero_image', '');
$eventDate = (string) setting('event_date', '');
$eventTime = (string) setting('event_time', '');
$countdownTarget = $eventDate !== '' ? $eventDate . 'T' . ($eventTime !== '' ? $eventTime : '09:00') . ':00' : '';
$salesOpen = \Cros\Controllers\TicketsController::salesOpen();
?>

<?= \Cros\Core\View::partial('partials/popup') ?>

<section class="hero<?= $heroImage === '' ? ' hero--plain' : '' ?>">
  <?php if ($heroImage !== ''): ?>
    <div class="hero__bg"><img src="<?= e(upload_url($heroImage)) ?>" alt="" fetchpriority="high"></div>
  <?php endif; ?>
  <div class="hero__overlay"></div>
  <?php if (setting('vine_pattern', '1') === '1'): ?>
    <?= \Cros\Core\View::partial('partials/vines', ['height' => 150]) ?>
  <?php endif; ?>

  <div class="container">
    <?php if (setting('hero_badge', '')): ?>
      <span class="hero__badge"><?= \Cros\Core\Icons::svg('vine', 'icon', 16) ?> <?= e(setting('hero_badge')) ?></span>
    <?php endif; ?>
    <h1><?= e(setting('hero_title', setting('site_name', 'Cros Escolar La Granada'))) ?></h1>
    <p class="hero__subtitle"><?= nl(setting('hero_subtitle', '')) ?></p>

    <div class="hero__meta">
      <?php if ($eventDate !== ''): ?>
        <div><?= \Cros\Core\Icons::svg('calendar', 'icon', 20) ?> <?= e(ucfirst(ca_date($eventDate, true))) ?></div>
      <?php endif; ?>
      <?php if ($eventTime !== ''): ?>
        <div><?= \Cros\Core\Icons::svg('clock', 'icon', 20) ?> A partir de les <?= e($eventTime) ?> h</div>
      <?php endif; ?>
      <?php if (setting('event_place', '')): ?>
        <div><?= \Cros\Core\Icons::svg('location', 'icon', 20) ?> <?= e(setting('event_place')) ?></div>
      <?php endif; ?>
    </div>

    <div class="hero__actions">
      <?php if (setting('hero_cta_label', '')): ?>
        <a class="btn btn--accent" href="<?= e(url(setting('hero_cta_url', '/inscripcio'))) ?>">
          <?= e(setting('hero_cta_label')) ?> <?= \Cros\Core\Icons::svg('arrow', 'icon', 18) ?>
        </a>
      <?php endif; ?>
      <?php if (setting('hero_cta2_label', '')): ?>
        <a class="btn btn--light" href="<?= e(url(setting('hero_cta2_url', '/categories-i-premis'))) ?>"><?= e(setting('hero_cta2_label')) ?></a>
      <?php endif; ?>
    </div>

    <?php if (setting('countdown_enabled', '1') === '1' && $countdownTarget !== ''): ?>
      <div class="countdown" data-countdown="<?= e($countdownTarget) ?>" aria-label="Compte enrere fins a la cursa">
        <div class="countdown__unit"><span class="countdown__num" data-unit="days">--</span><span class="countdown__label">dies</span></div>
        <div class="countdown__unit"><span class="countdown__num" data-unit="hours">--</span><span class="countdown__label">hores</span></div>
        <div class="countdown__unit"><span class="countdown__num" data-unit="minutes">--</span><span class="countdown__label">minuts</span></div>
        <div class="countdown__unit"><span class="countdown__num" data-unit="seconds">--</span><span class="countdown__label">segons</span></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($infoBlocks): ?>
<section class="section" id="informacio">
  <div class="container">
    <div class="grid grid--4">
      <?php foreach ($infoBlocks as $block): ?>
        <div class="card card--hover">
          <div class="card__icon"><?= \Cros\Core\Icons::svg((string) $block['icon'], 'icon', 24) ?></div>
          <h3><?= e($block['title']) ?></h3>
          <div style="color:var(--ink-soft);font-size:.95rem"><?= \Cros\Core\Html::clean((string) $block['body']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section<?= $infoBlocks ? ' section--tint' : '' ?>" id="la-cursa">
  <div class="container split">
    <div>
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('vine', 'icon', 16) ?> La jornada</span>
      <h2><?= e(setting('intro_title', 'La cursa del poble')) ?></h2>
      <div class="prose"><?= setting_html('intro_text') ?></div>
      <div class="flex" style="gap:.8rem;margin-top:1.4rem">
        <?php $registrationsOpen = \Cros\Controllers\RegistrationController::open(); ?>
        <a class="btn" href="<?= e(url('/inscripcio')) ?>">
          <?= \Cros\Core\Icons::svg('run', 'icon', 18) ?>
          <?= $registrationsOpen ? 'Inscriu-t\'hi' : 'Com inscriure-s\'hi' ?>
        </a>
        <a class="btn btn--ghost" href="<?= e(url('/categories-i-premis')) ?>">Categories i horaris</a>
      </div>
    </div>
    <div class="card" style="background:var(--white)">
      <h3 style="margin-bottom:1rem"><?= \Cros\Core\Icons::svg('info', 'icon', 20) ?> Dades pràctiques</h3>
      <table class="data" style="min-width:0">
        <tbody>
          <tr><th style="width:42%">Data</th><td><?= e(ucfirst(ca_date($eventDate, true))) ?></td></tr>
          <tr><th>Hora</th><td><?= e($eventTime) ?> h</td></tr>
          <tr><th>Lloc</th><td><?= e(setting('event_place', '')) ?></td></tr>
          <tr><th>Organitza</th><td><?= e(setting('organizer', '')) ?></td></tr>
          <?php if (setting('collaborators', '')): ?>
            <tr><th>Col·labora</th><td><?= e(setting('collaborators')) ?></td></tr>
          <?php endif; ?>
          <tr><th>Inscripció</th>
            <td><a href="<?= e(url('/inscripcio')) ?>"><?= \Cros\Controllers\RegistrationController::open() ? 'Oberta en línia' : 'Consulteu com fer-la' ?></a></td></tr>
        </tbody>
      </table>
      <?php if ($documents): ?>
        <div style="margin-top:1rem">
          <?php foreach ($documents as $document): ?>
            <a class="chip" style="margin:.2rem .2rem 0 0" href="<?= e(upload_url($document['file'])) ?>" target="_blank" rel="noopener">
              <?= \Cros\Core\Icons::svg('file', 'icon', 15) ?> <?= e($document['title']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($courses): ?>
<section class="section" id="recorreguts">
  <div class="container">
    <div class="section__head">
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('map', 'icon', 16) ?> Circuits</span>
      <h2><?= e(setting('courses_title', 'Els recorreguts')) ?></h2>
      <p class="lead"><?= nl(setting('courses_intro', '')) ?></p>
    </div>
    <div class="grid grid--3">
      <?php foreach ($courses as $course): ?>
        <?= \Cros\Core\View::partial('partials/course-card', ['course' => $course]) ?>
      <?php endforeach; ?>
    </div>
    <?php
    $mainCourse = null;
    foreach ($courses as $course) {
        if (\Cros\Models\Content::wikilocId($course) !== '' || !empty($course['gpx_file'])) {
            $mainCourse = $course;
            break;
        }
    }
    ?>
    <?php if ($mainCourse): ?>
      <div style="margin-top:2rem">
        <h3><?= e($mainCourse['name']) ?> — mapa del recorregut</h3>
        <?= \Cros\Core\View::partial('partials/wikiloc', ['course' => $mainCourse]) ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($schedule): ?>
<section class="section section--tint" id="programa">
  <div class="container split">
    <div>
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('clock', 'icon', 16) ?> Horaris</span>
      <h2><?= e(setting('schedule_title', 'Programa de la jornada')) ?></h2>
      <p class="lead"><?= nl(setting('schedule_intro', '')) ?></p>
      <div class="timeline">
        <?php foreach ($schedule as $item): ?>
          <div class="timeline__item">
            <span class="timeline__time"><?= e($item['time_label']) ?></span>
            <div>
              <h3><?= e($item['title']) ?></h3>
              <?php if (!empty($item['description'])): ?><p><?= e($item['description']) ?></p><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($categories): ?>
      <div class="card">
        <h3><?= \Cros\Core\Icons::svg('users', 'icon', 20) ?> Sortides per categoria</h3>
        <table class="data" style="min-width:0">
          <tbody>
          <?php foreach (array_slice($categories, 0, 9) as $category): ?>
            <tr>
              <th><?= e($category['name']) ?></th>
              <td style="white-space:nowrap"><?= e($category['start_time']) ?></td>
              <td><?= e($category['distance_label']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <a class="btn btn--ghost btn--sm" style="margin-top:1rem" href="<?= e(url('/categories-i-premis')) ?>">
          Totes les categories i premis <?= \Cros\Core\Icons::svg('arrow', 'icon', 16) ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="section section--dark" id="punt-de-recarrega">
  <div class="container">
    <div class="split">
      <div>
        <span class="eyebrow"><?= \Cros\Core\Icons::svg('coffee', 'icon', 16) ?> Punt de recàrrega</span>
        <h2><?= e(setting('tickets_title', 'Tiquets del punt de recàrrega')) ?></h2>
        <div class="prose" style="color:rgba(255,255,255,.85)"><?= setting_html('tickets_intro') ?></div>
        <div class="flex" style="margin-top:1.4rem">
          <?php if ($salesOpen): ?>
            <a class="btn btn--accent" href="<?= e(url('/punt-de-recarrega')) ?>">
              <?= \Cros\Core\Icons::svg('ticket', 'icon', 18) ?> Comprar tiquets
            </a>
            <a class="btn btn--light" href="<?= e(url('/els-meus-tiquets')) ?>">Els meus tiquets</a>
          <?php else: ?>
            <a class="btn btn--light" href="<?= e(url('/punt-de-recarrega')) ?>">
              <?= \Cros\Core\Icons::svg('info', 'icon', 18) ?> Informació del punt de recàrrega
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($ticketTypes): ?>
        <div class="card" style="background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.18);color:#fff">
          <h3 style="color:#fff">Què hi trobaràs</h3>
          <?php foreach ($ticketTypes as $type): ?>
            <div class="flex-between" style="padding:.6rem 0;border-bottom:1px solid rgba(255,255,255,.14)">
              <div>
                <strong><?= e($type['name']) ?></strong>
                <?php if (!empty($type['description'])): ?>
                  <div style="font-size:.88rem;color:rgba(255,255,255,.72)"><?= e(excerpt($type['description'], 70)) ?></div>
                <?php endif; ?>
              </div>
              <strong style="white-space:nowrap"><?= e(money((int) $type['price_cents'], (string) setting('payments_currency', 'EUR'))) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if (\Cros\Core\Settings::bool('results_published')): ?>
<section class="section">
  <div class="container" style="text-align:center">
    <span class="eyebrow"><?= \Cros\Core\Icons::svg('trophy', 'icon', 16) ?> Ja hi ha classificació</span>
    <h2><?= e(setting('results_title', 'Resultats de la cursa')) ?></h2>
    <p class="lead" style="margin-inline:auto">Consulta la classificació per categories de l'última edició.</p>
    <a class="btn" style="margin-top:.8rem" href="<?= e(url('/resultats')) ?>">
      Veure els resultats <?= \Cros\Core\Icons::svg('arrow', 'icon', 18) ?>
    </a>
  </div>
</section>
<?php endif; ?>

<?php if ($gallery): ?>
<section class="section" id="galeria">
  <div class="container">
    <div class="section__head section__head--center">
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('image', 'icon', 16) ?> Fotografies</span>
      <h2><?= e(setting('gallery_title', 'Edicions anteriors')) ?></h2>
    </div>
    <div class="gallery" data-gallery>
      <?php foreach ($gallery as $photo): ?>
        <figure>
          <img src="<?= e(upload_url($photo['file'])) ?>" alt="<?= e($photo['caption'] ?: 'Fotografia del cros') ?>" loading="lazy">
          <?php if (!empty($photo['caption'])): ?><figcaption><?= e($photo['caption']) ?></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?= \Cros\Core\View::partial('partials/divider') ?>

<section class="section section--tint" id="on-som">
  <div class="container split">
    <div>
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('location', 'icon', 16) ?> Ubicació</span>
      <h2><?= e(setting('location_title', 'Com arribar-hi')) ?></h2>
      <p class="lead"><?= nl(setting('location_text', '')) ?></p>
      <p><strong><?= e(setting('event_place', '')) ?></strong><br><?= e(setting('event_address', '')) ?></p>
      <?= \Cros\Core\View::partial('partials/location-map') ?>
    </div>
    <?php if ($faqs): ?>
      <div>
        <h3><?= \Cros\Core\Icons::svg('help', 'icon', 20) ?> Preguntes freqüents</h3>
        <div class="accordion">
          <?php foreach (array_slice($faqs, 0, 6) as $faq): ?>
            <details>
              <summary><?= e($faq['question']) ?></summary>
              <div class="accordion__body"><?= \Cros\Core\Html::clean((string) $faq['answer']) ?></div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($sponsors): ?>
<section class="section" id="patrocinadors">
  <div class="container">
    <div class="section__head section__head--center">
      <span class="eyebrow"><?= \Cros\Core\Icons::svg('heart', 'icon', 16) ?> Gràcies</span>
      <h2><?= e(setting('sponsors_title', 'Amb el suport de')) ?></h2>
      <p class="lead"><?= nl(setting('sponsors_intro', '')) ?></p>
    </div>
    <?= \Cros\Core\View::partial('partials/sponsors', ['sponsors' => $sponsors]) ?>
  </div>
</section>
<?php endif; ?>
