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
    /** Com es reconeix un paquet d'aquests. */
    public const FORMAT = 'cros-escolar-export';

    /** Versió del format del paquet, per si algun dia canvia. */
    public const FORMAT_VERSION = 1;

    /** Taules que no surten als fulls de càlcul: no diuen res a ningú. */
    private const SKIP = ['migrations', 'login_attempts', 'sessions', 'login_links'];

    /** Taules de les quals es desa l'estructura però no el contingut. */
    private const SKIP_DATA = ['login_attempts', 'sessions', 'login_links'];

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
        $counts = [];
        foreach ($tables as $table) {
            $rows = Db::all('SELECT * FROM `' . $table . '`');
            $counts[$table] = count($rows);
            $zip->addFromString('fulls/' . (self::NAMES[$table] ?? $table) . '.csv', self::csv($rows));
        }
        $sql = self::dump(self::allTables());
        $zip->addFromString('base-de-dades.sql', $sql);
        $uploads = self::addUploads($zip, (string) ($options['uploads'] ?? rtrim(upload_path(''), '/')));
        // La fitxa del paquet: és el que mira la plataforma per saber que això
        // és un cros de debò i que no s'ha fet malbé pel camí.
        $zip->addFromString('migracio.json', self::manifest($title, $tables, $counts, $sql, $uploads, $options));
        $zip->close();

        return $file;
    }

    /** Les taules del cros, sense les que només serveixen per funcionar. */
    public static function tables(): array
    {
        return array_values(array_filter(
            self::allTables(),
            static fn (string $table): bool => !in_array($table, self::SKIP, true)
        ));
    }

    /**
     * Totes les taules, sense excepció.
     * La còpia de la base de dades les ha de portar totes —també la de control
     * de versions—: sense això, qui la restauri no sabria per quina versió va i
     * tornaria a aplicar canvis que ja hi són.
     *
     * @return array<int,string>
     */
    public static function allTables(): array
    {
        $tables = [];
        foreach (Db::conn()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM) as $row) {
            $tables[] = (string) $row[0];
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

            if (in_array($table, self::SKIP_DATA, true)) {
                // L'estructura sí; el contingut no diu res i pot portar adreces IP.
                $sql .= "\n";
                continue;
            }
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
    /** @return array{files:int,bytes:int} */
    private static function addUploads(ZipArchive $zip, string $root): array
    {
        $root = rtrim($root, '/');
        $total = ['files' => 0, 'bytes' => 0];
        if (!is_dir($root)) {
            return $total;
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
            $total['files']++;
            $total['bytes'] += (int) $file->getSize();
        }

        return $total;
    }

    /**
     * La fitxa del paquet, en JSON.
     * @param array<int,string> $tables
     * @param array<string,int> $counts
     * @param array{files:int,bytes:int} $uploads
     */
    private static function manifest(
        string $title,
        array $tables,
        array $counts,
        string $sql,
        array $uploads,
        array $options
    ): string {
        return (string) json_encode([
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'app_version' => app_version(),
            'exported_at' => date('c'),
            'site_name' => $title,
            'base_url' => (string) ($options['base_url'] ?? base_url()),
            // Es llegeix de la base de dades i no de la configuració a memòria:
            // qui fa el paquet pot ser la plataforma, que té la seva.
            'event_date' => (string) Db::val("SELECT v FROM settings WHERE k = 'event_date'", [], ''),
            'tables' => self::allTables(),
            'sheets' => $tables,
            'rows' => $counts,
            'uploads' => $uploads,
            'database' => [
                'file' => 'base-de-dades.sql',
                'bytes' => strlen($sql),
                'sha256' => hash('sha256', $sql),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            . "  fitxers/         Les imatges i els documents que heu pujat.\n"
            . "  migracio.json    La fitxa del paquet. Serveix per traslladar aquest cros\n"
            . "                   a un altre servidor sense perdre res: qui el rebi només\n"
            . "                   ha de pujar aquest mateix fitxer ZIP.\n\n"
            . "Taules incloses: " . implode(', ', $tables) . "\n\n"
            . "Aquestes dades són vostres. Si les compartiu, tingueu present que hi ha\n"
            . "dades personals de les famílies inscrites i, a la llista d'usuaris, les\n"
            . "contrasenyes xifrades de qui gestiona el web.\n";
    }
}
