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
        $this->page('platform/home', [
            'title' => 'Cros Escolar · el web del vostre cros',
            'description' => 'Inscripcions, dorsals i resultats per al cros escolar de la vostra escola, AFA o club.',
            'instances' => Instance::directory(),
            'errors' => [],
        ]);
    }

    /** Arriba una sol·licitud del formulari. */
    public function request(): void
    {
        $this->checkCsrf();
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
