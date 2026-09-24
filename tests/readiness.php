<?php
/**
 * Proves del repàs de la preparació de la cursa.
 *
 * Ús:  php -S 127.0.0.1:8123 -t . tests/server.php &   i després   php tests/readiness.php
 *
 * Es toca la configuració del web de proves i es torna a deixar com estava.
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Db;
use Cros\Core\Readiness;
use Cros\Core\Settings;

$base = getenv('CROS_TEST_URL') ?: 'http://127.0.0.1:8123';
$jar = sys_get_temp_dir() . '/cros-readiness.txt';
@unlink($jar);
$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

function req(string $method, string $url, array $data = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
}

/** Els valors que es toquen, per poder-los tornar a deixar on eren. */
$original = [];
foreach (['event_date', 'event_time', 'event_place', 'map_lat', 'coming_soon',
          'mail_transport', 'registrations_close_at', 'bib_template'] as $key) {
    $original[$key] = (string) Settings::get($key, '');
}
$restore = static function () use ($original): void {
    Settings::setMany($original);
};

$find = static function (string $key): ?array {
    foreach (Readiness::checks() as $check) {
        if ($check['key'] === $key) {
            return $check;
        }
    }

    return null;
};

try {
    echo "\n== Quan toca ensenyar el repàs ==\n";
    Settings::set('event_date', '');
    check('Sense data de cursa no es diu res', !Readiness::due());
    check('I no se saben els dies que falten', Readiness::daysLeft() === null);

    Settings::set('event_date', date('Y-m-d', strtotime('+200 days')));
    check('Amb la cursa lluny, tampoc', !Readiness::due());
    check('Però els dies sí', Readiness::daysLeft() === 200);

    Settings::set('event_date', date('Y-m-d', strtotime('+10 days')));
    Settings::set('coming_soon', '1');
    check('Amb la cursa a prop i coses per fer, sí', Readiness::due());

    Settings::set('event_date', date('Y-m-d', strtotime('-3 days')));
    check('Passada la cursa, s\'acaba', !Readiness::due());

    echo "\n== Què es repassa ==\n";
    Settings::set('event_date', date('Y-m-d', strtotime('+10 days')));
    Settings::setMany([
        'event_time' => '09:30', 'event_place' => 'Zona esportiva', 'map_lat' => '41.4',
        'coming_soon' => '0', 'mail_transport' => 'mail', 'bib_template' => 'documents/dorsal.pdf',
        'registrations_close_at' => date('Y-m-d', strtotime('+5 days')),
    ]);
    check('Amb tot a punt no queda res per mirar', Readiness::pending() === [], count(Readiness::pending()) . ' pendents');

    Settings::set('coming_soon', '1');
    $check = $find('published');
    check('Si el web està amagat, es diu', $check !== null && !$check['ok']);
    check('I s\'explica per què importa', $check !== null && str_contains($check['hint'], 'ningú no el pot veure'));
    Settings::set('coming_soon', '0');

    Settings::set('mail_transport', 'log');
    $check = $find('mail');
    check('Si el correu està en assaig, es diu', $check !== null && !$check['ok']);
    Settings::set('mail_transport', 'mail');

    Settings::set('registrations_close_at', date('Y-m-d', strtotime('+30 days')));
    $check = $find('close');
    check('Si la inscripció es tanca després de la cursa, es diu', $check !== null && !$check['ok']);
    Settings::set('registrations_close_at', date('Y-m-d', strtotime('+5 days')));

    Settings::set('event_time', '');
    $check = $find('date');
    check('Si falta l\'hora, es diu', $check !== null && !$check['ok']);
    Settings::set('event_time', '09:30');

    Settings::set('map_lat', '');
    $check = $find('place');
    check('Si no hi ha mapa, es diu', $check !== null && !$check['ok']);
    Settings::set('map_lat', '41.4');

    check('Les categories del web de proves ja hi són',
        ($find('categories')['ok'] ?? false) === true);

    echo "\n== Al panell ==\n";
    $login = req('POST', $base . '/admin/acces', [
        '_token' => (preg_match('/name="_token" value="([^"]+)"/', req('GET', $base . '/admin/acces')['body'], $m) ? $m[1] : ''),
        'email' => 'admin@example.test',
        'password' => 'provaprova',
    ]);
    check('S\'entra al panell', $login['status'] === 302, 'estat ' . $login['status']);

    Settings::set('coming_soon', '1');
    $board = req('GET', $base . '/admin');
    check('El tauler ensenya el repàs', str_contains($board['body'], 'Falta poc: repasseu-ho'));
    check('Amb el que queda per mirar', str_contains($board['body'], 'aviat publicarem el web')
        || str_contains($board['body'], 'ningú no el pot veure'));
    check('I els dies que falten', str_contains($board['body'], 'Queden 10 dies'));

    Settings::set('event_date', date('Y-m-d', strtotime('+200 days')));
    $board = req('GET', $base . '/admin');
    check('Amb la cursa lluny, el tauler no en parla', !str_contains($board['body'], 'Falta poc: repasseu-ho'));
} finally {
    $restore();
}

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
