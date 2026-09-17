<?php
declare(strict_types=1);

namespace Cros\Core;

use ZipArchive;

/**
 * Actualitzacions OTA ("over the air").
 * Comprova un manifest remot, descarrega el paquet, fa còpia de seguretat,
 * substitueix els fitxers i executa les migracions pendents.
 */
class Updater
{
    /** Rutes que mai no se sobreescriuen en actualitzar. */
    private const PRESERVE = [
        'app/config.php',
        'uploads',
        'storage',
        '.env',
        '.htaccess.local',
        // L'instal·lador no es torna a crear en actualitzar: si l'heu esborrat,
        // per seguretat, ha de continuar esborrat.
        'install.php',
    ];

    public static function currentVersion(): string
    {
        return app_version();
    }

    public static function manifestUrl(): string
    {
        return (string) setting('update_manifest_url', '');
    }

    /** Consulta el manifest remot (amb memòria cau de 6 hores). */
    public static function check(bool $force = false): array
    {
        $cached = json_decode((string) setting('update_last_result', ''), true);
        $lastCheck = (int) setting('update_last_check', '0');
        if (!$force && is_array($cached) && $lastCheck > time() - 21600) {
            $cached['cached'] = true;
            return $cached;
        }

        $result = [
            'current'     => self::currentVersion(),
            'latest'      => self::currentVersion(),
            'available'   => false,
            'notes'       => '',
            'zip_url'     => '',
            'zip_api_url' => '',
            'sha256'      => '',
            'min_php'     => '',
            'published'   => '',
            'error'       => '',
            'notice'      => '',
            'checked_at'  => date('Y-m-d H:i:s'),
            'cached'      => false,
        ];

        $url = self::manifestUrl();
        if ($url === '') {
            $result['notice'] = 'No s\'ha configurat cap origen d\'actualitzacions.';
            return $result;
        }

        try {
            $manifest = self::fetchManifest($url);
            if ($manifest === null) {
                // L'origen funciona però encara no s'hi ha publicat cap versió.
                $result['notice'] = 'L\'origen d\'actualitzacions encara no té cap versió publicada.';
                log_line('update', 'Origen sense versions publicades', ['url' => $url]);
            } else {
                $result = array_merge($result, $manifest);
                $result['available'] = version_compare($result['latest'], self::currentVersion(), '>');
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            log_line('update', 'Error consultant el manifest', ['url' => $url, 'error' => $e->getMessage()]);
        }

        Settings::set('update_last_check', (string) time());
        Settings::set('update_last_result', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result;
    }

    /**
     * Descarrega i interpreta el manifest.
     * @return array|null null si l'origen respon bé però no hi ha cap versió publicada.
     */
    private static function fetchManifest(string $url): ?array
    {
        $response = self::http($url);

        // L'API de GitHub respon 404 a «releases/latest» quan encara no hi ha cap
        // versió publicada (o quan només n'hi ha de preliminars): ho comprovem a la llista.
        if ($response['status'] === 404 && self::isGithubApi($url) && str_contains($url, '/releases/latest')) {
            $listUrl = preg_replace('#/releases/latest.*$#', '/releases?per_page=10', $url) ?? $url;
            $list = self::http($listUrl);
            if ($list['status'] === 200) {
                $releases = json_decode($list['body'], true);
                if (is_array($releases)) {
                    foreach ($releases as $release) {
                        if (is_array($release) && empty($release['draft'])) {
                            return self::normalizeManifest($release);
                        }
                    }
                    return null;
                }
            }
            throw new \RuntimeException(self::describeError($list['status'], $list['body'], $url));
        }

        if ($response['status'] >= 400) {
            throw new \RuntimeException(self::describeError($response['status'], $response['body'], $url));
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new \RuntimeException('La resposta de l\'origen d\'actualitzacions no és un JSON vàlid. Comproveu l\'URL a Configuració → Actualitzacions.');
        }
        if ($data === []) {
            return null;
        }
        // Llista de versions de GitHub
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $release) {
                if (is_array($release) && empty($release['draft'])) {
                    return self::normalizeManifest($release);
                }
            }
            return null;
        }
        return self::normalizeManifest($data);
    }

    private static function isGithubApi(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) === 'api.github.com';
    }

    /** Converteix un codi d'error HTTP en un missatge entenedor. */
    private static function describeError(int $status, string $body, string $url): string
    {
        $data = json_decode($body, true);
        $apiMessage = is_array($data) && !empty($data['message']) ? (string) $data['message'] : '';
        $github = self::isGithubApi($url);

        if ($status === 404) {
            return $github
                ? 'GitHub respon que no ha trobat res (404). Comproveu que el dipòsit existeix, '
                    . 'que hi heu publicat alguna versió («release») i, si és privat, que heu indicat un token d\'accés.'
                : 'No s\'ha trobat el manifest d\'actualitzacions (404). Reviseu l\'URL a Configuració → Actualitzacions.';
        }
        if ($status === 401 || $status === 403) {
            if (stripos($apiMessage, 'rate limit') !== false) {
                return 'S\'ha superat el límit de consultes de l\'API de GitHub. Torneu-ho a provar d\'aquí una estona '
                    . 'o configureu un token d\'accés a Configuració → Actualitzacions.';
            }
            return 'L\'origen d\'actualitzacions ha denegat l\'accés (' . $status . '). '
                . 'Comproveu el token d\'accés' . ($apiMessage !== '' ? ': ' . $apiMessage : '.');
        }
        if ($status === 0) {
            return 'No s\'ha pogut connectar amb l\'origen d\'actualitzacions. Comproveu que el servidor té sortida a Internet.';
        }
        return 'El servidor d\'actualitzacions ha respost amb el codi ' . $status
            . ($apiMessage !== '' ? ' (' . $apiMessage . ')' : '') . '.';
    }

    /** Admet el format propi i el de l'API de versions de GitHub. */
    private static function normalizeManifest(array $data): array
    {
        if (isset($data['tag_name'])) { // GitHub Releases
            $zip = '';
            $apiUrl = '';
            foreach ($data['assets'] ?? [] as $asset) {
                if (str_ends_with((string) ($asset['name'] ?? ''), '.zip')) {
                    $zip = (string) ($asset['browser_download_url'] ?? '');
                    // Els dipòsits privats necessiten l'adreça de l'API amb el token.
                    $apiUrl = (string) ($asset['url'] ?? '');
                    break;
                }
            }
            $zip = $zip ?: (string) ($data['zipball_url'] ?? '');
            // Les notes de la versió poden incloure el resum SHA-256 del paquet
            // («SHA-256: abc123…»), que es fa servir per verificar-ne la integritat.
            $notes = (string) ($data['body'] ?? '');
            $sha = preg_match('/\b([a-f0-9]{64})\b/i', $notes, $shaMatch) ? strtolower($shaMatch[1]) : '';
            return [
                'latest'      => ltrim((string) $data['tag_name'], 'vV'),
                'notes'       => $notes,
                'zip_url'     => $zip,
                'zip_api_url' => $apiUrl,
                'sha256'      => $sha,
                'min_php'     => '',
                'published'   => (string) ($data['published_at'] ?? ''),
            ];
        }
        return [
            'latest'      => (string) ($data['version'] ?? '0.0.0'),
            'notes'       => (string) ($data['notes'] ?? ''),
            'zip_url'     => (string) ($data['zip_url'] ?? ($data['url'] ?? '')),
            'zip_api_url' => '',
            'sha256'      => (string) ($data['sha256'] ?? ''),
            'min_php'     => (string) ($data['min_php'] ?? ''),
            'published'   => (string) ($data['released'] ?? ''),
        ];
    }

    /** Descarrega el paquet i en verifica la integritat. */
    public static function download(string $url, string $sha256 = '', string $apiUrl = ''): string
    {
        // Amb token (dipòsits privats) s'ha de fer servir l'adreça de l'API de GitHub.
        $token = (string) setting('update_token', '');
        $useApi = $token !== '' && $apiUrl !== '';
        $source = $useApi ? $apiUrl : $url;

        if (!preg_match('#^https://#i', $source)) {
            throw new \RuntimeException('L\'URL del paquet ha de ser https.');
        }

        $response = self::http($source, [
            'accept' => 'application/octet-stream',
            'timeout' => 120,
            // El servidor de fitxers no ha de rebre la capçalera d'autorització.
            'strip_auth_on_redirect' => true,
        ]);
        if ($response['status'] >= 400) {
            throw new \RuntimeException(self::describeError($response['status'], $response['body'], $source));
        }
        if (strlen($response['body']) < 1024) {
            throw new \RuntimeException('El paquet descarregat és buit o massa petit.');
        }

        $target = CROS_ROOT . '/storage/tmp/update-' . date('YmdHis') . '.zip';
        self::ensureDir(dirname($target));
        file_put_contents($target, $response['body']);
        if ($sha256 !== '' && !hash_equals(strtolower($sha256), hash_file('sha256', $target))) {
            @unlink($target);
            throw new \RuntimeException('La signatura SHA-256 del paquet no coincideix. S\'ha cancel·lat l\'actualització.');
        }
        return $target;
    }

    /**
     * Aplica un paquet d'actualització.
     * @return array<int,string> registre de passos
     */
    public static function apply(string $zipPath, bool $makeBackup = true): array
    {
        $log = [];
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('L\'extensió ZIP de PHP no està disponible al servidor.');
        }
        if (!is_file($zipPath)) {
            throw new \RuntimeException('No s\'ha trobat el paquet d\'actualització.');
        }
        if (!is_writable(CROS_ROOT)) {
            throw new \RuntimeException('La carpeta de l\'aplicació no té permisos d\'escriptura.');
        }

        $workDir = CROS_ROOT . '/storage/tmp/extract-' . bin2hex(random_bytes(4));
        self::ensureDir($workDir);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('No s\'ha pogut obrir el paquet ZIP.');
        }
        $zip->extractTo($workDir);
        $zip->close();
        $log[] = 'Paquet descomprimit.';

        $source = self::findSourceRoot($workDir);
        if ($source === null) {
            self::deleteTree($workDir);
            throw new \RuntimeException('El paquet no té l\'estructura esperada (falta index.php o app/version.php).');
        }

        $newVersionData = @include $source . '/app/version.php';
        $newVersion = is_array($newVersionData) ? (string) ($newVersionData['version'] ?? '?') : '?';
        $minPhp = is_array($newVersionData) ? (string) ($newVersionData['min_php'] ?? '') : '';
        if ($minPhp !== '' && version_compare(PHP_VERSION, $minPhp, '<')) {
            self::deleteTree($workDir);
            throw new \RuntimeException('Aquesta versió requereix PHP ' . $minPhp . ' o superior (actual: ' . PHP_VERSION . ').');
        }

        self::maintenance(true);
        try {
            if ($makeBackup) {
                $backup = self::backup();
                $log[] = 'Còpia de seguretat creada: ' . basename($backup);
            }
            $copied = self::copyTree($source, CROS_ROOT);
            $log[] = $copied . ' fitxers actualitzats.';

            $migrations = Migrator::run();
            $log[] = $migrations ? count($migrations) . ' migracions aplicades.' : 'Cap migració pendent.';

            Settings::set('last_update_at', date('Y-m-d H:i:s'));
            Settings::set('update_last_check', '0');
            Auth::logActivity('update', 'system', 0, ['version' => $newVersion]);
            $log[] = 'Actualització completada (versió ' . $newVersion . ').';
        } finally {
            self::maintenance(false);
            self::deleteTree($workDir);
            @unlink($zipPath);
        }
        return $log;
    }

    /** Còpia de seguretat de fitxers i base de dades. */
    public static function backup(): string
    {
        $dir = CROS_ROOT . '/storage/backups';
        self::ensureDir($dir);
        $stamp = date('Y-m-d-His');
        $file = $dir . '/backup-' . app_version() . '-' . $stamp . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No s\'ha pogut crear la còpia de seguretat.');
        }
        $skip = ['storage', 'uploads', '.git', 'node_modules', 'dist'];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(CROS_ROOT, \FilesystemIterator::SKIP_DOTS),
                function ($current) use ($skip) {
                    $relative = str_replace('\\', '/', substr($current->getPathname(), strlen(CROS_ROOT) + 1));
                    $top = explode('/', $relative)[0];
                    return !in_array($top, $skip, true);
                }
            )
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(CROS_ROOT) + 1));
            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($item->getPathname(), $relative);
            }
        }
        $zip->addFromString('database.sql', self::dumpDatabase());
        $zip->close();
        self::pruneBackups($dir);
        return $file;
    }

    /** Manté només les 5 còpies més recents. */
    private static function pruneBackups(string $dir, int $keep = 5): void
    {
        $files = glob($dir . '/backup-*.zip') ?: [];
        if (count($files) <= $keep) {
            return;
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    /** Bolcat SQL de totes les taules. */
    public static function dumpDatabase(): string
    {
        $sqlite = Db::driver() === 'sqlite';
        $out = "-- Còpia de seguretat " . date('c') . "\n" . ($sqlite ? '' : "SET FOREIGN_KEY_CHECKS=0;\n");
        $tables = $sqlite
            ? array_column(Db::all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name')
            : array_map(fn ($row) => reset($row), Db::all('SHOW TABLES'));
        foreach ($tables as $table) {
            if ($sqlite) {
                $create = ['Create Table' => (string) Db::val('SELECT sql FROM sqlite_master WHERE name = :name', ['name' => $table], '')];
            } else {
                $create = Db::one('SHOW CREATE TABLE `' . $table . '`');
            }
            $out .= "\nDROP TABLE IF EXISTS `$table`;\n" . ($create['Create Table'] ?? '') . ";\n";
            $rows = Db::all('SELECT * FROM `' . $table . '`');
            foreach (array_chunk($rows, 50) as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $cells = array_map(function ($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return Db::conn()->quote((string) $value);
                    }, array_values($row));
                    $values[] = '(' . implode(',', $cells) . ')';
                }
                $columns = implode(',', array_map(fn ($c) => '`' . $c . '`', array_keys($chunk[0])));
                $out .= "INSERT INTO `$table` ($columns) VALUES " . implode(',', $values) . ";\n";
            }
        }
        return $out . ($sqlite ? "\n" : "\nSET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** Activa o desactiva el mode manteniment. */
    public static function maintenance(bool $on): void
    {
        $flag = CROS_ROOT . '/storage/maintenance.flag';
        if ($on) {
            self::ensureDir(dirname($flag));
            file_put_contents($flag, date('c'));
        } else {
            @unlink($flag);
        }
    }

    public static function inMaintenance(): bool
    {
        return is_file(CROS_ROOT . '/storage/maintenance.flag');
    }

    /** Troba l'arrel real del paquet descomprimit. */
    private static function findSourceRoot(string $dir): ?string
    {
        if (is_file($dir . '/index.php') && is_file($dir . '/app/version.php')) {
            return $dir;
        }
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $child) {
            if (is_file($child . '/index.php') && is_file($child . '/app/version.php')) {
                return $child;
            }
        }
        return null;
    }

    /** Copia recursivament preservant la configuració i els fitxers de l'usuari. */
    private static function copyTree(string $source, string $target): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
            if (self::isPreserved($relative)) {
                continue;
            }
            $destination = $target . '/' . $relative;
            if ($item->isDir()) {
                self::ensureDir($destination);
                continue;
            }
            self::ensureDir(dirname($destination));
            if (!@copy($item->getPathname(), $destination)) {
                throw new \RuntimeException('No s\'ha pogut escriure el fitxer: ' . $relative);
            }
            @chmod($destination, 0644);
            $count++;
        }
        return $count;
    }

    private static function isPreserved(string $relative): bool
    {
        foreach (self::PRESERVE as $preserved) {
            if ($relative === $preserved || str_starts_with($relative, $preserved . '/')) {
                return true;
            }
        }
        return false;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No s\'ha pogut crear la carpeta: ' . $dir);
        }
    }

    public static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * Petició HTTP GET.
     * @return array{status:int,body:string}
     */
    private static function http(string $url, array $options = []): array
    {
        $accept = (string) ($options['accept'] ?? 'application/json');
        $timeout = (int) ($options['timeout'] ?? 20);
        $withAuth = ($options['auth'] ?? true) !== false;
        $headers = [
            'Accept: ' . $accept,
            'User-Agent: CrosEscolar-Updater/' . app_version(),
        ];
        $token = (string) setting('update_token', '');
        if ($withAuth && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (function_exists('curl_init')) {
            // Amb «strip_auth_on_redirect» la redirecció se segueix a mà, sense el token:
            // els servidors de fitxers (per exemple els de GitHub) la rebutgen.
            $follow = empty($options['strip_auth_on_redirect']);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => $follow,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new \RuntimeException('No s\'ha pogut connectar amb l\'origen d\'actualitzacions: ' . $error);
            }
            if (!$follow && in_array($status, [301, 302, 303, 307, 308], true) && $location !== '') {
                return self::http($location, ['accept' => $accept, 'timeout' => $timeout, 'auth' => false]);
            }
            return ['status' => $status, 'body' => (string) $body];
        }

        $context = stream_context_create(['http' => [
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body === false) {
            throw new \RuntimeException('No s\'ha pogut descarregar: ' . $url);
        }
        return ['status' => $status, 'body' => (string) $body];
    }
}
