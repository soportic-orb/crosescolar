<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Migrator;
use Cros\Core\Settings;
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
        self::primeSettings($settings);

        return $pdo;
    }

    /**
     * Torna a deixar a memòria la configuració de la plataforma.
     * Cal després d'haver treballat amb la base de dades d'una instància:
     * en tancar-la es buida la memòria de configuració i, si no es refà, el
     * correu de la plataforma sortiria amb els valors per defecte.
     */
    public static function prime(?string $root = null): void
    {
        self::primeSettings(Tenancy::settings($root ?? CROS_ROOT));
    }

    /**
     * La plataforma no té taula de configuració, però el correu i les vistes
     * demanen valors com el nom de qui envia: es posen a memòria des del
     * fitxer de la plataforma.
     *
     * @param array<string,mixed> $settings
     */
    private static function primeSettings(array $settings): void
    {
        $mail = (array) ($settings['mail'] ?? []);
        $domain = (string) ($settings['base_domain'] ?? 'crosescolar.com');
        $values = [
            'site_name' => (string) ($settings['name'] ?? 'Cros Escolar'),
            'mail_from_name' => (string) ($mail['from_name'] ?? ($settings['name'] ?? 'Cros Escolar')),
            'mail_from_email' => (string) ($mail['from_email'] ?? ('no-reply@' . $domain)),
            'mail_admin_notify' => (string) ($mail['notify'] ?? ('hola@' . $domain)),
            'mail_transport' => (string) ($mail['transport'] ?? 'mail'),
        ];
        foreach (['reply_to' => 'mail_reply_to', 'smtp_host' => 'smtp_host', 'smtp_port' => 'smtp_port',
                  'smtp_user' => 'smtp_user', 'smtp_pass' => 'smtp_pass', 'smtp_secure' => 'smtp_secure'] as $key => $setting) {
            if (isset($mail[$key]) && (string) $mail[$key] !== '') {
                $values[$setting] = (string) $mail[$key];
            }
        }
        Settings::prime($values);
    }

    /** On arriben els avisos de la plataforma. */
    public static function notifyEmail(?string $root = null): string
    {
        $mail = (array) (Tenancy::settings($root ?? CROS_ROOT)['mail'] ?? []);

        return (string) ($mail['notify'] ?? ('hola@' . self::domain($root)));
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
