<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Mailer;
use Cros\Models\AccessCode;
use Cros\Models\Registration;

/**
 * «Les meves inscripcions»: les famílies hi entren amb un codi d'un sol ús que
 * reben per correu i poden revisar i modificar les dades dels seus participants.
 */
class AccountController extends Controller
{
    /** Minuts que dura la sessió un cop validat el codi. */
    private const SESSION_MINUTES = 60;

    /** L'apartat es pot desactivar des del panell. */
    public static function enabled(): bool
    {
        return setting('registrations_selfservice', '1') === '1';
    }

    /** Adreça validada en aquesta sessió, o '' si no n'hi ha cap. */
    public static function email(): string
    {
        $email = (string) ($_SESSION['account_email'] ?? '');
        $until = (int) ($_SESSION['account_until'] ?? 0);
        if ($email === '' || $until < time()) {
            return '';
        }
        return $email;
    }

    /** Pantalla d'entrada: demanar el codi, escriure'l o veure les inscripcions. */
    public function index(): void
    {
        $this->ensureEnabled();
        if (self::email() !== '') {
            $this->listing();
            return;
        }
        if (!empty($_SESSION['account_pending'])) {
            $this->codeForm();
            return;
        }
        $this->view('public/account-request', [
            'title' => 'Les meves inscripcions',
            'noindex' => true,
            'errors' => [],
        ]);
    }

    /** Pas 1: enviar el codi a l'adreça de contacte. */
    public function requestCode(): void
    {
        $this->ensureEnabled();
        $this->checkCsrf();
        $email = AccessCode::normalize((string) input('email'));
        if ($email === '') {
            set_old(['email' => (string) input('email')]);
            flash('error', 'L\'adreça electrònica no és vàlida.');
            redirect('/les-meves-inscripcions');
        }

        $registrations = Registration::forEmail($email);
        if ($registrations) {
            $code = AccessCode::create($email, client_ip());
            if ($code === '') {
                flash('error', 'S\'han demanat massa codis per a aquesta adreça. Torneu-ho a provar d\'aquí una estona.');
                redirect('/les-meves-inscripcions');
            }
            Mailer::sendTemplate($email, 'El vostre codi d\'accés — ' . setting('site_name', 'Cros Escolar La Granada'), 'access-code', [
                'code' => $code,
                'minutes' => AccessCode::TTL_MINUTES,
                'count' => count($registrations),
            ]);
        }

        // Tant si hi ha inscripcions com si no, la resposta és la mateixa: així el
        // formulari no serveix per esbrinar quines adreces estan inscrites.
        $_SESSION['account_pending'] = $email;
        $_SESSION['account_tries'] = 0;
        flash('success', 'Si hi ha inscripcions fetes amb aquesta adreça, hi rebreu un codi d\'accés.');
        redirect('/les-meves-inscripcions/codi');
    }

    /** Pas 2: formulari per escriure el codi rebut. */
    public function codeForm(): void
    {
        $this->ensureEnabled();
        if (self::email() !== '') {
            redirect('/les-meves-inscripcions');
        }
        $pending = (string) ($_SESSION['account_pending'] ?? '');
        if ($pending === '') {
            redirect('/les-meves-inscripcions');
        }
        $this->view('public/account-code', [
            'title' => 'Les meves inscripcions',
            'noindex' => true,
            'email' => $pending,
            'minutes' => AccessCode::TTL_MINUTES,
        ]);
    }

    /** Pas 2: comprovació del codi. */
    public function verifyCode(): void
    {
        $this->ensureEnabled();
        $this->checkCsrf();
        $pending = (string) ($_SESSION['account_pending'] ?? '');
        if ($pending === '') {
            redirect('/les-meves-inscripcions');
        }
        $tries = (int) ($_SESSION['account_tries'] ?? 0) + 1;
        $_SESSION['account_tries'] = $tries;
        if ($tries > 10) {
            unset($_SESSION['account_pending'], $_SESSION['account_tries']);
            flash('error', 'Massa intents. Torneu a demanar un codi nou.');
            redirect('/les-meves-inscripcions');
        }

        if (!AccessCode::verify($pending, (string) input('code'))) {
            flash('error', 'El codi no és correcte o ha caducat.');
            redirect('/les-meves-inscripcions/codi');
        }

        session_regenerate_id(true);
        $_SESSION['account_email'] = $pending;
        $_SESSION['account_until'] = time() + self::SESSION_MINUTES * 60;
        unset($_SESSION['account_pending'], $_SESSION['account_tries']);
        redirect('/les-meves-inscripcions');
    }

    /** Torna a la pantalla inicial per demanar un codi nou. */
    public function restart(): void
    {
        $this->ensureEnabled();
        $this->checkCsrf();
        unset($_SESSION['account_pending'], $_SESSION['account_tries']);
        redirect('/les-meves-inscripcions');
    }

    public function logout(): void
    {
        $this->checkCsrf();
        unset($_SESSION['account_email'], $_SESSION['account_until'], $_SESSION['account_pending'], $_SESSION['account_tries']);
        flash('success', 'Heu sortit de «Les meves inscripcions».');
        redirect('/');
    }

    /** Formulari per modificar una inscripció. */
    public function edit(array $params): void
    {
        $this->ensureEnabled();
        $email = $this->requireEmail();
        $registration = Registration::findForEmail((int) $params['id'], $email);
        if (!$registration) {
            abort(404, 'No hem trobat aquesta inscripció.');
        }
        $this->editView($registration, []);
    }

    /** Desa els canvis d'una inscripció. */
    public function update(array $params): void
    {
        $this->ensureEnabled();
        $this->checkCsrf();
        $email = $this->requireEmail();
        $registration = Registration::findForEmail((int) $params['id'], $email);
        if (!$registration) {
            abort(404, 'No hem trobat aquesta inscripció.');
        }

        $data = [
            'first_name' => (string) input('first_name'),
            'last_name' => (string) input('last_name'),
            'birth_year' => (string) input('birth_year'),
            'gender' => (string) input('gender'),
            'school' => (string) input('school'),
            'tutor_name' => (string) input('tutor_name'),
            'tutor_phone' => (string) input('tutor_phone'),
            'notes' => mb_substr((string) input('notes'), 0, 500),
            'consent_image' => input_bool('consent_image'),
        ];
        if (!isset(Registration::GENDERS[$data['gender']])) {
            $data['gender'] = '';
        }

        $errors = $this->validate([
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:150',
            'birth_year' => 'required|year',
            'tutor_name' => 'required|max:150',
            'tutor_phone' => 'max:40',
        ], $data);
        if ($errors) {
            flash('error', 'Reviseu les dades marcades.');
            $this->editView(array_merge($registration, $data), $errors);
            return;
        }

        Registration::updateForEmail((int) $registration['id'], $email, $data);
        log_line('inscripcions', 'Inscripció modificada per la família', [
            'id' => (int) $registration['id'],
            'email' => $email,
        ]);
        flash('success', 'Hem desat els canvis de ' . $data['first_name'] . '.');
        redirect('/les-meves-inscripcions');
    }

    /** Llista de participants inscrits amb aquesta adreça. */
    private function listing(): void
    {
        $email = self::email();
        $this->view('public/account', [
            'title' => 'Les meves inscripcions',
            'noindex' => true,
            'email' => $email,
            'registrations' => Registration::forEmail($email),
        ]);
    }

    private function editView(array $registration, array $errors): void
    {
        $this->view('public/account-edit', [
            'title' => 'Modificar la inscripció',
            'noindex' => true,
            'registration' => $registration,
            'errors' => $errors,
        ]);
    }

    /** Exigeix una sessió validada. */
    private function requireEmail(): string
    {
        $email = self::email();
        if ($email === '') {
            flash('error', 'Per veure les vostres inscripcions cal demanar un codi d\'accés.');
            redirect('/les-meves-inscripcions');
        }
        return $email;
    }

    private function ensureEnabled(): void
    {
        if (!self::enabled()) {
            abort(404, 'Aquest apartat no està disponible.');
        }
    }
}
