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

echo "\n== 0008: una categoria pot fer diversos recorreguts ==\n";

check('Hi ha la taula de recorreguts per categoria', Db::tableExists('category_courses'));

// Una instal·lació anterior: cada categoria tenia un sol recorregut a categories.course_id.
Db::conn()->exec('DELETE FROM category_courses');
$sql = (string) file_get_contents(CROS_APP . '/migrations/0008_recorreguts_per_categoria.sql');
foreach (\Cros\Core\Migrator::statements(cros_test_translate($sql)) as $statement) {
    if (str_starts_with(strtoupper(trim($statement)), 'INSERT')) {
        Db::conn()->exec($statement);
    }
}

$withCourse = (int) Db::val('SELECT COUNT(*) FROM categories WHERE course_id IS NOT NULL', [], 0);
check('Cada categoria conserva el recorregut que tenia',
    (int) Db::val('SELECT COUNT(*) FROM category_courses', [], 0) === $withCourse,
    $withCourse . ' categories amb recorregut');
check('I hi fa una volta',
    (int) Db::val('SELECT COUNT(*) FROM category_courses WHERE laps <> 1', [], 0) === 0);
$sample = Db::one('SELECT c.id, c.course_id, cc.course_id AS relacio FROM categories c
                   JOIN category_courses cc ON cc.category_id = c.id WHERE c.course_id IS NOT NULL');
check('El recorregut és el mateix', (int) ($sample['course_id'] ?? 0) === (int) ($sample['relacio'] ?? -1), json_encode($sample));

echo "\n== 0009: el mapa surt de les dades de la cursa ==\n";

$mapBefore = [];
foreach (['map_lat', 'map_lng', 'map_embed', 'event_address'] as $key) {
    $mapBefore[$key] = (string) Settings::get($key, '');
}
$oldEmbed = 'https://www.openstreetmap.org/?mlat=41.3778&mlon=1.7203#map=16/41.3778/1.7203';
$migration = require CROS_APP . '/migrations/0009_mapa_ubicacio.php';

// Una instal·lació que no havia tocat el mapa mai.
Settings::set('map_embed', $oldEmbed);
Settings::set('map_lat', '41.3778');
Settings::set('map_lng', '1.7203');
Settings::set('event_address', 'Carrer de l\'Esport, s/n — 08792 La Granada (Alt Penedès)');
Settings::load(true);
$migration(Db::conn());
Settings::load(true);
check('L\'enllaç antic per defecte es retira', (string) Settings::get('map_embed', '') === '',
    (string) Settings::get('map_embed', ''));
check('El punt passa a ser la zona esportiva',
    (string) Settings::get('map_lat', '') === '41.376699' && (string) Settings::get('map_lng', '') === '1.713535',
    Settings::get('map_lat', '') . ', ' . Settings::get('map_lng', ''));
check('L\'adreça per defecte apunta a un carrer que existeix al mapa',
    str_contains((string) Settings::get('event_address', ''), 'Carrer de Vilafranca'),
    (string) Settings::get('event_address', ''));
check('I el mapa obre aquest punt',
    Map::link() === 'https://www.openstreetmap.org/?mlat=41.376699&mlon=1.713535#map=17/41.376699/1.713535',
    Map::link());

// Una instal·lació que hi havia posat el seu enllaç de Google Maps.
Settings::set('map_embed', 'https://www.google.com/maps/place/Escola/@41.3452,1.6988,18z');
Settings::set('map_lat', '');
Settings::set('map_lng', '');
Settings::load(true);
$migration(Db::conn());
Settings::load(true);
check('D\'un enllaç propi se\'n treu el punt',
    (string) Settings::get('map_lat', '') === '41.3452' && (string) Settings::get('map_lng', '') === '1.6988',
    Settings::get('map_lat', '') . ', ' . Settings::get('map_lng', ''));
check('I l\'enllaç de l\'organització es conserva',
    str_contains((string) Settings::get('map_embed', ''), 'google.com'));

// Una instal·lació que ja havia ajustat les coordenades: no s'hi toca.
Settings::set('map_embed', '');
Settings::set('map_lat', '41.5');
Settings::set('map_lng', '1.5');
Settings::load(true);
$migration(Db::conn());
Settings::load(true);
check('Les coordenades que ja s\'havien ajustat es respecten',
    (string) Settings::get('map_lat', '') === '41.5', (string) Settings::get('map_lat', ''));

foreach ($mapBefore as $key => $value) {
    Settings::set($key, $value);
}
Settings::load(true);

echo "\n== 0011: els textos legals ==\n";

$legalBefore = [];
foreach (['legal_entity', 'legal_notice', 'privacy_text'] as $key) {
    $legalBefore[$key] = (string) Settings::get($key, '');
}
$migration = require CROS_APP . '/migrations/0011_textos_legals.php';

// Una instal·lació amb els textos d'exemple de les versions anteriors.
Settings::set('legal_entity', 'AFA Escola La Granada');
Settings::set('legal_notice', '<p>Aquest lloc web és titularitat de l\'AFA de l\'Escola La Granada, entitat sense ànim de lucre que organitza el Cros Escolar de La Granada.</p>');
Settings::set('privacy_text', '<p>Les dades personals recollides mitjançant els formularis d\'inscripció i de compra de tiquets s\'utilitzen exclusivament per organitzar el Cros Escolar La Granada i no se cedeixen a tercers, tret de les obligacions legals i dels serveis necessaris per al pagament (Stripe).</p><p>Podeu exercir els drets d\'accés, rectificació i supressió escrivint a l\'adreça de contacte.</p>');
Settings::load(true);
$migration(Db::conn());
Settings::load(true);

check('L\'entitat responsable passa a ser l\'AFA Jacint Verdaguer',
    (string) Settings::get('legal_entity', '') === 'AFA Jacint Verdaguer de La Granada',
    (string) Settings::get('legal_entity', ''));
check('L\'avís legal queda redactat', substr_count((string) Settings::get('legal_notice', ''), '<h2>') >= 6);
check('La privacitat també', substr_count((string) Settings::get('privacy_text', ''), '<h2>') >= 8);
check('I ja no parla de Stripe',
    stripos((string) Settings::get('privacy_text', ''), 'stripe') === false);

// Una que ja s'havia redactat els seus textos: no s'hi toca.
Settings::set('legal_notice', '<p>El nostre avís legal, escrit per l\'advocada de l\'entitat.</p>');
Settings::set('legal_entity', 'AFA de prova');
Settings::load(true);
$migration(Db::conn());
Settings::load(true);
check('Els textos ja redactats es respecten',
    (string) Settings::get('legal_notice', '') === '<p>El nostre avís legal, escrit per l\'advocada de l\'entitat.</p>');
check('I el nom de l\'entitat també', (string) Settings::get('legal_entity', '') === 'AFA de prova');

foreach ($legalBefore as $key => $value) {
    Settings::set($key, $value);
}
Settings::load(true);

echo "\n== 0012: l'acceptació del reglament ==\n";

check('Les inscripcions tenen el camp del reglament',
    in_array('consent_rules', array_column(Db::all('PRAGMA table_info(registrations)'), 'name'), true));

// Les inscripcions fetes abans que hi hagués la casella es donen per acceptades.
$before = Db::insert('registrations', [
    'code' => 'PREV-' . substr(bin2hex(random_bytes(3)), 0, 5),
    'token' => bin2hex(random_bytes(8)),
    'first_name' => 'Abans', 'last_name' => 'del reglament',
    'birth_year' => (int) date('Y') - 10,
    'tutor_name' => 'Prova', 'tutor_email' => 'abans@example.test',
    'consent_data' => 1, 'consent_image' => 0, 'consent_rules' => 0,
    'status' => 'confirmed', 'created_at' => date('Y-m-d H:i:s'),
]);
Db::conn()->exec('UPDATE registrations SET consent_rules = 0');
$migration = require CROS_APP . '/migrations/0012_acceptacio_reglament.php';
$migration(Db::conn());
$row = Db::one('SELECT consent_rules FROM registrations WHERE id = :id', ['id' => $before]);
check('No es toca res si la columna ja hi era', (int) ($row['consent_rules'] ?? -1) === 0,
    'la migració ja aplicada no ha de tornar a marcar-les');
Db::delete('registrations', 'id = :id', ['id' => $before]);

echo "\n== 0014: el menú del web ==\n";

check('Hi ha la taula del menú', Db::tableExists('menu_items'));
$menuBefore = Db::all('SELECT * FROM menu_items');
Db::conn()->exec('DELETE FROM menu_items');

// Sense res configurat, el menú és el de sempre.
check('Sense configurar, el menú és el de sempre',
    array_column(\Cros\Models\Menu::visible(), 'title') === ['Inici', 'Recorreguts', 'Categories i premis', 'Les meves inscripcions'],
    implode(' · ', array_column(\Cros\Models\Menu::visible(), 'title')));
check('I al panell hi són tots', count(\Cros\Models\Menu::all()) >= 10,
    (string) count(\Cros\Models\Menu::all()));

// Un apartat que encara no es veu no surt al web encara que estigui marcat.
\Cros\Models\Menu::save(['home', 'results'], []);
$titles = array_column(\Cros\Models\Menu::visible(), 'title');
check('Els apartats que encara no toquen no surten al web',
    !in_array('Resultats', $titles, true) && in_array('Inici', $titles, true), implode(' · ', $titles));

Db::conn()->exec('DELETE FROM menu_items');
foreach ($menuBefore as $row) {
    unset($row['id']);
    Db::insert('menu_items', $row);
}

echo "\n== Les opcions noves d'una versió arriben amb el seu valor per defecte ==\n";

$asideBefore = (string) Settings::get('registrations_aside_text', '');
$titleBefore = (string) Settings::get('registrations_aside_title', '');

// Una instal·lació de la versió anterior: encara no té l'opció nova.
Db::delete('settings', 'k = :k', ['k' => 'registrations_aside_text']);
Settings::set('registrations_aside_title', 'El que cal saber');
Settings::load(true);

\Cros\Core\Migrator::run();
Settings::load(true);

check('L\'opció que faltava s\'omple amb el valor per defecte',
    str_contains((string) Settings::get('registrations_aside_text', ''), 'un formulari per cada participant'),
    substr((string) Settings::get('registrations_aside_text', ''), 0, 60));
check('I les que ja estaven escrites no es toquen',
    (string) Settings::get('registrations_aside_title', '') === 'El que cal saber',
    (string) Settings::get('registrations_aside_title', ''));

Settings::set('registrations_aside_text', $asideBefore);
Settings::set('registrations_aside_title', $titleBefore);
Settings::load(true);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
