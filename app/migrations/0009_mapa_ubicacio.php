<?php
/**
 * El mapa de la ubicació passa a sortir de les dades de la cursa.
 *
 * Fins ara l'enllaç del mapa era un camp de text independent que no tenia res a
 * veure amb l'adreça configurada, i els camps de latitud i longitud no
 * s'utilitzaven enlloc: el mapa sempre obria el mateix punt, encara que
 * l'organització hagués canviat l'adreça.
 *
 * Aquí es passa el punt de l'enllaç antic als camps de coordenades, que ara sí
 * que manen. Només es toca el que encara tenia el valor per defecte de les
 * versions anteriors; si l'organització ja ho havia configurat, es respecta.
 */

use Cros\Core\Map;
use Cros\Core\Settings;

return static function (PDO $pdo): void {
    $oldEmbed = 'https://www.openstreetmap.org/?mlat=41.3778&mlon=1.7203#map=16/41.3778/1.7203';
    $oldLat = '41.3778';
    $oldLng = '1.7203';

    $embed = trim((string) Settings::get('map_embed', ''));
    $lat = trim((string) Settings::get('map_lat', ''));
    $lng = trim((string) Settings::get('map_lng', ''));
    $untouched = ($lat === '' || $lat === $oldLat) && ($lng === '' || $lng === $oldLng);

    if ($embed === '' || $embed === $oldEmbed) {
        // Instal·lació sense configurar: el punt passa a ser la zona esportiva.
        if ($untouched) {
            Settings::set('map_lat', '41.376699');
            Settings::set('map_lng', '1.713535');
        }
        if ($embed === $oldEmbed) {
            Settings::set('map_embed', '');
        }
    } elseif ($untouched) {
        // Hi havia un enllaç propi: se'n treu el punt perquè el marqui el mapa.
        $point = Map::parse($embed);
        if ($point !== null) {
            Settings::set('map_lat', Map::format($point['lat']));
            Settings::set('map_lng', Map::format($point['lng']));
        } else {
            // No se'n poden treure coordenades: es deixen buides i mana l'enllaç.
            Settings::set('map_lat', '');
            Settings::set('map_lng', '');
        }
    }

    // L'adreça per defecte apuntava a un carrer que no existeix al mapa.
    if ((string) Settings::get('event_address', '') === 'Carrer de l\'Esport, s/n — 08792 La Granada (Alt Penedès)') {
        Settings::set('event_address', 'Carrer de Vilafranca, s/n — 08792 La Granada (Alt Penedès)');
    }
};
