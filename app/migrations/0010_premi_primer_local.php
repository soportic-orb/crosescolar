<?php
/**
 * Premi «Primer local»: el primer classificat de l'escola del poble dins de cada
 * categoria. Cada categoria diu si el dona, i el guanyador es marca a mà des de
 * Resultats, perquè qui és «del poble» no sempre es dedueix del nom de l'escola.
 *
 * Les categories que ja hi havia el donen per defecte; si no es marca ningú, no
 * canvia res del que es veu.
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    $columns = [
        'categories' => ['local_prize' => 'TINYINT(1) NOT NULL DEFAULT 1'],
        'results' => ['local_prize' => 'TINYINT(1) NOT NULL DEFAULT 0'],
    ];
    foreach ($columns as $table => $definitions) {
        $existing = array_keys(Db::one('SELECT * FROM ' . $table . ' LIMIT 1') ?? []);
        foreach ($definitions as $name => $definition) {
            if ($existing && in_array($name, $existing, true)) {
                continue;
            }
            try {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
            } catch (\Throwable $e) {
                // Ja hi era (per exemple, en una instal·lació nova).
            }
        }
    }
};
