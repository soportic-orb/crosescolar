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
use Cros\Platform\Signup;
use Cros\Platform\Site;

/**
 * La pàgina pública de la plataforma: el llistat dels webs que ja hi són i el
 * formulari per crear-ne un.
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
            'title' => (string) setting('site_name', 'EsportWeb') . ' · ' . setting('platform_tagline', ''),
            'description' => $description,
            'noindex' => \Cros\Core\Settings::bool('platform_noindex'),
            'instances' => Instance::directory(),
            'errors' => [],
        ]);
    }

    /**
     * Algú es dona d'alta des del web.
     *
     * No hi ha cap tràmit pel mig: el web es crea al moment i l'accés se li
     * envia per correu. Aquell correu és l'única porta d'entrada, de manera
     * que prémer-ne el botó és alhora entrar i validar l'adreça.
     */
    public function signup(): void
    {
        $this->checkCsrf();
        if (!Signup::open()) {
            flash('error', (string) setting('platform_requests_closed_text',
                'Ara mateix no donem altes noves.'));
            redirect('/');
        }
        // Parany per a robots: un camp que ningú no veu i que només ells omplen.
        if (trim((string) input('website_url')) !== '') {
            redirect('/');
        }
        // La pàgina pública no posa al dia la base de dades a cada visita, que
        // és la que rep el trànsit. Però una alta sí que hi escriu, i ha de
        // trobar-hi les taules al dia encara que ningú no hagi obert el panell
        // des de l'última actualització.
        try {
            Platform::migrate();
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'han pogut aplicar les migracions abans d\'una alta', ['error' => $e->getMessage()]);
        }

        $data = [
            'site_name' => trim((string) input('site_name')),
            'town' => trim((string) input('town')),
            'admin_name' => trim((string) input('admin_name')),
            'admin_email' => mb_strtolower(trim((string) input('admin_email'))),
            'client_kind' => (string) input('client_kind'),
            'slug' => mb_strtolower(trim((string) input('slug'))),
            'domain' => Platform::validDomain((string) input('domain')),
            'event_date' => trim((string) input('event_date')),
            'consent' => input_bool('consent') ? '1' : '',
        ];
        // Qui no s'hagi mirat l'adreça, que en tingui una de raonable.
        if ($data['slug'] === '') {
            $data['slug'] = Signup::suggest($data['site_name']);
        }

        $errors = Signup::check($data);
        if (!$errors && Signup::tooMany($data['admin_email'])) {
            $errors['admin_email'] = 'Avui ja heu creat uns quants webs. Proveu-ho demà o escriviu-nos.';
        }
        if ($errors) {
            $this->signupFailed($data, $errors);

            return;
        }

        try {
            $alta = Signup::create($data);
        } catch (\Throwable $e) {
            log_line('platform', 'Alta lliure fallida', ['slug' => $data['slug'], 'error' => $e->getMessage()]);
            $this->signupFailed($data, ['slug' => 'No hem pogut crear el web. Proveu-ho d\'aquí una estona o escriviu-nos.']);

            return;
        }

        $_SESSION['signup'] = ['slug' => $alta['slug'], 'url' => $alta['url'], 'email' => $alta['email']];
        redirect('/benvinguda');
    }

    /** Torna a la portada amb el formulari i els errors marcats. */
    private function signupFailed(array $data, array $errors): void
    {
        set_old($data);
        flash('error', 'Reviseu les dades marcades.');
        $this->page('platform/home', [
            'title' => (string) setting('site_name', 'EsportWeb'),
            'instances' => Instance::directory(),
            'errors' => $errors,
        ]);
    }

    /** «Ja està: mireu el correu». */
    public function welcome(): void
    {
        $alta = (array) ($_SESSION['signup'] ?? []);
        if (($alta['slug'] ?? '') === '') {
            redirect('/');
        }
        unset($_SESSION['signup']);
        $this->page('platform/signup-done', [
            'title' => 'Ja teniu el vostre web',
            'noindex' => true,
            'alta' => $alta,
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
                'title' => (string) setting('site_name', 'EsportWeb'),
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
            'description' => $title . ' de ' . Site::current() . '.',
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
            '{{web}}' => e(Site::current()),
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
        echo '  <url><loc>' . e('https://' . Site::current() . '/') . '</loc></url>' . "\n";
        foreach (Instance::directory() as $instance) {
            echo '  <url><loc>' . e(Instance::url($instance) . '/') . '</loc></url>' . "\n";
        }
        $pages = ['condicions', 'privadesa', 'galetes'];
        if (\Cros\Core\Settings::bool('features_enabled', true)) {
            array_unshift($pages, 'funcionalitats');
        }
        foreach ($pages as $page) {
            echo '  <url><loc>' . e('https://' . Site::current() . '/' . $page) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    /**
     * L'avís de Stripe sobre els pagaments **de la plataforma**.
     *
     * És el que mana: si algú tanca la finestra just després de pagar, aquest
     * és l'únic avís que arriba. No té res a veure amb el webhook del cros,
     * que va al web del client i amb les claus del client.
     */
    public function stripeWebhook(): void
    {
        $payload = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        try {
            $event = \Cros\Core\Stripe::constructEvent($payload, $signature, \Cros\Core\Stripe::webhookSecret());
        } catch (\Throwable $e) {
            log_line('platform', 'Avís de Stripe rebutjat', ['error' => $e->getMessage()]);
            json_out(['error' => 'Signatura no vàlida'], 400);

            return;
        }

        $object = $event['data']['object'] ?? [];
        $type = (string) ($event['type'] ?? '');
        // Es paga de dues maneres i cadascuna avisa amb els seus esdeveniments:
        // a la pàgina de Stripe hi ha una sessió, i al panell —el formulari de
        // la targeta— hi ha un PaymentIntent. Les dues han d'arribar aquí, que
        // aquest avís és l'única xarxa que hi ha si el navegador no torna.
        $session = str_starts_with($type, 'checkout.session.');
        $intent = str_starts_with($type, 'payment_intent.');
        if (!$session && !$intent) {
            json_out(['received' => true]);

            return;
        }

        $reference = (string) ($object['id'] ?? '');
        $charge = $session
            ? \Cros\Platform\Charge::findBySession($reference)
            : \Cros\Platform\Charge::findByIntent($reference);
        if (!$charge && !empty($object['metadata']['charge_id'])) {
            $charge = \Cros\Platform\Charge::find((int) $object['metadata']['charge_id']);
        }
        if (!$charge) {
            json_out(['received' => true]);

            return;
        }

        try {
            // Amb un PaymentIntent no hi ha sessió que mirar: confirm() se'n va
            // a preguntar-li l'estat a Stripe pel seu compte.
            \Cros\Platform\Charge::confirm($charge, $session ? $reference : '');
        } catch (\Throwable $e) {
            log_line('platform', 'Error atenent l\'avís de Stripe', [
                'code' => $charge['code'], 'error' => $e->getMessage(),
            ]);
            json_out(['error' => 'Error intern'], 500);

            return;
        }

        json_out(['received' => true]);
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
        echo 'Sitemap: https://' . Site::current() . "/sitemap.xml\n";
        exit;
    }

    /** Avisa qui l'ha demanada i la superadministració. */
    private function notify(array $request): void
    {
        $domain = Site::current();
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
