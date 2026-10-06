<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Configuració del lloc emmagatzemada a la taula `settings`.
 * Els valors marcats com a secrets es desen xifrats i es desxifren en llegir-los.
 */
class Settings
{
    private static ?array $cache = null;
    private static ?array $schema = null;
    private static ?array $defaults = null;

    /**
     * Àmbit: un prefix per a les claus que no són de tothom.
     *
     * La plataforma serveix més d'una pàgina pública —una per domini— amb la
     * mateixa base de dades. El text de la portada d'un domini no és el de
     * l'altre, però el servidor de correu o les claus de Stripe sí. Per això
     * només les claus que s'hi diuen es desen amb prefix; la resta van on
     * han anat sempre.
     */
    private static string $scope = '';
    /** @var array<string,bool> */
    private static array $scopeKeys = [];
    /** @var array<string,string> */
    private static array $scopeDefaults = [];
    /** @var array<string,bool> claus de l'àmbit que, si no diuen res, valen el que valen fora */
    private static array $scopeInherit = [];

    /** Carrega tots els valors a memòria. */
    public static function load(bool $force = false): array
    {
        if (self::$cache !== null && !$force) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            foreach (Db::all('SELECT k, v FROM settings') as $row) {
                self::$cache[$row['k']] = Crypto::isEncrypted($row['v']) ? Crypto::decrypt($row['v']) : $row['v'];
            }
        } catch (\Throwable $e) {
            // Base de dades no disponible encara (instal·lació): s'utilitzen els valors per defecte.
            self::$cache = [];
        }
        return self::$cache;
    }

    /**
     * Posa uns valors a memòria sense tocar cap base de dades.
     *
     * Ho fa servir la plataforma, que no té taula de configuració però sí que
     * necessita que funcionin coses com l'enviament de correu.
     *
     * @param array<string,string> $values
     */
    public static function prime(array $values): void
    {
        self::$cache = array_merge(self::$cache ?? [], $values);
    }

    /**
     * Oblida el que hi ha a memòria sense llegir res.
     * Es fa servir quan s'ha treballat amb una altra base de dades i els valors
     * que hi ha carregats ja no són els d'aquesta instal·lació.
     */
    public static function forget(): void
    {
        self::$cache = null;
    }

    /**
     * Fa servir un àmbit per a unes claus concretes.
     *
     * @param array<int,string>    $keys     claus que hi pertanyen
     * @param array<string,string> $defaults què valen si l'àmbit no diu res
     * @param array<int,string>    $inherit  claus que, si l'àmbit no diu res,
     *                                       valen el que hi hagi desat sense àmbit
     */
    public static function scope(string $prefix = '', array $keys = [], array $defaults = [], array $inherit = []): void
    {
        self::$scope = $prefix;
        self::$scopeKeys = array_fill_keys($keys, true);
        self::$scopeDefaults = $defaults;
        self::$scopeInherit = array_fill_keys($inherit, true);
    }

    /** Quin àmbit hi ha actiu, o '' si cap. */
    public static function scopeName(): string
    {
        return self::$scope;
    }

    /** Aquesta clau va per àmbits? */
    public static function isScoped(string $key): bool
    {
        return self::$scope !== '' && isset(self::$scopeKeys[$key]);
    }

    /** Com es diu aquesta clau dins de l'àmbit actiu. */
    public static function scopedKey(string $key): string
    {
        return self::isScoped($key) ? self::$scope . $key : $key;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::load();
        if (self::isScoped($key)) {
            $own = $all[self::$scope . $key] ?? null;
            if ($own !== null && $own !== '') {
                return $own;
            }
            if (isset(self::$scopeInherit[$key]) && isset($all[$key]) && (string) $all[$key] !== '') {
                return $all[$key];
            }
            $seed = self::$scopeDefaults[$key] ?? null;
            if ($seed !== null && $seed !== '') {
                return $seed;
            }
            if ($default !== null) {
                return $default;
            }

            return self::defaults()[$key] ?? null;
        }
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }
        if ($default !== null) {
            return $default;
        }
        $defaults = self::defaults();
        return $defaults[$key] ?? ($all[$key] ?? null);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');
        return in_array((string) $value, ['1', 'on', 'true', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }

    /** Desa un valor. */
    public static function set(string $key, $value): void
    {
        $field = self::field($key);
        $store = (string) $value;
        if (($field['secret'] ?? false) && $store !== '') {
            $store = Crypto::encrypt($store);
        }
        $key = self::scopedKey($key);
        $sql = Db::driver() === 'sqlite'
            ? 'INSERT INTO settings (k, v, updated_at) VALUES (:k, :v, CURRENT_TIMESTAMP)
               ON CONFLICT(k) DO UPDATE SET v = excluded.v, updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO settings (k, v, updated_at) VALUES (:k, :v, NOW())
               ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()';
        Db::q($sql, ['k' => $key, 'v' => $store]);
        self::$cache[$key] = (string) $value;
    }

    /** Desa diversos valors. */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    /** Esquema de camps configurables. */
    public static function schema(): array
    {
        if (self::$schema === null) {
            self::$schema = require CROS_APP . '/settings_schema.php';
        }
        return self::$schema;
    }

    /** L'esquema que hi ha actiu ara mateix, sense carregar-ne cap. */
    public static function currentSchema(): ?array
    {
        return self::$schema;
    }

    /**
     * Canvia l'esquema de configuració.
     *
     * El web d'un cros i el panell de la plataforma tenen coses diferents per
     * configurar, però la manera de desar-les i de dibuixar-les és la mateixa:
     * només canvia la llista de camps.
     *
     * @param array<string,mixed>|null $schema null per tornar al de sempre
     */
    public static function useSchema(?array $schema): void
    {
        self::$schema = $schema;
        self::$defaults = null;
        self::scope();
    }

    /** Definició d'un camp concret. */
    public static function field(string $key): array
    {
        foreach (self::schema() as $group) {
            foreach ($group['fields'] as $name => $field) {
                if ($name === $key) {
                    return $field;
                }
            }
        }
        return [];
    }

    /** Valors per defecte de tot l'esquema. */
    public static function defaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }
        $defaults = [];
        foreach (self::schema() as $group) {
            foreach ($group['fields'] as $name => $field) {
                $defaults[$name] = $field['default'] ?? '';
            }
        }

        return self::$defaults = $defaults;
    }

    /** Grups de l'esquema (per al menú del panell). */
    public static function group(string $key): ?array
    {
        return self::schema()[$key] ?? null;
    }

    /**
     * Quan es va tocar per última vegada alguna d'aquestes opcions.
     *
     * Serveix per dir al mapa del web quan va canviar cada pàgina: si no se'n
     * passa cap, es mira tota la configuració. Torna null si no se'n sap res.
     */
    public static function changedAt(array $keys = []): ?string
    {
        try {
            if ($keys === []) {
                $when = Db::val('SELECT MAX(updated_at) FROM settings');
            } else {
                $marks = implode(',', array_fill(0, count($keys), '?'));
                $when = Db::val('SELECT MAX(updated_at) FROM settings WHERE k IN (' . $marks . ')', array_values($keys));
            }
        } catch (\Throwable) {
            return null;
        }

        return $when ? (string) $when : null;
    }

    /** Escriu tots els valors per defecte que encara no existeixen. */
    public static function seedDefaults(): void
    {
        $existing = [];
        foreach (Db::all('SELECT k FROM settings') as $row) {
            $existing[$row['k']] = true;
        }
        foreach (self::defaults() as $key => $value) {
            if (!isset($existing[$key]) && $value !== '') {
                self::set($key, $value);
            }
        }
        self::load(true);
    }
}
