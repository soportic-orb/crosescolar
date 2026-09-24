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

    public static function get(string $key, $default = null)
    {
        $all = self::load();
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
