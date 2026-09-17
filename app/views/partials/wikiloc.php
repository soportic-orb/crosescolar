<?php
/**
 * Mapa de Wikiloc amb càrrega diferida (només es connecta si s'hi fa clic).
 * @var array $course
 */
$wikilocId = \Cros\Models\Content::wikilocId($course);
$embed = $wikilocId !== ''
    ? 'https://ca.wikiloc.com/wikiloc/embedv2.do?id=' . rawurlencode($wikilocId) . '&measures=on&title=off&near=off&images=off&maptype=H'
    : '';
?>
<?php if ($embed !== ''): ?>
  <div class="map-embed">
    <div class="map-embed__placeholder" data-embed="<?= e($embed) ?>" data-title="<?= e('Mapa de ' . $course['name']) ?>" role="button" tabindex="0">
      <?= \Cros\Core\Icons::svg('map', 'icon', 40) ?>
      <strong>Mostra el mapa del recorregut</strong>
      <span style="font-size:.9rem">Es carregarà el mapa de Wikiloc (servei extern)</span>
    </div>
  </div>
  <p style="margin-top:.7rem;font-size:.9rem">
    <a href="<?= e($course['wikiloc_url'] ?: 'https://ca.wikiloc.com/wikiloc/view.do?id=' . $wikilocId) ?>" target="_blank" rel="noopener">
      Obre la ruta a Wikiloc <?= \Cros\Core\Icons::svg('external', 'icon', 14) ?>
    </a>
    <?php if (!empty($course['gpx_file'])): ?>
      · <a href="<?= e(upload_url($course['gpx_file'])) ?>" download>Descarrega el GPX <?= \Cros\Core\Icons::svg('download', 'icon', 14) ?></a>
    <?php endif; ?>
  </p>
<?php elseif (!empty($course['gpx_file'])): ?>
  <p><a class="btn btn--ghost" href="<?= e(upload_url($course['gpx_file'])) ?>" download>
    <?= \Cros\Core\Icons::svg('download', 'icon', 18) ?> Descarrega el GPX</a></p>
<?php endif; ?>
