<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Models\Content;
use Cros\Models\Registration;

/** Gestió de les inscripcions. */
class RegistrationsController extends Controller
{
    private const PER_PAGE = 40;

    public function index(): void
    {
        Auth::requireLogin();
        $search = trim((string) input('q'));
        $categoryId = (int) input('categoria', 0);
        $page = max(1, (int) input('p', 1));

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(r.first_name LIKE :q OR r.last_name LIKE :q OR r.tutor_email LIKE :q OR r.code LIKE :q OR r.school LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[] = 'r.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Db::val('SELECT COUNT(*) FROM registrations r' . $clause, $params, 0);
        $rows = Db::all(
            'SELECT r.*, c.name AS category_name FROM registrations r
             LEFT JOIN categories c ON c.id = r.category_id' . $clause . '
             ORDER BY r.created_at DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params
        );

        $this->adminView('registrations/index', [
            'title' => 'Inscripcions',
            'rows' => $rows,
            'categories' => Content::categories(),
            'search' => $search,
            'categoryId' => $categoryId,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
            'byCategory' => Db::all(
                'SELECT c.name, COUNT(r.id) AS total FROM categories c
                 LEFT JOIN registrations r ON r.category_id = c.id
                 GROUP BY c.id, c.name ORDER BY c.sort_order ASC'
            ),
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        $this->adminView('registrations/form', [
            'title' => 'Nova inscripció',
            'row' => ['id' => 0, 'status' => 'confirmed', 'consent_data' => 1, 'consent_image' => 0],
            'categories' => Content::categories(),
            'errors' => [],
            'isNew' => true,
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        [$data, $errors] = $this->collect();
        if ($errors) {
            flash('error', reset($errors));
            $this->adminView('registrations/form', [
                'title' => 'Nova inscripció',
                'row' => $data,
                'categories' => Content::categories(),
                'errors' => $errors,
                'isNew' => true,
            ]);
            return;
        }
        $data['code'] = Registration::generateCode();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $id = Db::insert('registrations', $data);
        Auth::logActivity('registration_create', 'registration', $id);
        flash('success', 'Inscripció afegida.');
        redirect('/admin/inscripcions/' . $id);
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();
        $row = Registration::find((int) $params['id']);
        if (!$row) {
            abort(404, 'Inscripció no trobada.');
        }
        $this->adminView('registrations/form', [
            'title' => 'Inscripció ' . $row['code'],
            'row' => $row,
            'categories' => Content::categories(),
            'errors' => [],
            'isNew' => false,
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $row = Registration::find((int) $params['id']);
        if (!$row) {
            abort(404);
        }
        [$data, $errors] = $this->collect();
        if ($errors) {
            flash('error', reset($errors));
            $this->adminView('registrations/form', [
                'title' => 'Inscripció ' . $row['code'],
                'row' => array_merge($row, $data),
                'categories' => Content::categories(),
                'errors' => $errors,
                'isNew' => false,
            ]);
            return;
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        Db::update('registrations', $data, 'id = :id', ['id' => $row['id']]);
        Auth::logActivity('registration_update', 'registration', (int) $row['id']);
        flash('success', 'Canvis desats.');
        redirect('/admin/inscripcions/' . $row['id']);
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        Db::delete('registrations', 'id = :id', ['id' => (int) $params['id']]);
        Auth::logActivity('registration_delete', 'registration', (int) $params['id']);
        flash('success', 'Inscripció esborrada.');
        redirect('/admin/inscripcions');
    }

    public function export(): void
    {
        Auth::requireLogin();
        $rows = Registration::exportRows();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inscripcions-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Codi', 'Nom', 'Cognoms', 'Any', 'Gènere', 'Categoria', 'Escola', 'Curs', 'Tutor/a', 'Correu', 'Telèfon', 'Talla', 'Notes', 'Estat', 'Consent. dades', 'Consent. imatge', 'Data'], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    private function collect(): array
    {
        $data = [
            'first_name' => (string) input('first_name'),
            'last_name' => (string) input('last_name'),
            'birth_year' => input('birth_year') !== '' ? (int) input('birth_year') : null,
            'gender' => (string) input('gender'),
            'category_id' => (int) input('category_id') ?: null,
            'school' => (string) input('school'),
            'class_group' => (string) input('class_group'),
            'tutor_name' => (string) input('tutor_name'),
            'tutor_email' => mb_strtolower((string) input('tutor_email')),
            'tutor_phone' => (string) input('tutor_phone'),
            'shirt_size' => (string) input('shirt_size'),
            'notes' => mb_substr((string) input('notes'), 0, 500),
            'status' => in_array((string) input('status'), ['confirmed', 'pending', 'cancelled'], true) ? (string) input('status') : 'confirmed',
            'consent_data' => input_bool('consent_data'),
            'consent_image' => input_bool('consent_image'),
        ];
        $errors = $this->validate([
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:150',
            'tutor_email' => 'email|max:190',
        ], $data);
        return [$data, $errors];
    }
}
