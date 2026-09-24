<?php
declare(strict_types=1);

namespace Cros\Core;

use PDO;
use RuntimeException;

/**
 * Posa en marxa una instal·lació del cros: escriu la configuració, crea les
 * taules, el compte d'administració i els continguts inicials.
 *
 * El formulari d'install.php només és una manera de cridar-la; el panell de la
 * plataforma en crida una altra, sense formulari, per donar d'alta la instància
 * d'un client. Per això la classe no sap res de peticions ni de pantalles.
 *
 * Totes les fases es poden repetir sense fer cap destrossa.
 */
class Installer
{
    /** Les fases, en ordre, amb el que es veu mentre s'executen. */
    public const PHASES = [
        'config'   => 'Escrivint la configuració',
        'schema'   => 'Creant les taules de la base de dades',
        'admin'    => 'Creant el compte d\'administració',
        'settings' => 'Aplicant la configuració inicial',
        'demo'     => 'Creant els continguts d\'exemple',
        'finish'   => 'Finalitzant la instal·lació',
    ];

    /** @var array<string,mixed> dades de la instal·lació */
    private array $data;

    /** On va el fitxer de configuració d'aquesta instal·lació. */
    private string $configFile;

    /** Carpeta de treball d'aquesta instal·lació (testimoni i bloqueig). */
    private string $storageDir;

    private string $token = '';

    /**
     * @param array<string,mixed> $data  db_host, db_port, db_name, db_user, db_pass,
     *                                   db_socket, base_url, site_name, event_date,
     *                                   admin_name, admin_email, admin_pass, demo
     * @param array<string,mixed> $options  config_file, storage_dir, timezone, debug
     */
    public function __construct(array $data, array $options = [])
    {
        $this->data = $data;
        $this->configFile = (string) ($options['config_file'] ?? CROS_APP . '/config.php');
        $this->storageDir = rtrim((string) ($options['storage_dir'] ?? CROS_STORAGE), '/');
        $this->data['timezone'] = (string) ($options['timezone'] ?? 'Europe/Madrid');
        $this->data['debug'] = (bool) ($options['debug'] ?? false);
    }

    /**
     * Fa la instal·lació sencera i torna el missatge de cada fase.
     *
     * Deixa la connexió i la configuració tal com les ha trobat: qui la crida
     * pot estar treballant amb una altra base de dades (el panell de la
     * plataforma, per exemple) i no s'ha de quedar apuntant a la nova.
     *
     * @return array<string,string>
     */
    public function run(): array
    {
        $previous = Db::connection();
        $schema = Settings::currentSchema();
        try {
            $messages = [];
            foreach (array_keys(self::PHASES) as $phase) {
                $messages[$phase] = $this->phase($phase);
            }
            return $messages;
        } finally {
            Db::setConnection($previous);
            Settings::useSchema($schema);
            Settings::forget();
        }
    }

    /** Executa una fase i torna què hi ha passat. Llança una excepció si va malament. */
    public function phase(string $phase): string
    {
        switch ($phase) {
            case 'config':
                return $this->writeConfig();
            case 'schema':
                return $this->createSchema();
            case 'admin':
                return $this->createAdmin();
            case 'settings':
                return $this->applySettings();
            case 'demo':
                return $this->seedDemo();
            case 'finish':
                return $this->finish();
        }

        throw new RuntimeException('Fase desconeguda: ' . $phase);
    }

    /** Testimoni d'un sol ús escrit a la fase de configuració. */
    public function token(): string
    {
        return $this->token;
    }

    /** Connexió amb la base de dades d'aquesta instal·lació. */
    public function connect(): PDO
    {
        return Db::connect($this->dbConfig() + ['timeout' => 10]);
    }

    /** Text del fitxer de configuració, perquè també es pugui escriure des de fora. */
    public function config(): string
    {
        return "<?php\n/**\n * Configuració generada per l'instal·lador el " . date('d/m/Y H:i') . ".\n */\nreturn "
            . var_export([
                'db' => $this->dbConfig(),
                'app_key' => Crypto::generateKey(),
                'base_url' => rtrim((string) $this->data['base_url'], '/'),
                'debug' => (bool) $this->data['debug'],
                'timezone' => (string) $this->data['timezone'],
            ], true) . ";\n";
    }

    /** @return array<string,mixed> */
    private function dbConfig(): array
    {
        return [
            'host' => trim((string) ($this->data['db_host'] ?? 'localhost')),
            'port' => (int) ($this->data['db_port'] ?? 3306),
            'name' => trim((string) ($this->data['db_name'] ?? '')),
            'user' => trim((string) ($this->data['db_user'] ?? '')),
            'pass' => (string) ($this->data['db_pass'] ?? ''),
            'charset' => 'utf8mb4',
            'socket' => trim((string) ($this->data['db_socket'] ?? '')),
        ];
    }

    private function writeConfig(): string
    {
        Db::setConnection($this->connect());

        $directory = dirname($this->configFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        if (@file_put_contents($this->configFile, $this->config()) === false) {
            throw new RuntimeException('No s\'ha pogut escriure ' . $this->relative($this->configFile)
                . '. Doneu permisos d\'escriptura a la carpeta ' . $this->relative($directory) . '.');
        }
        @chmod($this->configFile, 0640);

        $this->token = bin2hex(random_bytes(16));
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
        if (@file_put_contents($this->storageDir . '/install.token', $this->token) === false) {
            throw new RuntimeException('No s\'ha pogut escriure a la carpeta ' . $this->relative($this->storageDir)
                . '. Doneu-hi permisos d\'escriptura.');
        }

        return 'Configuració desada i connexió amb «' . $this->data['db_name'] . '» comprovada.';
    }

    private function createSchema(): string
    {
        Db::setConnection($this->connect());
        $applied = Migrator::run();
        $tables = count(Db::all('SHOW TABLES'));

        return ($applied ? count($applied) . ' migracions aplicades' : 'Les taules ja existien')
            . ' · ' . $tables . ' taules a la base de dades.';
    }

    private function createAdmin(): string
    {
        Db::setConnection($this->connect());
        $email = mb_strtolower(trim((string) $this->data['admin_email']));
        $existing = Db::one('SELECT id FROM users WHERE email = :email', ['email' => $email]);
        $fields = [
            'name' => trim((string) $this->data['admin_name']),
            'email' => $email,
            'password_hash' => password_hash((string) $this->data['admin_pass'], PASSWORD_DEFAULT),
            'role' => 'admin',
            'active' => 1,
        ];
        if ($existing) {
            Db::update('users', $fields, 'id = :id', ['id' => $existing['id']]);

            return 'Compte d\'administració actualitzat (' . $email . ').';
        }
        Db::insert('users', $fields + ['created_at' => date('Y-m-d H:i:s')]);

        return 'Compte d\'administració creat (' . $email . ').';
    }

    private function applySettings(): string
    {
        Db::setConnection($this->connect());
        // La configuració que s'hi escriu és la d'un cros, sempre: qui crida
        // l'instal·lador pot ser la plataforma, que té la seva de ben diferent.
        Settings::useSchema(require CROS_APP . '/settings_schema.php');
        Settings::seedDefaults();
        Settings::set('site_name', (string) $this->data['site_name']);
        Settings::set('hero_title', (string) $this->data['site_name']);
        Settings::set('event_date', (string) ($this->data['event_date'] ?? ''));

        $email = mb_strtolower(trim((string) $this->data['admin_email']));
        Settings::set('contact_email', $email);
        Settings::set('mail_admin_notify', $email);
        $host = parse_url((string) $this->data['base_url'], PHP_URL_HOST) ?: 'localhost';
        Settings::set('mail_from_email', 'no-reply@' . preg_replace('/^www\./', '', (string) $host));

        return count(Settings::defaults()) . ' opcions de configuració inicialitzades.';
    }

    private function seedDemo(): string
    {
        if ((string) ($this->data['demo'] ?? '') !== '1') {
            return 'Continguts d\'exemple omesos.';
        }
        Db::setConnection($this->connect());
        Seeder::run();

        return sprintf(
            '%d recorreguts, %d categories, %d actes del programa i %d tipus de tiquet creats.',
            (int) Db::val('SELECT COUNT(*) FROM courses', [], 0),
            (int) Db::val('SELECT COUNT(*) FROM categories', [], 0),
            (int) Db::val('SELECT COUNT(*) FROM schedule_items', [], 0),
            (int) Db::val('SELECT COUNT(*) FROM ticket_types', [], 0)
        );
    }

    private function finish(): string
    {
        $lock = $this->storageDir . '/installed.lock';
        if (@file_put_contents($lock, json_encode([
            'installed_at' => date('c'),
            'version' => app_version(),
        ], JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException('No s\'ha pogut crear ' . $this->relative($lock) . '. Reviseu-ne els permisos.');
        }
        @unlink($this->storageDir . '/install.token');

        return 'Instal·lació completada.';
    }

    /** Ruta curta per als missatges d'error, sense ensenyar tot el camí del servidor. */
    private function relative(string $path): string
    {
        return str_starts_with($path, CROS_ROOT . '/') ? substr($path, strlen(CROS_ROOT) + 1) : $path;
    }
}
