<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Installer;
use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Core\Tenancy;
use PDO;
use RuntimeException;

/**
 * Dona d'alta la instància d'un client: li crea la base de dades, les carpetes,
 * hi instal·la el cros i n'avisa qui l'ha de gestionar.
 *
 * Si alguna cosa falla pel camí no es queda res a mitges: es desfà el que s'ha
 * fet i es torna l'error, de manera que es pugui tornar a provar.
 */
class Provisioner
{
    /** Passos, en ordre, amb el que es veu al panell. */
    public const STEPS = [
        'database' => 'Creant la base de dades',
        'folders' => 'Preparant les carpetes',
        'install' => 'Instal·lant el cros',
        'record' => 'Apuntant la instància',
        'welcome' => 'Enviant les claus',
    ];

    /**
     * Crea la instància sencera.
     *
     * @param array{slug:string,site_name:string,town?:string,language?:string,client_id?:int,
     *              admin_name:string,admin_email:string,event_date?:string,demo?:bool,listed?:bool} $data
     * @return array{instance_id:int,link:string,steps:array<string,string>}
     */
    public static function create(array $data, ?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $slug = mb_strtolower(trim((string) $data['slug']));
        $domain = Platform::validDomain((string) ($data['domain'] ?? ''), $root);
        $problem = Instance::slugProblem($slug, null, $root);
        if ($problem !== '') {
            throw new RuntimeException($problem);
        }

        $settings = Tenancy::settings($root);
        $provision = (array) ($settings['provision'] ?? []);
        $names = self::names($slug, $provision);
        $dir = Tenancy::dir($root, $slug);
        // Cal posar-li una contrasenya a l'administrador, però no la sabrà
        // ningú: s'entra amb l'enllaç d'un sol ús i se'n tria una de pròpia.
        $password = self::password(24);
        $dbPassword = self::password(24);
        $steps = [];
        $link = '';
        $created = ['database' => false, 'folders' => false, 'instance' => 0];

        try {
            $admin = self::adminConnection($provision);
            self::createDatabase($admin, $names, $dbPassword, $provision);
            $created['database'] = true;
            $steps['database'] = 'Base de dades «' . $names['db'] . '» creada.';

            self::createFolders($dir);
            $created['folders'] = true;
            $steps['folders'] = 'Carpetes preparades a tenants/' . $slug . '/.';

            $installer = new Installer([
                'db_host' => (string) ($provision['tenant_host'] ?? $provision['db_host'] ?? 'localhost'),
                'db_port' => (int) ($provision['db_port'] ?? 3306),
                'db_name' => $names['db'],
                'db_user' => $names['user'],
                'db_pass' => $dbPassword,
                'db_socket' => (string) ($provision['db_socket'] ?? ''),
                'base_url' => Platform::url($slug, $root, $domain),
                'site_name' => (string) $data['site_name'],
                'event_date' => (string) ($data['event_date'] ?? ''),
                'admin_name' => (string) $data['admin_name'],
                'admin_email' => (string) $data['admin_email'],
                'admin_pass' => $password,
                'demo' => !empty($data['demo']) ? '1' : '',
            ], [
                'config_file' => $dir . '/config.php',
                'storage_dir' => $dir . '/storage',
            ]);
            $installer->run();
            $steps['install'] = 'Cros instal·lat i llest.';

            // El web neix amagat: el publica el client quan ho tingui a punt.
            self::hide($dir, (string) ($data['language'] ?? 'ca'));
            // Treballar amb la base de dades del client ha buidat la configuració
            // que hi havia a memòria: es torna a posar la de la plataforma.
            Platform::prime($root);

            $created['instance'] = Instance::create([
                'client_id' => $data['client_id'] ?? null,
                'slug' => $slug,
                'domain' => $domain,
                'site_name' => (string) $data['site_name'],
                'town' => (string) ($data['town'] ?? ''),
                'language' => (string) ($data['language'] ?? 'ca'),
                'db_name' => $names['db'],
                'db_user' => $names['user'],
                'admin_email' => (string) $data['admin_email'],
                'event_date' => (string) ($data['event_date'] ?? ''),
                'listed' => $data['listed'] ?? true,
            ]);
            $steps['record'] = 'Instància apuntada a la plataforma.';

            // Ningú no ha de saber la contrasenya que s'ha posat aquí: qui
            // gestionarà el cros entra amb un enllaç i se'n posa una de seva.
            $access = Instance::accessLink($created['instance'], 'welcome', $root, 'Alta de la instància');
            $link = $access['ok'] ? $access['url'] : '';
            self::welcome($data, $slug, $link, $root, $domain);
            $steps['welcome'] = $link !== ''
                ? 'Enllaç d\'accés enviat a ' . $data['admin_email'] . '.'
                : 'No s\'ha pogut crear l\'enllaç d\'accés: ' . $access['error'];

            Platform::log('instance_create', 'instance', $created['instance'], ['slug' => $slug]);
        } catch (\Throwable $e) {
            self::rollback($slug, $names, $dir, $created, $provision);
            log_line('platform', 'Alta d\'instància fallida', ['slug' => $slug, 'error' => $e->getMessage()]);

            throw new RuntimeException('No s\'ha pogut crear la instància: ' . $e->getMessage(), 0, $e);
        }

        return ['instance_id' => $created['instance'], 'link' => $link, 'steps' => $steps];
    }

    /**
     * Esborra de debò una instància donada de baixa: la base de dades, el seu
     * usuari i la carpeta. La fitxa es queda, marcada com a esborrada, perquè
     * consti que va existir.
     */
    public static function purge(int $id, ?string $root = null): bool
    {
        $root = $root ?? CROS_ROOT;
        $instance = Instance::find($id);
        if (!$instance || (string) $instance['status'] !== 'cancelled') {
            return false;
        }
        $provision = (array) (Tenancy::settings($root)['provision'] ?? []);
        $dir = Tenancy::dir($root, (string) $instance['slug']);

        try {
            $admin = self::adminConnection($provision);
            $admin->exec('DROP DATABASE IF EXISTS `' . preg_replace('/[^a-z0-9_]/i', '', (string) $instance['db_name']) . '`');
            if (!empty($instance['db_user'])) {
                $drop = $admin->prepare('DROP USER IF EXISTS ?@?');
                $drop->execute([(string) $instance['db_user'], (string) ($provision['tenant_from'] ?? 'localhost')]);
            }
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut esborrar la base de dades', ['slug' => $instance['slug'], 'error' => $e->getMessage()]);

            return false;
        }
        if ($dir !== '' && is_dir($dir)) {
            self::removeTree($dir);
        }
        Instance::update($id, ['status' => 'purged', 'purge_at' => null]);
        Platform::log('instance_purge', 'instance', $id, ['slug' => $instance['slug']]);

        return true;
    }

    /** Noms de la base de dades i de l'usuari d'una instància. */
    public static function names(string $slug, array $provision = []): array
    {
        $prefix = (string) ($provision['db_prefix'] ?? 'cros_');
        $clean = preg_replace('/[^a-z0-9]/', '', $slug) ?: 'cros';
        // Els noms d'usuari de MySQL són curts: val més escurçar-los aquí que
        // no pas que ho faci el servidor i acabin xocant dos clients.
        $user = mb_substr($prefix . $clean, 0, 30);

        return ['db' => $prefix . $clean, 'user' => $user];
    }

    /** Connexió amb permisos per crear bases de dades. */
    private static function adminConnection(array $provision): PDO
    {
        $user = (string) ($provision['admin_user'] ?? '');
        if ($user === '') {
            throw new RuntimeException('Falta l\'usuari que pot crear bases de dades a tenants/platform.php.');
        }
        $host = (string) ($provision['db_host'] ?? 'localhost');
        $port = (int) ($provision['db_port'] ?? 3306);
        $socket = (string) ($provision['db_socket'] ?? '');
        $dsn = $socket !== ''
            ? 'mysql:unix_socket=' . $socket . ';charset=utf8mb4'
            : sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);

        return new PDO($dsn, $user, (string) ($provision['admin_pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /** Crea la base de dades i el seu usuari, que només hi pot entrar a ella. */
    private static function createDatabase(PDO $admin, array $names, string $password, array $provision): void
    {
        $from = (string) ($provision['tenant_from'] ?? 'localhost');
        $admin->exec('CREATE DATABASE IF NOT EXISTS `' . $names['db'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $create = $admin->prepare('CREATE USER IF NOT EXISTS ?@? IDENTIFIED BY ?');
        $create->execute([$names['user'], $from, $password]);
        $set = $admin->prepare('ALTER USER ?@? IDENTIFIED BY ?');
        $set->execute([$names['user'], $from, $password]);
        // El GRANT no admet paràmetres: els noms ja són nostres i només porten
        // lletres, números i guions baixos, però es tornen a filtrar per si de cas.
        $db = preg_replace('/[^a-z0-9_]/i', '', $names['db']);
        $user = preg_replace('/[^a-z0-9_]/i', '', $names['user']);
        $host = preg_replace('/[^a-z0-9_.%-]/i', '', $from);
        $admin->exec("GRANT ALL PRIVILEGES ON `$db`.* TO '$user'@'$host'");
        $admin->exec('FLUSH PRIVILEGES');
    }

    /** Les carpetes de la instància. */
    private static function createFolders(string $dir): void
    {
        foreach ([$dir, $dir . '/uploads', $dir . '/storage', $dir . '/storage/logs'] as $folder) {
            if (!is_dir($folder) && !@mkdir($folder, 0775, true) && !is_dir($folder)) {
                throw new RuntimeException('No s\'ha pogut crear la carpeta ' . basename($folder) . '.');
            }
        }
    }

    /** El web neix en mode «en preparació» i amb l'idioma que s'hagi triat. */
    private static function hide(string $dir, string $language): void
    {
        $platform = Db::connection();
        try {
            $config = require $dir . '/config.php';
            Db::setConnection(Db::connect((array) $config['db'] + ['charset' => 'utf8mb4', 'timeout' => 10]));
            Settings::set('coming_soon', '1');
            Settings::set('site_language', in_array($language, ['ca', 'es'], true) ? $language : 'ca');
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut amagar el web nou', ['error' => $e->getMessage()]);
        } finally {
            Db::setConnection($platform);
            Settings::forget();
        }
    }

    /** Avisa qui gestionarà el cros, amb l'adreça i l'enllaç per entrar-hi. */
    private static function welcome(array $data, string $slug, string $link, string $root, string $domain = ''): void
    {
        $email = (string) $data['admin_email'];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Mailer::sendTemplate($email, 'Ja teniu el web del vostre cros', 'instance-welcome', [
            'name' => (string) $data['admin_name'],
            'site_name' => (string) $data['site_name'],
            'url' => Platform::url($slug, $root, $domain),
            'email' => $email,
            'link' => $link,
        ]);
    }

    /** Desfà el que s'hagi fet si l'alta s'ha quedat a mitges. */
    private static function rollback(string $slug, array $names, string $dir, array $created, array $provision): void
    {
        if ($created['instance'] > 0) {
            try {
                Db::delete('instances', 'id = :id', ['id' => $created['instance']]);
            } catch (\Throwable $e) {
                // ja se'n parlarà al registre
            }
        }
        if ($created['folders'] && $dir !== '' && is_dir($dir)) {
            self::removeTree($dir);
        }
        if ($created['database']) {
            try {
                $admin = self::adminConnection($provision);
                $admin->exec('DROP DATABASE IF EXISTS `' . $names['db'] . '`');
                $drop = $admin->prepare('DROP USER IF EXISTS ?@?');
                $drop->execute([$names['user'], (string) ($provision['tenant_from'] ?? 'localhost')]);
            } catch (\Throwable $e) {
                log_line('platform', 'No s\'ha pogut desfer la base de dades', ['slug' => $slug, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Esborra una carpeta i tot el que hi ha a dins. */
    public static function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /** Contrasenya llarga i a l'atzar, sense caràcters que es confonguin. */
    public static function password(int $length = 14): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
