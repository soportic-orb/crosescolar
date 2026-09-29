<?php
/**
 * Les columnes que lliguen les comandes i les inscripcions amb el seu cobrament.
 *
 * Van a part de 0017 i amb comprovació perquè una migració s'ha de poder tornar
 * a aplicar sense fer mal: en restaurar la còpia d'un cros, el sistema li torna
 * a passar els canvis que li falten i un «ALTER TABLE ADD COLUMN» pelat
 * s'hi trobaria la columna ja feta.
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    $add = static function (string $table, string $column, string $definition) use ($pdo): void {
        $row = Db::one('SELECT * FROM ' . $table . ' LIMIT 1');
        if (is_array($row) && array_key_exists($column, $row)) {
            return;
        }
        try {
            $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ja hi era: en una taula buida no es pot mirar amb un SELECT.
        }
    };

    $add('orders', 'payment_id', 'INT UNSIGNED NULL');
    $add('registrations', 'fee_type_id', 'INT UNSIGNED NULL');
    $add('registrations', 'payment_id', 'INT UNSIGNED NULL');
};
