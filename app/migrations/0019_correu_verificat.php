<?php
/**
 * Quan una persona ha demostrat que el seu correu és seu.
 *
 * Amb el registre lliure, qui es dona d'alta no passa per cap validació: el
 * que fa de porta és el correu. L'enllaç d'estrena arriba a l'adreça que ha
 * escrit i, en prémer-lo, hi entra i alhora queda constància que l'adreça és
 * bona. Això és el que s'hi apunta.
 *
 * Va amb comprovació perquè una migració s'ha de poder tornar a aplicar sense
 * fer mal: en restaurar la còpia d'un cros se li tornen a passar totes.
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    $row = Db::one('SELECT * FROM users LIMIT 1');
    if (is_array($row) && array_key_exists('email_verified_at', $row)) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL');
    } catch (\Throwable $e) {
        // Ja hi era: en una taula buida no es pot mirar amb un SELECT.
    }
};
