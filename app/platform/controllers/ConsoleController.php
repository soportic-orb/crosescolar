<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\View;
use Cros\Platform\Client;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\Request;

/** Accés, tauler i pantalles generals del panell de superadministració. */
class ConsoleController extends Controller
{
    /** Formulari d'accés. */
    public function login(): void
    {
        if (Console::check()) {
            redirect('/');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->checkCsrf();
            $error = Console::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($error === null) {
                $target = (string) ($_SESSION['console_redirect'] ?? '/');
                unset($_SESSION['console_redirect']);
                redirect($target !== '' && str_starts_with($target, '/') ? $target : '/');
            }
            flash('error', $error);
            set_old(['email' => (string) ($_POST['email'] ?? '')]);
            redirect('/acces');
        }
        View::render('platform/console/login', ['title' => 'Accés'], 'layouts/minimal');
    }

    public function logout(): void
    {
        Console::logout();
        flash('success', 'Heu sortit del panell.');
        redirect('/acces');
    }

    /** Tauler: què hi ha en marxa i què espera resposta. */
    public function home(): void
    {
        Console::requireLogin();
        $stats = [
            'instances' => (int) Db::val('SELECT COUNT(*) FROM instances', [], 0),
            'active' => (int) Db::val('SELECT COUNT(*) FROM instances WHERE status = :s', ['s' => 'active'], 0),
            'published' => (int) Db::val('SELECT COUNT(*) FROM instances WHERE published = 1', [], 0),
            'clients' => (int) Db::val('SELECT COUNT(*) FROM clients WHERE status = :s', ['s' => 'active'], 0),
            'registrations' => (int) Db::val('SELECT COALESCE(SUM(registrations), 0) FROM instances', [], 0),
            'pending' => Request::pending(),
            'outdated' => count(Instance::outdated()),
        ];
        $failing = Db::all(
            "SELECT * FROM instances WHERE health = 'error' AND status IN ('new', 'active') ORDER BY health_since"
        );
        // Una instància en marxa sense còpia de fa més de tres dies vol dir que
        // el cron no funciona: val més saber-ho abans de necessitar-la.
        $unsaved = Db::all(
            "SELECT * FROM instances WHERE status IN ('new', 'active')
             AND (backup_at IS NULL OR backup_at < :limit) ORDER BY slug",
            ['limit' => date('Y-m-d H:i:s', time() - 86400 * 3)]
        );
        $upcoming = Db::all(
            'SELECT * FROM instances WHERE status = :s AND event_date >= :today ORDER BY event_date LIMIT 6',
            ['s' => 'active', 'today' => date('Y-m-d')]
        );

        View::render('platform/console/dashboard', [
            'title' => 'Tauler',
            'stats' => $stats,
            'requests' => array_slice(Request::all('pending'), 0, 5),
            'instances' => array_slice(Instance::all(), 0, 6),
            'upcoming' => $upcoming,
            'failing' => $failing,
            'unsaved' => $unsaved,
            'activity' => Db::all('SELECT * FROM platform_activity ORDER BY id DESC LIMIT 8'),
        ], 'layouts/console');
    }

    /** Llista de clients. */
    public function clients(): void
    {
        Console::requireLogin();
        $clients = Client::all();
        $counts = [];
        foreach (Db::all('SELECT client_id, COUNT(*) AS total FROM instances WHERE client_id IS NOT NULL GROUP BY client_id') as $row) {
            $counts[(int) $row['client_id']] = (int) $row['total'];
        }

        View::render('platform/console/clients', [
            'title' => 'Clients',
            'clients' => $clients,
            'counts' => $counts,
        ], 'layouts/console');
    }

    /** Registre d'activitat de la plataforma. */
    public function activity(): void
    {
        Console::requireLogin();
        $rows = Db::all('SELECT a.*, u.name AS user_name FROM platform_activity a
            LEFT JOIN platform_users u ON u.id = a.user_id
            ORDER BY a.id DESC LIMIT 200');

        View::render('platform/console/activity', [
            'title' => 'Registre',
            'rows' => $rows,
        ], 'layouts/console');
    }
}
