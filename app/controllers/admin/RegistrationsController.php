<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Models\Bib;
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
        // Sense número al formulari, el sistema li dona el següent lliure.
        $id = Registration::insert($data);
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
        [$data, $errors] = $this->collect((int) $row['id']);
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
        if ($data['bib_number'] === null) {
            // Ningú no es queda sense dorsal: si es buida, se'n dona un altre.
            $data['bib_number'] = Registration::nextBib();
        }
        if (empty($row['token'])) {
            $data['token'] = random_token(16);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        try {
            Db::update('registrations', $data, 'id = :id', ['id' => $row['id']]);
        } catch (\PDOException $e) {
            // Xoc de dorsals entre dues pantalles obertes alhora.
            flash('error', 'El dorsal ' . Bib::number($data['bib_number']) . ' l\'acaba d\'agafar un altre participant.');
            $this->adminView('registrations/form', [
                'title' => 'Inscripció ' . $row['code'],
                'row' => array_merge($row, $data),
                'categories' => Content::categories(),
                'errors' => ['bib_number' => 'Aquest dorsal ja és d\'un altre participant.'],
                'isNew' => false,
            ]);
            return;
        }
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
        fputcsv($out, ['Dorsal', 'Codi', 'Nom', 'Cognoms', 'Any', 'Gènere', 'Categoria', 'Escola', 'Curs', 'Tutor/a', 'Correu', 'Telèfon', 'Talla', 'Notes', 'Estat', 'Consent. dades', 'Consent. imatge', 'Data'], ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /** Dorsal d'un participant en PDF. */
    public function bibPdf(array $params): void
    {
        Auth::requireLogin();
        $row = Registration::find((int) $params['id']);
        if (!$row) {
            abort(404, 'Inscripció no trobada.');
        }
        $this->sendPdf(Bib::pdf([$row]), 'dorsal-' . Bib::number($row) . '.pdf');
    }

    /** Tots els dorsals (o els d'una categoria) en un sol PDF per imprimir. */
    public function bibsPdf(): void
    {
        Auth::requireLogin();
        $categoryId = (int) input('categoria', 0);
        $sql = 'SELECT r.*, c.name AS category_name FROM registrations r
                LEFT JOIN categories c ON c.id = r.category_id';
        $params = [];
        if ($categoryId > 0) {
            $sql .= ' WHERE r.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        $sql .= ' ORDER BY r.bib_number ASC, r.id ASC';
        $rows = Db::all($sql, $params);
        if (!$rows) {
            flash('info', 'No hi ha cap inscripció amb aquests filtres.');
            $this->back('/admin/inscripcions');
        }
        $name = $categoryId > 0 ? 'dorsals-categoria-' . $categoryId : 'dorsals-tots';
        $this->sendPdf(Bib::pdf($rows), $name . '.pdf');
    }

    /** Dorsal d'exemple per comprovar el disseny. */
    public function sampleBib(): void
    {
        Auth::requireLogin();
        $pdf = Bib::sample();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="dorsal-de-prova.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** Assigna dorsal a les inscripcions que encara no en tenen. */
    public function assignBibs(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $count = Registration::assignMissing();
        flash('success', $count > 0
            ? 'S\'han assignat ' . $count . ' dorsals.'
            : 'Totes les inscripcions ja tenien dorsal.');
        $this->back('/admin/inscripcions');
    }

    private function sendPdf(string $pdf, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    /**
     * Dades del formulari. El dorsal es pot deixar buit: s'assigna sol.
     * @param int $id inscripció que s'està editant (0 si és nova)
     */
    private function collect(int $id = 0): array
    {
        $data = [
            'bib_number' => input('bib_number') !== '' ? (int) input('bib_number') : null,
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

        // El dorsal, si se'n posa un a mà, ha de ser un número lliure.
        $bib = $data['bib_number'];
        if ($bib !== null && $bib < 1) {
            $errors['bib_number'] = 'El dorsal ha de ser un número més gran que zero.';
        } elseif ($bib !== null && Registration::bibTaken($bib, $id ?: null)) {
            $errors['bib_number'] = 'El dorsal ' . Bib::number($bib) . ' ja és d\'un altre participant.';
        }
        return [$data, $errors];
    }
}
