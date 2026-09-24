<?php
declare(strict_types=1);

namespace Cros\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Capa d'accés a la base de dades (PDO/MySQL).
 */
class Db
{
    private static ?PDO $pdo = null;

    /** Connexió PDO (mandrosa). */
    public static function conn(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = [
            'host'    => (string) config('db.host', 'localhost'),
            'port'    => (int) config('db.port', 3306),
            'name'    => (string) config('db.name', ''),
            'user'    => (string) config('db.user', ''),
            'pass'    => (string) config('db.pass', ''),
            'charset' => (string) config('db.charset', 'utf8mb4'),
            'socket'  => (string) config('db.socket', ''),
            'timeout' => (int) config('db.timeout', 15),
        ];
        return self::$pdo = self::connect($cfg);
    }

    /** Crea una connexió a partir d'una configuració concreta (l'usa l'instal·lador). */
    public static function connect(array $cfg): PDO
    {
        $charset = $cfg['charset'] ?: 'utf8mb4';
        if (!empty($cfg['socket'])) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $cfg['socket'], $cfg['name'], $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], (int) $cfg['port'], $cfg['name'], $charset);
        }
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Evita que una base de dades que no respon deixi la petició penjada.
            PDO::ATTR_TIMEOUT            => (int) ($cfg['timeout'] ?? 15),
        ]);
        $pdo->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    }

    /** Injecta una connexió ja creada (instal·lador i proves). */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * La connexió que hi ha ara mateix, sense obrir-ne cap de nova.
     * Serveix per desar-la i tornar-la a deixar on era (instal·lar una altra
     * instància enmig d'una petició, per exemple).
     */
    public static function connection(): ?PDO
    {
        return self::$pdo;
    }

    /** Executa una consulta preparada. */
    public static function q(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Totes les files. */
    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    /** Primera fila o null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Primer valor de la primera fila. */
    public static function val(string $sql, array $params = [], $default = null)
    {
        $value = self::q($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    /** Insereix una fila i retorna l'identificador. */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns)),
            implode(', ', array_map(fn ($c) => ':' . $c, $columns))
        );
        self::q($sql, $data);
        return (int) self::conn()->lastInsertId();
    }

    /** Actualitza files i retorna el nombre d'afectades. */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = sprintf('`%s` = :set_%s', $column, $column);
            $params['set_' . $column] = $value;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::q($sql, array_merge($params, $whereParams))->rowCount();
    }

    /** Esborra files. */
    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::q(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $params)->rowCount();
    }

    /** Executa una funció dins d'una transacció. */
    public static function transaction(callable $callback)
    {
        $pdo = self::conn();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Comprova si existeix una taula. */
    public static function tableExists(string $table): bool
    {
        try {
            self::conn()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** Nom del controlador PDO actiu (mysql, sqlite...). */
    public static function driver(): string
    {
        return (string) self::conn()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
