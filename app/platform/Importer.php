<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Exporter;
use Cros\Core\Migrator;
use Cros\Core\Settings;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Porta un cros que ja existia cap a una instància nova.
 *
 * El paquet és el mateix que el client es descarrega del seu panell: hi ha la
 * còpia de la base de dades, els fitxers pujats i una fitxa que diu què és.
 * Aquí es buida a la base de dades acabada de fer i es posen els fitxers a la
 * seva carpeta, sense tocar res de l'original: el web vell continua com estava
 * fins que es decideixi apagar-lo.
 */
class Importer
{
    /** Mida màxima del paquet, si no es diu res (en bytes). */
    public const MAX_BYTES = 512 * 1024 * 1024;

    /**
     * Mira un paquet i en torna la fitxa, sense tocar res.
     *
     * @return array<string,mixed>
     */
    public static function inspect(string $file): array
    {
        if (!is_file($file)) {
            throw new RuntimeException('No hi ha cap fitxer per importar.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Aquest servidor no té l\'extensió zip de PHP.');
        }
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('El fitxer no s\'obre: ha de ser el ZIP que dona el panell del cros.');
        }
        try {
            $raw = $zip->getFromName('migracio.json');
            if ($raw === false) {
                throw new RuntimeException(
                    'Aquest ZIP no porta la fitxa «migracio.json». Ha de ser el que dona '
                    . '«Les meves dades» del panell del cros, amb la versió 1.25 o posterior.'
                );
            }
            $manifest = json_decode((string) $raw, true);
            if (!is_array($manifest) || ($manifest['format'] ?? '') !== Exporter::FORMAT) {
                throw new RuntimeException('La fitxa del paquet no és la d\'un cros escolar.');
            }
            if ((int) ($manifest['format_version'] ?? 0) > Exporter::FORMAT_VERSION) {
                throw new RuntimeException(
                    'Aquest paquet és d\'una versió més nova del sistema. Actualitzeu la plataforma abans d\'importar-lo.'
                );
            }
            $sql = $zip->getFromName('base-de-dades.sql');
            if ($sql === false) {
                throw new RuntimeException('El paquet no porta la còpia de la base de dades.');
            }
            $expected = (string) ($manifest['database']['sha256'] ?? '');
            if ($expected !== '' && !hash_equals($expected, hash('sha256', (string) $sql))) {
                throw new RuntimeException('La còpia de la base de dades no quadra amb la fitxa: el fitxer s\'ha fet malbé.');
            }
            $manifest['files'] = $zip->numFiles;
        } finally {
            $zip->close();
        }

        return $manifest;
    }

    /**
     * Buida el paquet a la base de dades i a la carpeta d'una instància nova.
     *
     * @param array<string,mixed> $db      configuració de la base de dades de destí
     * @return array{tables:int,rows:int,files:int}
     */
    public static function into(string $file, array $db, string $uploadsDir): array
    {
        $manifest = self::inspect($file);
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('El fitxer no s\'obre.');
        }

        $previous = Db::connection();
        $report = ['tables' => 0, 'rows' => 0, 'files' => 0];
        try {
            $pdo = Db::connect($db + ['charset' => 'utf8mb4', 'timeout' => 60]);
            Db::setConnection($pdo);
            // El que s'hi escriu és la configuració d'un cros, no la de la plataforma.
            Settings::useSchema(null);
            $report['tables'] = self::loadSql($pdo, (string) $zip->getFromName('base-de-dades.sql'));
            $report['rows'] = (int) array_sum((array) ($manifest['rows'] ?? []));
            $report['files'] = self::extractUploads($zip, $uploadsDir);

            // El cros vell pot venir d'una versió anterior: se li apliquen els
            // canvis que li falten abans de deixar-lo en marxa.
            Migrator::run();
        } finally {
            $zip->close();
            Db::setConnection($previous);
            Settings::forget();
        }

        return $report;
    }

    /** Executa la còpia de la base de dades. */
    private static function loadSql(PDO $pdo, string $sql): int
    {
        if (trim($sql) === '') {
            throw new RuntimeException('La còpia de la base de dades és buida.');
        }
        $tables = 0;
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::statements($sql) as $statement) {
                if (stripos(ltrim($statement), 'CREATE TABLE') === 0) {
                    $tables++;
                }
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $tables;
    }

    /**
     * Parteix la còpia en ordres.
     *
     * Es llegeix caràcter a caràcter perquè dins d'un text hi pot haver de tot
     * —un punt i coma a final de línia, una ratlla que comenci amb dos guions,
     * cometes escapades—, i partir pel punt i coma i prou faria malbé les dades
     * de la gent sense dir-ho.
     *
     * @return \Generator<int,string>
     */
    public static function statements(string $sql): \Generator
    {
        $length = strlen($sql);
        $buffer = '';
        $quote = '';
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== '') {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    // Dues cometes seguides són una cometa dins del text.
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $buffer .= $quote;
                        $i++;
                        continue;
                    }
                    $quote = '';
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            // Comentaris: només compten a principi de línia, que és on els posem.
            $lineStart = $buffer === '' || str_ends_with($buffer, "\n");
            if ($lineStart && ($char === '#' || ($char === '-' && ($sql[$i + 1] ?? '') === '-'))) {
                $end = strpos($sql, "\n", $i);
                if ($end === false) {
                    break;
                }
                $i = $end;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    yield trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
    }

    /** Treu els fitxers pujats del paquet i els deixa a la carpeta de la instància. */
    private static function extractUploads(ZipArchive $zip, string $dir): int
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No s\'ha pogut crear la carpeta dels fitxers.');
        }
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!str_starts_with($name, 'fitxers/') || str_ends_with($name, '/')) {
                continue;
            }
            $relative = substr($name, strlen('fitxers/'));
            // Un nom que vulgui sortir de la carpeta no es copia enlloc.
            if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
                continue;
            }
            $target = $dir . '/' . $relative;
            $folder = dirname($target);
            if (!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder)) {
                continue;
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $out = @fopen($target, 'wb');
            if ($out === false) {
                fclose($stream);
                continue;
            }
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
            $count++;
        }

        return $count;
    }

    /**
     * Canvia l'adreça antiga per la nova dins de les dades.
     *
     * Els textos del web poden portar enllaços i imatges amb l'adreça de sempre
     * («https://crosdelagranada.cat/uploads/...»). Es canvia només això, tal
     * qual: ni es toca res més ni s'endevina res.
     *
     * @param array<int,string> $tables
     * @return int quants camps s'han canviat
     */
    public static function rewriteUrls(string $old, string $new, array $tables = []): int
    {
        $old = rtrim(trim($old), '/');
        $new = rtrim(trim($new), '/');
        if ($old === '' || $new === '' || $old === $new) {
            return 0;
        }
        $changed = 0;
        foreach ($tables ?: Exporter::tables() as $table) {
            foreach (self::textColumns($table) as $column) {
                $changed += Db::q(
                    sprintf(
                        'UPDATE `%s` SET `%s` = REPLACE(`%s`, :old, :new) WHERE `%s` LIKE :like',
                        $table,
                        $column,
                        $column,
                        $column
                    ),
                    ['old' => $old, 'new' => $new, 'like' => '%' . $old . '%']
                )->rowCount();
            }
        }

        return $changed;
    }

    /** Columnes d'una taula que poden portar text. @return array<int,string> */
    private static function textColumns(string $table): array
    {
        $columns = [];
        foreach (Db::all('SHOW COLUMNS FROM `' . $table . '`') as $column) {
            $type = strtolower((string) ($column['Type'] ?? ''));
            if (str_contains($type, 'char') || str_contains($type, 'text')) {
                $columns[] = (string) $column['Field'];
            }
        }

        return $columns;
    }
}
