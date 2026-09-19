<?php
/**
 * Proves del sistema d'actualitzacions (OTA): interpretació del manifest i
 * missatges d'error entenedors.
 *
 * Ús:  php tests/updates.php
 * Amb CROS_TEST_ONLINE=1 també comprova l'API real de GitHub.
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Settings;
use Cros\Core\Updater;

$port = (int) (getenv('CROS_FIXTURE_PORT') ?: 8127);
$fixtures = __DIR__ . '/fixtures/updates';
$base = 'http://127.0.0.1:' . $port;
$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/** Consulta un origen concret sense memòria cau. */
function checkUrl(string $url): array
{
    Settings::set('update_manifest_url', $url);
    Settings::set('update_last_check', '0');
    return Updater::check(true);
}

$server = proc_open(
    sprintf('php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($fixtures)),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);
for ($i = 0; $i < 40 && @file_get_contents($base . '/manifest.json') === false; $i++) {
    usleep(150000);
}

try {
    echo "\n== Manifest propi ==\n";
    $result = checkUrl($base . '/manifest.json');
    check('Detecta una versió nova', $result['available'] === true, $result['error'] ?: 'sense error');
    check('Llegeix la versió', $result['latest'] === '9.9.9');
    check('Llegeix el resum SHA-256', str_starts_with((string) $result['sha256'], '0000'));
    check('Llegeix les notes', str_contains((string) $result['notes'], 'prova'));
    check('No dona cap error', $result['error'] === '' && $result['notice'] === '');

    $result = checkUrl($base . '/manifest-igual.json');
    check('No ofereix versions més antigues', $result['available'] === false);

    echo "\n== Versions de GitHub ==\n";
    $result = checkUrl($base . '/releases.json');
    check('Interpreta una llista de versions', $result['available'] === true && $result['latest'] === '9.9.9', $result['error']);
    check('Pren el paquet adjunt', $result['zip_url'] === 'https://exemple.test/paquet.zip');
    check('Guarda l\'adreça de l\'API per a dipòsits privats', str_contains((string) $result['zip_api_url'], '/releases/assets/1'));
    check('Llegeix el resum SHA-256 de les notes de la versió', $result['sha256'] === str_repeat('1', 64), $result['sha256']);

    $result = checkUrl($base . '/releases-buit.json');
    check('Un origen sense versions no és cap error', $result['error'] === '' && $result['available'] === false);
    check('Avisa que encara no hi ha cap versió', str_contains((string) $result['notice'], 'cap versió publicada'), $result['notice']);

    echo "\n== Errors entenedors ==\n";
    $result = checkUrl($base . '/no-existeix.json');
    check('Explica un 404 d\'un manifest propi', str_contains((string) $result['error'], 'No s\'ha trobat el manifest'), $result['error']);
    check('No diu que hi hagi cap versió nova', $result['available'] === false);

    $result = checkUrl($base . '/no-json.txt');
    check('Detecta respostes que no són JSON', str_contains((string) $result['error'], 'no és un JSON vàlid'), $result['error']);

    $result = checkUrl('');
    check('Avisa si no hi ha origen configurat', str_contains((string) $result['notice'], 'No s\'ha configurat'), $result['notice']);

    if (getenv('CROS_TEST_ONLINE') === '1') {
        echo "\n== API real de GitHub ==\n";
        $result = checkUrl('https://api.github.com/repos/soportic-orb/crosescolar/releases/latest');
        check('Llegeix la darrera versió publicada del dipòsit real',
            $result['error'] === '' && preg_match('/^\d+\.\d+\.\d+$/', (string) $result['latest']) === 1,
            $result['error'] ?: $result['latest']);
        check('El paquet real porta adreça i resum SHA-256',
            str_ends_with((string) $result['zip_url'], '.zip')
            && preg_match('/^[0-9a-f]{64}$/', (string) $result['sha256']) === 1,
            $result['zip_url'] . ' / ' . $result['sha256']);

        $result = checkUrl('https://api.github.com/repos/soportic-orb/no-existeix-mai/releases/latest');
        check('Un dipòsit inaccessible dona un missatge útil i no ofereix cap versió',
            $result['error'] !== '' && $result['available'] === false
            && (str_contains($result['error'], 'no ha trobat') || str_contains($result['error'], 'denegat')),
            $result['error']);
    } else {
        echo "\n(Proves contra GitHub omeses: definiu CROS_TEST_ONLINE=1)\n";
    }
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    Settings::set('update_manifest_url', 'https://api.github.com/repos/soportic-orb/crosescolar/releases/latest');
    Settings::set('update_last_check', '0');
    Settings::set('update_last_result', '');
}

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
