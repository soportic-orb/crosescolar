<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Migrator;
use Cros\Core\Updater;

/** Actualitzacions OTA i còpies de seguretat. */
class UpdateController extends Controller
{
    public function index(): void
    {
        Auth::requireAdmin();
        $result = json_decode((string) setting('update_last_result', ''), true);
        $this->adminView('updates/index', [
            'title' => 'Actualitzacions',
            'current' => app_version(),
            'manifestUrl' => Updater::manifestUrl(),
            'result' => is_array($result) ? $result : null,
            'backups' => $this->backups(),
            'pendingMigrations' => array_map('basename', Migrator::pending()),
            'writable' => is_writable(CROS_ROOT) && is_writable(CROS_APP),
            'zipAvailable' => class_exists(\ZipArchive::class),
        ]);
    }

    public function check(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $result = Updater::check(true);
        if ($result['error'] !== '') {
            flash('error', 'No s\'ha pogut comprovar: ' . $result['error']);
        } elseif (($result['notice'] ?? '') !== '') {
            flash('info', $result['notice']);
        } elseif ($result['available']) {
            flash('success', 'Hi ha una versió nova disponible: ' . $result['latest']);
        } else {
            flash('info', 'Ja teniu l\'última versió (' . $result['current'] . ').');
        }
        redirect('/admin/actualitzacions');
    }

    public function install(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $result = Updater::check(false);
        if (empty($result['zip_url'])) {
            flash('error', 'No hi ha cap paquet disponible per instal·lar.');
            redirect('/admin/actualitzacions');
        }
        try {
            @set_time_limit(300);
            $zip = Updater::download(
                (string) $result['zip_url'],
                (string) ($result['sha256'] ?? ''),
                (string) ($result['zip_api_url'] ?? '')
            );
            $log = Updater::apply($zip, setting('update_backup', '1') === '1');
            flash('success', implode(' ', $log));
        } catch (\Throwable $e) {
            log_line('update', 'Error aplicant l\'actualització', ['error' => $e->getMessage()]);
            flash('error', 'Error durant l\'actualització: ' . $e->getMessage());
        }
        redirect('/admin/actualitzacions');
    }

    /** Instal·lació manual pujant un fitxer ZIP. */
    public function upload(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        if (!isset($_FILES['package']) || (int) $_FILES['package']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Cal seleccionar un fitxer ZIP.');
            redirect('/admin/actualitzacions');
        }
        $target = CROS_ROOT . '/storage/tmp/manual-' . date('YmdHis') . '.zip';
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0775, true);
        }
        if (!move_uploaded_file($_FILES['package']['tmp_name'], $target)) {
            flash('error', 'No s\'ha pogut desar el paquet.');
            redirect('/admin/actualitzacions');
        }
        try {
            @set_time_limit(300);
            $log = Updater::apply($target, setting('update_backup', '1') === '1');
            flash('success', implode(' ', $log));
        } catch (\Throwable $e) {
            flash('error', 'Error durant l\'actualització: ' . $e->getMessage());
        }
        redirect('/admin/actualitzacions');
    }

    public function backup(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        try {
            @set_time_limit(300);
            $file = Updater::backup();
            Auth::logActivity('backup', 'system');
            flash('success', 'Còpia de seguretat creada: ' . basename($file));
        } catch (\Throwable $e) {
            flash('error', 'No s\'ha pogut crear la còpia: ' . $e->getMessage());
        }
        redirect('/admin/actualitzacions');
    }

    public function download(array $params): void
    {
        Auth::requireAdmin();
        $name = basename((string) $params['file']);
        $path = CROS_ROOT . '/storage/backups/' . $name;
        if (!preg_match('/^backup-[\w.\-]+\.zip$/', $name) || !is_file($path)) {
            abort(404, 'Còpia no trobada.');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function backups(): array
    {
        $files = glob(CROS_ROOT . '/storage/backups/backup-*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(fn ($file) => [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i', (int) filemtime($file)),
        ], $files);
    }
}
