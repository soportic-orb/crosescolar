<?php
/**
 * Les medalles dels guanyadors passen de ser una opció general a definir-se
 * categoria per categoria: cada una diu si en mostra i quants participants en
 * reben. Els valors que hi havia a la configuració es copien a totes les
 * categories perquè el web continuï igual que abans d'actualitzar.
 */

use Cros\Core\Db;
use Cros\Core\Settings;

return static function (PDO $pdo): void {
    $columns = [
        'medals' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'winners' => 'INT NOT NULL DEFAULT 3',
    ];
    $existing = [];
    foreach (Db::all('SELECT * FROM categories LIMIT 1') as $row) {
        $existing = array_keys($row);
    }
    foreach ($columns as $name => $definition) {
        if ($existing && in_array($name, $existing, true)) {
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE categories ADD COLUMN ' . $name . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ja hi era (per exemple, en una instal·lació nova).
        }
    }

    $medals = Settings::get('prizes_medals', '1') === '0' ? 0 : 1;
    $winners = (int) Settings::get('prizes_winners', '3');
    $winners = $winners > 0 ? min(50, $winners) : 3;
    Db::conn()->prepare('UPDATE categories SET medals = :medals, winners = :winners')
        ->execute(['medals' => $medals, 'winners' => $winners]);
};
