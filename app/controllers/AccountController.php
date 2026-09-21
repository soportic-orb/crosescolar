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

    /** Quantes inscripcions pròpies recorda el navegador com a màxim. */
    private const OWN_LIMIT = 30;

    /** L'apartat es pot desactivar des del panell. */
    public static function enabled(): bool
    {
        return setting('registrations_selfservice', '1') === '1';
    }

    /**
     * Recorda una inscripció acabada de fer en aquest navegador.
     *
     * Serveix perquè qui acaba d'inscriure algú pugui anar directament a
     * «Les meves inscripcions» i corregir-hi el que calgui, sense esperar cap
     * codi. Només dona accés al que ha escrit aquesta mateixa sessió: per veure
     * la resta d'inscripcions d'una adreça continua fent falta el codi, perquè
     * escriure una adreça en un formulari no demostra que sigui teva.
     */
    public static function remember(int $registrationId): void
    {
        if ($registrationId <= 0) {
            return;
        }
        $own = (array) ($_SESSION['own_registrations'] ?? []);
        $own[] = $registrationId;
        $own = array_values(array_unique(array_map('intval', $own)));
        $_SESSION['own_registrations'] = array_slice($own, -self::OWN_LIMIT);
    }

    /**
     * Inscripcions fetes des d'aquest navegador que encara existeixen.
     * @return array<int,int>
     */
    public static function ownIds(): array
    {
        $own = array_values(array_unique(array_map('intval', (array) ($_SESSION['own_registrations'] ?? []))));
        if (!$own) {
            return [];
        }
        $found = array_map(static fn (array $row): int => (int) $row['id'], Registration::forIds($own));
        if (count($found) !== count($own)) {
            // Si alguna s'ha esborrat des del panell, s'oblida.
            $_SESSION['own_registrations'] = $found;
        }

        return $found;
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
        // Qui acaba d'inscriure algú veu de seguida el que ha escrit; amb «?codi»
        // demana el codi per veure també la resta d'inscripcions de l'adreça.
        if (input('codi', '') === '' && self::ownIds()) {
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
        unset($_SESSION['account_email'], $_SESSION['account_until'], $_SESSION['account_pending'],
            $_SESSION['account_tries'], $_SESSION['own_registrations']);
        flash('success', 'Heu sortit de «Les meves inscripcions».');
        redirect('/');
    }

    /** Formulari per modificar una inscripció. */
    public function edit(array $params): void
    {
        $this->ensureEnabled();
        $this->editView($this->activeOrFail((int) $params['id']), []);
    }

    /** Desa els canvis d'una inscripció. */
    public function update(array $params): void
    {
        $this->ensureEnabled();
        $this->checkCsrf();
        $registration = $this->activeOrFail((int) $params['id']);

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

        Registration::applyChanges($registration, $data);
        log_line('inscripcions', 'Inscripció modificada per la família', [
            'id' => (int) $registration['id'],
            'email' => (string) ($registration['tutor_email'] ?? ''),
            'acces' => self::email() !== '' ? 'codi' : 'acabada d\'inscriure',
        ]);
        flash('success', 'Hem desat els canvis de ' . $data['first_name'] . '.');
        redirect('/les-meves-inscripcions');
    }

    /**
     * Anul·la una inscripció.
     *
     * La inscripció no s'esborra: continua al panell de l'organització amb
     * l'estat «Anul·lada» i es queda el seu número de dorsal, que ja no serà
     * de ningú més. Per desfer-ho cal parlar amb l'organització.
     */
    public function cancel(array $params): void
    {
        $this->ensureEnabled();
        $this->checkCsrf();
        $registration = $this->registrationOrFail((int) $params['id']);
        if (Registration::isCancelled($registration)) {
            flash('info', 'Aquesta inscripció ja estava anul·lada.');
            redirect('/les-meves-inscripcions');
        }

        Registration::cancel($registration, 'familia');
        log_line('inscripcions', 'Inscripció anul·lada per la família', [
            'id' => (int) $registration['id'],
            'dorsal' => (int) ($registration['bib_number'] ?? 0),
            'email' => (string) ($registration['tutor_email'] ?? ''),
            'acces' => self::email() !== '' ? 'codi' : 'acabada d\'inscriure',
        ]);
        flash('success', 'Hem anul·lat la inscripció de ' . $registration['first_name'] . '.');
        redirect('/les-meves-inscripcions');
    }

    /** Llista de participants: tots els de l'adreça validada, o els acabats d'inscriure. */
    private function listing(): void
    {
        $email = self::email();
        $registrations = $email !== '' ? Registration::forEmail($email) : Registration::forIds(self::ownIds());
        $this->view('public/account', [
            'title' => 'Les meves inscripcions',
            'noindex' => true,
            'email' => $email,
            // Amb «session» només s'hi veu el que s'ha inscrit des d'aquest navegador.
            'scope' => $email !== '' ? 'email' : 'session',
            'registrations' => $registrations,
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

    /**
     * La inscripció que s'està consultant, si aquesta sessió hi té dret: perquè
     * ha validat l'adreça amb un codi o perquè l'acaba d'inscriure ella mateixa.
     */
    private function registrationOrFail(int $id): array
    {
        $email = self::email();
        $registration = $email !== '' ? Registration::findForEmail($id, $email) : null;
        if (!$registration && in_array($id, self::ownIds(), true)) {
            $registration = Registration::find($id);
        }
        if (!$registration) {
            if ($email === '' && !self::ownIds()) {
                flash('error', 'Per veure les vostres inscripcions cal demanar un codi d\'accés.');
                redirect('/les-meves-inscripcions');
            }
            abort(404, 'No hem trobat aquesta inscripció.');
        }

        return $registration;
    }

    /** Com registrationOrFail(), però una inscripció anul·lada ja no es toca. */
    private function activeOrFail(int $id): array
    {
        $registration = $this->registrationOrFail($id);
        if (Registration::isCancelled($registration)) {
            flash('info', 'Aquesta inscripció està anul·lada i ja no es pot modificar.');
            redirect('/les-meves-inscripcions');
        }

        return $registration;
    }

    private function ensureEnabled(): void
    {
        if (!self::enabled()) {
            abort(404, 'Aquest apartat no està disponible.');
        }
    }
}
