<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Migrator;
use Cros\Core\Tenancy;
use PDO;
use RuntimeException;

/**
 * La plataforma: la base de dades on hi ha els clients, les seves instàncies i
 * les sol·licituds de gent que en vol una.
 *
 * Les dades de cada cros són a la seva pròpia base de dades i aquí no n'hi ha
 * cap: això només sap quins webs existeixen i de qui són.
 */
class Platform
{
    /** Taula de control de les migracions de la plataforma. */
    private const MIGRATIONS = 'platform_migrations';

    /** Obre la base de dades de la plataforma i la deixa llesta per treballar. */
    public static function boot(?string $root = null): PDO
    {
        $settings = Tenancy::settings($root ?? CROS_ROOT);
        $db = (array) ($settings['db'] ?? []);
        if (trim((string) ($db['name'] ?? '')) === '') {
            throw new RuntimeException('La plataforma no té base de dades configurada a tenants/platform.php.');
        }
        $pdo = Db::connect($db + ['charset' => 'utf8mb4', 'timeout' => 10]);
        Db::setConnection($pdo);

        return $pdo;
    }

    /** Hi ha plataforma configurada en aquest servidor? */
    public static function configured(?string $root = null): bool
    {
        $settings = Tenancy::settings($root ?? CROS_ROOT);

        return trim((string) ($settings['base_domain'] ?? '')) !== '';
    }

    /** Domini on pengen les instàncies. */
    public static function domain(?string $root = null): string
    {
        return (string) (Tenancy::settings($root ?? CROS_ROOT)['base_domain'] ?? '');
    }

    /** Adreça pública d'una instància. */
    public static function url(string $slug, ?string $root = null): string
    {
        return 'https://' . $slug . '.' . self::domain($root);
    }

    /**
     * Posa al dia l'esquema de la plataforma.
     * Funciona igual que el de les instàncies, però amb els seus fitxers i la
     * seva taula de control, perquè els dos no es barregin mai.
     *
     * @return array<int,string> migracions aplicades
     */
    public static function migrate(): array
    {
        Db::conn()->exec('CREATE TABLE IF NOT EXISTS ' . self::MIGRATIONS . ' (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_platform_migration (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $applied = array_column(Db::all('SELECT name FROM ' . self::MIGRATIONS . ' ORDER BY name'), 'name');
        $done = [];
        foreach (self::files() as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            foreach (Migrator::statements((string) file_get_contents($file)) as $statement) {
                Db::conn()->exec($statement);
            }
            Db::insert(self::MIGRATIONS, ['name' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
            $done[] = $name;
            log_line('platform', 'Migració de la plataforma aplicada', ['file' => $name]);
        }

        return $done;
    }

    /** @return array<int,string> */
    public static function files(): array
    {
        $files = glob(CROS_APP . '/platform/migrations/*.sql') ?: [];
        sort($files, SORT_NATURAL);

        return $files;
    }

    /** Apunta qui ha fet què, per poder mirar-ho després. */
    public static function log(string $action, string $subject = '', ?int $subjectId = null, array $context = [], ?int $userId = null): void
    {
        try {
            Db::insert('platform_activity', [
                'user_id' => $userId,
                'action' => mb_substr($action, 0, 60),
                'subject' => $subject !== '' ? mb_substr($subject, 0, 40) : null,
                'subject_id' => $subjectId,
                'context' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                'ip' => client_ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut apuntar l\'activitat', ['error' => $e->getMessage()]);
        }
    }
}
