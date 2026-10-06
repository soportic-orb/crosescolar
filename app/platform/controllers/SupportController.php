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
use Cros\Platform\Support;
use Cros\Platform\Site;

/** La safata del suport: els tiquets de tots els clients en un sol lloc. */
class SupportController extends Controller
{
    /** La safata, amb els filtres de sempre. */
    public function index(): void
    {
        Console::requireLogin();
        $filters = [
            'status' => (string) ($_GET['estat'] ?? 'active'),
            'department' => (int) ($_GET['departament'] ?? 0),
            'search' => trim((string) ($_GET['q'] ?? '')),
        ];

        View::render('platform/console/support/index', [
            'title' => 'Suport',
            'tickets' => Support::inbox($filters),
            'departments' => Support::departments(),
            'counts' => Support::counts(),
            'filters' => $filters,
        ], 'layouts/console');
    }

    /** Un tiquet, amb el fil sencer i les notes internes. */
    public function show(array $params): void
    {
        Console::requireLogin();
        $ticket = Support::find((int) $params['id']);
        if (!$ticket) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }

        View::render('platform/console/support/ticket', [
            'title' => 'Consulta ' . $ticket['reference'],
            'ticket' => $ticket,
            'messages' => Support::messages((int) $ticket['id'], true),
            'departments' => Support::departments(),
            'instance' => !empty($ticket['instance_id']) ? Instance::find((int) $ticket['instance_id']) : null,
        ], 'layouts/console');
    }

    /** Contestar el client, o deixar-hi una nota que només veiem nosaltres. */
    public function reply(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $ticket = Support::find((int) $params['id']);
        if (!$ticket) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }
        $body = (string) ($_POST['body'] ?? '');
        if (trim(strip_tags($body)) === '') {
            flash('error', 'El missatge no pot anar buit.');
            $this->back('/suport/' . (int) $ticket['id']);
        }
        $internal = (bool) input_bool('internal');
        $user = Console::user() ?? [];
        Support::write((int) $ticket['id'], 'support', $body, [
            'author_name' => (string) ($user['name'] ?? ''),
            'author_email' => (string) ($user['email'] ?? ''),
            'internal' => $internal,
        ]);
        Console::log($internal ? 'support_note' : 'support_reply', 'support', (int) $ticket['id'],
            ['referencia' => $ticket['reference']]);

        if ($internal) {
            flash('success', 'Nota desada. El client no la veu.');
            $this->back('/suport/' . (int) $ticket['id']);
        }

        $sent = $this->notifyClient($ticket, $body);
        flash($sent ? 'success' : 'info', $sent
            ? 'Resposta enviada i avisada per correu.'
            : 'Resposta desada. No s\'ha pogut avisar per correu: el client la veurà al seu panell.');
        $this->back('/suport/' . (int) $ticket['id']);
    }

    /** Estat, prioritat i departament. */
    public function update(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $ticket = Support::find((int) $params['id']);
        if (!$ticket) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }
        Support::update((int) $ticket['id'], [
            'status' => (string) input('status', (string) $ticket['status']),
            'priority' => (string) input('priority', (string) $ticket['priority']),
            'department_id' => (int) input('department_id', (string) (int) ($ticket['department_id'] ?? 0)),
        ]);
        Console::log('support_update', 'support', (int) $ticket['id'], ['estat' => (string) input('status', '')]);
        flash('success', 'Consulta actualitzada.');
        $this->back('/suport/' . (int) $ticket['id']);
    }

    public function destroy(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $ticket = Support::find((int) $params['id']);
        if (!$ticket) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }
        Support::delete((int) $ticket['id']);
        Console::log('support_delete', 'support', (int) $ticket['id'], ['referencia' => $ticket['reference']]);
        flash('success', 'Consulta esborrada.');
        redirect('/suport');
    }

    // ------------------------------------------------------ departaments

    public function departments(): void
    {
        Console::requireLogin();
        View::render('platform/console/support/departments', [
            'title' => 'Departaments de suport',
            'departments' => Support::departments(),
            'edit' => isset($_GET['editar']) ? Support::department((int) $_GET['editar']) : null,
        ], 'layouts/console');
    }

    public function saveDepartment(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $id = (int) input('id', '0');
        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'El departament necessita un nom.');
            $this->back('/suport/departaments');
        }
        $email = trim((string) input('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'L\'adreça del departament no és vàlida.');
            $this->back('/suport/departaments');
        }
        $saved = Support::saveDepartment($id, [
            'name' => $name,
            'description' => (string) input('description'),
            'email' => $email,
            'sort_order' => (int) input('sort_order', '0'),
            'active' => input_bool('active'),
        ]);
        Console::log($id > 0 ? 'department_update' : 'department_create', 'department', $saved, ['nom' => $name]);
        flash('success', 'Departament desat.');
        redirect('/suport/departaments');
    }

    public function deleteDepartment(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $department = Support::department((int) $params['id']);
        if (!$department) {
            throw new HttpException(404, 'Aquest departament no existeix.');
        }
        Support::deleteDepartment((int) $department['id']);
        Console::log('department_delete', 'department', (int) $department['id'], ['nom' => $department['name']]);
        flash('success', 'Departament esborrat. Els seus tiquets s\'han quedat sense departament.');
        redirect('/suport/departaments');
    }

    /**
     * Avisa el client que té resposta, amb l'enllaç al seu panell.
     * Si el correu no surt, no és cap drama: la resposta ja és al seu panell.
     */
    private function notifyClient(array $ticket, string $body): bool
    {
        $to = (string) ($ticket['author_email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $instance = !empty($ticket['instance_id']) ? Instance::find((int) $ticket['instance_id']) : null;
        $base = $instance ? Instance::url($instance) : Platform::url((string) ($ticket['slug'] ?? ''));

        // Pel servidor de correu del domini del seu web.
        return Site::during((string) ($instance['domain'] ?? ''), static fn (): bool => Mailer::sendTemplate(
            $to, 'Resposta a la consulta ' . $ticket['reference'], 'support-reply', [
                'ticket' => $ticket,
                'body' => $body,
                'link' => ($ticket['slug'] ?? '') !== '' ? $base . '/admin/suport/' . (int) $ticket['id'] : '',
            ]
        ));
    }
}
