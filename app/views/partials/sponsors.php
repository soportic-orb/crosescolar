<?php
/** Blocs de logotips de patrocinadors agrupats per tipus. */
$labels = [
    'institucional' => 'Amb el suport institucional de',
    'principal' => 'Patrocinadors principals',
    'collaborador' => 'Col·laboradors',
    'mitjans' => 'Mitjans i altres',
];
$groups = $sponsors ?? [];
?>
<?php if ($groups): ?>
<div class="sponsors">
  <?php foreach ($labels as $tier => $label): ?>
    <?php if (empty($groups[$tier])) { continue; } ?>
    <div class="sponsors__group sponsors__group--<?= e($tier) ?>">
      <h3><?= e($label) ?></h3>
      <div class="sponsors__list">
        <?php foreach ($groups[$tier] as $sponsor): ?>
          <?php $inner = $sponsor['logo']
              ? '<img src="' . e(upload_url($sponsor['logo'])) . '" alt="' . e($sponsor['name']) . '" loading="lazy">'
              : '<span>' . e($sponsor['name']) . '</span>'; ?>
          <?php if (!empty($sponsor['url'])): ?>
            <a class="sponsor" href="<?= e($sponsor['url']) ?>" target="_blank" rel="noopener"
               title="<?= e($sponsor['name'] . ($sponsor['description'] ? ' — ' . $sponsor['description'] : '')) ?>"><?= $inner ?></a>
          <?php else: ?>
            <div class="sponsor" title="<?= e($sponsor['name']) ?>"><?= $inner ?></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
