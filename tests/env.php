<?php
/**
 * Entorn de proves: executa l'aplicació sobre SQLite sense necessitat d'un
 * servidor MySQL. Només s'utilitza per a les proves automatitzades locals.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$testDir = $root . '/storage/tmp/test';
@mkdir($testDir, 0775, true);

$configFile = $testDir . '/config.php';
if (!is_file($configFile)) {
    file_put_contents($configFile, "<?php\nreturn " . var_export([
        'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'cros_test', 'user' => 'test', 'pass' => '', 'charset' => 'utf8mb4', 'socket' => ''],
        'app_key' => base64_encode(str_repeat('k', 32)),
        'base_url' => getenv('CROS_TEST_URL') ?: 'http://localhost:8123',
        'debug' => true,
        'timezone' => 'Europe/Madrid',
    ], true) . ";\n");
}
putenv('CROS_CONFIG=' . $configFile);

require_once $root . '/app/bootstrap.php';

use Cros\Core\Db;
use Cros\Core\Migrator;

/** Tradueix l'SQL de MySQL a SQLite per poder provar l'esquema real. */
function cros_test_translate(string $sql): string
{
    $sql = preg_replace('/\)\s*ENGINE=\w+[^;]*/i', ')', $sql) ?? $sql;
    $sql = preg_replace('/INT UNSIGNED NOT NULL AUTO_INCREMENT/i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('/\bINT UNSIGNED\b/i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('/\bTINYINT\(1\)\b/i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('/\s*PRIMARY KEY \(id\),?/i', '', $sql) ?? $sql;
    $sql = preg_replace('/\s*PRIMARY KEY \(k\),?/i', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*UNIQUE KEY \w+ \(([^)]+)\),?/mi', '  UNIQUE ($1),', $sql) ?? $sql;
    $sql = preg_replace('/^\s*KEY \w+ \([^)]+\),?\n/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/`id` INTEGER,/i', '`id` INTEGER PRIMARY KEY AUTOINCREMENT,', $sql) ?? $sql;
    $sql = preg_replace('/^(\s*)id INTEGER NOT NULL,/mi', '$1id INTEGER PRIMARY KEY AUTOINCREMENT,', $sql) ?? $sql;
    $sql = preg_replace('/^(\s*)id INTEGER,/mi', '$1id INTEGER PRIMARY KEY AUTOINCREMENT,', $sql) ?? $sql;
    $sql = preg_replace('/^(\s*)k VARCHAR\(100\) NOT NULL,/mi', '$1k VARCHAR(100) NOT NULL PRIMARY KEY,', $sql) ?? $sql;
    $sql = preg_replace('/,(\s*)\)/', '$1)', $sql) ?? $sql;
    return $sql;
}

$dbFile = $testDir . '/cros.sqlite';
$fresh = !is_file($dbFile) || getenv('CROS_TEST_FRESH') === '1';
if ($fresh && is_file($dbFile)) {
    @unlink($dbFile);
}
$pdo = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
Db::setConnection($pdo);

if ($fresh) {
    Migrator::ensureTable();
    foreach (glob($root . '/app/migrations/*.sql') ?: [] as $file) {
        foreach (Migrator::statements(cros_test_translate((string) file_get_contents($file))) as $statement) {
            $pdo->exec($statement);
        }
        Db::insert('migrations', ['name' => basename($file), 'applied_at' => date('Y-m-d H:i:s')]);
    }
    // Migracions escrites en PHP (les .sql ja s'han aplicat traduïdes)
    Migrator::run();
    \Cros\Core\Seeder::run();
    \Cros\Core\Settings::set('stripe_webhook_test', 'whsec_test_secret_de_prova');
    \Cros\Core\Settings::set('stripe_sk_test', 'sk_test_fals');
    \Cros\Core\Settings::set('stripe_pk_test', 'pk_test_fals');
    Db::insert('users', [
        'name' => 'Administració de proves',
        'email' => 'admin@example.test',
        'password_hash' => password_hash('provaprova', PASSWORD_DEFAULT),
        'role' => 'admin',
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}
