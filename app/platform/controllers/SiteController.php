<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\Mailer;
use Cros\Core\Tenancy;
use Cros\Core\View;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Request;

/**
 * La pàgina pública de la plataforma: el llistat dels cros que ja hi són i el
 * formulari per demanar-ne un de nou.
 */
class SiteController extends Controller
{
    public function home(): void
    {
        $description = trim((string) setting('platform_meta_description', ''));
        if ($description === '') {
            $description = trim((string) setting('platform_tagline', ''))
                ?: 'Inscripcions, dorsals i resultats per al cros escolar de la vostra escola, AFA o club.';
        }
        $this->page('platform/home', [
            'title' => (string) setting('site_name', 'Cros Escolar') . ' · el web del vostre cros',
            'description' => $description,
            'noindex' => \Cros\Core\Settings::bool('platform_noindex'),
            'instances' => Instance::directory(),
            'errors' => [],
        ]);
    }

    /** Arriba una sol·licitud del formulari. */
    public function request(): void
    {
        $this->checkCsrf();
        // Si les altes estan tancades, el formulari no hi és; una petició que
        // arribi igualment tampoc no s'accepta.
        if (!\Cros\Core\Settings::bool('platform_requests_open', true)) {
            flash('error', (string) setting('platform_requests_closed_text',
                'Ara mateix no donem altes noves.'));
            redirect('/');
        }
        // Parany per a robots: un camp que ningú no veu i que només ells omplen.
        if (trim((string) input('website_url')) !== '') {
            redirect('/');
        }

        $data = [
            'entity' => (string) input('entity'),
            'nif' => (string) input('nif'),
            'town' => (string) input('town'),
            'website' => (string) input('website'),
            'contact_name' => (string) input('contact_name'),
            'contact_role' => (string) input('contact_role'),
            'contact_email' => (string) input('contact_email'),
            'contact_phone' => (string) input('contact_phone'),
            'slug' => mb_strtolower(trim((string) input('slug'))),
            'domain' => Platform::validDomain((string) input('domain')),
            'language' => (string) input('language'),
            'event_date' => (string) input('event_date'),
            'participants' => (string) input('participants'),
            'referral' => (string) input('referral'),
            'message' => mb_substr((string) input('message'), 0, 1000),
            'consent' => input_bool('consent'),
        ];

        $errors = $this->validate([
            'entity' => 'required|max:190',
            'town' => 'required|max:120',
            'contact_name' => 'required|max:150',
            'contact_email' => 'required|email|max:190',
            'contact_phone' => 'required|max:40',
            'consent' => 'accepted',
        ], $data);

        // El subdomini es demana aquí, però qui el dona és la superadministració.
        if ($data['slug'] !== '') {
            $problem = Instance::slugProblem($data['slug']);
            if ($problem !== '') {
                $errors['slug'] = $problem;
            }
        }
        if (!$errors && Request::tooMany($data['contact_email'])) {
            $errors['contact_email'] = 'Ja ens heu enviat unes quantes sol·licituds avui. Espereu que us responguem.';
        }

        if ($errors) {
            set_old($data);
            flash('error', 'Reviseu les dades marcades.');
            $this->page('platform/home', [
                'title' => 'Cros Escolar · el web del vostre cros',
                'instances' => Instance::directory(),
                'errors' => $errors,
            ]);

            return;
        }

        $request = Request::create($data);
        Platform::log('request_new', 'request', (int) ($request['id'] ?? 0), ['entity' => $request['entity']]);
        $this->notify($request);

        flash('success', 'Hem rebut la vostra sol·licitud.');
        redirect('/sollicitud/' . $request['code']);
    }

    /** Pantalla que es veu just després d'enviar-la. */
    public function sent(array $params): void
    {
        $code = (string) $params['code'];
        $request = \Cros\Core\Db::one('SELECT * FROM instance_requests WHERE code = :code', ['code' => $code]);
        if (!$request) {
            abort(404, 'No hem trobat aquesta sol·licitud.');
        }
        $this->page('platform/request-sent', [
            'title' => 'Sol·licitud rebuda',
            'request' => $request,
            'noindex' => true,
        ]);
    }

    /**
     * Les pàgines legals: condicions, privadesa i galetes.
     *
     * El text s'escriu al panell i aquí només se n'hi posen les dades de
     * l'entitat, que així no s'han de repetir a tres llocs.
     */
    /**
     * Què sap fer la plataforma.
     *
     * La llista s'edita al panell i no és aquí al codi: qui porta el servei hi
     * afegeix el que va sortint sense haver d'esperar cap versió nova.
     */
    public function features(): void
    {
        if (!\Cros\Core\Settings::bool('features_enabled', true)) {
            abort(404, 'Aquesta pàgina no existeix.');
        }
        $rows = json_decode((string) setting('features_list', ''), true);
        $rows = is_array($rows) ? array_values($rows) : [];

        $this->page('platform/features', [
            'title' => (string) setting('features_title', 'Funcionalitats'),
            'description' => mb_substr(trim((string) setting('features_intro', '')), 0, 300),
            'noindex' => \Cros\Core\Settings::bool('platform_noindex'),
            'intro' => (string) setting('features_intro', ''),
            'closing' => (string) setting('features_closing', ''),
            'features' => $rows,
        ]);
    }

    public function legal(array $params): void
    {
        $pages = [
            'condicions' => ['platform_terms', 'Condicions del servei'],
            'privadesa' => ['platform_privacy', 'Política de privadesa'],
            'galetes' => ['platform_cookies', 'Política de galetes'],
        ];
        $which = (string) ($params['page'] ?? '');
        if (!isset($pages[$which])) {
            abort(404, 'Aquesta pàgina no existeix.');
        }
        [$key, $title] = $pages[$which];
        $body = trim(strip_tags((string) setting($key, ''))) === ''
            ? ''
            : self::markers((string) setting_html($key));

        $this->page('platform/text-page', [
            'title' => $title,
            'description' => $title . ' de ' . Platform::domain() . '.',
            'body' => $body,
            'updated' => \Cros\Core\Settings::changedAt([$key]),
        ]);
    }

    /**
     * Posa les dades de l'entitat als marcadors dels textos legals.
     * Són els mateixos que al panell d'un cros, perquè qui n'ha escrit un ja
     * sap escriure l'altre.
     */
    private static function markers(string $html): string
    {
        $email = trim((string) setting('platform_contact_email', '')) ?: Platform::notifyEmail();

        return strtr($html, [
            '{{entitat}}' => e((string) setting('platform_legal_entity', '') ?: (string) setting('site_name', '')),
            '{{nif}}' => e((string) setting('platform_legal_nif', '')),
            '{{adreca}}' => e((string) setting('platform_legal_address', '')),
            '{{web}}' => e(Platform::domain()),
            '{{correu}}' => $email === '' ? '' : '<a href="mailto:' . e($email) . '">' . e($email) . '</a>',
        ]);
    }

    /**
     * Mapa del web de la plataforma.
     *
     * Hi surt la portada i, sobretot, el web de cada cros que està publicat i
     * demana sortir al llistat: és la manera que Google els trobi de seguida
     * sense esperar que algú els enllaci.
     */
    public function sitemap(): void
    {
        if (\Cros\Core\Settings::bool('platform_noindex')) {
            abort(404, 'Aquesta portada no surt als cercadors.');
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo '  <url><loc>' . e('https://' . Platform::domain() . '/') . '</loc></url>' . "\n";
        foreach (Instance::directory() as $instance) {
            echo '  <url><loc>' . e(Instance::url($instance) . '/') . '</loc></url>' . "\n";
        }
        $pages = ['condicions', 'privadesa', 'galetes'];
        if (\Cros\Core\Settings::bool('features_enabled', true)) {
            array_unshift($pages, 'funcionalitats');
        }
        foreach ($pages as $page) {
            echo '  <url><loc>' . e('https://' . Platform::domain() . '/' . $page) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    /** Instruccions per als cercadors. */
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        if (\Cros\Core\Settings::bool('platform_noindex')) {
            echo "Disallow: /\n";
            exit;
        }
        echo "Disallow: /sollicitud/\n";
        echo "Allow: /assets/\n";
        echo "\n";
        echo 'Sitemap: https://' . Platform::domain() . "/sitemap.xml\n";
        exit;
    }

    /** Avisa qui l'ha demanada i la superadministració. */
    private function notify(array $request): void
    {
        $domain = Platform::domain();
        $email = (string) $request['contact_email'];
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Mailer::sendTemplate(
                $email,
                'Hem rebut la vostra sol·licitud (#' . $request['code'] . ')',
                'request-received',
                ['request' => $request, 'domain' => $domain]
            );
        }
        $notify = Platform::notifyEmail();
        if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            Mailer::sendTemplate(
                $notify,
                'Nova sol·licitud: ' . $request['entity'],
                'request-admin',
                ['request' => $request, 'domain' => $domain, 'pending' => Request::pending()],
                ['reply_to' => $email]
            );
        }
    }

    /** Vista de la plataforma, amb la seva plantilla. */
    private function page(string $template, array $data): void
    {
        View::render($template, $data, 'layouts/platform');
    }
}
