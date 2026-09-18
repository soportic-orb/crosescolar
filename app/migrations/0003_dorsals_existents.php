<?php
/**
 * Assigna número de dorsal i codi d'accés a les inscripcions que ja existien
 * abans d'aquesta versió, per ordre d'inscripció.
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    $rows = Db::all('SELECT id FROM registrations WHERE bib_number IS NULL OR token IS NULL ORDER BY created_at ASC, id ASC');
    if (!$rows) {
        return;
    }
    $next = (int) Db::val('SELECT COALESCE(MAX(bib_number), 0) FROM registrations', [], 0) + 1;
    foreach ($rows as $row) {
        Db::update('registrations', [
            'bib_number' => $next++,
            'token' => bin2hex(random_bytes(16)),
        ], 'id = :id AND (bib_number IS NULL OR token IS NULL)', ['id' => $row['id']]);
    }
};
