<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Mailer;
use Cros\Models\Content;
use Cros\Models\Mailing;

/** Correus a les persones inscrites. */
class MailingsController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->adminView('mailings/index', [
            'title' => 'Enviaments de correu',
            'rows' => Mailing::recent(),
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        $this->form([
            'id' => 0,
            'subject' => '',
            'body' => '',
            'audience' => 'all',
            'categories' => '',
            'reg_status' => 'confirmed',
            'manual_emails' => '',
            'status' => 'draft',
        ], 'Nou enviament');
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();
        $mailing = Mailing::find((int) $params['id']);
        if (!$mailing) {
            abort(404, 'Enviament no trobat.');
        }
        if ($mailing['status'] !== 'draft') {
            redirect('/admin/enviaments/' . (int) $mailing['id']);
        }
        $this->form($mailing, 'Editar l\'enviament');
    }

    public function store(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $data = $this->collect();
        $errors = $this->validateMailing($data);
        if ($errors) {
            $this->form($data + ['id' => 0, 'status' => 'draft'], 'Nou enviament', $errors);
            return;
        }
        $id = Mailing::save(0, $data);
        Auth::logActivity('mailing_create', 'mailing', $id, ['assumpte' => $data['subject']]);
        flash('success', 'Esborrany desat. Reviseu els destinataris abans d\'enviar-lo.');
        redirect('/admin/enviaments/' . $id);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        $mailing = Mailing::find($id);
        if (!$mailing) {
            abort(404, 'Enviament no trobat.');
        }
        if ($mailing['status'] !== 'draft') {
            flash('error', 'Aquest enviament ja s\'ha començat a enviar i no es pot modificar.');
            $this->back('/admin/enviaments/' . $id);
        }
        $data = $this->collect();
        $errors = $this->validateMailing($data);
        if ($errors) {
            $this->form($data + ['id' => $id, 'status' => 'draft'], 'Editar l\'enviament', $errors);
            return;
        }
        Mailing::save($id, $data);
        flash('success', 'Esborrany desat.');
        redirect('/admin/enviaments/' . $id);
    }

    /** Fitxa amb els destinataris i el botó d'enviar. */
    public function show(array $params): void
    {
        Auth::requireLogin();
        $mailing = Mailing::find((int) $params['id']);
        if (!$mailing) {
            abort(404, 'Enviament no trobat.');
        }
        $this->adminView('mailings/show', [
            'title' => $mailing['subject'] ?: 'Enviament',
            'mailing' => $mailing,
            'audience' => $mailing['status'] === 'draft' ? Mailing::audience($mailing) : [],
            'recipients' => $mailing['status'] === 'draft' ? [] : Mailing::recipients((int) $mailing['id']),
            'categories' => Content::categories(),
            'batch' => Mailing::batchSize(),
        ]);
    }

    /** Com quedarà el correu. */
    public function preview(array $params): void
    {
        Auth::requireLogin();
        $mailing = Mailing::find((int) $params['id']);
        if (!$mailing) {
            abort(404, 'Enviament no trobat.');
        }
        $sample = ['name' => 'Anna Duran', 'participants' => 'Laia i Pau Duran', 'bibs' => '012, 013'];
        foreach (Mailing::audience($mailing) as $person) {
            $sample = $person;
            break;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo Mailing::render($mailing, $sample);
        exit;
    }

    /** Una prova a l'adreça de qui ho demana. */
    public function test(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $mailing = Mailing::find((int) $params['id']);
        if (!$mailing) {
            abort(404, 'Enviament no trobat.');
        }
        $to = trim((string) input('email')) ?: (string) (Auth::user()['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Indiqueu una adreça vàlida per a la prova.');
            $this->back('/admin/enviaments/' . (int) $mailing['id']);
        }
        $sample = ['name' => (string) (Auth::user()['name'] ?? ''), 'participants' => 'Laia i Pau Duran', 'bibs' => '012, 013'];
        $ok = Mailer::send($to, '[PROVA] ' . $mailing['subject'], Mailing::render($mailing, $sample));
        flash($ok ? 'success' : 'error', $ok
            ? 'Prova enviada a ' . $to . '.'
            : 'No s\'ha pogut enviar la prova. Reviseu la configuració del correu.');
        $this->back('/admin/enviaments/' . (int) $mailing['id']);
    }

    /** Prepara la llista de destinataris i deixa l'enviament a punt. */
    public function start(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        $mailing = Mailing::find($id);
        if (!$mailing) {
            abort(404, 'Enviament no trobat.');
        }
        $wasSent = $mailing['status'] === 'sent';
        $count = Mailing::prepare($id);
        if ($count === 0) {
            flash($wasSent ? 'info' : 'error', $wasSent
                ? 'No hi ha cap inscripció nova: ja han rebut el correu totes les adreces que hi encaixen.'
                : 'No hi ha cap destinatari amb els criteris triats.');
            $this->back('/admin/enviaments/' . $id);
        }
        Auth::logActivity('mailing_start', 'mailing', $id, ['destinataris' => $count]);
        flash('success', $wasSent
            ? 'N\'hi ha ' . $count . ' ' . ($count === 1 ? 'destinatari nou' : 'destinataris nous') . '.'
            : 'Enviament preparat: ' . $count . ' ' . ($count === 1 ? 'destinatari' : 'destinataris') . '.');
        redirect('/admin/enviaments/' . $id);
    }

    /** Envia una tanda. Amb JavaScript es va cridant fins a acabar. */
    public function batch(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        if (!Mailing::find($id)) {
            abort(404, 'Enviament no trobat.');
        }
        $result = Mailing::sendBatch($id);

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            $mailing = Mailing::find($id);
            json_out($result + [
                'total' => (int) ($mailing['total'] ?? 0),
                'sentTotal' => (int) ($mailing['sent'] ?? 0),
                'failedTotal' => (int) ($mailing['failed'] ?? 0),
            ]);
        }

        if ($result['done']) {
            flash('success', 'Enviament acabat.');
        } else {
            flash('info', 'Tanda enviada. En queden ' . $result['pending'] . '.');
        }
        $this->back('/admin/enviaments/' . $id);
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        Mailing::delete((int) $params['id']);
        flash('success', 'Enviament esborrat.');
        redirect('/admin/enviaments');
    }

    /** @return array<string,mixed> */
    private function collect(): array
    {
        return [
            'subject' => (string) input('subject'),
            'body' => (string) ($_POST['body'] ?? ''),
            'audience' => (string) input('audience', 'all'),
            'categories' => (array) ($_POST['categories'] ?? []),
            'reg_status' => (string) input('reg_status', 'confirmed'),
            'manual_emails' => (string) input('manual_emails'),
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
        if ($data['audience'] === 'category' && !array_filter((array) $data['categories'])) {
            $errors['categories'] = 'Trieu almenys una categoria.';
        }
        if ($data['audience'] === 'manual' && trim((string) $data['manual_emails']) === '') {
            $errors['manual_emails'] = 'Escriviu almenys una adreça.';
        }

        return $errors;
    }

    private function form(array $row, string $title, array $errors = []): void
    {
        $this->adminView('mailings/form', [
            'title' => $title,
            'row' => $row,
            'errors' => $errors,
            'categories' => Content::categories(),
        ]);
    }
}
