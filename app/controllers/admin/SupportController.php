<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Mailer;
use Cros\Core\Tenancy;
use Cros\Platform\Platform;
use Cros\Platform\Support;

/**
 * L'apartat de suport del panell d'un cros.
 *
 * Els tiquets no són en aquesta base de dades sinó en la de la plataforma, de
 * manera que tot el que toca dades va dins d'un Support::run(): obre la
 * connexió de la plataforma, fa la feina i torna a deixar-ho tot com estava.
 */
class SupportController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $slug = $this->slug();
        // Tot d'una sola connexió a la plataforma: obrir-ne una per cada cosa
        // que es vol saber és car i no fa falta.
        [$tickets, $departments, $unread, $canOpen] = Support::run(static fn (): array => [
            Support::forSlug($slug),
            Support::departments(true),
            Support::unreadFor($slug),
            \Cros\Core\Settings::bool('support_enabled', true),
        ], null, true);

        $this->adminView('support/index', [
            'title' => 'Suport',
            'tickets' => $tickets,
            'departments' => $departments,
            'unread' => $unread,
            'canOpen' => $canOpen,
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        $this->form(['department_id' => 0, 'priority' => 'normal', 'subject' => '', 'body' => '']);
    }

    public function store(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        if (!$this->open()) {
            flash('error', 'Ara mateix no s\'accepten consultes noves.');
            redirect('/admin/suport');
        }
        $data = [
            'department_id' => (int) input('department_id', '0'),
            'priority' => (string) input('priority', 'normal'),
            'subject' => trim((string) input('subject')),
            'body' => (string) ($_POST['body'] ?? ''),
        ];
        $errors = [];
        if ($data['subject'] === '') {
            $errors['subject'] = 'Digueu en una línia de què va la consulta.';
        }
        if (trim(strip_tags((string) $data['body'])) === '') {
            $errors['body'] = 'Expliqueu-nos què us passa.';
        }
        if ($errors) {
            $this->form($data, $errors);

            return;
        }

        $user = Auth::user() ?? [];
        $slug = $this->slug();
        $ticket = Support::run(function () use ($data, $user, $slug): array {
            $ticket = Support::open($data + Support::context($slug) + [
                'author_name' => (string) ($user['name'] ?? ''),
                'author_email' => (string) ($user['email'] ?? ''),
            ]);
            $this->notify($ticket, (string) $data['body']);

            return $ticket;
        }, null, true);

        Auth::logActivity('support_open', 'support', (int) ($ticket['id'] ?? 0), ['assumpte' => $data['subject']]);
        flash('success', 'Consulta enviada amb el número ' . ($ticket['reference'] ?? '') . '. Us contestarem per aquí i per correu.');
        redirect('/admin/suport/' . (int) ($ticket['id'] ?? 0));
    }

    public function show(array $params): void
    {
        Auth::requireLogin();
        $slug = $this->slug();
        $id = (int) $params['id'];
        [$ticket, $messages, $departments] = Support::run(function () use ($id, $slug): array {
            $ticket = Support::findFor($id, $slug);
            if (!$ticket) {
                return [null, [], []];
            }
            Support::markRead($id);

            return [$ticket, Support::messages($id), Support::departments(true)];
        });
        if (!$ticket) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }

        $this->adminView('support/show', [
            'title' => 'Consulta ' . $ticket['reference'],
            'ticket' => $ticket,
            'messages' => $messages,
            'departments' => $departments,
        ]);
    }

    /** Una resposta del client al seu propi tiquet. */
    public function reply(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        $slug = $this->slug();
        $body = (string) ($_POST['body'] ?? '');
        if (trim(strip_tags($body)) === '') {
            flash('error', 'La resposta no pot anar buida.');
            $this->back('/admin/suport/' . $id);
        }
        $user = Auth::user() ?? [];
        $ok = Support::run(function () use ($id, $slug, $body, $user): bool {
            $ticket = Support::findFor($id, $slug);
            if (!$ticket) {
                return false;
            }
            Support::write($id, 'client', $body, [
                'author_name' => (string) ($user['name'] ?? ''),
                'author_email' => (string) ($user['email'] ?? ''),
            ]);
            $this->notify(Support::find($id) ?? $ticket, $body, true);

            return true;
        }, null, true);
        if (!$ok) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }

        flash('success', 'Resposta enviada.');
        redirect('/admin/suport/' . $id);
    }

    /** El client dona la consulta per resolta. */
    public function close(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        $slug = $this->slug();
        $ok = Support::run(function () use ($id, $slug): bool {
            if (!Support::findFor($id, $slug)) {
                return false;
            }
            Support::update($id, ['status' => 'closed']);

            return true;
        });
        if (!$ok) {
            throw new HttpException(404, 'Aquesta consulta no existeix.');
        }

        flash('success', 'Consulta tancada. Si cal, sempre en podeu obrir una de nova.');
        redirect('/admin/suport');
    }

    /**
     * Avisa el suport que hi ha feina.
     *
     * S'executa amb la configuració de la plataforma carregada, de manera que
     * el correu surt del remitent del servei i no del cros.
     */
    private function notify(array $ticket, string $body, bool $isReply = false): void
    {
        $to = '';
        if (!empty($ticket['department_id'])) {
            $department = Support::department((int) $ticket['department_id']);
            $to = (string) ($department['email'] ?? '');
        }
        $to = $to !== '' ? $to : trim((string) setting('support_notify', ''));
        $to = $to !== '' ? $to : Platform::notifyEmail();
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Mailer::sendTemplate(
            $to,
            ($isReply ? 'Resposta a ' : 'Consulta nova ') . $ticket['reference'] . ': ' . $ticket['subject'],
            'support-ticket',
            [
                'ticket' => $ticket,
                'body' => $body,
                'isReply' => $isReply,
                'link' => Platform::consoleUrl(null, '/suport/' . (int) $ticket['id']),
            ]
        );
    }

    /** El subdomini del web que s'està administrant. */
    private function slug(): string
    {
        $slug = Tenancy::slugOf();
        if ($slug === '' || !Support::available()) {
            throw new HttpException(404, 'Aquesta instal·lació no té servei de suport.');
        }

        return $slug;
    }

    /** Es poden obrir consultes noves? Ho decideix la plataforma. */
    private function open(): bool
    {
        return Support::run(static fn (): bool => \Cros\Core\Settings::bool('support_enabled', true), null, true);
    }

    private function form(array $row, array $errors = []): void
    {
        $this->slug();
        [$departments, $intro, $hours, $canOpen] = Support::run(static fn (): array => [
            Support::departments(true),
            (string) setting('support_intro', ''),
            (string) setting('support_hours', ''),
            \Cros\Core\Settings::bool('support_enabled', true),
        ], null, true);
        if (!$canOpen) {
            flash('info', 'Ara mateix no s\'accepten consultes noves.');
            redirect('/admin/suport');
        }

        $this->adminView('support/form', [
            'title' => 'Nova consulta',
            'row' => $row,
            'errors' => $errors,
            'departments' => $departments,
            'intro' => $intro,
            'hours' => $hours,
        ]);
    }
}
