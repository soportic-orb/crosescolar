<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Captcha;
use Cros\Core\Controller;
use Cros\Core\Mailer;
use Cros\Core\Tenancy;
use Cros\Core\View;
use Cros\Platform\Contact;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Request;
use Cros\Platform\Seo;
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
        $instances = Instance::directory();
        $this->page('platform/home', [
            'title' => (string) setting('site_name', 'EsportWeb') . ' · ' . setting('platform_tagline', ''),
            'description' => $description,
            'noindex' => \Cros\Core\Settings::bool('platform_noindex'),
            'instances' => $instances,
            'errors' => [],
            // Què és el servei i quines curses hi ha, perquè ho entenguin
            // els cercadors i els assistents sense haver d'endevinar-ho.
            'jsonLd' => [Seo::software(), Seo::events($instances)],
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
        // I si la que surt del nom ja és d'un altre, una que s'hi assembli: no
        // té sentit queixar-se d'un camp que no han omplert.
        if ($data['slug'] === '') {
            $data['slug'] = Signup::suggest($data['site_name']);
            if (Instance::slugProblem($data['slug']) !== '') {
                $data['slug'] = Signup::alternative($data['slug']) ?: $data['slug'];
            }
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

    /**
     * Si una adreça està lliure, consultat mentre s'escriu al formulari.
     *
     * Així qui la tria sap de seguida si ja és d'un altre i la pot canviar
     * abans d'enviar res. Si no hi ha adreça però sí nom, es mira la que en
     * sortiria, que és la que es faria servir.
     */
    public function slugCheck(): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        $slug = trim((string) input('slug'));
        $fromName = $slug === '';
        if ($fromName) {
            $slug = Signup::suggest((string) input('nom'));
        }
        if ($slug === '') {
            json_out(['slug' => '', 'ok' => false, 'message' => '', 'alternative' => '', 'from_name' => $fromName]);
        }
        $result = Signup::availability(mb_substr($slug, 0, 60));
        // Una adreça que surt del nom i que ja és d'un altre no és un error de
        // ningú: ja es farà servir la semblant.
        if ($fromName && !$result['ok'] && $result['alternative'] !== '') {
            $result = Signup::availability($result['alternative']);
        }
        json_out($result + ['from_name' => $fromName]);
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
            'jsonLd' => [Seo::software()],
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
        if (\Cros\Core\Settings::bool('contact_enabled', true)) {
            array_unshift($pages, 'contacte');
        }
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
        echo Seo::robots();
        exit;
    }

    /**
     * El resum del web per als assistents d'IA (llmstxt.org).
     *
     * Si la portada no vol sortir enlloc, o no vol saber res d'assistents, no
     * hi ha resum: seria contradir el que diu el robots.txt.
     */
    public function llms(): void
    {
        if (Seo::hidden() || Seo::aiMode() === 'none') {
            abort(404, 'Aquesta pàgina no existeix.');
        }
        header('Content-Type: text/markdown; charset=utf-8');
        echo Seo::llms(Instance::directory());
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

    /** El formulari del captcha de contacte, perquè no es barregi amb cap altre. */
    private const CAPTCHA = 'platform-contact';

    /** Segons que cal per omplir el formulari: menys, és un robot. */
    private const MIN_SECONDS = 3;

    /** La pàgina de contacte. */
    public function contact(): void
    {
        if (!\Cros\Core\Settings::bool('contact_enabled', true)) {
            abort(404, 'Aquesta pàgina no existeix.');
        }
        $this->contactPage([]);
    }

    /**
     * Arriba un missatge del formulari de contacte.
     *
     * Per davant de tot hi ha quatre filtres contra el correu brossa: el
     * parany que només omplen els robots, el captcha, que no s'hagi enviat
     * massa de pressa i que una mateixa adreça no n'enviï una pila. Un robot
     * que caigui al parany rep el mateix «gràcies» que una persona: si sabés
     * que l'han enxampat, provaria una altra cosa.
     */
    public function contactSend(): void
    {
        if (!\Cros\Core\Settings::bool('contact_enabled', true)) {
            abort(404, 'Aquesta pàgina no existeix.');
        }
        $this->checkCsrf();
        $thanks = (string) setting('contact_success',
            'Gràcies! Hem rebut el vostre missatge i us respondrem al correu que ens heu deixat.');
        if (trim((string) input('website_url')) !== '') {
            flash('success', $thanks);
            redirect('/contacte');
        }
        // La pàgina pública no posa al dia la base de dades a cada visita; un
        // missatge sí que hi escriu, i ha de trobar-hi la taula.
        try {
            Platform::migrate();
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'han pogut aplicar les migracions abans d\'un contacte', ['error' => $e->getMessage()]);
        }

        $data = [
            'name' => trim((string) input('name')),
            'entity' => trim((string) input('entity')),
            'email' => mb_strtolower(trim((string) input('email'))),
            'phone' => trim((string) input('phone')),
            'message' => trim((string) input('message')),
            'privacy' => input_bool('privacy'),
            'news' => input_bool('news'),
        ];
        $errors = $this->validate([
            'name' => 'required|max:150',
            'entity' => 'max:190',
            'email' => 'required|email|max:190',
            'phone' => 'max:40',
            'message' => 'required|max:5000',
            'privacy' => 'accepted',
        ], $data);
        if (!isset($errors['message']) && mb_strlen($data['message']) < 10) {
            $errors['message'] = 'Expliqueu-nos una mica més què necessiteu.';
        }
        if ($data['phone'] !== '' && !preg_match('/^[0-9 +().\-]{6,40}$/', $data['phone'])) {
            $errors['phone'] = 'Aquest telèfon no sembla bo: només xifres, espais i el signe +.';
        }
        // Els missatges brossa porten una pila d'enllaços; una consulta de debò, cap o un.
        if (!isset($errors['message']) && preg_match_all('#https?://|www\.#i', $data['message']) > 3) {
            $errors['message'] = 'El missatge porta massa enllaços. Traieu-ne alguns i torneu-lo a enviar.';
        }
        // El captcha es comprova sempre, encara que hi hagi altres errors: si
        // no, un robot podria saber quins camps fallen sense haver-lo resolt.
        if (!Captcha::check(self::CAPTCHA, (string) input('captcha'), self::MIN_SECONDS)) {
            $errors['captcha'] = 'El codi de la imatge no és correcte. Torneu-lo a escriure.';
        }
        $ip = client_ip();
        if (!$errors && Contact::tooMany($ip)) {
            $errors['message'] = 'Ja ens heu escrit unes quantes vegades fa poc. Espereu una estona o escriviu-nos per correu.';
        }

        if ($errors) {
            set_old($data);
            flash('error', 'Reviseu les dades marcades.');
            $this->contactPage($errors);

            return;
        }

        $contact = Contact::create($data + ['domain' => Site::current(), 'ip' => $ip]);
        Platform::log('contact_new', 'contact', (int) ($contact['id'] ?? 0), ['domini' => Site::current()]);
        $this->notifyContact($contact);

        flash('success', $thanks);
        redirect('/contacte');
    }

    /**
     * La imatge del captcha.
     *
     * No es guarda mai a la memòria cau: cada vegada que es demana, ha de ser
     * la del repte que hi ha ara a la sessió.
     */
    public function captcha(): void
    {
        if (!Captcha::drawable()) {
            abort(404, 'Aquest servidor no dibuixa imatges.');
        }
        // «Doneu-me'n un altre»: un repte nou, amb codi i rellotge nous.
        if (isset($_GET['nou'])) {
            Captcha::issue(self::CAPTCHA);
        }
        $png = Captcha::png(self::CAPTCHA);
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex');
        header('Content-Length: ' . strlen($png));
        echo $png;
        exit;
    }

    /** Pinta la pàgina de contacte amb un repte nou. */
    private function contactPage(array $errors): void
    {
        Captcha::issue(self::CAPTCHA);
        $this->page('platform/contact', [
            'title' => (string) setting('contact_title', 'Parlem-ne'),
            'description' => (string) setting('contact_intro', ''),
            'errors' => $errors,
            'captchaImage' => Captcha::isImage(self::CAPTCHA),
            'captchaQuestion' => Captcha::question(self::CAPTCHA),
            'jsonLd' => [Seo::contactPage()],
        ]);
    }

    /**
     * Avisa qui porta la plataforma que ha arribat un missatge.
     *
     * No se n'envia cap còpia a qui l'ha escrit. Seria còmode, però el
     * formulari passaria a ser una manera d'enviar correus a qualsevol adreça
     * en nom nostre: n'hi hauria prou d'escriure-hi la de la víctima.
     */
    private function notifyContact(array $contact): void
    {
        $notify = Platform::notifyEmail();
        if ($notify === '' || !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Mailer::sendTemplate(
            $notify,
            'Contacte: ' . $contact['name'] . ($contact['entity'] ? ' (' . $contact['entity'] . ')' : ''),
            'contact-admin',
            ['contact' => $contact, 'domain' => Site::current()],
            ['reply_to' => (string) $contact['email']]
        );
    }

    /** Vista de la plataforma, amb la seva plantilla. */
    private function page(string $template, array $data): void
    {
        View::render($template, $data, 'layouts/platform');
    }
}
