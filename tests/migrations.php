<?php
/**
 * Proves de les migracions que reescriuen continguts d'instal·lacions ja existents.
 * Han de canviar només el que encara tenia el text per defecte.
 *
 * Ús:  php tests/migrations.php
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Db;
use Cros\Core\Settings;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

echo "\n== 0006: l'esmorzar passa a ser el punt de recàrrega ==\n";

$before = [
    'tickets_title' => (string) Settings::get('tickets_title', ''),
    'tickets_intro' => (string) Settings::get('tickets_intro', ''),
    'meta_description' => (string) Settings::get('meta_description', ''),
    'hero_cta_url' => (string) Settings::get('hero_cta_url', ''),
];

// Una instal·lació de la versió anterior: textos per defecte antics…
Settings::set('tickets_title', 'Tiquets per a l\'esmorzar');
Settings::set('hero_cta_url', '/esmorzar');
// …i un text que l'organització ja havia redactat.
Settings::set('tickets_intro', '<p>Enguany l\'esmorzar el fem a la plaça.</p>');
Settings::set('meta_description', 'Cros Escolar La Granada: cursa popular entre vinyes de l\'Alt Penedès per a totes les edats. Inscripcions, recorreguts i tiquets de l\'esmorzar.');

$schedule = Db::insert('schedule_items', [
    'time_label' => '12:30', 'title' => 'Esmorzar popular',
    'description' => 'Amb tiquet. Botifarra, entrepans i beguda.',
    'sort_order' => 99, 'active' => 0,
]);
$block = Db::insert('info_blocks', [
    'icon' => 'coffee', 'title' => 'Esmorzar popular',
    'body' => '<p>En acabar les curses: entrepans, beguda i fruita a la carpa de l\'AFA.</p>',
    'sort_order' => 99, 'active' => 0,
]);

$migration = require CROS_APP . '/migrations/0006_punt_de_recarrega.php';
$migration(Db::conn());

check('Canvia el títol de la pàgina', Settings::get('tickets_title') === 'Tiquets del punt de recàrrega',
    (string) Settings::get('tickets_title'));
check('Canvia l\'enllaç antic', Settings::get('hero_cta_url') === '/punt-de-recarrega');
check('Canvia la descripció per a cercadors',
    str_contains((string) Settings::get('meta_description'), 'tiquets del punt de recàrrega'));
check('Respecta els textos que ja s\'havien redactat',
    Settings::get('tickets_intro') === '<p>Enguany l\'esmorzar el fem a la plaça.</p>',
    (string) Settings::get('tickets_intro'));

$row = Db::one('SELECT * FROM schedule_items WHERE id = :id', ['id' => $schedule]);
check('Canvia el programa', ($row['title'] ?? '') === 'Punt de recàrrega'
    && str_contains((string) ($row['description'] ?? ''), 'recuperar forces'), json_encode($row));
$row = Db::one('SELECT * FROM info_blocks WHERE id = :id', ['id' => $block]);
check('Canvia el bloc de la portada', ($row['title'] ?? '') === 'Punt de recàrrega'
    && str_contains((string) ($row['body'] ?? ''), 'recuperar l\'energia'), json_encode($row));

// Deixa la base de dades com estava.
Db::delete('schedule_items', 'id = :id', ['id' => $schedule]);
Db::delete('info_blocks', 'id = :id', ['id' => $block]);
foreach ($before as $key => $value) {
    Settings::set($key, $value);
}

echo "\n== 0007: les medalles passen a ser de cada categoria ==\n";

$columns = array_keys(Db::one('SELECT * FROM categories LIMIT 1') ?? []);
check('Les categories tenen els camps de medalles',
    in_array('medals', $columns, true) && in_array('winners', $columns, true), implode(', ', $columns));

// Una instal·lació que tenia l'opció general a «quatre primers».
Settings::set('prizes_medals', '1');
Settings::set('prizes_winners', '4');
Db::conn()->exec('UPDATE categories SET medals = 1, winners = 3');

$migration = require CROS_APP . '/migrations/0007_medalles_per_categoria.php';
$migration(Db::conn());

$row = Db::one('SELECT medals, winners FROM categories ORDER BY id ASC');
check('Els valors generals es copien a cada categoria',
    (int) ($row['winners'] ?? 0) === 4 && (int) ($row['medals'] ?? 0) === 1, json_encode($row));
check('Totes les categories queden igual',
    (int) Db::val('SELECT COUNT(*) FROM categories WHERE winners <> 4', [], 0) === 0);

// I una que les tenia desactivades.
Settings::set('prizes_medals', '0');
$migration(Db::conn());
check('Si estaven desactivades, també es respecta',
    (int) Db::val('SELECT COUNT(*) FROM categories WHERE medals <> 0', [], 0) === 0);

// Deixa les categories com estaven.
Db::conn()->exec('UPDATE categories SET medals = 1, winners = 3');
Settings::set('prizes_medals', '1');
Settings::set('prizes_winners', '3');

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
