<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Csrf;
use Cros\Core\Mailer;
use Cros\Models\Content;

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
        $categories = array_values(array_filter(
            Content::categories(),
            fn ($category) => (int) ($category['course_id'] ?? 0) === (int) $course['id']
        ));
        $this->view('public/course', [
            'title' => $course['name'],
            'description' => excerpt(strip_tags((string) $course['description']), 160),
            'course' => $course,
            'categories' => $categories,
            'otherCourses' => array_values(array_filter(Content::courses(), fn ($c) => $c['id'] !== $course['id'])),
        ]);
    }

    public function faqs(): void
    {
        $this->view('public/faqs', [
            'title' => 'Preguntes freqüents',
            'faqs' => Content::faqs(),
        ]);
    }

    public function legal(): void
    {
        $this->view('public/text-page', [
            'title' => 'Avís legal',
            'body' => setting_html('legal_notice'),
        ]);
    }

    public function privacy(): void
    {
        $this->view('public/text-page', [
            'title' => 'Política de privacitat',
            'body' => setting_html('privacy_text'),
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

    public function sitemap(): void
    {
        $urls = [url('/'), url('/categories-i-premis'), url('/recorreguts'), url('/esmorzar'), url('/inscripcio'), url('/contacte')];
        foreach (Content::courses() as $course) {
            $urls[] = url('/recorreguts/' . $course['slug']);
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo '  <url><loc>' . e($url) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /tiquets\n";
        echo "Disallow: /els-meus-tiquets\n";
        echo 'Sitemap: ' . url('/sitemap.xml') . "\n";
        exit;
    }
}
