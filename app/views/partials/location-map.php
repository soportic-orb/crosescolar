<?php
/**
 * Mapa de la ubicació de l'esdeveniment, amb càrrega diferida:
 * no es connecta a OpenStreetMap fins que s'hi fa clic.
 */
$mapLink = \Cros\Core\Map::link();
$mapEmbed = \Cros\Core\Map::embed();
?>
<?php if ($mapEmbed !== ''): ?>
  <div class="map-embed">
    <div class="map-embed__placeholder" data-embed="<?= e($mapEmbed) ?>"
         data-title="<?= e('Mapa de ' . setting('event_place', 'la sortida')) ?>" role="button" tabindex="0">
      <?= \Cros\Core\Icons::svg('location', 'icon', 40) ?>
      <strong>Mostra el mapa de la sortida</strong>
      <span style="font-size:.9rem">Es carregarà el mapa d'OpenStreetMap (servei extern)</span>
    </div>
  </div>
<?php endif; ?>
<?php if ($mapLink !== ''): ?>
  <a class="btn btn--ghost" href="<?= e($mapLink) ?>" target="_blank" rel="noopener"
     style="<?= $mapEmbed !== '' ? 'margin-top:1rem' : '' ?>">
    <?= \Cros\Core\Icons::svg('map', 'icon', 18) ?> Obre el mapa
  </a>
<?php endif; ?>
