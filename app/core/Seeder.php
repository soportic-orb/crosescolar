<?php
declare(strict_types=1);

namespace Cros\Core;

/** Continguts d'exemple que es creen durant la instal·lació. */
class Seeder
{
    /** Omple les taules buides amb contingut inicial en català. */
    public static function run(): void
    {
        Settings::seedDefaults();
        self::courses();
        self::categories();
        self::schedule();
        self::infoBlocks();
        self::prizes();
        self::faqs();
        self::ticketTypes();
        self::sponsors();
        Settings::load(true);
    }

    private static function isEmpty(string $table): bool
    {
        return (int) Db::val('SELECT COUNT(*) FROM `' . $table . '`', [], 0) === 0;
    }

    private static function courses(): void
    {
        if (!self::isEmpty('courses')) {
            return;
        }
        $courses = [
            ['Circuit petit — escola', 'circuit-petit', 600, 8, 'Pista i camí de terra', 'Volta curta per la zona esportiva, ideal per a les categories més petites. Tot el recorregut és pla i està controlat per voluntariat.', 1],
            ['Circuit mitjà — vinyes', 'circuit-mitja', 1500, 22, 'Camins de vinya', 'Sortida de la zona esportiva, volta pels camins entre vinyes i tornada pel camí del molí. Terreny de terra compacta amb algun repunt suau.', 2],
            ['Circuit llarg — La Granada', 'circuit-llarg', 3000, 45, 'Camins de vinya i asfalt', 'Recorregut més exigent que envolta el nucli del poble i les vinyes del sud. Dues voltes al circuit mitjà amb una allargada final.', 3],
        ];
        foreach ($courses as [$name, $slug, $distance, $elevation, $surface, $description, $order]) {
            Db::insert('courses', [
                'name' => $name,
                'slug' => $slug,
                'distance_m' => $distance,
                'elevation_m' => $elevation,
                'surface' => $surface,
                'description' => '<p>' . $description . '</p>',
                'color' => '#2f6b3c',
                'sort_order' => $order,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private static function categories(): void
    {
        if (!self::isEmpty('categories')) {
            return;
        }
        $year = (int) date('Y');
        $small = (int) Db::val('SELECT id FROM courses WHERE slug = \'circuit-petit\'', [], 0);
        $medium = (int) Db::val('SELECT id FROM courses WHERE slug = \'circuit-mitja\'', [], 0);
        $long = (int) Db::val('SELECT id FROM courses WHERE slug = \'circuit-llarg\'', [], 0);

        $categories = [
            ['Prebenjamí (P3-P5)', 'P3-P5', $year - 6, $year - 3, '09:45', '400 m', $small, 'Medalla per a tothom'],
            ['Benjamí (1r-2n)', 'BEN', $year - 8, $year - 7, '10:00', '600 m', $small, 'Trofeu als tres primers de cada gènere'],
            ['Aleví (3r-4t)', 'ALE', $year - 10, $year - 9, '10:20', '1.000 m', $medium, 'Trofeu als tres primers de cada gènere'],
            ['Infantil (5è-6è)', 'INF', $year - 12, $year - 11, '10:45', '1.500 m', $medium, 'Trofeu als tres primers de cada gènere'],
            ['Cadet (ESO)', 'CAD', $year - 16, $year - 13, '11:10', '2.000 m', $medium, 'Trofeu als tres primers de cada gènere'],
            ['Popular i famílies', 'POP', null, null, '11:40', '3.000 m', $long, 'Obsequi per a tots els participants'],
        ];
        $order = 1;
        foreach ($categories as [$name, $code, $from, $to, $time, $distance, $courseId, $prizes]) {
            $categoryId = Db::insert('categories', [
                'name' => $name,
                'code' => $code,
                'year_from' => $from,
                'year_to' => $to,
                'gender' => 'mixt',
                'start_time' => $time,
                'distance_label' => $distance,
                'course_id' => $courseId ?: null,
                'prizes' => $prizes,
                'sort_order' => $order++,
                'active' => 1,
            ]);
            if ($courseId) {
                Db::insert('category_courses', [
                    'category_id' => $categoryId,
                    'course_id' => $courseId,
                    'laps' => 1,
                    'sort_order' => 0,
                ]);
            }
        }
    }

    private static function schedule(): void
    {
        if (!self::isEmpty('schedule_items')) {
            return;
        }
        $items = [
            ['08:45', 'Recollida de dorsals', 'A la carpa de l\'AFA, a la zona esportiva.'],
            ['09:30', 'Escalfament conjunt', 'Amb l\'equip d\'educació física de l\'escola.'],
            ['09:45', 'Primera sortida', 'Categoria prebenjamí (P3-P5).'],
            ['11:40', 'Cursa popular i famílies', 'Oberta a totes les edats.'],
            ['12:15', 'Lliurament de premis', 'A l\'escenari de la zona esportiva.'],
            ['12:30', 'Punt de recàrrega', 'Amb tiquet. Botifarra, entrepans i beguda per recuperar forces.'],
        ];
        $order = 1;
        foreach ($items as [$time, $title, $description]) {
            Db::insert('schedule_items', [
                'time_label' => $time,
                'title' => $title,
                'description' => $description,
                'sort_order' => $order++,
                'active' => 1,
            ]);
        }
    }

    private static function infoBlocks(): void
    {
        if (!self::isEmpty('info_blocks')) {
            return;
        }
        $blocks = [
            ['run', 'Per a totes les edats', '<p>Des de P3 fins a la cursa popular de famílies: sis curses adaptades a cada edat.</p>'],
            ['ticket', 'Inscripció gratuïta', '<p>La participació és gratuïta. Els tiquets del punt de recàrrega es compren a part i ajuden a finançar les activitats de l\'AFA.</p>'],
            ['parking', 'Aparcament i accessos', '<p>Aparcament gratuït a la zona esportiva i estació de Rodalies (R4) a 10 minuts a peu.</p>'],
            ['coffee', 'Punt de recàrrega', '<p>En acabar les curses, a recuperar l\'energia: entrepans, beguda i fruita a la carpa de l\'AFA.</p>'],
        ];
        $order = 1;
        foreach ($blocks as [$icon, $title, $body]) {
            Db::insert('info_blocks', ['icon' => $icon, 'title' => $title, 'body' => $body, 'sort_order' => $order++, 'active' => 1]);
        }
    }

    private static function prizes(): void
    {
        if (!self::isEmpty('prizes')) {
            return;
        }
        $prizes = [
            ['trophy', 'Trofeus per categoria', '<p>Trofeu per als tres primers classificats de cada categoria i gènere.</p>'],
            ['medal', 'Medalla finisher', '<p>Totes les persones participants reben una medalla o obsequi de record en creuar la meta.</p>'],
            ['gift', 'Premi a l\'escola més nombrosa', '<p>Lot esportiu per al centre educatiu amb més participants inscrits.</p>'],
            ['heart', 'Sorteig entre participants', '<p>Sorteig de productes de les empreses col·laboradores del Penedès durant el lliurament de premis.</p>'],
        ];
        $order = 1;
        foreach ($prizes as [$icon, $title, $description]) {
            Db::insert('prizes', ['icon' => $icon, 'title' => $title, 'description' => $description, 'sort_order' => $order++, 'active' => 1]);
        }
    }

    private static function faqs(): void
    {
        if (!self::isEmpty('faqs')) {
            return;
        }
        $faqs = [
            ['Cal inscriure\'s per avançat?', '<p>És recomanable per agilitzar la recollida de dorsals, però el mateix dia hi haurà inscripcions presencials fins a 30 minuts abans de cada sortida.</p>'],
            ['Quant costa participar-hi?', '<p>La cursa és gratuïta. Només es paguen els tiquets del punt de recàrrega, que es poden comprar en línia des d\'aquest web.</p>'],
            ['On es recullen els dorsals?', '<p>A la carpa de l\'AFA, a la zona esportiva, a partir de les 8.45 h.</p>'],
            ['Què passa si plou?', '<p>La cursa se celebra igualment si la pluja és feble. En cas d\'alerta meteorològica s\'anunciarà la suspensió en aquest web i a les xarxes de l\'AFA, i es retornarà l\'import dels tiquets.</p>'],
            ['Puc córrer amb el meu gos o cotxet?', '<p>A la cursa popular sí, sempre que us situeu al final de la graella per seguretat.</p>'],
        ];
        $order = 1;
        foreach ($faqs as [$question, $answer]) {
            Db::insert('faqs', ['question' => $question, 'answer' => $answer, 'sort_order' => $order++, 'active' => 1]);
        }
    }

    private static function ticketTypes(): void
    {
        if (!self::isEmpty('ticket_types')) {
            return;
        }
        $types = [
            ['Esmorzar complet', 'Entrepà de botifarra, beguda i fruita', 600, 300, 10],
            ['Esmorzar infantil', 'Entrepà petit, suc i peça de fruita', 400, 200, 10],
            ['Beguda', 'Aigua, refresc o cervesa artesana del Penedès', 200, null, 20],
        ];
        $order = 1;
        foreach ($types as [$name, $description, $price, $stock, $max]) {
            Db::insert('ticket_types', [
                'name' => $name,
                'description' => $description,
                'price_cents' => $price,
                'stock' => $stock,
                'max_per_order' => $max,
                'sort_order' => $order++,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private static function sponsors(): void
    {
        if (!self::isEmpty('sponsors')) {
            return;
        }
        $sponsors = [
            ['Ajuntament de La Granada', 'institucional', 1],
            ['Consell Esportiu de l\'Alt Penedès', 'institucional', 2],
            ['Escola La Granada', 'institucional', 3],
            ['AFA Escola La Granada', 'principal', 1],
        ];
        foreach ($sponsors as [$name, $tier, $order]) {
            Db::insert('sponsors', [
                'name' => $name,
                'tier' => $tier,
                'sort_order' => $order,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
