<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\LoginLink;
use Cros\Core\Migrator;
use Cros\Core\Settings;
use Cros\Core\Tenancy;

/** Les instàncies: el web de cada client, amb el seu subdomini. */
class Instance
{
    /** Estats pels quals passa una instància. */
    public const STATUSES = [
        'new' => 'Sense estrenar',
        'active' => 'Activa',
        'suspended' => 'Aturada',
        'cancelled' => 'Donada de baixa',
        'purged' => 'Esborrada',
    ];

    /** Dies que es guarden les dades d'una instància donada de baixa. */
    public const PURGE_DAYS = 90;

    /** Color amb què es pinta cada estat al panell. */
    public static function tone(string $status): string
    {
        return [
            'new' => 'blue',
            'active' => 'green',
            'suspended' => 'amber',
            'cancelled' => 'red',
            'purged' => 'red',
        ][$status] ?? '';
    }

    /** Nom de l'estat tal com es llegeix. */
    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM instances WHERE id = :id', ['id' => $id]);
    }

    public static function bySlug(string $slug): ?array
    {
        return Db::one('SELECT * FROM instances WHERE slug = :slug', ['slug' => mb_strtolower(trim($slug))]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $status = ''): array
    {
        $sql = 'SELECT i.*, c.name AS client_name FROM instances i
                LEFT JOIN clients c ON c.id = i.client_id';
        $params = [];
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $sql .= ' WHERE i.status = :status';
            $params['status'] = $status;
        }

        return Db::all($sql . ' ORDER BY i.slug ASC', $params);
    }

    /**
     * El que surt a la pàgina pública: les instàncies que ja han publicat el
     * seu web i volen sortir al llistat, primer les que tenen la cursa a prop.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function directory(): array
    {
        return Db::all(
            "SELECT slug, site_name, town, event_date, language, registrations
             FROM instances
             WHERE status = 'active' AND published = 1 AND listed = 1
             ORDER BY (event_date IS NULL OR event_date < :today), event_date ASC, site_name ASC",
            ['today' => date('Y-m-d')]
        );
    }

    /**
     * Un subdomini es pot donar? Torna '' si sí, o el motiu si no.
     * Mira alhora el que diu el codi (noms reservats i forma del nom) i el que
     * ja hi ha a la plataforma, perquè no se'n pugui repetir cap.
     */
    public static function slugProblem(string $slug, ?int $exceptId = null, ?string $root = null): string
    {
        $slug = mb_strtolower(trim($slug));
        if ($slug === '') {
            return 'Cal indicar un subdomini.';
        }
        if (Tenancy::reserved($slug, (array) (Tenancy::settings($root ?? CROS_ROOT)['reserved'] ?? []))) {
            return 'Aquest subdomini està reservat per al sistema.';
        }
        if (!Tenancy::valid($slug)) {
            return 'Només lletres, números i guions, entre 2 i 30 caràcters, sense guions al final.';
        }
        $existing = self::bySlug($slug);
        if ($existing && (int) $existing['id'] !== (int) $exceptId) {
            return 'Ja hi ha un cros en aquesta adreça.';
        }
        if (Tenancy::exists($root ?? CROS_ROOT, $slug) && $existing === null) {
            return 'Ja hi ha una carpeta instal·lada amb aquest nom.';
        }

        return '';
    }

    /** Desa una instància nova i en torna l'identificador. */
    public static function create(array $data): int
    {
        $slug = mb_strtolower(trim((string) $data['slug']));
        $language = (string) ($data['language'] ?? 'ca');
        $language = in_array($language, ['ca', 'es'], true) ? $language : 'ca';

        return Db::insert('instances', [
            'client_id' => !empty($data['client_id']) ? (int) $data['client_id'] : null,
            'slug' => $slug,
            'site_name' => trim((string) $data['site_name']),
            'town' => trim((string) ($data['town'] ?? '')) ?: null,
            'language' => $language,
            'status' => 'new',
            'db_name' => trim((string) $data['db_name']),
            'db_user' => trim((string) ($data['db_user'] ?? '')) ?: null,
            'admin_email' => mb_strtolower(trim((string) ($data['admin_email'] ?? ''))) ?: null,
            'event_date' => !empty($data['event_date']) ? $data['event_date'] : null,
            'version' => app_version(),
            'listed' => isset($data['listed']) ? (int) (bool) $data['listed'] : 1,
            'installed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Canvia camps d'una instància. */
    public static function update(int $id, array $fields): void
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        Db::update('instances', $fields, 'id = :id', ['id' => $id]);
    }

    /**
     * Atura el web d'una instància: es queda tot com està, però deixa de
     * servir-se fins que es torni a engegar.
     */
    public static function suspend(int $id, ?string $root = null): bool
    {
        $instance = self::find($id);
        if (!$instance) {
            return false;
        }
        $dir = Tenancy::dir($root ?? CROS_ROOT, (string) $instance['slug']);
        if ($dir === '') {
            return false;
        }
        @file_put_contents($dir . '/' . Tenancy::SUSPENDED, date('c') . "\n");
        self::update($id, ['status' => 'suspended', 'suspended_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    /** Torna a engegar una instància aturada. */
    public static function resume(int $id, ?string $root = null): bool
    {
        $instance = self::find($id);
        if (!$instance) {
            return false;
        }
        $dir = Tenancy::dir($root ?? CROS_ROOT, (string) $instance['slug']);
        if ($dir === '') {
            return false;
        }
        @unlink($dir . '/' . Tenancy::SUSPENDED);
        self::update($id, [
            'status' => (int) $instance['registrations'] > 0 || !empty($instance['published']) ? 'active' : 'new',
            'suspended_at' => null,
        ]);

        return true;
    }

    /**
     * Dona de baixa una instància. El web deixa de servir-se i les dades es
     * guarden els dies que diu PURGE_DAYS abans d'esborrar-se.
     */
    public static function cancel(int $id, ?string $root = null): bool
    {
        if (!self::suspend($id, $root)) {
            return false;
        }
        self::update($id, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'purge_at' => date('Y-m-d', strtotime('+' . self::PURGE_DAYS . ' days')),
        ]);

        return true;
    }

    /**
     * Crea un enllaç d'accés d'un sol ús al panell d'una instància.
     *
     * El fa a la base de dades del client, per a qui hi consta com a
     * administrador, i en torna l'adreça. La plataforma no sap ni desa cap
     * contrasenya de ningú: quan cal entrar-hi, es fa així i queda apuntat al
     * registre del seu web.
     *
     * @return array{ok:bool,url:string,email:string,error:string}
     */
    public static function accessLink(int $id, string $purpose = 'reset', ?string $root = null, string $note = ''): array
    {
        $root = $root ?? CROS_ROOT;
        $instance = self::find($id);
        if (!$instance || in_array((string) $instance['status'], ['cancelled', 'purged'], true)) {
            return ['ok' => false, 'url' => '', 'email' => '', 'error' => 'La instància no està en marxa.'];
        }
        $dir = Tenancy::dir($root, (string) $instance['slug']);
        if ($dir === '' || !is_file($dir . '/config.php')) {
            return ['ok' => false, 'url' => '', 'email' => '', 'error' => 'No hi ha la carpeta de la instància.'];
        }
        $config = require $dir . '/config.php';
        $platform = Db::connection();
        $url = '';
        $email = '';
        $error = '';
        try {
            Db::setConnection(Db::connect((array) ($config['db'] ?? []) + ['charset' => 'utf8mb4', 'timeout' => 10]));
            $user = null;
            if (!empty($instance['admin_email'])) {
                $user = Db::one("SELECT * FROM users WHERE email = :email AND active = 1 AND role = 'admin'",
                    ['email' => $instance['admin_email']]);
            }
            $user = $user ?: Db::one("SELECT * FROM users WHERE active = 1 AND role = 'admin' ORDER BY id LIMIT 1");
            if (!$user) {
                throw new \RuntimeException('Aquest web no té cap administrador actiu.');
            }
            $token = LoginLink::issue((int) $user['id'], $purpose, $note);
            $email = (string) $user['email'];
            $url = LoginLink::urlFor((string) ($config['base_url'] ?? Platform::url((string) $instance['slug'], $root)), $token);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            log_line('platform', 'No s\'ha pogut crear l\'enllaç d\'accés', ['slug' => $instance['slug'], 'error' => $error]);
        } finally {
            Db::setConnection($platform);
            Settings::forget();
            Platform::prime($root);
        }

        return ['ok' => $error === '', 'url' => $url, 'email' => $email, 'error' => $error];
    }

    /**
     * Posa al dia el codi d'una instància: aplica les migracions que li falten
     * i apunta amb quina versió es queda. El codi és el mateix per a tothom
     * (una sola còpia), de manera que aquí només es toca la base de dades.
     *
     * @return array{ok:bool,applied:array<int,string>,error:string}
     */
    public static function upgrade(int $id, ?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $instance = self::find($id);
        if (!$instance || in_array((string) $instance['status'], ['cancelled', 'purged'], true)) {
            return ['ok' => false, 'applied' => [], 'error' => 'La instància no s\'ha d\'actualitzar.'];
        }
        $dir = Tenancy::dir($root, (string) $instance['slug']);
        if ($dir === '' || !is_file($dir . '/config.php')) {
            return ['ok' => false, 'applied' => [], 'error' => 'No hi ha la carpeta de la instància.'];
        }
        $config = require $dir . '/config.php';
        $platform = Db::connection();
        $applied = [];
        $error = '';
        try {
            Db::setConnection(Db::connect((array) ($config['db'] ?? []) + ['charset' => 'utf8mb4', 'timeout' => 10]));
            $applied = Migrator::run();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            log_line('platform', 'No s\'ha pogut actualitzar la instància', ['slug' => $instance['slug'], 'error' => $error]);
        } finally {
            Db::setConnection($platform);
            // Treballar amb la base de dades del client buida la configuració
            // que hi havia a memòria: es torna a posar la de la plataforma.
            Settings::forget();
            Platform::prime($root);
        }
        if ($error === '') {
            self::update($id, ['version' => app_version()]);
        }

        return ['ok' => $error === '', 'applied' => $applied, 'error' => $error];
    }

    /**
     * Les instàncies que encara no tenen l'última versió.
     * @return array<int,array<string,mixed>>
     */
    public static function outdated(): array
    {
        return Db::all(
            "SELECT * FROM instances WHERE status NOT IN ('cancelled', 'purged')
             AND (version IS NULL OR version <> :version) ORDER BY slug",
            ['version' => app_version()]
        );
    }

    /**
     * Posa al dia el que la plataforma sap d'una instància llegint-ne la base
     * de dades: com es diu el web, quan és la cursa, quanta gent hi ha inscrita
     * i si ja l'han publicat. No toca res de la instància.
     */
    public static function sync(int $id, ?string $root = null): bool
    {
        $instance = self::find($id);
        if (!$instance) {
            return false;
        }
        $dir = Tenancy::dir($root ?? CROS_ROOT, (string) $instance['slug']);
        if ($dir === '' || !is_file($dir . '/config.php')) {
            return false;
        }
        $config = require $dir . '/config.php';
        $platform = Db::connection();
        try {
            Db::setConnection(Db::connect((array) ($config['db'] ?? []) + ['charset' => 'utf8mb4', 'timeout' => 10]));
            $settings = [];
            foreach (Db::all('SELECT k, v FROM settings') as $row) {
                $settings[$row['k']] = $row['v'];
            }
            $fields = [
                'site_name' => (string) ($settings['site_name'] ?? $instance['site_name']),
                'town' => (string) ($settings['event_town'] ?? $instance['town']) ?: null,
                'event_date' => (string) ($settings['event_date'] ?? '') ?: null,
                'registrations' => (int) Db::val("SELECT COUNT(*) FROM registrations WHERE status <> 'cancelled'", [], 0),
                // Un web «en preparació» encara no és públic.
                'published' => (string) ($settings['coming_soon'] ?? '0') === '1' ? 0 : 1,
                'synced_at' => date('Y-m-d H:i:s'),
            ];
            // Una instància deixa d'estar «sense estrenar» quan el client
            // publica el web o comença a rebre inscripcions.
            if ((string) $instance['status'] === 'new' && ($fields['published'] === 1 || $fields['registrations'] > 0)) {
                $fields['status'] = 'active';
            }
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut llegir la instància', ['slug' => $instance['slug'], 'error' => $e->getMessage()]);
            Db::setConnection($platform);

            return false;
        }
        Db::setConnection($platform);
        self::update($id, $fields);

        return true;
    }
}
