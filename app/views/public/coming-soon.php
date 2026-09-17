<?php
/** Avís públic mentre el web està en preparació. */
$logo = (string) setting('logo', '');
$eventDate = (string) setting('event_date', '');
$eventTime = (string) setting('event_time', '09:30');
$showCountdown = setting('coming_soon_countdown', '1') === '1' && $eventDate !== '';
$countdownTarget = $eventDate . 'T' . ($eventTime !== '' ? $eventTime : '09:00') . ':00';
$upcoming = $eventDate !== '' && strtotime($eventDate . ' 23:59:59') >= time();
$social = array_filter([
    'instagram' => setting('social_instagram', ''),
    'facebook' => setting('social_facebook', ''),
    'whatsapp' => setting('social_whatsapp', ''),
]);
?>
<div class="soon__card">
  <?php if ($logo !== ''): ?>
    <img class="soon__logo" src="<?= e(upload_url($logo)) ?>" alt="<?= e(setting('site_name', '')) ?>">
  <?php else: ?>
    <span class="soon__mark"><?= \Cros\Core\Icons::svg('run', 'icon', 38) ?></span>
  <?php endif; ?>

  <span class="hero__badge"><?= \Cros\Core\Icons::svg('vine', 'icon', 16) ?> <?= e(setting('site_name', 'Cros Escolar La Granada')) ?></span>
  <h1><?= e(setting('coming_soon_title', 'Aviat publicarem el web')) ?></h1>
  <div class="soon__text"><?= setting_html('coming_soon_text') ?></div>

  <?php if ($showCountdown): ?>
    <div class="soon__meta">
      <?php if ($eventDate !== ''): ?>
        <div><?= \Cros\Core\Icons::svg('calendar', 'icon', 20) ?> <?= e(ucfirst(ca_date($eventDate, true))) ?></div>
      <?php endif; ?>
      <?php if (setting('event_place', '')): ?>
        <div><?= \Cros\Core\Icons::svg('location', 'icon', 20) ?> <?= e(setting('event_place')) ?></div>
      <?php endif; ?>
    </div>
    <?php if ($upcoming): ?>
      <div class="countdown" data-countdown="<?= e($countdownTarget) ?>" aria-label="Compte enrere fins a la cursa">
        <div class="countdown__unit"><span class="countdown__num" data-unit="days">--</span><span class="countdown__label">dies</span></div>
        <div class="countdown__unit"><span class="countdown__num" data-unit="hours">--</span><span class="countdown__label">hores</span></div>
        <div class="countdown__unit"><span class="countdown__num" data-unit="minutes">--</span><span class="countdown__label">minuts</span></div>
        <div class="countdown__unit"><span class="countdown__num" data-unit="seconds">--</span><span class="countdown__label">segons</span></div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (setting('coming_soon_contact', '1') === '1'): ?>
    <div class="soon__contact">
      <?php if (setting('contact_email', '')): ?>
        Per a qualsevol consulta: <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
      <?php endif; ?>
      <?php if (setting('organizer', '')): ?>
        <div style="margin-top:.4rem"><?= e(setting('organizer')) ?></div>
      <?php endif; ?>
      <?php if ($social): ?>
        <div class="social-links">
          <?php foreach ($social as $network => $link): ?>
            <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($network)) ?>">
              <?= \Cros\Core\Icons::svg($network, 'icon', 20) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
