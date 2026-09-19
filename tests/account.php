<?php
/**
 * Proves de «Les meves inscripcions»: codi d'un sol ús per correu, accés i
 * modificació de les dades pròpies.
 *
 * Ús:  php -S 127.0.0.1:8123 -t . tests/server.php &   i després   php tests/account.php
 *
 * A diferència de les altres bateries, aquesta també obre la base de dades: el
 * codi que s'envia per correu no es pot llegir de la resposta del web (ni s'hi
 * ha de poder llegir), així que el genera el model i el circuit web el fa servir.
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Models\AccessCode;
use Cros\Models\Registration;

$base = getenv('CROS_TEST_URL') ?: 'http://127.0.0.1:8123';
$jar = sys_get_temp_dir() . '/cros-account.txt';
@unlink($jar);
$passed = 0;
$failed = 0;
$unique = 'P' . substr(bin2hex(random_bytes(3)), 0, 5);
$email = 'familia' . strtolower($unique) . '@example.test';

function req(string $method, string $url, array $data = [], array $options = []): array
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
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
}

function token(string $html): string
{
    return preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

// L'apartat ha d'estar actiu tant si s'executa sola com després d'altres bateries.
Settings::set('registrations_selfservice', '1');

/* ------------------------------------------------ Participants de la prova */
$laia = Registration::create([
    'first_name' => 'Laia' . $unique, 'last_name' => 'Duran', 'birth_year' => (string) ((int) date('Y') - 9),
    'gender' => 'femeni', 'category_id' => '', 'school' => 'Escola La Granada', 'class_group' => 'Primària 4rt',
    'tutor_name' => 'Anna Duran', 'tutor_email' => $email, 'tutor_phone' => '600111222',
    'shirt_size' => '10', 'notes' => '', 'consent_data' => 1, 'consent_image' => 0,
]);
$pau = Registration::create([
    'first_name' => 'Pau' . $unique, 'last_name' => 'Duran', 'birth_year' => (string) ((int) date('Y') - 11),
    'gender' => 'masculi', 'category_id' => '', 'school' => 'Escola La Granada', 'class_group' => 'Primària 6è',
    'tutor_name' => 'Anna Duran', 'tutor_email' => $email, 'tutor_phone' => '600111222',
    'shirt_size' => '12', 'notes' => '', 'consent_data' => 1, 'consent_image' => 0,
]);
$altri = Registration::create([
    'first_name' => 'Roc' . $unique, 'last_name' => 'Soler', 'birth_year' => (string) ((int) date('Y') - 10),
    'gender' => 'masculi', 'category_id' => '', 'school' => 'Escola La Granada', 'class_group' => 'Primària 5è',
    'tutor_name' => 'Jordi Soler', 'tutor_email' => 'altrafamilia' . strtolower($unique) . '@example.test',
    'tutor_phone' => '600333444', 'shirt_size' => '', 'notes' => '', 'consent_data' => 1, 'consent_image' => 0,
]);

echo "\n== Demanar el codi ==\n";
$page = req('GET', $base . '/les-meves-inscripcions');
check('La pàgina demana el correu', $page['status'] === 200 && str_contains($page['body'], 'name="email"'));
check('No la indexen els cercadors', str_contains($page['body'], 'noindex'));

$before = (int) Db::val('SELECT COUNT(*) FROM access_codes WHERE email = :e', ['e' => $email], 0);
$sent = req('POST', $base . '/les-meves-inscripcions', ['_token' => token($page['body']), 'email' => $email]);
check('Demanar el codi porta al pas següent', $sent['status'] === 302 && str_contains($sent['headers'], '/les-meves-inscripcions/codi'));
check('Es desa un codi per a aquesta adreça',
    (int) Db::val('SELECT COUNT(*) FROM access_codes WHERE email = :e', ['e' => $email], 0) === $before + 1);
check('El codi no es desa en clar',
    (string) Db::val('SELECT code_hash FROM access_codes WHERE email = :e ORDER BY id DESC', ['e' => $email], '') !== ''
    && preg_match('/^[0-9a-f]{64}$/', (string) Db::val('SELECT code_hash FROM access_codes WHERE email = :e ORDER BY id DESC', ['e' => $email], '')) === 1);

$codeForm = req('GET', $base . '/les-meves-inscripcions/codi');
check('El pas del codi mostra l\'adreça', $codeForm['status'] === 200 && str_contains($codeForm['body'], $email));
check('El codi no apareix enlloc del web', !preg_match('/\b\d{6}\b/', strip_tags($codeForm['body'])), 'hi ha sis xifres a la pàgina');

$unknown = req('POST', $base . '/les-meves-inscripcions', [
    '_token' => token(req('GET', $base . '/les-meves-inscripcions')['body']),
    'email' => 'ningu' . strtolower($unique) . '@example.test',
]);
check('Una adreça sense inscripcions respon igual', $unknown['status'] === 302 && str_contains($unknown['headers'], '/les-meves-inscripcions/codi'));
check('...però no se li genera cap codi',
    (int) Db::val('SELECT COUNT(*) FROM access_codes WHERE email = :e', ['e' => 'ningu' . strtolower($unique) . '@example.test'], 0) === 0);

echo "\n== Comprovació del codi ==\n";
@unlink($jar);
$page = req('GET', $base . '/les-meves-inscripcions');
req('POST', $base . '/les-meves-inscripcions', ['_token' => token($page['body']), 'email' => $email]);
$code = AccessCode::create($email, '127.0.0.1');
$form = req('GET', $base . '/les-meves-inscripcions/codi');
$wrong = req('POST', $base . '/les-meves-inscripcions/codi', ['_token' => token($form['body']), 'code' => '000000' === $code ? '111111' : '000000']);
check('Un codi incorrecte no dona accés', $wrong['status'] === 302 && str_contains($wrong['headers'], '/les-meves-inscripcions/codi'));
check('Encara no es veuen les inscripcions',
    !str_contains(req('GET', $base . '/les-meves-inscripcions')['body'], 'Laia' . $unique));

$ok = req('POST', $base . '/les-meves-inscripcions/codi', ['_token' => token($form['body']), 'code' => $code]);
check('El codi correcte dona accés', $ok['status'] === 302 && !str_contains($ok['headers'], '/codi'), $ok['headers']);
check('El codi només val un cop',
    !AccessCode::verify($email, $code));

echo "\n== Les meves inscripcions ==\n";
$list = req('GET', $base . '/les-meves-inscripcions');
check('Es veuen els dos participants de la família',
    str_contains($list['body'], 'Laia' . $unique) && str_contains($list['body'], 'Pau' . $unique));
check('No es veuen els d\'una altra família', !str_contains($list['body'], 'Roc' . $unique));
check('Hi ha el número de dorsal', str_contains($list['body'], \Cros\Models\Bib::number($laia)));
check('Es pot descarregar el dorsal', str_contains($list['body'], '/inscripcio/dorsal/' . $laia['token']));

echo "\n== Modificar les dades ==\n";
$edit = req('GET', $base . '/les-meves-inscripcions/' . $laia['id'] . '/modificar');
check('S\'obre el formulari de modificació', $edit['status'] === 200 && str_contains($edit['body'], 'Laia' . $unique));
check('El correu no es pot canviar des del web', preg_match('/name="tutor_email"/', $edit['body']) === 0);

$saved = req('POST', $base . '/les-meves-inscripcions/' . $laia['id'] . '/modificar', [
    '_token' => token($edit['body']),
    'first_name' => 'Laieta' . $unique,
    'last_name' => 'Duran',
    'birth_year' => (string) ((int) date('Y') - 9),
    'gender' => 'femeni',
    'school' => 'Escola La Granada',
    'class_group' => 'Primària 5è',
    'shirt_size' => '12',
    'tutor_name' => 'Anna Duran',
    'tutor_phone' => '600999888',
    'notes' => 'Ve amb la germana',
    'consent_image' => '1',
]);
check('Es desen els canvis', $saved['status'] === 302);
$row = Registration::find((int) $laia['id']);
check('El nom s\'ha actualitzat', ($row['first_name'] ?? '') === 'Laieta' . $unique, (string) ($row['first_name'] ?? ''));
check('El curs s\'ha actualitzat', ($row['class_group'] ?? '') === 'Primària 5è');
check('El telèfon s\'ha actualitzat', ($row['tutor_phone'] ?? '') === '600999888');
check('El consentiment d\'imatge s\'ha activat', (int) ($row['consent_image'] ?? 0) === 1);
check('El dorsal no canvia', (int) ($row['bib_number'] ?? 0) === (int) $laia['bib_number']);
check('El correu de contacte no canvia', ($row['tutor_email'] ?? '') === $email);

$year = (int) date('Y') - 12;
req('POST', $base . '/les-meves-inscripcions/' . $laia['id'] . '/modificar', [
    '_token' => token(req('GET', $base . '/les-meves-inscripcions/' . $laia['id'] . '/modificar')['body']),
    'first_name' => 'Laieta' . $unique, 'last_name' => 'Duran', 'birth_year' => (string) $year,
    'tutor_name' => 'Anna Duran',
]);
$row = Registration::find((int) $laia['id']);
$expected = Registration::categoryForYear($year);
check('Canviar l\'any recalcula la categoria',
    (int) ($row['category_id'] ?? 0) === (int) ($expected['id'] ?? 0) && $expected !== null,
    (string) ($row['category_name'] ?? 'cap'));

$invalid = req('POST', $base . '/les-meves-inscripcions/' . $laia['id'] . '/modificar', [
    '_token' => token(req('GET', $base . '/les-meves-inscripcions/' . $laia['id'] . '/modificar')['body']),
    'first_name' => '', 'last_name' => 'Duran', 'birth_year' => (string) $year, 'tutor_name' => 'Anna Duran',
]);
check('Un nom buit no es desa', $invalid['status'] === 200 && str_contains($invalid['body'], 'field--error'));
check('La inscripció manté el nom', (Registration::find((int) $laia['id'])['first_name'] ?? '') === 'Laieta' . $unique);

echo "\n== Només les pròpies ==\n";
$other = req('GET', $base . '/les-meves-inscripcions/' . $altri['id'] . '/modificar');
check('No es pot obrir la inscripció d\'una altra família', $other['status'] === 404, 'estat ' . $other['status']);
$otherPost = req('POST', $base . '/les-meves-inscripcions/' . $altri['id'] . '/modificar', [
    '_token' => token($edit['body']),
    'first_name' => 'Segrestat', 'last_name' => 'Soler', 'birth_year' => (string) ((int) date('Y') - 10),
    'tutor_name' => 'Jordi Soler',
]);
check('Ni desar-hi canvis', $otherPost['status'] === 404
    && (Registration::find((int) $altri['id'])['first_name'] ?? '') === 'Roc' . $unique);

echo "\n== Sortir i límits ==\n";
$out = req('POST', $base . '/les-meves-inscripcions/sortir', ['_token' => token(req('GET', $base . '/les-meves-inscripcions')['body'])]);
check('Es pot sortir', $out['status'] === 302);
check('Després de sortir ja no es veuen les inscripcions',
    !str_contains(req('GET', $base . '/les-meves-inscripcions')['body'], 'Laieta' . $unique));

$limitEmail = 'limit' . strtolower($unique) . '@example.test';
Registration::create([
    'first_name' => 'Limit' . $unique, 'last_name' => 'Prova', 'birth_year' => (string) ((int) date('Y') - 10),
    'gender' => '', 'category_id' => '', 'school' => '', 'class_group' => '',
    'tutor_name' => 'Prova', 'tutor_email' => $limitEmail, 'tutor_phone' => '', 'shirt_size' => '',
    'notes' => '', 'consent_data' => 1, 'consent_image' => 0,
]);
for ($i = 0; $i < 5; $i++) {
    AccessCode::create($limitEmail, '127.0.0.1');
}
check('Es limiten els codis per adreça i hora', AccessCode::create($limitEmail, '127.0.0.1') === '');

$expiredEmail = 'caducat' . strtolower($unique) . '@example.test';
$expiredCode = AccessCode::create($expiredEmail, '127.0.0.1');
Db::update('access_codes', ['expires_at' => date('Y-m-d H:i:s', time() - 60)], 'email = :e', ['e' => $expiredEmail]);
check('Un codi caducat no val', !AccessCode::verify($expiredEmail, $expiredCode));

$bruteEmail = 'forca' . strtolower($unique) . '@example.test';
$bruteCode = AccessCode::create($bruteEmail, '127.0.0.1');
for ($i = 0; $i < 5; $i++) {
    AccessCode::verify($bruteEmail, '000000' === $bruteCode ? '111111' : '000000');
}
check('Després de cinc intents el codi es descarta', !AccessCode::verify($bruteEmail, $bruteCode));

echo "\n== Es pot desactivar ==\n";
Settings::set('registrations_selfservice', '0');
check('Sense l\'apartat actiu la pàgina no existeix', req('GET', $base . '/les-meves-inscripcions')['status'] === 404);
check('El peu de pàgina no hi enllaça',
    !str_contains(req('GET', $base . '/')['body'], '/les-meves-inscripcions'));
Settings::set('registrations_selfservice', '1');
check('En tornar-lo a activar hi torna a ser', req('GET', $base . '/les-meves-inscripcions')['status'] === 200);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
