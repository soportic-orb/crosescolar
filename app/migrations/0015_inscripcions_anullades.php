<?php
/**
 * Inscripcions anul·lades per la família.
 *
 * Una inscripció anul·lada no s'esborra: queda al panell amb l'estat «Anul·lada»
 * i conserva el seu número de dorsal, que així no se li pot donar a ningú més.
 * Es desa quan es va anul·lar i qui ho va fer (la família o l'organització).
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    $existing = array_keys(Db::one('SELECT * FROM registrations LIMIT 1') ?? []);
    foreach (['cancelled_at' => 'DATETIME NULL', 'cancelled_by' => 'VARCHAR(20) NULL'] as $column => $type) {
        if ($existing && in_array($column, $existing, true)) {
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE registrations ADD COLUMN ' . $column . ' ' . $type);
        } catch (\Throwable $e) {
            // Ja hi era (per exemple, en una instal·lació nova).
        }
    }
};
