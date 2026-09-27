<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Csrf;
use Cros\Core\Mailer;
use Cros\Core\Seo;
use Cros\Core\Settings;
use Cros\Models\Content;
use Cros\Models\RaceResult;

/** Pàgines informatives. */
class PageController extends Controller
{
    /** Categories, horaris i premis. */
    public function categories(): void
    {
        $this->view('public/categories', [
            'title' => setting('categories_title', 'Categories i premis'),
            'description' => excerpt(strip_tags((string) setting('categories_intro', '')), 160),
            'categories' => Content::categories(),
            'prizes' => Content::prizes(),
            'courses' => Content::courses(),
            'sponsors' => Content::sponsorsByTier(),
        ]);
    }

    /** Llistat de recorreguts. */
    public function courses(): void
    {
        $this->view('public/courses', [
            'title' => setting('courses_title', 'Els recorreguts'),
            'description' => excerpt((string) setting('courses_intro', ''), 160),
            'courses' => Content::courses(),
            'categories' => Content::categories(),
        ]);
    }

    /** Detall d'un recorregut. */
    public function course(array $params): void
    {
        $course = Content::course((string) $params['slug']);
        if (!$course) {
            abort(404, 'No hem trobat aquest recorregut.');
        }
        // Hi surten totes les categories que hi facin alguna volta.
        $categories = array_values(array_filter(
            Content::categories(),
            fn ($category) => in_array((int) $course['id'], array_column($category['courses'] ?? [], 'course_id'), true)
        ));
        $this->view('public/course', [
            'title' => $course['name'],
            'description' => excerpt(strip_tags((string) $course['description']), 160),
            // Inici → Recorreguts → aquest recorregut, per als cercadors.
            'breadcrumbs' => [
                [(string) setting('courses_title', 'Recorreguts'), '/recorreguts'],
                [(string) $course['name'], '/recorreguts/' . $course['slug']],
            ],
            'course' => $course,
            'categories' => $categories,
            'otherCourses' => array_values(array_filter(Content::courses(), fn ($c) => $c['id'] !== $course['id'])),
        ]);
    }

    /** Classificació de la cursa. */
    public function results(): void
    {
        if (!Settings::bool('results_published')) {
            abort(404, 'Els resultats encara no s\'han publicat.');
        }
        $this->view('public/results', [
            'title' => setting('results_title', 'Resultats de la cursa'),
            'description' => excerpt(strip_tags((string) setting('results_intro', '')), 160),
            'groups' => RaceResult::byCategory(),
            'total' => RaceResult::stats()['total'],
        ]);
    }

    /** Classificació en PDF (si l'organització ho permet). */
    public function resultsPdf(): void
    {
        if (!Settings::bool('results_published') || !Settings::bool('results_public_pdf')) {
            abort(404, 'La descàrrega dels resultats no està disponible.');
        }
        $categoryId = (int) input('categoria', 0);
        $arrival = input('tipus') === 'arribada';
        $pdf = RaceResult::pdf($categoryId > 0 ? $categoryId : null, $arrival);
        // El nom del fitxer diu què s'hi ha descarregat.
        $name = 'resultats-' . (slugify((string) setting('site_name', 'cros')) ?: 'cros');
        if ($arrival) {
            $name .= '-ordre-arribada';
        } elseif ($categoryId > 0 && ($slug = slugify(RaceResult::categoryTitle($categoryId))) !== '') {
            $name .= '-' . $slug;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
        // El PDF diu el mateix que la pàgina de resultats: que Google indexi la
        // pàgina i no el fitxer.
        header('X-Robots-Tag: noindex');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public function faqs(): void
    {
        $this->view('public/faqs', [
            'title' => 'Preguntes freqüents',
            'faqs' => Content::faqs(),
        ]);
    }

    /** Reglament de la cursa. */
    public function rules(): void
    {
        $this->view('public/text-page', [
            'title' => setting('rules_title', 'Reglament de la cursa'),
            'body' => legal_html('rules_text'),
        ]);
    }

    public function legal(): void
    {
        $this->view('public/text-page', [
            'title' => 'Avís legal',
            'body' => legal_html('legal_notice'),
        ]);
    }

    public function privacy(): void
    {
        $this->view('public/text-page', [
            'title' => 'Política de privacitat',
            'body' => legal_html('privacy_text'),
        ]);
    }

    public function contact(): void
    {
        $this->view('public/contact', [
            'title' => 'Contacte',
            'sent' => (bool) ($_GET['enviat'] ?? false),
        ]);
    }

    public function contactSubmit(): void
    {
        Csrf::verify();
        // Camp trampa per a robots
        if (trim((string) input('website')) !== '') {
            redirect('/contacte?enviat=1');
        }
        $data = [
            'name' => (string) input('name'),
            'email' => (string) input('email'),
            'message' => (string) input('message'),
        ];
        $errors = $this->validate([
            'name' => 'required|max:120',
            'email' => 'required|email',
            'message' => 'required|min:10|max:3000',
        ], $data);

        if ($errors) {
            set_old($data);
            flash('error', 'Reviseu les dades del formulari.');
            $this->view('public/contact', [
                'title' => 'Contacte',
                'errors' => $errors,
                'sent' => false,
            ]);
            return;
        }

        $to = (string) (setting('mail_admin_notify', '') ?: setting('contact_email', ''));
        if ($to !== '') {
            Mailer::send(
                $to,
                'Consulta des del web: ' . excerpt($data['message'], 50),
                '<p><strong>' . e($data['name']) . '</strong> (' . e($data['email']) . ') ha escrit:</p>'
                . '<p>' . nl2br(e($data['message'])) . '</p>',
                ['reply_to' => $data['email']]
            );
        }
        flash('success', 'Missatge enviat. Us respondrem tan aviat com puguem.');
        redirect('/contacte?enviat=1');
    }

    /**
     * Mapa del web per als cercadors.
     *
     * Només hi surt el que és públic i està obert: si les inscripcions estan
     * tancades o els resultats no s'han publicat, aquelles adreces no hi
     * consten. El «lastmod» surt de quan es va tocar la configuració que fa
     * aquella pàgina, que és l'únic que en sabem de debò.
     */
    public function sitemap(): void
    {
        if (Seo::hidden()) {
            // Un web en preparació o amagat expressament no té mapa.
            abort(404, 'Aquest web encara no surt als cercadors.');
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');

        $urls = [
            ['/', Settings::changedAt()],
            ['/categories-i-premis', Settings::changedAt(['categories_title', 'categories_intro'])],
            ['/recorreguts', Settings::changedAt(['courses_title', 'courses_intro'])],
            ['/inscripcio', Settings::changedAt(['registrations_title', 'registrations_intro', 'registrations_closed_text'])],
            ['/contacte', Settings::changedAt(['contact_email', 'contact_phone'])],
        ];
        foreach (Content::courses() as $course) {
            $urls[] = ['/recorreguts/' . $course['slug'], null];
        }
        if (Content::faqs() !== []) {
            $urls[] = ['/preguntes-frequents', null];
        }
        if (trim(strip_tags((string) setting('rules_text', ''))) !== '') {
            $urls[] = ['/reglament', Settings::changedAt(['rules_text', 'rules_title'])];
        }
        // El punt de recàrrega hi surt si hi ha res a dir-hi: o s'hi venen
        // tiquets o s'hi explica com va.
        if (TicketsController::saleMode() || trim(strip_tags((string) setting('tickets_intro', ''))) !== '') {
            $urls[] = ['/punt-de-recarrega', Settings::changedAt(['tickets_title', 'tickets_intro'])];
        }
        if (Settings::bool('results_published')) {
            $urls[] = ['/resultats', Settings::changedAt(['results_published', 'results_intro'])];
        }
        $urls[] = ['/avis-legal', Settings::changedAt(['legal_notice'])];
        $urls[] = ['/privacitat', Settings::changedAt(['privacy_text'])];

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $changed]) {
            echo '  <url><loc>' . e(Seo::canonical($path)) . '</loc>';
            $stamp = $changed !== null ? strtotime((string) $changed) : false;
            if ($stamp !== false) {
                echo '<lastmod>' . date('Y-m-d', $stamp) . '</lastmod>';
            }
            echo '</url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    /** Instruccions per als cercadors. */
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        if (Seo::hidden()) {
            // Mentre el web està en preparació (o s'ha demanat que no
            // s'indexi) no s'ha de rastrejar res.
            echo "Disallow: /\n";
            exit;
        }

        // Pàgines que no tenen cap sentit al cercador: l'àrea de gestió, el que
        // demana un codi per correu i els fitxers personals de cadascú.
        foreach ([
            '/admin',
            '/validar',
            '/inscripcio/confirmada',
            '/inscripcio/dorsal',
            '/inscripcio/dorsals',
            '/les-meves-inscripcions',
            '/els-meus-tiquets',
            '/tiquets/',
            '/qr/',
            '/uploads/documents/',
        ] as $path) {
            echo 'Disallow: ' . $path . "\n";
        }
        // El PDF dels resultats no es bloqueja aquí a propòsit: porta una
        // capçalera que diu que no s'indexi, i per llegir-la el cercador ha de
        // poder demanar-lo. Si el bloquegéssim, no la veuria mai.
        //
        // Els fulls d'estil i les imatges sí que es deixen veure: Google
        // necessita veure el web tal com el veu la gent per saber que funciona
        // bé al mòbil.
        echo "Allow: /assets/\n";
        echo "Allow: /uploads/\n";
        echo "\n";
        echo 'Sitemap: ' . Seo::canonical('/sitemap.xml') . "\n";
        exit;
    }
}
