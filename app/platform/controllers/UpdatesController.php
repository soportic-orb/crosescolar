<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Settings;
use Cros\Core\Updater;
use Cros\Core\View;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\Platform;

/**
 * Actualitzacions del sistema, des del panell de la plataforma.
 *
 * El codi és un de sol per a tots els cros: qui l'actualitza és qui manté la
 * plataforma, i en acabar pot posar al dia les bases de dades de tots els
 * clients amb un botó.
 */
class UpdatesController extends Controller
{
    public function index(): void
    {
        Console::requireLogin();
        $result = json_decode((string) setting('update_last_result', ''), true);

        View::render('platform/console/updates', [
            'title' => 'Actualitzacions',
            'current' => app_version(),
            'manifestUrl' => Updater::manifestUrl(),
            'result' => is_array($result) ? $result : null,
            'backups' => self::backups(),
            'pending' => array_map('basename', Platform::pending()),
            'outdated' => count(Instance::outdated()),
            'writable' => is_writable(CROS_ROOT) && is_writable(CROS_APP),
            'zipAvailable' => class_exists(\ZipArchive::class),
            'lastUpdate' => (string) setting('last_update_at', ''),
        ], 'layouts/console');
    }

    public function check(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $result = Updater::check(true);
        if ($result['error'] !== '') {
            flash('error', 'No s\'ha pogut comprovar: ' . $result['error']);
        } elseif (($result['notice'] ?? '') !== '') {
            flash('info', (string) $result['notice']);
        } elseif ($result['available']) {
            flash('success', 'Hi ha una versió nova: ' . $result['latest'] . '.');
        } else {
            flash('info', 'Ja teniu l\'última versió (' . $result['current'] . ').');
        }
        redirect('/actualitzacions');
    }

    public function install(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $result = Updater::check(false);
        if (empty($result['zip_url'])) {
            flash('error', 'No hi ha cap paquet a punt per instal·lar.');
            redirect('/actualitzacions');
        }
        try {
            @set_time_limit(600);
            $zip = Updater::download(
                (string) $result['zip_url'],
                (string) ($result['sha256'] ?? ''),
                (string) ($result['zip_api_url'] ?? '')
            );
            $this->applyPackage($zip);
        } catch (\Throwable $e) {
            log_line('platform', 'Error actualitzant la plataforma', ['error' => $e->getMessage()]);
            flash('error', 'Error durant l\'actualització: ' . $e->getMessage());
        }
        redirect('/actualitzacions');
    }

    /** Instal·lació d'un paquet pujat a mà. */
    public function upload(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        if (!isset($_FILES['package']) || (int) $_FILES['package']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Cal triar un fitxer ZIP.');
            redirect('/actualitzacions');
        }
        $target = storage_path('tmp') . '/plataforma-' . date('YmdHis') . '.zip';
        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            flash('error', 'No s\'ha pogut desar el paquet al servidor.');
            redirect('/actualitzacions');
        }
        if (!move_uploaded_file($_FILES['package']['tmp_name'], $target)) {
            flash('error', 'No s\'ha pogut desar el paquet al servidor.');
            redirect('/actualitzacions');
        }
        try {
            @set_time_limit(600);
            $this->applyPackage($target);
        } catch (\Throwable $e) {
            flash('error', 'Error durant l\'actualització: ' . $e->getMessage());
        }
        redirect('/actualitzacions');
    }

    /**
     * Aplica el paquet i posa al dia la base de dades de la plataforma.
     * Les dels clients es fan a part, des d'«Instàncies», perquè queda clar
     * quantes se'n toquen i quan.
     */
    private function applyPackage(string $zip): void
    {
        $log = Updater::apply($zip, Settings::bool('update_backup', true), static fn (): array => Platform::migrate());
        Console::log('platform_update', '', null, ['versio' => app_version()]);
        flash('success', implode(' ', $log));

        $outdated = count(Instance::outdated());
        if ($outdated > 0) {
            flash('info', $outdated === 1
                ? 'Queda una instància per posar al dia: ho podeu fer des d\'«Instàncies».'
                : 'Queden ' . $outdated . ' instàncies per posar al dia: ho podeu fer des d\'«Instàncies».');
        }
    }

    public function backup(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        try {
            @set_time_limit(600);
            $file = Updater::backup();
            Console::log('platform_backup', '', null, ['fitxer' => basename($file)]);
            flash('success', 'Còpia del sistema creada: ' . basename($file));
        } catch (\Throwable $e) {
            flash('error', 'No s\'ha pogut crear la còpia: ' . $e->getMessage());
        }
        redirect('/actualitzacions');
    }

    public function download(array $params): void
    {
        Console::requireLogin();
        $name = basename((string) $params['file']);
        $path = storage_path('backups') . '/' . $name;
        if (!preg_match('/^backup-[\w.\-]+\.zip$/', $name) || !is_file($path)) {
            throw new HttpException(404, 'Aquesta còpia ja no hi és.');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    /** Les còpies del sistema que hi ha desades. */
    private static function backups(): array
    {
        $files = glob(storage_path('backups') . '/backup-*.zip') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return array_map(static fn (string $file): array => [
            'name' => basename($file),
            'size' => (int) filesize($file),
            'date' => date('d/m/Y H:i', (int) filemtime($file)),
        ], $files);
    }
}
