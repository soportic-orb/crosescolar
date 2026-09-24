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

    /** La carpeta de la instal·lació que s'ha obert amb boot(). */
    private static ?string $root = null;

    /** Obre la base de dades de la plataforma i la deixa llesta per treballar. */
    public static function boot(?string $root = null): PDO
    {
        self::$root = $root ?? CROS_ROOT;
        $settings = Tenancy::settings(self::$root);
        $db = (array) ($settings['db'] ?? []);
        if (trim((string) ($db['name'] ?? '')) === '') {
            throw new RuntimeException('La plataforma no té base de dades configurada a tenants/platform.php.');
        }
        $pdo = Db::connect($db + ['charset' => 'utf8mb4', 'timeout' => 10]);
        Db::setConnection($pdo);
        // La plataforma té els seus propis camps de configuració.
        Settings::useSchema(require CROS_APP . '/platform/config_schema.php');
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
        // Es torna a posar també l'esquema: si s'ha treballat amb el web d'un
        // client, el que hi havia actiu era el seu.
        Settings::useSchema(require CROS_APP . '/platform/config_schema.php');
        self::primeSettings(Tenancy::settings($root ?? self::$root ?? CROS_ROOT));
    }

    /**
     * Deixa a punt la configuració de la plataforma.
     *
     * Mana el que hi hagi desat a la base de dades, que és el que s'edita des
     * del panell. El que no s'hagi tocat mai surt del fitxer tenants/platform.php,
     * que és el que va escriure qui va instal·lar-ho. Així es pot canviar el
     * correu o els colors sense tocar cap fitxer, i abans de fer-ho tot va com
     * anava.
     *
     * @param array<string,mixed> $settings
     */
    private static function primeSettings(array $settings): void
    {
        // Només s'agafa del fitxer el que no s'hagi desat mai al panell.
        $saved = Settings::load();
        $missing = [];
        foreach (self::fileValues($settings) as $key => $value) {
            if (!isset($saved[$key]) || (string) $saved[$key] === '') {
                $missing[$key] = $value;
            }
        }
        Settings::prime($missing);
    }

    /**
     * La configuració que va escriure qui va instal·lar la plataforma, dita
     * amb els noms dels camps del panell.
     *
     * @param array<string,mixed> $settings
     * @return array<string,string>
     */
    private static function fileValues(array $settings): array
    {
        $mail = (array) ($settings['mail'] ?? []);
        $monitor = (array) ($settings['monitor'] ?? []);
        $backups = (array) ($settings['backups'] ?? []);
        $domain = (string) ($settings['base_domain'] ?? 'crosescolar.cat');
        $file = [
            'site_name' => (string) ($settings['name'] ?? 'Cros Escolar'),
            'mail_from_name' => (string) ($mail['from_name'] ?? ($settings['name'] ?? 'Cros Escolar')),
            'mail_from_email' => (string) ($mail['from_email'] ?? ('no-reply@' . $domain)),
            'mail_admin_notify' => (string) ($mail['notify'] ?? ('hola@' . $domain)),
            'mail_transport' => (string) ($mail['transport'] ?? 'mail'),
        ];
        foreach (['reply_to' => 'mail_reply_to', 'smtp_host' => 'smtp_host', 'smtp_port' => 'smtp_port',
                  'smtp_user' => 'smtp_user', 'smtp_pass' => 'smtp_pass', 'smtp_secure' => 'smtp_secure'] as $key => $setting) {
            if (isset($mail[$key]) && (string) $mail[$key] !== '') {
                $file[$setting] = (string) $mail[$key];
            }
        }
        if (isset($monitor['web'])) {
            $file['platform_monitor_web'] = $monitor['web'] ? '1' : '0';
        }
        if (isset($backups['keep'])) {
            $file['platform_backups_keep'] = (string) $backups['keep'];
        }

        return $file;
    }

    /**
     * Escriu la configuració inicial de la plataforma.
     *
     * Primer el que digui el fitxer de la instal·lació, que és el que va posar
     * qui la va muntar, i després els valors per defecte de la resta de camps.
     * Si es fes al revés, actualitzar el sistema tornaria enrere coses com el
     * servidor de correu.
     */
    private static function seedSettings(array $settings): void
    {
        $existing = [];
        foreach (Db::all('SELECT k FROM settings') as $row) {
            $existing[$row['k']] = true;
        }
        foreach (self::fileValues($settings) as $key => $value) {
            if (!isset($existing[$key]) && (string) $value !== '') {
                Settings::set($key, (string) $value);
            }
        }
        Settings::seedDefaults();
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

    /** Domini principal de la plataforma. */
    public static function domain(?string $root = null): string
    {
        return Tenancy::primary($root ?? CROS_ROOT);
    }

    /**
     * Dominis on es pot penjar un cros, el principal primer.
     * @return array<int,string>
     */
    public static function domains(?string $root = null): array
    {
        return Tenancy::domains($root ?? CROS_ROOT);
    }

    /** Un domini que sigui nostre; si no ho és, el principal. */
    public static function validDomain(string $domain, ?string $root = null): string
    {
        $domain = strtolower(trim(trim($domain), '.'));
        $domains = self::domains($root);

        return in_array($domain, $domains, true) ? $domain : ($domains[0] ?? '');
    }

    /** Adreça pública d'una instància, al domini que hagi triat. */
    public static function url(string $slug, ?string $root = null, string $domain = ''): string
    {
        return 'https://' . $slug . '.' . self::validDomain($domain, $root);
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
        if ($done) {
            try {
                self::seedSettings(Tenancy::settings(self::$root ?? CROS_ROOT));
            } catch (\Throwable $e) {
                log_line('platform', 'No s\'han pogut escriure els valors per defecte', ['error' => $e->getMessage()]);
            }
        }

        return $done;
    }

    /**
     * Migracions de la plataforma que encara no s'han aplicat.
     * @return array<int,string>
     */
    public static function pending(): array
    {
        try {
            $applied = array_column(Db::all('SELECT name FROM ' . self::MIGRATIONS), 'name');
        } catch (\Throwable $e) {
            $applied = [];
        }

        return array_values(array_filter(
            self::files(),
            static fn (string $file): bool => !in_array(basename($file), $applied, true)
        ));
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
