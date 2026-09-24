<?php
declare(strict_types=1);

namespace Cros\Core;

use RuntimeException;
use ZipArchive;

/**
 * S'emporta totes les dades d'un cros en un fitxer ZIP.
 *
 * Hi ha de tot dues vegades, a posta: els fulls de càlcul (CSV) perquè es
 * puguin obrir i llegir, i una còpia de la base de dades (SQL) per si algun dia
 * es vol tornar a muntar el web en un altre lloc. I els fitxers pujats, és clar.
 */
class Exporter
{
    /** Taules que no tenen sentit fora d'aquí. */
    private const SKIP = ['migrations', 'login_attempts', 'sessions'];

    /** Noms bonics per als fulls de càlcul de les taules que es miren més. */
    private const NAMES = [
        'registrations' => 'inscripcions',
        'results' => 'resultats',
        'categories' => 'categories',
        'orders' => 'comandes',
        'tickets' => 'tiquets',
        'ticket_types' => 'tipus-de-tiquet',
        'pages' => 'pagines',
        'news' => 'noticies',
        'sponsors' => 'patrocinadors',
        'settings' => 'configuracio',
        'users' => 'usuaris',
        'email_log' => 'correus-enviats',
        'activity_log' => 'registre-activitat',
    ];

    /**
     * Prepara el ZIP i en torna el camí. Qui el demana és qui l'ha d'esborrar
     * després d'enviar-lo.
     */
    public static function create(?string $name = null, array $options = []): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Aquest servidor no té l\'extensió zip de PHP.');
        }
        // Normalment tot surt del web on som; la plataforma, en fer còpies de
        // seguretat, diu d'on són les dades i on s'han de desar.
        $dir = (string) ($options['dir'] ?? storage_path('exports'));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No s\'ha pogut crear la carpeta on desar l\'exportació.');
        }
        $title = (string) ($options['site_name'] ?? setting('site_name', 'cros'));
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title)) ?: 'cros';
        $base = $dir . '/' . trim($slug, '-') . '-' . ($name ?? date('Y-m-d-His'));
        // Si ja n'hi ha una amb aquest nom (dues còpies el mateix segon), se'n
        // fa una de nova al costat en comptes d'esborrar la que hi havia.
        $file = $base . '.zip';
        for ($i = 2; is_file($file); $i++) {
            $file = $base . '-' . $i . '.zip';
        }

        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No s\'ha pogut crear el fitxer ZIP.');
        }

        $tables = self::tables();
        $zip->addFromString('llegeix-me.txt', self::readme($tables, $title));
        foreach ($tables as $table) {
            $rows = Db::all('SELECT * FROM `' . $table . '`');
            $zip->addFromString('fulls/' . (self::NAMES[$table] ?? $table) . '.csv', self::csv($rows));
        }
        $zip->addFromString('base-de-dades.sql', self::dump($tables));
        self::addUploads($zip, (string) ($options['uploads'] ?? rtrim(upload_path(''), '/')));
        $zip->close();

        return $file;
    }

    /** Les taules del cros, sense les que només serveixen per funcionar. */
    public static function tables(): array
    {
        $tables = [];
        foreach (Db::conn()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM) as $row) {
            $name = (string) $row[0];
            if (!in_array($name, self::SKIP, true)) {
                $tables[] = $name;
            }
        }
        sort($tables);

        return $tables;
    }

    /** Un full de càlcul, amb la primera fila de capçaleres. */
    private static function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ';', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($v) => $v === null ? '' : (string) $v, $row), ';', '"', '\\');
            }
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        // El BOM fa que els accents es vegin bé en obrir-ho amb l'Excel.
        return "\xEF\xBB\xBF" . $csv;
    }

    /** Còpia de la base de dades, amb l'estructura i les dades. */
    private static function dump(array $tables): string
    {
        $pdo = Db::conn();
        $sql = "-- Còpia de les dades del cros\n"
            . '-- ' . date('d/m/Y H:i') . ' · versió ' . app_version() . "\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_NUM);
            $sql .= 'DROP TABLE IF EXISTS `' . $table . "`;\n" . ($create[1] ?? '') . ";\n\n";

            $statement = $pdo->query('SELECT * FROM `' . $table . '`');
            $lines = [];
            $columns = [];
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $columns = $columns ?: array_keys($row);
                $values = array_map(
                    static fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    $row
                );
                $lines[] = '(' . implode(', ', $values) . ')';
                // Es van escrivint per blocs per no omplir la memòria amb
                // taules llargues (inscripcions, resultats, correus…).
                if (count($lines) >= 200) {
                    $sql .= self::insert($table, $columns, $lines);
                    $lines = [];
                }
            }
            if ($lines) {
                $sql .= self::insert($table, $columns, $lines);
            }
            $sql .= "\n";
        }

        return $sql . "SET FOREIGN_KEY_CHECKS = 1;\n";
    }

    private static function insert(string $table, array $columns, array $lines): string
    {
        if (!$columns || !$lines) {
            return '';
        }

        return 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . "`) VALUES\n"
            . implode(",\n", $lines) . ";\n";
    }

    /** Els fitxers pujats, tal com estan. */
    private static function addUploads(ZipArchive $zip, string $root): void
    {
        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
            $zip->addFile($file->getPathname(), 'fitxers/' . $relative);
        }
    }

    /** Què hi ha dins del ZIP, explicat. */
    private static function readme(array $tables, string $title = ''): string
    {
        return "Dades de " . ($title !== '' ? $title : (string) setting('site_name', 'el cros')) . "\n"
            . str_repeat('=', 40) . "\n\n"
            . 'Exportació del ' . date('d/m/Y') . " a les " . date('H:i') . ".\n"
            . 'Versió del sistema: ' . app_version() . "\n\n"
            . "Què hi trobareu\n---------------\n"
            . "  fulls/           Un full de càlcul (CSV) per cada llista: inscripcions,\n"
            . "                   resultats, categories, comandes, configuració…\n"
            . "                   S'obren amb l'Excel, el Numbers o el LibreOffice.\n"
            . "  base-de-dades.sql  Còpia completa de la base de dades, per si algun dia\n"
            . "                   voleu tornar a muntar el web en un altre servidor.\n"
            . "  fitxers/         Les imatges i els documents que heu pujat.\n\n"
            . "Taules incloses: " . implode(', ', $tables) . "\n\n"
            . "Aquestes dades són vostres. Si les compartiu, tingueu present que hi ha\n"
            . "dades personals de les famílies inscrites i, a la llista d'usuaris, les\n"
            . "contrasenyes xifrades de qui gestiona el web.\n";
    }
}
