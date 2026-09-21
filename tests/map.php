<?php
/**
 * Proves del mapa de la ubicació: que el punt que s'obre sigui el que hi ha
 * configurat a «Dades de la cursa».
 *
 * Ús:  php tests/map.php
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Map;
use Cros\Core\Settings;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

$before = [];
foreach (['map_lat', 'map_lng', 'map_embed', 'event_place', 'event_address', 'event_town'] as $key) {
    $before[$key] = (string) Settings::get($key, '');
}
$restore = static function () use ($before): void {
    foreach ($before as $key => $value) {
        Settings::set($key, $value);
    }
    Settings::load(true);
};
$set = static function (array $values): void {
    foreach ($values as $key => $value) {
        Settings::set($key, $value);
    }
    Settings::load(true);
};

echo "\n== Coordenades escrites a mà ==\n";

$set(['map_lat' => '41,376699', 'map_lng' => '1,713535', 'map_embed' => '']);
check('La coma decimal s\'entén igual', Map::coords() !== null && abs(Map::coords()['lat'] - 41.376699) < 0.000001,
    json_encode(Map::coords()));
check('L\'enllaç marca el punt (mlat/mlon) i hi centra el mapa',
    Map::link() === 'https://www.openstreetmap.org/?mlat=41.376699&mlon=1.713535#map=17/41.376699/1.713535',
    Map::link());
check('El mapa incrustat porta el marcador',
    str_contains(Map::embed(), 'marker=41.376699,1.713535') && str_contains(Map::embed(), 'bbox='),
    Map::embed());

$set(['map_lat' => '41.376699 N', 'map_lng' => '1.713535 E']);
check('Els graus amb lletra d\'hemisferi també valen', Map::coords()['lng'] > 0, json_encode(Map::coords()));
$set(['map_lat' => '41.376699', 'map_lng' => '1.713535 O']);
check('«O» (oest) fa la longitud negativa', Map::coords()['lng'] < 0, json_encode(Map::coords()));

echo "\n== El punt es treu d'un enllaç enganxat ==\n";

$links = [
    'OpenStreetMap amb marcador' => 'https://www.openstreetmap.org/?mlat=41.3767&mlon=1.7135#map=18/41.3767/1.7135',
    'OpenStreetMap només amb la vista' => 'https://www.openstreetmap.org/#map=18/41.3767/1.7135',
    'Google Maps (@)' => 'https://www.google.com/maps/place/Camp/@41.3767,1.7135,17z',
    'Google Maps (punt exacte)' => 'https://www.google.com/maps/place/X/data=!4m2!3m1!8m2!3d41.3767!4d1.7135',
    'Google Maps (?q=)' => 'https://maps.google.com/?q=41.3767,1.7135',
    'Adreça geo:' => 'geo:41.3767,1.7135',
    'Coordenades enganxades' => '41.3767, 1.7135',
];
foreach ($links as $name => $link) {
    $point = Map::parse($link);
    check($name, $point !== null && abs($point['lat'] - 41.3767) < 0.0001 && abs($point['lng'] - 1.7135) < 0.0001,
        json_encode($point));
}
check('Un enllaç sense coordenades no n\'inventa', Map::parse('https://goo.gl/maps/abcdef') === null);
check('Una adreça de text no és un punt', Map::parse('Carrer de Vilafranca, La Granada') === null);
check('El punt 0,0 no compta com a configurat', Map::parse('0, 0') === null);
check('Una latitud impossible es descarta', Map::parse('141.5, 1.7') === null);

$set(['map_lat' => '', 'map_lng' => '', 'map_embed' => 'https://www.google.com/maps/place/X/@41.3767,1.7135,17z']);
check('Amb un enllaç de Google Maps, el web obre el punt a OpenStreetMap',
    Map::link() === 'https://www.openstreetmap.org/?mlat=41.3767&mlon=1.7135#map=17/41.3767/1.7135', Map::link());

echo "\n== Sense coordenades, es cerca l'adreça de la cursa ==\n";

$set([
    'map_lat' => '', 'map_lng' => '', 'map_embed' => '',
    'event_place' => 'Pavelló Municipal', 'event_address' => 'Plaça Major, 1 — 08720 Vilafranca', 'event_town' => 'Vilafranca',
]);
$link = Map::link();
check('L\'enllaç cerca el lloc i l\'adreça configurats',
    str_starts_with($link, 'https://www.openstreetmap.org/search?query=')
    && str_contains(rawurldecode($link), 'Pavelló Municipal')
    && str_contains(rawurldecode($link), 'Plaça Major, 1'), $link);
check('No repeteix la població si ja surt dins l\'adreça',
    substr_count(rawurldecode($link), 'Vilafranca') === 1, rawurldecode($link));
check('Sense punt no s\'incrusta cap mapa', Map::embed() === '');

$set(['event_town' => 'Sant Sadurní d\'Anoia']);
check('Si la població és una altra, s\'hi afegeix',
    str_contains(rawurldecode(Map::link()), 'Sant Sadurní'), rawurldecode(Map::link()));

$set(['map_embed' => 'https://mapes.example.org/cros']);
check('Un mapa propi sense coordenades es respecta tal qual',
    Map::link() === 'https://mapes.example.org/cros', Map::link());

$set(['map_embed' => '', 'event_place' => '', 'event_address' => '', 'event_town' => '']);
check('Sense cap dada no es mostra cap mapa', Map::link() === '' && !Map::has());

$restore();

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
