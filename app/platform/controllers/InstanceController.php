<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\HttpException;
use Cros\Core\Mailer;
use Cros\Core\View;
use Cros\Platform\Backup;
use Cros\Platform\Client;
use Cros\Platform\Console;
use Cros\Platform\Importer;
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
            'domains' => Platform::domains(),
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
            'domain' => (string) ($_POST['domain'] ?? ''),
            'demo' => !empty($_POST['demo']),
            'listed' => !empty($_POST['listed']),
        ];
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $migration = $this->migrationFile();
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
            @unlink($migration);
            set_old($_POST);
            flash('error', reset($errors));
            redirect('/instancies/nova' . ($requestId > 0 ? '?peticio=' . $requestId : ''));
        }
        $data['migration'] = $migration;

        // Si ve d'una sol·licitud i no s'ha triat client, se li crea la fitxa.
        $request = $requestId > 0 ? Request::find($requestId) : null;
        if ($clientId <= 0 && $request) {
            $clientId = Client::fromRequest($request);
        }
        $data['client_id'] = $clientId > 0 ? $clientId : null;

        // Importar un cros sencer pot trigar més que instal·lar-ne un de nou.
        @set_time_limit(600);
        try {
            $result = Provisioner::create($data);
        } catch (RuntimeException $e) {
            log_line('platform', 'Alta d\'instància fallida des del panell', ['slug' => $data['slug'], 'error' => $e->getMessage()]);
            @unlink($migration);
            set_old($_POST);
            flash('error', $e->getMessage());
            redirect('/instancies/nova' . ($requestId > 0 ? '?peticio=' . $requestId : ''));
        }

        if ($request) {
            Request::decide($requestId, 'approved', '', $result['instance_id'], Console::id());
        }
        Console::log('instance_create', 'instance', $result['instance_id'], ['slug' => $data['slug']]);

        if ($migration !== '' && str_starts_with(basename($migration), 'migracio-')) {
            @unlink($migration);
        }
        if ($migration !== '') {
            flash('success', $result['steps']['install'] ?? 'Cros importat.');
        }

        // L'enllaç d'estrena només es veu un cop, just després de crear-la.
        $_SESSION['console_new_instance'] = ['id' => $result['instance_id'], 'link' => $result['link']];
        flash('success', 'La instància ' . $data['slug'] . '.'
            . Platform::validDomain((string) $data['domain']) . ' ja està en marxa.');
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

    /**
     * El paquet de migració que s'hagi pujat, desat en un lloc segur.
     * Torna '' si no n'hi ha cap; si n'hi ha un de dolent, s'atura aquí.
     */
    private function migrationFile(): string
    {
        // Un paquet gros no passa pel navegador: es deixa a storage/imports/
        // del servidor i aquí només se'n diu el nom.
        $name = trim((string) ($_POST['migration_file'] ?? ''));
        if ($name !== '') {
            $path = storage_path('imports') . '/' . basename($name);
            if (!is_file($path)) {
                set_old($_POST);
                flash('error', 'No hi ha cap fitxer «' . basename($name) . '» a storage/imports/ del servidor.');
                redirect('/instancies/nova');
            }

            return $this->checked($path, false);
        }

        $file = $_FILES['migration'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            set_old($_POST);
            flash('error', 'El fitxer és massa gran per a aquest servidor. Pugeu «upload_max_filesize» '
                . 'i «post_max_size» del PHP, o deixeu el paquet al servidor i importeu-lo per consola.');
            redirect('/instancies/nova');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            set_old($_POST);
            flash('error', 'La pujada del fitxer ha fallat. Torneu-ho a provar.');
            redirect('/instancies/nova');
        }
        $target = storage_path('imports');
        if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
            flash('error', 'No s\'ha pogut desar el fitxer al servidor.');
            redirect('/instancies/nova');
        }
        $path = $target . '/migracio-' . bin2hex(random_bytes(6)) . '.zip';
        if (!@move_uploaded_file((string) $file['tmp_name'], $path)) {
            flash('error', 'No s\'ha pogut desar el fitxer al servidor.');
            redirect('/instancies/nova');
        }

        return $this->checked($path, true);
    }

    /**
     * Comprova que el paquet sigui bo abans de tocar res.
     * @param bool $own si el fitxer l'hem desat nosaltres (i per tant el podem esborrar)
     */
    private function checked(string $path, bool $own): string
    {
        try {
            Importer::inspect($path);
        } catch (RuntimeException $e) {
            if ($own) {
                @unlink($path);
            }
            set_old($_POST);
            flash('error', $e->getMessage());
            redirect('/instancies/nova');
        }

        return $path;
    }

    /** Es descarrega una còpia de seguretat. */
    public function backup(array $params): void
    {
        Console::requireLogin();
        $instance = Instance::find((int) $params['id']);
        if (!$instance) {
            throw new HttpException(404, 'Aquesta instància no existeix.');
        }
        try {
            $file = Backup::file((string) $instance['slug'], (string) ($_GET['fitxer'] ?? ''));
        } catch (RuntimeException $e) {
            throw new HttpException(404, $e->getMessage());
        }
        Console::log('backup_download', 'instance', (int) $instance['id'], [
            'slug' => $instance['slug'],
            'fitxer' => basename($file),
        ]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . (string) filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /** Una mida en lletres. */
    public static function size(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
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
            'backups' => Backup::all((string) $instance['slug']),
            'domains' => Platform::domains(),
            'activity' => Db::all(
                'SELECT * FROM platform_activity WHERE subject = :s AND subject_id = :id ORDER BY id DESC LIMIT 20',
                ['s' => 'instance', 'id' => $instance['id']]
            ),
            'url' => Instance::url($instance),
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
            case 'backup':
                @set_time_limit(300);
                $copy = Backup::create($id);
                Console::log('instance_backup', 'instance', $id, ['slug' => $instance['slug'], 'ok' => $copy['ok']]);
                flash(
                    $copy['ok'] ? 'success' : 'error',
                    $copy['ok']
                        ? 'Còpia feta: ' . basename($copy['file']) . ' (' . self::size($copy['size']) . ').'
                        : 'No s\'ha pogut fer la còpia: ' . $copy['error']
                );
                break;
            case 'link':
                $link = Instance::accessLink($id, 'reset', null, 'Demanat des del panell de la plataforma');
                Console::log('instance_link', 'instance', $id, ['slug' => $instance['slug'], 'ok' => $link['ok']]);
                if (!$link['ok']) {
                    flash('error', 'No s\'ha pogut crear l\'enllaç: ' . $link['error']);
                    break;
                }
                Mailer::sendTemplate($link['email'], 'Enllaç per entrar al panell', 'access-link', [
                    'site_name' => (string) $instance['site_name'],
                    'link' => $link['url'],
                ]);
                flash('success', 'Enllaç d\'accés enviat a ' . $link['email'] . '. Val dues hores i serveix un sol cop.');
                break;
            case 'support':
                $link = Instance::accessLink($id, 'support', null, 'Suport des de la plataforma');
                Console::log('instance_support', 'instance', $id, ['slug' => $instance['slug'], 'ok' => $link['ok']]);
                if (!$link['ok']) {
                    flash('error', 'No s\'ha pogut entrar: ' . $link['error']);
                    break;
                }
                // Qui gestiona el cros ho veurà al registre del seu web.
                redirect($link['url']);
                // no s'hi arriba
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
            case 'domain':
                $moved = Instance::moveTo($id, (string) ($_POST['domain'] ?? ''));
                Console::log('instance_domain', 'instance', $id, [
                    'slug' => $instance['slug'],
                    'domini' => (string) ($_POST['domain'] ?? ''),
                    'ok' => $moved['ok'],
                ]);
                flash(
                    $moved['ok'] ? 'success' : 'error',
                    $moved['ok']
                        ? 'El web passa a ser ' . $moved['host'] . '. L\'adreça anterior hi mena sola.'
                        : 'No s\'ha pogut canviar el domini: ' . $moved['error']
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
