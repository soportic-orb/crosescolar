<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Exporter;
use Cros\Core\Settings;
use Cros\Core\Tenancy;
use RuntimeException;

/**
 * Còpies de seguretat de cada client.
 *
 * Es fa el mateix ZIP que el client es pot descarregar des del seu panell
 * (fulls de càlcul, còpia de la base de dades i fitxers pujats), però desat
 * al servidor i guardant-ne unes quantes enrere. Cada client té la seva
 * carpeta: mai no es barreja res de dos cros diferents.
 */
class Backup
{
    /** Quantes còpies es guarden de cada instància si no es diu res. */
    public const KEEP = 7;

    /** On van les còpies. */
    public static function dir(?string $root = null, string $slug = ''): string
    {
        $root = $root ?? CROS_ROOT;
        $settings = (array) (Tenancy::settings($root)['backups'] ?? []);
        $base = trim((string) ($settings['dir'] ?? ''));
        $base = $base !== '' ? rtrim($base, '/') : $root . '/storage/backups';

        return $slug === '' ? $base : $base . '/' . $slug;
    }

    /** Quantes se'n guarden, segons la configuració. */
    public static function keep(?string $root = null): int
    {
        $settings = (array) (Tenancy::settings($root ?? CROS_ROOT)['backups'] ?? []);

        return max(1, (int) ($settings['keep'] ?? self::KEEP));
    }

    /**
     * Fa la còpia d'una instància.
     *
     * @return array{ok:bool,file:string,size:int,error:string}
     */
    public static function create(int $id, ?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $instance = Instance::find($id);
        if (!$instance || (string) $instance['status'] === 'purged') {
            return ['ok' => false, 'file' => '', 'size' => 0, 'error' => 'La instància no existeix.'];
        }
        $dir = Tenancy::dir($root, (string) $instance['slug']);
        if ($dir === '' || !is_file($dir . '/config.php')) {
            return ['ok' => false, 'file' => '', 'size' => 0, 'error' => 'No hi ha la carpeta de la instància.'];
        }

        $config = require $dir . '/config.php';
        $platform = Db::connection();
        $file = '';
        $error = '';
        try {
            Db::setConnection(Db::connect((array) ($config['db'] ?? []) + ['charset' => 'utf8mb4', 'timeout' => 20]));
            $file = Exporter::create(date('Y-m-d-His'), [
                'dir' => self::dir($root, (string) $instance['slug']),
                'uploads' => $dir . '/uploads',
                'site_name' => (string) $instance['site_name'],
            ]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            log_line('platform', 'No s\'ha pogut fer la còpia', ['slug' => $instance['slug'], 'error' => $error]);
        } finally {
            Db::setConnection($platform);
            Settings::forget();
            Platform::prime($root);
        }
        if ($error !== '') {
            return ['ok' => false, 'file' => '', 'size' => 0, 'error' => $error];
        }

        $size = (int) @filesize($file);
        Instance::update($id, ['backup_at' => date('Y-m-d H:i:s'), 'backup_size' => $size]);
        self::rotate((string) $instance['slug'], self::keep($root), $root);

        return ['ok' => true, 'file' => $file, 'size' => $size, 'error' => ''];
    }

    /**
     * Fa la còpia de totes les instàncies en marxa.
     *
     * @return array{done:array<int,string>,failed:array<string,string>,bytes:int}
     */
    public static function run(?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $report = ['done' => [], 'failed' => [], 'bytes' => 0];
        foreach (Instance::all() as $instance) {
            // De les donades de baixa també se'n fa, mentre es guardin: són
            // justament les que el client encara pot voler recuperar.
            if ((string) $instance['status'] === 'purged') {
                continue;
            }
            $result = self::create((int) $instance['id'], $root);
            if ($result['ok']) {
                $report['done'][] = (string) $instance['slug'];
                $report['bytes'] += $result['size'];
            } else {
                $report['failed'][(string) $instance['slug']] = $result['error'];
            }
        }
        Platform::log('backup_run', '', null, [
            'fetes' => count($report['done']),
            'fallides' => array_keys($report['failed']),
        ]);

        return $report;
    }

    /** Les còpies que hi ha d'una instància, de la més nova a la més vella. */
    public static function all(string $slug, ?string $root = null): array
    {
        $files = glob(self::dir($root, $slug) . '/*.zip') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return array_map(static fn (string $file): array => [
            'name' => basename($file),
            'size' => (int) filesize($file),
            'time' => (int) filemtime($file),
        ], $files);
    }

    /** Esborra les que sobren i deixa les $keep més noves. */
    public static function rotate(string $slug, int $keep, ?string $root = null): int
    {
        $removed = 0;
        foreach (array_slice(self::all($slug, $root), max(1, $keep)) as $old) {
            if (@unlink(self::dir($root, $slug) . '/' . $old['name'])) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * El camí d'una còpia concreta, comprovant que sigui d'aquesta instància.
     * Mai no es munta un camí amb el que arribi de fora sense passar per aquí.
     */
    public static function file(string $slug, string $name, ?string $root = null): string
    {
        if ($name !== basename($name) || !str_ends_with($name, '.zip')) {
            throw new RuntimeException('Aquest fitxer no és una còpia.');
        }
        $file = self::dir($root, $slug) . '/' . $name;
        if (!is_file($file)) {
            throw new RuntimeException('Aquesta còpia ja no hi és.');
        }

        return $file;
    }
}
