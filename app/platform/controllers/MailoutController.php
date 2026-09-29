<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Core\View;
use Cros\Platform\Console;
use Cros\Platform\MailList;
use Cros\Platform\MailTemplate;
use Cros\Platform\Mailout;

/** Els enviaments de correu de la plataforma, les llistes i la plantilla. */
class MailoutController extends Controller
{
    public function index(): void
    {
        Console::requireLogin();
        View::render('platform/console/mail/index', [
            'title' => 'Enviaments',
            'rows' => Mailout::recent(),
            'lists' => MailList::all(),
        ], 'layouts/console');
    }

    public function create(): void
    {
        Console::requireLogin();
        $this->form([
            'id' => 0, 'subject' => '', 'body' => '', 'audience' => 'clients',
            'list_id' => 0, 'instance_status' => 'active', 'manual_emails' => '', 'status' => 'draft',
        ], 'Nou enviament');
    }

    public function store(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $data = $this->collect();
        $errors = $this->validateMailing($data);
        if ($errors) {
            $this->form($data + ['id' => 0, 'status' => 'draft'], 'Nou enviament', $errors);

            return;
        }
        $id = Mailout::save(0, $data, Console::id());
        Console::log('mailout_create', 'mailout', $id, ['assumpte' => $data['subject']]);
        flash('success', 'Esborrany desat. Reviseu els destinataris abans d\'enviar-lo.');
        redirect('/enviaments/' . $id);
    }

    public function edit(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        if ($mailing['status'] !== 'draft') {
            redirect('/enviaments/' . (int) $mailing['id']);
        }
        $this->form($mailing, 'Editar l\'enviament');
    }

    public function update(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $id = (int) $mailing['id'];
        if ($mailing['status'] !== 'draft') {
            flash('error', 'Aquest enviament ja s\'ha començat a enviar i no es pot modificar.');
            $this->back('/enviaments/' . $id);
        }
        $data = $this->collect();
        $errors = $this->validateMailing($data);
        if ($errors) {
            $this->form($data + ['id' => $id, 'status' => 'draft'], 'Editar l\'enviament', $errors);

            return;
        }
        Mailout::save($id, $data, Console::id());
        flash('success', 'Esborrany desat.');
        redirect('/enviaments/' . $id);
    }

    public function show(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        $isDraft = $mailing['status'] === 'draft';

        View::render('platform/console/mail/show', [
            'title' => $mailing['subject'] ?: 'Enviament',
            'mailing' => $mailing,
            'audience' => $isDraft ? Mailout::audience($mailing) : [],
            'recipients' => $isDraft ? [] : Mailout::recipients((int) $mailing['id']),
            'list' => !empty($mailing['list_id']) ? MailList::find((int) $mailing['list_id']) : null,
            'batch' => Mailout::batchSize(),
        ], 'layouts/console');
    }

    /** Com quedarà el correu, amb el primer destinatari de debò. */
    public function preview(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        $sample = ['name' => 'Anna Duran', 'entity' => 'AFA Escola Pompeu Fabra', 'email' => 'anna@example.cat'];
        foreach (Mailout::audience($mailing) as $person) {
            $sample = $person;
            break;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo Mailout::render($mailing, $sample);
        exit;
    }

    public function test(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $to = trim((string) input('email')) ?: (string) (Console::user()['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Indiqueu una adreça vàlida per a la prova.');
            $this->back('/enviaments/' . (int) $mailing['id']);
        }
        $sample = ['name' => (string) (Console::user()['name'] ?? ''), 'entity' => 'Entitat de prova', 'email' => $to];
        $ok = Mailer::send($to, '[PROVA] ' . Mailout::subjectFor($mailing, $sample), Mailout::render($mailing, $sample));
        flash($ok ? 'success' : 'error', $ok
            ? 'Prova enviada a ' . $to . '.'
            : 'No s\'ha pogut enviar la prova. Reviseu la configuració del correu.');
        $this->back('/enviaments/' . (int) $mailing['id']);
    }

    public function start(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $id = (int) $mailing['id'];
        $wasSent = $mailing['status'] === 'sent';
        $count = Mailout::prepare($id);
        if ($count === 0) {
            flash($wasSent ? 'info' : 'error', $wasSent
                ? 'No hi ha cap adreça nova: ja l\'han rebut totes les que hi encaixen.'
                : 'No hi ha cap destinatari amb els criteris triats.');
            $this->back('/enviaments/' . $id);
        }
        Console::log('mailout_start', 'mailout', $id, ['destinataris' => $count]);
        flash('success', 'Enviament preparat: ' . $count . ' ' . ($count === 1 ? 'destinatari' : 'destinataris') . '.');
        redirect('/enviaments/' . $id);
    }

    /** Una tanda. Amb JavaScript es va cridant fins a acabar. */
    public function batch(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $id = (int) $mailing['id'];
        $result = Mailout::sendBatch($id);

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            $after = Mailout::find($id) ?? [];
            json_out($result + [
                'total' => (int) ($after['total'] ?? 0),
                'sentTotal' => (int) ($after['sent'] ?? 0),
                'failedTotal' => (int) ($after['failed'] ?? 0),
            ]);
        }

        flash($result['done'] ? 'success' : 'info', $result['done']
            ? 'Enviament acabat.'
            : 'Tanda enviada. En queden ' . $result['pending'] . '.');
        $this->back('/enviaments/' . $id);
    }

    public function destroy(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        Mailout::delete((int) $mailing['id']);
        Console::log('mailout_delete', 'mailout', (int) $mailing['id'], ['assumpte' => $mailing['subject']]);
        flash('success', 'Enviament esborrat.');
        redirect('/enviaments');
    }

    // ------------------------------------------------------- les llistes

    public function lists(): void
    {
        Console::requireLogin();
        View::render('platform/console/mail/lists', [
            'title' => 'Llistes de correu',
            'lists' => MailList::all(),
            'edit' => isset($_GET['editar']) ? MailList::find((int) $_GET['editar']) : null,
        ], 'layouts/console');
    }

    public function saveList(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'La llista necessita un nom.');
            $this->back('/enviaments/llistes');
        }
        $id = MailList::save((int) input('id', '0'), $name, (string) input('description'));
        Console::log('maillist_save', 'maillist', $id, ['nom' => $name]);
        flash('success', 'Llista desada.');
        redirect('/enviaments/llistes/' . $id);
    }

    public function listShow(array $params): void
    {
        Console::requireLogin();
        $list = MailList::find((int) $params['id']);
        if (!$list) {
            throw new HttpException(404, 'Aquesta llista no existeix.');
        }
        View::render('platform/console/mail/list', [
            'title' => (string) $list['name'],
            'list' => $list,
            'contacts' => MailList::contacts((int) $list['id']),
        ], 'layouts/console');
    }

    /** Afegir adreces enganxant-les, una per línia. */
    public function addContacts(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $list = MailList::find((int) $params['id']);
        if (!$list) {
            throw new HttpException(404, 'Aquesta llista no existeix.');
        }
        $result = MailList::addContacts((int) $list['id'], (string) ($_POST['contacts'] ?? ''));
        $parts = [];
        if ($result['added'] > 0) {
            $parts[] = $result['added'] . ($result['added'] === 1 ? ' adreça nova' : ' adreces noves');
        }
        if ($result['updated'] > 0) {
            $parts[] = $result['updated'] . ' completades';
        }
        if ($result['skipped']) {
            $parts[] = count($result['skipped']) . ' sense adreça vàlida ('
                . implode('; ', array_slice($result['skipped'], 0, 3)) . ')';
        }
        Console::log('maillist_contacts', 'maillist', (int) $list['id'], $result);
        flash($result['added'] + $result['updated'] > 0 ? 'success' : 'error',
            $parts ? ucfirst(implode(', ', $parts)) . '.' : 'No s\'hi ha afegit res.');
        $this->back('/enviaments/llistes/' . (int) $list['id']);
    }

    public function contactAction(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        $listId = (int) input('list_id', '0');
        if ((string) input('action') === 'remove') {
            MailList::removeContact($id);
            flash('success', 'Adreça treta de la llista.');
        } else {
            MailList::toggleContact($id);
            flash('success', 'Adreça canviada.');
        }
        $this->back('/enviaments/llistes/' . $listId);
    }

    public function deleteList(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $list = MailList::find((int) $params['id']);
        if (!$list) {
            throw new HttpException(404, 'Aquesta llista no existeix.');
        }
        MailList::delete((int) $list['id']);
        Console::log('maillist_delete', 'maillist', (int) $list['id'], ['nom' => $list['name']]);
        flash('success', 'Llista esborrada.');
        redirect('/enviaments/llistes');
    }

    // ----------------------------------------------------- la plantilla

    public function template(): void
    {
        Console::requireLogin();
        View::render('platform/console/mail/template', [
            'title' => 'Plantilla del correu',
            'header' => MailTemplate::header(),
            'footer' => MailTemplate::footer(),
            'custom' => trim((string) Settings::get('platform_mail_header', '')) !== ''
                || trim((string) Settings::get('platform_mail_footer', '')) !== '',
        ], 'layouts/console');
    }

    public function saveTemplate(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        if ((string) input('action') === 'reset') {
            Settings::set('platform_mail_header', '');
            Settings::set('platform_mail_footer', '');
            Console::log('mailtemplate_reset', 'settings');
            flash('success', 'Plantilla tornada a com era de fàbrica.');
            redirect('/enviaments/plantilla');
        }
        Settings::set('platform_mail_header', trim((string) ($_POST['header'] ?? '')));
        Settings::set('platform_mail_footer', trim((string) ($_POST['footer'] ?? '')));
        Console::log('mailtemplate_save', 'settings');
        flash('success', 'Plantilla desada.');
        redirect('/enviaments/plantilla');
    }

    /** Com queda la plantilla amb un text qualsevol a dins. */
    public function templatePreview(): void
    {
        Console::requireLogin();
        header('Content-Type: text/html; charset=utf-8');
        echo MailTemplate::render(
            '<h1 style="font-size:20px;margin:0 0 14px;color:#1b452a">Hola, {{nom}}</h1>'
            . '<p>Aquí hi aniria el text de l\'enviament. Això és una mostra per veure com queden '
            . 'la capçalera i el peu.</p>'
            . '<p>Podeu fer-hi servir els marcadors: entitat <strong>{{entitat}}</strong>, '
            . 'adreça <strong>{{correu}}</strong>.</p>',
            'Mostra de la plantilla',
            ['name' => 'Anna Duran', 'entity' => 'AFA Escola Pompeu Fabra', 'email' => 'anna@example.cat']
        );
        exit;
    }

    // ------------------------------------------------------------ ajuda

    private function mailing(int $id): array
    {
        $mailing = Mailout::find($id);
        if (!$mailing) {
            throw new HttpException(404, 'Aquest enviament no existeix.');
        }

        return $mailing;
    }

    /** @return array<string,mixed> */
    private function collect(): array
    {
        return [
            'subject' => (string) input('subject'),
            'body' => (string) ($_POST['body'] ?? ''),
            'audience' => (string) input('audience', 'clients'),
            'list_id' => (int) input('list_id', '0'),
            'instance_status' => (string) input('instance_status', 'active'),
            'manual_emails' => (string) ($_POST['manual_emails'] ?? ''),
        ];
    }

    /** @return array<string,string> */
    private function validateMailing(array $data): array
    {
        $errors = [];
        if (trim((string) $data['subject']) === '') {
            $errors['subject'] = 'Cal posar un assumpte al correu.';
        }
        if (trim(strip_tags((string) $data['body'])) === '') {
            $errors['body'] = 'El correu no pot anar buit.';
        }
        if ($data['audience'] === 'list' && (int) $data['list_id'] <= 0) {
            $errors['list_id'] = 'Trieu una llista.';
        }
        if ($data['audience'] === 'manual' && trim((string) $data['manual_emails']) === '') {
            $errors['manual_emails'] = 'Escriviu almenys una adreça.';
        }

        return $errors;
    }

    private function form(array $row, string $title, array $errors = []): void
    {
        View::render('platform/console/mail/form', [
            'title' => $title,
            'row' => $row,
            'errors' => $errors,
            'lists' => MailList::all(),
        ], 'layouts/console');
    }
}
