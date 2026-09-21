<?php
/**
 * Acceptació del reglament: queda constància de qui l'ha acceptat en inscriure's,
 * igual que ja passa amb els consentiments de dades i d'imatge.
 *
 * Les inscripcions que ja hi havia es marquen com a acceptades: es van fer abans
 * que hi hagués la casella i no seria just deixar-les com si l'haguessin refusat.
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    $existing = array_keys(Db::one('SELECT * FROM registrations LIMIT 1') ?? []);
    if ($existing && in_array('consent_rules', $existing, true)) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE registrations ADD COLUMN consent_rules TINYINT(1) NOT NULL DEFAULT 0');
    } catch (\Throwable $e) {
        return; // Ja hi era (per exemple, en una instal·lació nova).
    }
    $pdo->exec('UPDATE registrations SET consent_rules = 1');
};
