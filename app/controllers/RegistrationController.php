<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Db;
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
            'class_group' => (string) input('class_group'),
            'tutor_name' => (string) input('tutor_name'),
            'tutor_email' => (string) input('tutor_email'),
            'tutor_phone' => (string) input('tutor_phone'),
            'shirt_size' => (string) input('shirt_size'),
            'notes' => mb_substr((string) input('notes'), 0, 500),
            'consent_data' => input_bool('consent_data'),
            'consent_image' => input_bool('consent_image'),
        ];

        $errors = $this->validate([
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:150',
            'birth_year' => 'required|year',
            'tutor_name' => 'required|max:150',
            'tutor_email' => 'required|email|max:190',
            'tutor_phone' => 'max:40',
            'consent_data' => 'accepted',
        ], $data);

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
