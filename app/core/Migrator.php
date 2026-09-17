<?php
declare(strict_types=1);

namespace Cros\Core;

/** Executa les migracions de base de dades pendents (fitxers app/migrations). */
class Migrator
{
    /** Crea la taula de control de migracions. */
    public static function ensureTable(): void
    {
        $sql = Db::driver() === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(190) NOT NULL UNIQUE, applied_at DATETIME NOT NULL)'
            : 'CREATE TABLE IF NOT EXISTS migrations (
                 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                 name VARCHAR(190) NOT NULL,
                 applied_at DATETIME NOT NULL,
                 PRIMARY KEY (id),
                 UNIQUE KEY uniq_migration (name)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        Db::conn()->exec($sql);
    }

    /** Migracions ja aplicades. */
    public static function applied(): array
    {
        self::ensureTable();
        return array_column(Db::all('SELECT name FROM migrations ORDER BY name'), 'name');
    }

    /** Fitxers de migració disponibles, ordenats. */
    public static function files(): array
    {
        $files = glob(CROS_APP . '/migrations/*.{sql,php}', GLOB_BRACE) ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }

    /** Migracions pendents d'aplicar. */
    public static function pending(): array
    {
        $applied = self::applied();
        return array_values(array_filter(self::files(), fn ($file) => !in_array(basename($file), $applied, true)));
    }

    /**
     * Aplica totes les migracions pendents.
     * @return array<int,string> noms aplicats
     */
    public static function run(): array
    {
        self::ensureTable();
        $done = [];
        foreach (self::pending() as $file) {
            $name = basename($file);
            if (str_ends_with($file, '.php')) {
                $callback = require $file;
                if (is_callable($callback)) {
                    $callback(Db::conn());
                }
            } else {
                foreach (self::statements((string) file_get_contents($file)) as $statement) {
                    Db::conn()->exec($statement);
                }
            }
            Db::insert('migrations', ['name' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
            $done[] = $name;
            log_line('migrate', 'Migració aplicada', ['file' => $name]);
        }
        return $done;
    }

    /** Divideix un fitxer SQL en sentències. */
    public static function statements(string $sql): array
    {
        $clean = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $clean[] = $line;
        }
        $statements = [];
        foreach (explode(";\n", implode("\n", $clean) . "\n") as $statement) {
            $statement = trim(rtrim(trim($statement), ';'));
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }
        return $statements;
    }
}
