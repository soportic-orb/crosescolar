<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\View;
use Cros\Platform\Console;
use Cros\Platform\Contact;

/** Els missatges del formulari de contacte, al Panell de Superadministració. */
class ContactController extends Controller
{
    /** La safata: per llegir i llegits, o un estat concret. */
    public function index(): void
    {
        Console::requireLogin();
        $status = (string) ($_GET['estat'] ?? '');
        if ($status !== '' && !isset(Contact::STATUSES[$status])) {
            $status = '';
        }
        $search = trim((string) ($_GET['q'] ?? ''));

        View::render('platform/console/contacts', [
            'title' => 'Contacte',
            'status' => $status,
            'search' => $search,
            'contacts' => Contact::all($status, $search),
        ], 'layouts/console');
    }

    /** Un missatge. Obrir-lo el marca com a llegit. */
    public function show(array $params): void
    {
        Console::requireLogin();
        $contact = Contact::find((int) $params['id']);
        if (!$contact) {
            throw new HttpException(404, 'Aquest missatge no existeix.');
        }
        if ((string) $contact['status'] === 'new') {
            Contact::open((int) $contact['id']);
            $contact = (array) Contact::find((int) $contact['id']);
        }

        View::render('platform/console/contact', [
            'title' => 'Missatge de ' . $contact['name'],
            'contact' => $contact,
        ], 'layouts/console');
    }

    /** Canviar-ne l'estat o esborrar-lo. */
    public function action(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $contact = Contact::find((int) $params['id']);
        if (!$contact) {
            throw new HttpException(404, 'Aquest missatge no existeix.');
        }
        $id = (int) $contact['id'];
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            Contact::delete($id);
            Console::log('contact_delete', 'contact', $id, ['correu' => $contact['email']]);
            flash('success', 'Missatge esborrat.');
            redirect('/contacte');
        }
        if (isset(Contact::STATUSES[$action])) {
            Contact::setStatus($id, $action);
            Console::log('contact_status', 'contact', $id, ['estat' => $action]);
            flash('success', 'El missatge queda com a «' . Contact::STATUSES[$action] . '».');
            // Un cop arxivat o tornat a «per llegir», el que toca és seguir amb la safata.
            redirect(in_array($action, ['archived', 'new'], true) ? '/contacte' : '/contacte/' . $id);
        }
        throw new HttpException(400, 'Acció desconeguda.');
    }
}
