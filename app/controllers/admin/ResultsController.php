<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Models\Bib;
use Cros\Models\Content;
use Cros\Models\RaceResult;

/** Control dels resultats de la cursa. */
class ResultsController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $categoryId = (int) input('categoria', 0);
        $this->adminView('results/index', [
            'title' => 'Resultats de la cursa',
            'stats' => RaceResult::stats(),
            'recent' => RaceResult::recent(12),
            'groups' => RaceResult::byCategory($categoryId > 0 ? $categoryId : null),
            'categories' => Content::categories(),
            'categoryId' => $categoryId,
            'pending' => Db::all(
                'SELECT r.id, r.bib_number, r.first_name, r.last_name, c.name AS category_name
                 FROM registrations r
                 LEFT JOIN categories c ON c.id = r.category_id
                 LEFT JOIN results res ON res.registration_id = r.id
                 WHERE res.id IS NULL
                 ORDER BY r.bib_number ASC LIMIT 60'
            ),
            'published' => Settings::bool('results_published'),
            'result' => null,
        ]);
    }

    /** Registra una arribada a meta. */
    public function store(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $result = RaceResult::addByBib((string) input('bib'));

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            json_out([
                'status' => $result['status'],
                'message' => $result['message'],
                'total' => RaceResult::stats()['total'],
            ]);
        }
        flash($result['status'] === 'ok' ? 'success' : ($result['status'] === 'warning' ? 'info' : 'error'), $result['message']);
        $this->back('/admin/resultats');
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        RaceResult::remove((int) $params['id']);
        flash('success', 'Arribada esborrada i posicions recalculades.');
        $this->back('/admin/resultats');
    }

    /** Puja o baixa una posició dins de la categoria. */
    public function move(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        RaceResult::move((int) $params['id'], input('direccio') === 'baixa' ? 1 : -1);
        $this->back('/admin/resultats');
    }

    /** Marca o desmarca qui s'endú el premi «Primer local» de la categoria. */
    public function localPrize(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $enable = input_bool('enable') === 1;
        if (!RaceResult::setLocalPrize((int) $params['id'], $enable)) {
            flash('error', 'Aquesta arribada ja no existeix.');
            $this->back('/admin/resultats');
        }
        flash('success', $enable
            ? 'Premi «' . RaceResult::localPrizeLabel() . '» assignat. Dins de la categoria només el pot tenir una persona.'
            : 'Premi «' . RaceResult::localPrizeLabel() . '» retirat.');
        $this->back('/admin/resultats');
    }

    /** Publica o amaga els resultats al web. */
    public function publish(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $enable = input_bool('enable');
        Settings::set('results_published', (string) $enable);
        Auth::logActivity('results_publish', 'settings', 0, ['actiu' => $enable]);
        flash('success', $enable === 1
            ? 'Els resultats ja es poden consultar al web.'
            : 'Els resultats han deixat de ser visibles al web.');
        $this->back('/admin/resultats');
    }

    /** Classificacions en PDF. */
    public function pdf(): void
    {
        Auth::requireLogin();
        $categoryId = (int) input('categoria', 0);
        $arrival = input('tipus') === 'arribada';
        $pdf = RaceResult::pdf($categoryId > 0 ? $categoryId : null, $arrival);

        $name = $arrival ? 'ordre-arribada' : ($categoryId > 0 ? 'categoria-' . $categoryId : 'totes-les-categories');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="resultats-' . $name . '-' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** Classificació completa en CSV. */
    public function csv(): void
    {
        Auth::requireLogin();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="resultats-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Ordre d\'arribada', 'Posició a la categoria', 'Dorsal', 'Participant', 'Categoria', 'Escola', 'Premi local', 'Hora de registre'], ';', '"', '\\');
        foreach (RaceResult::csvRows() as $row) {
            fputcsv($out, $row, ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
