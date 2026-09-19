<?php
/**
 * L'esmorzar popular passa a dir-se «punt de recàrrega»: és el servei de bar on
 * els participants recuperen l'energia gastada durant la cursa.
 *
 * Només es canvien els textos que encara tenien el valor per defecte de les
 * versions anteriors; si l'organització ja els havia redactat, es respecten.
 */

use Cros\Core\Db;
use Cros\Core\Settings;

return static function (PDO $pdo): void {
    $settings = [
        'coming_soon_text' => [
            '<p>Estem preparant el web del Cros Escolar La Granada amb tota la informació de la cursa, els recorreguts i la venda de tiquets de l\'esmorzar.</p><p>Torneu-hi ben aviat!</p>',
            '<p>Estem preparant el web del Cros Escolar La Granada amb tota la informació de la cursa, els recorreguts i els tiquets del punt de recàrrega.</p><p>Torneu-hi ben aviat!</p>',
        ],
        'intro_text' => [
            '<p>El Cros Escolar de La Granada és una matinal esportiva oberta a infants, joves i famílies. Un recorregut entre vinyes, camins i carrers del poble per gaudir de l\'esport en equip, tant si competeixes com si només vens a passar-ho bé.</p><p>En acabar les curses hi haurà lliurament de premis i esmorzar popular a la zona esportiva.</p>',
            '<p>El Cros Escolar de La Granada és una matinal esportiva oberta a infants, joves i famílies. Un recorregut entre vinyes, camins i carrers del poble per gaudir de l\'esport en equip, tant si competeixes com si només vens a passar-ho bé.</p><p>En acabar les curses hi haurà lliurament de premis i punt de recàrrega a la zona esportiva, per recuperar forces.</p>',
        ],
        'tickets_title' => [
            'Tiquets per a l\'esmorzar',
            'Tiquets del punt de recàrrega',
        ],
        'tickets_intro' => [
            '<p>En acabar les curses farem l\'esmorzar popular a la zona esportiva, amb entrepans, beguda i fruita. Els tiquets es podran comprar a la carpa de l\'AFA el mateix dia de la cursa.</p>',
            '<p>En acabar les curses obrirem el punt de recàrrega a la zona esportiva: entrepans, beguda i fruita per recuperar l\'energia gastada corrent. Els tiquets es podran comprar a la carpa de l\'AFA el mateix dia de la cursa.</p>',
        ],
        'tickets_email_intro' => [
            'Gràcies per comprar els tiquets de l\'esmorzar del Cros Escolar La Granada. Presenta aquest correu o el codi QR de cada tiquet a la carpa de l\'AFA.',
            'Gràcies per comprar els tiquets del punt de recàrrega del Cros Escolar La Granada. Presenta aquest correu o el codi QR de cada tiquet a la carpa de l\'AFA.',
        ],
        'meta_description' => [
            'Cros Escolar La Granada: cursa popular entre vinyes de l\'Alt Penedès per a totes les edats. Inscripcions, recorreguts i tiquets de l\'esmorzar.',
            'Cros Escolar La Granada: cursa popular entre vinyes de l\'Alt Penedès per a totes les edats. Inscripcions, recorreguts i tiquets del punt de recàrrega.',
        ],
    ];
    foreach ($settings as $key => [$old, $new]) {
        if ((string) Settings::get($key, '') === $old) {
            Settings::set($key, $new);
        }
    }

    // Els enllaços a l'adreça antiga passen a la nova (l'antiga continua redirigint).
    foreach (['hero_cta_url', 'registrations_closed_link_url'] as $key) {
        if ((string) Settings::get($key, '') === '/esmorzar') {
            Settings::set($key, '/punt-de-recarrega');
        }
    }

    // Continguts creats en instal·lar, si no s'han tocat.
    $rows = [
        ['schedule_items', 'title', 'Esmorzar popular', 'Punt de recàrrega'],
        ['info_blocks', 'title', 'Esmorzar popular', 'Punt de recàrrega'],
    ];
    foreach ($rows as [$table, $column, $old, $new]) {
        Db::update($table, [$column => $new], $column . ' = :old', ['old' => $old]);
    }

    $texts = [
        ['schedule_items', 'description',
            'Amb tiquet. Botifarra, entrepans i beguda.',
            'Amb tiquet. Botifarra, entrepans i beguda per recuperar forces.'],
        ['info_blocks', 'body',
            '<p>En acabar les curses: entrepans, beguda i fruita a la carpa de l\'AFA.</p>',
            '<p>En acabar les curses, a recuperar l\'energia: entrepans, beguda i fruita a la carpa de l\'AFA.</p>'],
        ['info_blocks', 'body',
            '<p>La participació és gratuïta. Els tiquets de l\'esmorzar es compren a part i ajuden a finançar les activitats de l\'AFA.</p>',
            '<p>La participació és gratuïta. Els tiquets del punt de recàrrega es compren a part i ajuden a finançar les activitats de l\'AFA.</p>'],
        ['faqs', 'answer',
            '<p>La cursa és gratuïta. Només es paguen els tiquets de l\'esmorzar popular, que es poden comprar en línia des d\'aquest web.</p>',
            '<p>La cursa és gratuïta. Només es paguen els tiquets del punt de recàrrega, que es poden comprar en línia des d\'aquest web.</p>'],
    ];
    foreach ($texts as [$table, $column, $old, $new]) {
        Db::update($table, [$column => $new], $column . ' = :old', ['old' => $old]);
    }
};
