<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Models\Bib;
use Cros\Models\Content;
use Cros\Models\Registration;

/** Inscripcions públiques a les curses. */
class RegistrationController extends Controller
{
    public static function open(): bool
    {
        if (setting('registrations_enabled', '1') !== '1') {
            return false;
        }
        $close = (string) setting('registrations_close_at', '');
        return !($close !== '' && strtotime($close . ' 23:59:59') < time());
    }

    /** Cal acceptar el reglament per inscriure's? */
    public static function rulesConsent(): bool
    {
        return \Cros\Core\Settings::bool('rules_consent', true)
            && trim(strip_tags((string) setting('rules_text', ''))) !== '';
    }

    public function form(): void
    {
        $this->view('public/registration', [
            'title' => setting('registrations_title', 'Inscripció a la cursa'),
            'description' => excerpt(strip_tags((string) setting('registrations_intro', '')), 160),
            'categories' => Content::categories(),
            'open' => self::open(),
            'errors' => [],
        ]);
    }

    public function submit(): void
    {
        $this->checkCsrf();
        if (!self::open()) {
            flash('error', 'Les inscripcions en línia estan tancades.');
            redirect('/inscripcio');
        }
        if (trim((string) input('website')) !== '') {
            redirect('/inscripcio');
        }

        $data = [
            'first_name' => (string) input('first_name'),
            'last_name' => (string) input('last_name'),
            'birth_year' => (string) input('birth_year'),
            'gender' => (string) input('gender'),
            'category_id' => (string) input('category_id'),
            'school' => (string) input('school'),
            'tutor_name' => (string) input('tutor_name'),
            'tutor_email' => (string) input('tutor_email'),
            'tutor_phone' => (string) input('tutor_phone'),
            'notes' => mb_substr((string) input('notes'), 0, 500),
            'consent_data' => input_bool('consent_data'),
            'consent_image' => input_bool('consent_image'),
            'consent_rules' => input_bool('consent_rules'),
        ];

        // El gènere només pot portar una de les opcions del formulari.
        if (!isset(Registration::GENDERS[$data['gender']])) {
            $data['gender'] = '';
        }

        $rules = [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:150',
            'birth_year' => 'required|year',
            'tutor_name' => 'required|max:150',
            'tutor_email' => 'required|email|max:190',
            'tutor_phone' => 'max:40',
            'consent_data' => 'accepted',
        ];
        // El reglament només és obligatori si se'n demana l'acceptació.
        if (self::rulesConsent()) {
            $rules['consent_rules'] = 'accepted';
        } else {
            $data['consent_rules'] = 0;
        }
        $errors = $this->validate($rules, $data);

        if ($errors) {
            set_old($data);
            flash('error', 'Reviseu les dades marcades.');
            $this->view('public/registration', [
                'title' => setting('registrations_title', 'Inscripció a la cursa'),
                'categories' => Content::categories(),
                'open' => true,
                'errors' => $errors,
            ]);
            return;
        }

        // Evita duplicats exactes en pocs minuts
        $duplicate = Db::val(
            'SELECT 1 FROM registrations WHERE first_name = :f AND last_name = :l AND created_at > :since',
            ['f' => $data['first_name'], 'l' => $data['last_name'], 'since' => date('Y-m-d H:i:s', time() - 300)]
        );
        if ($duplicate) {
            flash('info', 'Aquesta inscripció ja consta registrada.');
            redirect('/inscripcio');
        }

        if ($data['category_id'] === '' && $data['birth_year'] !== '') {
            $category = Registration::categoryForYear((int) $data['birth_year']);
            $data['category_id'] = $category['id'] ?? '';
        }

        $registration = Registration::create($data);
        redirect('/inscripcio/confirmada/' . $registration['code']);
    }

    /** Dorsal del participant (enllaç privat del correu de confirmació). */
    public function bib(array $params): void
    {
        $this->ensureBibsArePublic();
        $registration = Registration::findByToken((string) $params['token']);
        if (!$registration) {
            abort(404, 'Aquest enllaç no és vàlid.');
        }
        $this->sendBib([$registration], 'dorsal-' . Bib::number($registration) . '.pdf');
    }

    /** Amb la descàrrega desactivada, aquestes adreces no existeixen. */
    private function ensureBibsArePublic(): void
    {
        if (!Bib::publicDownload()) {
            abort(404, 'Aquest enllaç no és vàlid.');
        }
    }

    /** Dorsals de tots els participants inscrits amb la mateixa adreça de contacte. */
    public function bibs(array $params): void
    {
        $this->ensureBibsArePublic();
        $registration = Registration::findByToken((string) $params['token']);
        if (!$registration) {
            abort(404, 'Aquest enllaç no és vàlid.');
        }
        $rows = Registration::forEmail((string) $registration['tutor_email']);
        if (!$rows) {
            $rows = [$registration];
        }
        $this->sendBib($rows, 'dorsals.pdf');
    }

    private function sendBib(array $registrations, string $filename): void
    {
        $pdf = Bib::pdf($registrations);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    public function done(array $params): void
    {
        $registration = Db::one('SELECT r.*, c.name AS category_name FROM registrations r
            LEFT JOIN categories c ON c.id = r.category_id WHERE r.code = :code', ['code' => (string) $params['code']]);
        if (!$registration) {
            abort(404, 'No hem trobat aquesta inscripció.');
        }
        $this->view('public/registration-done', [
            'title' => 'Inscripció confirmada',
            'registration' => $registration,
            'noindex' => true,
        ]);
    }
}
