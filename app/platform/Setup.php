<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use PDO;
use RuntimeException;

/**
 * La feina de l'assistent d'instal·lació de la plataforma.
 *
 * L'script del servidor (tools/instalar-vps.sh) deixa el sistema a punt i hi
 * escriu què ha fet; a partir d'aquí tot és feina d'aplicació i no cal cap
 * permís especial: comprovar, provar les connexions, escriure la configuració i
 * engegar-ho.
 */
class Setup
{
    /** El que l'script del servidor deixa escrit per a l'assistent. */
    public const HINTS = 'tenants/instalacio.json';

    /** El testimoni d'un sol ús que dona pas a l'assistent. */
    public const TOKEN = 'instal-plataforma.token';

    /** Hi ha instal·lació per fer? */
    public static function pending(?string $root = null): bool
    {
        return !is_file(($root ?? CROS_ROOT) . '/tenants/platform.php');
    }

    /**
     * Què ha deixat dit l'script del servidor.
     * @return array<string,mixed>
     */
    public static function hints(?string $root = null): array
    {
        $file = ($root ?? CROS_ROOT) . '/' . self::HINTS;
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /** El testimoni que hi ha al servidor, o '' si no n'hi ha. */
    public static function token(?string $root = null): string
    {
        return trim((string) @file_get_contents(self::tokenFile($root)));
    }

    public static function tokenFile(?string $root = null): string
    {
        return ($root ?? CROS_ROOT) . '/storage/' . self::TOKEN;
    }

    /**
     * Qui obre l'assistent ha de portar el testimoni.
     * Si no n'hi ha cap al servidor, no s'hi entra: val més quedar-se fora que
     * deixar l'assistent obert a qualsevol que passi.
     */
    public static function allowed(string $given, ?string $root = null): bool
    {
        $token = self::token($root);

        return $token !== '' && hash_equals($token, trim($given));
    }

    /**
     * Comprovacions del servidor.
     * @return array<int,array{0:string,1:bool,2:string,3:bool}>
     */
    public static function requirements(?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $writable = static fn (string $path): bool => is_dir($path) && is_writable($path);

        return [
            ['PHP 8.0 o superior', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION, true],
            ['Extensió PDO MySQL', extension_loaded('pdo_mysql'), 'pdo_mysql', true],
            ['Extensió mbstring', extension_loaded('mbstring'), 'mbstring', true],
            ['Extensió OpenSSL', extension_loaded('openssl'), 'openssl', true],
            ['Extensió ZIP (còpies i migracions)', class_exists('ZipArchive'), 'zip', true],
            ['Extensió cURL (vigilància)', function_exists('curl_init'), 'curl', false],
            ['Extensió GD (imatges)', extension_loaded('gd'), 'gd', false],
            ['Carpeta tenants/ escrivible', $writable($root . '/tenants'), 'tenants/', true],
            ['Carpeta storage/ escrivible', $writable($root . '/storage'), 'storage/', true],
            ['Carpeta uploads/ escrivible', $writable($root . '/uploads'), 'uploads/', true],
        ];
    }

    /** Totes les comprovacions obligatòries, correctes? */
    public static function ready(?string $root = null): bool
    {
        foreach (self::requirements($root) as [$label, $ok, $value, $required]) {
            if ($required && !$ok) {
                return false;
            }
        }

        return true;
    }

    /** Un domini que es pugui servir. */
    public static function validDomain(string $domain): bool
    {
        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain) === 1;
    }

    /**
     * Els dominis d'un text separat per comes o línies, en minúscules i sense repetits.
     * @return array<int,string>
     */
    public static function domains(string $text): array
    {
        $list = preg_split('/[\s,;]+/', mb_strtolower(trim($text))) ?: [];
        $clean = [];
        foreach ($list as $domain) {
            $domain = trim(trim($domain), '.');
            if ($domain !== '' && !in_array($domain, $clean, true)) {
                $clean[] = $domain;
            }
        }

        return $clean;
    }

    /**
     * Prova la connexió a la base de dades de la plataforma.
     * @return array{ok:bool,version:string,error:string}
     */
    public static function testDb(array $db): array
    {
        try {
            $pdo = Db::connect([
                'host' => (string) ($db['host'] ?? 'localhost'),
                'port' => (int) ($db['port'] ?? 3306),
                'name' => (string) ($db['name'] ?? ''),
                'user' => (string) ($db['user'] ?? ''),
                'pass' => (string) ($db['pass'] ?? ''),
                'socket' => (string) ($db['socket'] ?? ''),
                'charset' => 'utf8mb4',
                'timeout' => 8,
            ]);

            return ['ok' => true, 'version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(), 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'version' => '', 'error' => self::friendly($e->getMessage())];
        }
    }

    /**
     * Prova que l'usuari d'altes pugui crear bases de dades de debò.
     * Se'n crea una amb un nom que no és de ningú i s'esborra tot seguit.
     *
     * @return array{ok:bool,error:string}
     */
    public static function testProvision(array $provision): array
    {
        $name = 'cros_prova_' . bin2hex(random_bytes(4));
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=utf8mb4',
                (string) ($provision['db_host'] ?? 'localhost'),
                (int) ($provision['db_port'] ?? 3306)
            );
            $pdo = new PDO($dsn, (string) ($provision['admin_user'] ?? ''), (string) ($provision['admin_pass'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 8,
            ]);
            $pdo->exec('CREATE DATABASE `' . $name . '`');
            $pdo->exec('DROP DATABASE `' . $name . '`');

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::friendly($e->getMessage())];
        }
    }

    /** Errors de base de dades, dits com els entén qui instal·la. */
    private static function friendly(string $message): string
    {
        if (str_contains($message, '1045')) {
            return 'L\'usuari o la contrasenya no són correctes.';
        }
        if (str_contains($message, '1049')) {
            return 'Aquesta base de dades no existeix.';
        }
        if (str_contains($message, '2002') || str_contains($message, 'refused')) {
            return 'El servidor de bases de dades no respon. Proveu amb «127.0.0.1» en comptes de «localhost».';
        }
        if (str_contains($message, '1044') || str_contains($message, 'denied')) {
            return 'Aquest usuari no té prou permisos.';
        }

        return $message;
    }

    /**
     * El fitxer de configuració de la plataforma, tal com quedarà.
     * @param array<string,mixed> $values
     */
    public static function render(array $values): string
    {
        $domains = array_values((array) ($values['domains'] ?? []));
        $config = [
            'name' => (string) ($values['name'] ?? 'Cros Escolar'),
            'base_domain' => (string) array_shift($domains),
            'domains' => $domains,
            'console' => [(string) ($values['console'] ?? 'admin')],
            'reserved' => [],
            'db' => [
                'host' => (string) ($values['db']['host'] ?? 'localhost'),
                'port' => (int) ($values['db']['port'] ?? 3306),
                'name' => (string) ($values['db']['name'] ?? ''),
                'user' => (string) ($values['db']['user'] ?? ''),
                'pass' => (string) ($values['db']['pass'] ?? ''),
                'charset' => 'utf8mb4',
                'socket' => (string) ($values['db']['socket'] ?? ''),
            ],
            'provision' => [
                'db_host' => (string) ($values['provision']['db_host'] ?? 'localhost'),
                'db_port' => (int) ($values['provision']['db_port'] ?? 3306),
                'db_socket' => '',
                'admin_user' => (string) ($values['provision']['admin_user'] ?? ''),
                'admin_pass' => (string) ($values['provision']['admin_pass'] ?? ''),
                'db_prefix' => (string) ($values['provision']['db_prefix'] ?? 'cros_'),
                'tenant_from' => 'localhost',
                'tenant_host' => (string) ($values['provision']['db_host'] ?? 'localhost'),
            ],
            'monitor' => ['web' => true, 'proxy' => ''],
            'backups' => ['dir' => '', 'keep' => 7],
            'mail' => [
                'from_name' => (string) ($values['mail']['from_name'] ?? 'Cros Escolar'),
                'from_email' => (string) ($values['mail']['from_email'] ?? ''),
                'notify' => (string) ($values['mail']['notify'] ?? ''),
                'transport' => (string) ($values['mail']['transport'] ?? 'mail'),
                'smtp_host' => (string) ($values['mail']['smtp_host'] ?? ''),
                'smtp_port' => (int) ($values['mail']['smtp_port'] ?? 587),
                'smtp_user' => (string) ($values['mail']['smtp_user'] ?? ''),
                'smtp_pass' => (string) ($values['mail']['smtp_pass'] ?? ''),
                'smtp_secure' => (string) ($values['mail']['smtp_secure'] ?? 'tls'),
            ],
        ];

        return "<?php\n/**\n * Configuració de la plataforma, escrita per l'assistent el "
            . date('d/m/Y H:i') . ".\n */\nreturn " . var_export($config, true) . ";\n";
    }

    /** Desa la configuració de la plataforma. */
    public static function save(array $values, ?string $root = null): string
    {
        $file = ($root ?? CROS_ROOT) . '/tenants/platform.php';
        if (@file_put_contents($file, self::render($values)) === false) {
            throw new RuntimeException('No s\'ha pogut escriure tenants/platform.php. Reviseu-ne els permisos.');
        }
        @chmod($file, 0640);

        return $file;
    }

    /**
     * S'ha acabat: es treuen el testimoni i les dades que va deixar l'script.
     * A partir d'aquí l'assistent no torna a obrir-se.
     */
    public static function finish(?string $root = null): void
    {
        $root = $root ?? CROS_ROOT;
        @unlink(self::tokenFile($root));
        @unlink($root . '/' . self::HINTS);
    }
}
