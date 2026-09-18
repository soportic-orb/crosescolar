<?php
/** Classificació pública de la cursa. */
use Cros\Core\Icons;
use Cros\Models\Bib;
$showBib = setting('results_show_bib', '1') === '1';
$publicPdf = setting('results_public_pdf', '1') === '1';
$medals = ['#c9a227', '#9aa6ad', '#b06a3b'];
?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => setting('results_title', 'Resultats de la cursa'),
    'subtitle' => setting('event_date', '') ? ucfirst(ca_date(setting('event_date'), true)) . ' · ' . $total . ' participants classificats' : '',
    'breadcrumb' => ['Resultats' => ''],
]) ?>

<section class="section">
  <div class="container">
    <div class="prose" style="max-width:70ch"><?= setting_html('results_intro') ?></div>

    <?php if ($publicPdf && $groups): ?>
      <div class="flex" style="margin:1.4rem 0 2rem">
        <a class="btn btn--ghost" href="<?= e(url('/resultats/pdf')) ?>">
          <?= Icons::svg('download', 'icon', 18) ?> Totes les categories (PDF)
        </a>
        <a class="btn btn--ghost" href="<?= e(url('/resultats/pdf', ['tipus' => 'arribada'])) ?>">
          <?= Icons::svg('download', 'icon', 18) ?> Ordre d'arribada (PDF)
        </a>
      </div>
    <?php endif; ?>

    <?php if (!$groups): ?>
      <div class="notice-box">Encara no hi ha resultats publicats. Els penjarem tan bon punt acabi la cursa.</div>
    <?php endif; ?>

    <?php foreach ($groups as $group): ?>
      <div style="margin-bottom:2.6rem">
        <div class="flex-between" style="margin-bottom:.8rem">
          <h2 style="margin:0"><?= e($group['name']) ?></h2>
          <?php if ($publicPdf): ?>
            <a class="chip" href="<?= e(url('/resultats/pdf', ['categoria' => $group['id']])) ?>">
              <?= Icons::svg('download', 'icon', 15) ?> PDF
            </a>
          <?php endif; ?>
        </div>
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th style="width:70px">Posició</th>
                <?php if ($showBib): ?><th style="width:80px">Dorsal</th><?php endif; ?>
                <th>Participant</th>
                <th>Escola</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($group['rows'] as $row): ?>
                <?php $position = (int) $row['position']; ?>
                <tr>
                  <td>
                    <?php if ($position <= 3): ?>
                      <span class="chip" style="background:<?= e($medals[$position - 1]) ?>22;color:<?= e($medals[$position - 1]) ?>">
                        <?= Icons::svg('medal', 'icon', 15) ?> <?= $position ?>
                      </span>
                    <?php else: ?>
                      <strong><?= $position ?></strong>
                    <?php endif; ?>
                  </td>
                  <?php if ($showBib): ?><td class="mono"><?= e(Bib::number($row)) ?></td><?php endif; ?>
                  <td><strong><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong></td>
                  <td style="color:var(--ink-soft)"><?= e($row['school'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (setting('results_note', '')): ?>
      <div class="notice-box" style="margin-top:1.5rem"><?= setting_html('results_note') ?></div>
    <?php endif; ?>
  </div>
</section>
