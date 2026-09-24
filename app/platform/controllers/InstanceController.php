<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\HttpException;
use Cros\Core\View;
use Cros\Platform\Client;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Provisioner;
use Cros\Platform\Request;
use RuntimeException;

/** Les instàncies: alta, fitxa i manteniment. */
class InstanceController extends Controller
{
    /** Llista d'instàncies. */
    public function index(): void
    {
        Console::requireLogin();
        $status = (string) ($_GET['estat'] ?? '');
        if (!isset(Instance::STATUSES[$status])) {
            $status = '';
        }
        $counts = ['all' => (int) Db::val('SELECT COUNT(*) FROM instances', [], 0)];
        foreach (Db::all('SELECT status, COUNT(*) AS total FROM instances GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        View::render('platform/console/instances', [
            'title' => 'Instàncies',
            'status' => $status,
            'counts' => $counts,
            'instances' => Instance::all($status),
            'outdated' => count(Instance::outdated()),
        ], 'layouts/console');
    }

    /** Formulari d'alta, opcionalment a partir d'una sol·licitud. */
    public function create(): void
    {
        Console::requireLogin();
        $request = null;
        if (!empty($_GET['peticio'])) {
            $request = Request::find((int) $_GET['peticio']);
        }

        View::render('platform/console/instance-new', [
            'title' => 'Nova instància',
            'request' => $request,
            'clients' => Client::all(),
            'domain' => Platform::domain(),
        ], 'layouts/console');
    }

    /** Dona d'alta la instància de debò. */
    public function store(): void
    {
        Console::requireLogin();
        $this->checkCsrf();

        $data = [
            'slug' => mb_strtolower(trim((string) ($_POST['slug'] ?? ''))),
            'site_name' => trim((string) ($_POST['site_name'] ?? '')),
            'town' => trim((string) ($_POST['town'] ?? '')),
            'language' => (string) ($_POST['language'] ?? 'ca'),
            'event_date' => trim((string) ($_POST['event_date'] ?? '')),
            'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
            'admin_email' => mb_strtolower(trim((string) ($_POST['admin_email'] ?? ''))),
            'demo' => !empty($_POST['demo']),
            'listed' => !empty($_POST['listed']),
        ];
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);

        $errors = $this->validate([
            'slug' => 'required|max:40',
            'site_name' => 'required|max:190',
            'admin_name' => 'required|max:150',
            'admin_email' => 'required|email|max:190',
        ], $data);
        $problem = $errors ? '' : Instance::slugProblem($data['slug']);
        if ($problem !== '') {
            $errors['slug'] = $problem;
        }
        if ($errors) {
            set_old($_POST);
            flash('error', reset($errors));
            redirect('/instancies/nova' . ($requestId > 0 ? '?peticio=' . $requestId : ''));
        }

        // Si ve d'una sol·licitud i no s'ha triat client, se li crea la fitxa.
        $request = $requestId > 0 ? Request::find($requestId) : null;
        if ($clientId <= 0 && $request) {
            $clientId = Client::fromRequest($request);
        }
        $data['client_id'] = $clientId > 0 ? $clientId : null;

        try {
            $result = Provisioner::create($data);
        } catch (RuntimeException $e) {
            log_line('platform', 'Alta d\'instància fallida des del panell', ['slug' => $data['slug'], 'error' => $e->getMessage()]);
            set_old($_POST);
            flash('error', $e->getMessage());
            redirect('/instancies/nova' . ($requestId > 0 ? '?peticio=' . $requestId : ''));
        }

        if ($request) {
            Request::decide($requestId, 'approved', '', $result['instance_id'], Console::id());
        }
        Console::log('instance_create', 'instance', $result['instance_id'], ['slug' => $data['slug']]);

        // La contrasenya només es veu un cop, just després de crear-la.
        $_SESSION['console_new_instance'] = ['id' => $result['instance_id'], 'password' => $result['password']];
        flash('success', 'La instància ' . $data['slug'] . '.' . Platform::domain() . ' ja està en marxa.');
        redirect('/instancies/' . $result['instance_id']);
    }

    /**
     * Posa al dia totes les instàncies.
     * El codi ja és el mateix per a tothom; el que cal repassar és la base de
     * dades de cadascuna, que pot tenir migracions pendents.
     */
    public function upgradeAll(): void
    {
        Console::requireLogin();
        $this->checkCsrf();

        $done = 0;
        $already = 0;
        $failed = [];
        foreach (Instance::outdated() as $row) {
            $result = Instance::upgrade((int) $row['id']);
            if (!$result['ok']) {
                $failed[] = (string) $row['slug'];
            } elseif ($result['applied']) {
                $done++;
            } else {
                $already++;
            }
        }
        Console::log('instances_upgrade', 'instance', null, ['fetes' => $done, 'fallides' => $failed]);

        if (!$done && !$already && !$failed) {
            flash('success', 'Totes les instàncies ja estaven al dia.');
        } else {
            $parts = [];
            if ($done) {
                $parts[] = $done . ($done === 1 ? ' instància actualitzada' : ' instàncies actualitzades');
            }
            if ($already) {
                $parts[] = $already . ' que ja ho estaven';
            }
            flash($failed ? 'error' : 'success', implode(', ', $parts ?: ['Cap canvi'])
                . ($failed ? '. No s\'han pogut actualitzar: ' . implode(', ', $failed) . '.' : '.'));
        }

        redirect('/instancies');
    }

    /** Fitxa d'una instància. */
    public function show(array $params): void
    {
        Console::requireLogin();
        $instance = Instance::find((int) $params['id']);
        if (!$instance) {
            throw new HttpException(404, 'Aquesta instància no existeix.');
        }
        $fresh = $_SESSION['console_new_instance'] ?? null;
        unset($_SESSION['console_new_instance']);
        if (!is_array($fresh) || (int) ($fresh['id'] ?? 0) !== (int) $instance['id']) {
            $fresh = null;
        }

        View::render('platform/console/instance', [
            'title' => $instance['site_name'],
            'instance' => $instance,
            'client' => $instance['client_id'] ? Client::find((int) $instance['client_id']) : null,
            'request' => Db::one('SELECT * FROM instance_requests WHERE instance_id = :id ORDER BY id DESC LIMIT 1', ['id' => $instance['id']]),
            'activity' => Db::all(
                'SELECT * FROM platform_activity WHERE subject = :s AND subject_id = :id ORDER BY id DESC LIMIT 20',
                ['s' => 'instance', 'id' => $instance['id']]
            ),
            'url' => Platform::url((string) $instance['slug']),
            'fresh' => $fresh,
        ], 'layouts/console');
    }

    /** Aturar, engegar, donar de baixa, actualitzar dades o desar canvis. */
    public function action(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $instance = Instance::find((int) $params['id']);
        if (!$instance) {
            throw new HttpException(404, 'Aquesta instància no existeix.');
        }
        $id = (int) $instance['id'];
        $action = (string) ($_POST['action'] ?? '');

        switch ($action) {
            case 'suspend':
                Instance::suspend($id);
                Console::log('instance_suspend', 'instance', $id, ['slug' => $instance['slug']]);
                flash('success', 'El web s\'ha aturat: els visitants hi veuen un avís.');
                break;
            case 'resume':
                Instance::resume($id);
                Console::log('instance_resume', 'instance', $id, ['slug' => $instance['slug']]);
                flash('success', 'El web torna a estar en marxa.');
                break;
            case 'cancel':
                if (mb_strtolower(trim((string) ($_POST['confirm'] ?? ''))) !== mb_strtolower((string) $instance['slug'])) {
                    flash('error', 'Per donar de baixa la instància cal escriure'
                        . ' «' . $instance['slug'] . '» a la casella de confirmació.');
                    break;
                }
                Instance::cancel($id);
                Console::log('instance_cancel', 'instance', $id, ['slug' => $instance['slug']]);
                flash('success', 'Instància donada de baixa. Les dades es guarden '
                    . Instance::PURGE_DAYS . ' dies abans d\'esborrar-se.');
                break;
            case 'upgrade':
                $result = Instance::upgrade($id);
                Console::log('instance_upgrade', 'instance', $id, ['slug' => $instance['slug'], 'ok' => $result['ok']]);
                flash(
                    $result['ok'] ? 'success' : 'error',
                    $result['ok']
                        ? ($result['applied']
                            ? 'Actualitzada: ' . count($result['applied']) . ' canvi(s) aplicats.'
                            : 'Ja estava al dia.')
                        : 'No s\'ha pogut actualitzar: ' . $result['error']
                );
                break;
            case 'sync':
                $synced = Instance::sync($id);
                Console::log('instance_sync', 'instance', $id, ['slug' => $instance['slug'], 'ok' => $synced]);
                flash(
                    $synced ? 'success' : 'error',
                    $synced
                        ? 'Dades actualitzades des del web del client.'
                        : 'No s\'ha pogut llegir la base de dades de la instància.'
                );
                break;
            case 'save':
                Instance::update($id, [
                    'site_name' => trim((string) ($_POST['site_name'] ?? $instance['site_name'])),
                    'town' => trim((string) ($_POST['town'] ?? '')) ?: null,
                    'admin_email' => mb_strtolower(trim((string) ($_POST['admin_email'] ?? ''))) ?: null,
                    'listed' => !empty($_POST['listed']) ? 1 : 0,
                ]);
                Console::log('instance_update', 'instance', $id, ['slug' => $instance['slug']]);
                flash('success', 'Canvis desats.');
                break;
            default:
                flash('error', 'Acció desconeguda.');
        }

        redirect('/instancies/' . $id);
    }
}
