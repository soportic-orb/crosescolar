<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Mailer;
use Cros\Core\View;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Request;

/** Les sol·licituds de gent que vol el web del seu cros. */
class RequestController extends Controller
{
    /** Llista, per estat. */
    public function index(): void
    {
        Console::requireLogin();
        $status = (string) ($_GET['estat'] ?? 'pending');
        if (!isset(Request::STATUSES[$status]) && $status !== 'all') {
            $status = 'pending';
        }

        View::render('platform/console/requests', [
            'title' => 'Sol·licituds',
            'status' => $status,
            'requests' => Request::all($status === 'all' ? '' : $status),
            'counts' => Request::counts(),
        ], 'layouts/console');
    }

    /** Fitxa d'una sol·licitud. */
    public function show(array $params): void
    {
        Console::requireLogin();
        $request = Request::find((int) $params['id']);
        if (!$request) {
            throw new HttpException(404, 'Aquesta sol·licitud no existeix.');
        }

        View::render('platform/console/request', [
            'title' => 'Sol·licitud ' . $request['code'],
            'request' => $request,
            'instance' => $request['instance_id'] ? Instance::find((int) $request['instance_id']) : null,
        ], 'layouts/console');
    }

    /** Desestimar-la o tornar-la a deixar pendent. */
    public function decide(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $request = Request::find((int) $params['id']);
        if (!$request) {
            throw new HttpException(404, 'Aquesta sol·licitud no existeix.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($action === 'reject') {
            Request::decide((int) $request['id'], 'rejected', $reason, null, Console::id());
            Console::log('request_reject', 'request', (int) $request['id'], ['code' => $request['code']]);
            $this->notifyRejection($request, $reason);
            flash('success', 'Sol·licitud desestimada.' . ($reason !== '' ? ' S\'ha avisat qui la va enviar.' : ''));
        } elseif ($action === 'pending') {
            Request::decide((int) $request['id'], 'pending', '', null, Console::id());
            Console::log('request_reopen', 'request', (int) $request['id'], ['code' => $request['code']]);
            flash('success', 'Sol·licitud tornada a pendent.');
        } else {
            flash('error', 'Acció desconeguda.');
        }

        redirect('/sollicituds/' . (int) $request['id']);
    }

    /** Avisa qui va demanar el web que de moment no tira endavant. */
    private function notifyRejection(array $request, string $reason): void
    {
        if ($reason === '' || !filter_var((string) $request['contact_email'], FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Mailer::sendTemplate((string) $request['contact_email'], 'Sobre la vostra sol·licitud ' . $request['code'], 'request-rejected', [
            'name' => (string) $request['contact_name'],
            'entity' => (string) $request['entity'],
            'code' => (string) $request['code'],
            'reason' => $reason,
            'contact' => Platform::notifyEmail(),
        ]);
    }
}
