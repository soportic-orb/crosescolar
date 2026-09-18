<?php
/**
 * Proves del circuit de cursa: esmorzar informatiu, dorsals i resultats.
 *
 * Ús:  php -S 127.0.0.1:8123 -t . tests/server.php &   i després   php tests/race.php
 */
declare(strict_types=1);

$base = getenv('CROS_TEST_URL') ?: 'http://127.0.0.1:8123';
$adminJar = sys_get_temp_dir() . '/cros-race-admin.txt';
$anonJar = sys_get_temp_dir() . '/cros-race-anon.txt';
@unlink($adminJar);
@unlink($anonJar);
$passed = 0;
$failed = 0;
$unique = 'P' . substr(bin2hex(random_bytes(3)), 0, 5);

function req(string $method, string $url, array $data = [], array $options = []): array
{
    global $adminJar, $anonJar;
    $ch = curl_init($url);
    $jar = !empty($options['anon']) ? $anonJar : $adminJar;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    if (!empty($options['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
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

function text(string $html): string
{
    return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

function isPdf(string $body): bool
{
    return str_starts_with($body, '%PDF-');
}

/** Inscriu un participant i retorna [codi, dorsal, enllaç privat]. */
function register(string $base, string $first, string $last, int $year): array
{
    $form = req('GET', $base . '/inscripcio', [], ['anon' => true]);
    $response = req('POST', $base . '/inscripcio', [
        '_token' => token($form['body']),
        'first_name' => $first,
        'last_name' => $last,
        'birth_year' => (string) $year,
        'school' => 'Escola La Granada',
        'tutor_name' => 'Tutor ' . $first,
        'tutor_email' => strtolower($first) . '@example.test',
        'consent_data' => '1',
    ], ['anon' => true]);
    preg_match('#/inscripcio/confirmada/([A-Z0-9-]+)#', $response['headers'], $m);
    $code = $m[1] ?? '';
    $page = $code !== '' ? req('GET', $base . '/inscripcio/confirmada/' . $code, [], ['anon' => true]) : ['body' => ''];
    preg_match('#<th>Dorsal</th>\s*<td>(\d+)</td>#', $page['body'], $bibMatch);
    preg_match('#/inscripcio/dorsal/([a-f0-9]{32})#', $page['body'], $tokenMatch);
    return [$code, $bibMatch[1] ?? '', $tokenMatch[1] ?? '', $page['body']];
}

/* ----------------------------------------------------------- Accés admin */
$login = req('GET', $base . '/admin/acces');
req('POST', $base . '/admin/acces', [
    '_token' => token($login['body']),
    'email' => 'admin@example.test',
    'password' => 'provaprova',
]);

echo "\n== L'esmorzar només informa ==\n";
$shop = req('GET', $base . '/esmorzar', [], ['anon' => true]);
check('La pàgina de l\'esmorzar carrega', $shop['status'] === 200);
check('Mostra els tiquets i el preu', str_contains($shop['body'], 'Esmorzar complet') && str_contains($shop['body'], '6,00'));
check('No hi ha formulari de compra', !str_contains($shop['body'], 'name="qty['));
check('No hi ha botó de pagament', !str_contains($shop['body'], 'Pagar amb targeta'));
check('Convida a inscriure\'s a la cursa', str_contains(text($shop['body']), 'Inscripcions al cros'));

$home = req('GET', $base . '/', [], ['anon' => true]);
check('El botó destacat del menú porta a les inscripcions',
    str_contains(text($home['body']), 'Inscripcions al cros') && str_contains($home['body'], '/inscripcio"'));

$blocked = req('POST', $base . '/esmorzar', [
    '_token' => token(req('GET', $base . '/els-meus-tiquets', [], ['anon' => true])['body']),
    'name' => 'Prova ' . $unique, 'email' => 'prova@example.test', 'terms' => '1', 'qty' => [1 => 1],
], ['anon' => true]);
check('No s\'accepten compres en línia', $blocked['status'] === 302);
check('La compra bloquejada no crea cap comanda',
    str_contains(req('GET', $base . '/admin/comandes?q=prova%40example.test')['body'], 'Cap comanda amb aquests filtres'));

echo "\n== Dorsals ==\n";
[$code1, $bib1, $token1] = register($base, 'Marta' . $unique, 'Soler', 2016);
[$code2, $bib2, $token2] = register($base, 'Jordi' . $unique, 'Munt', 2016);
[$code3, $bib3, $token3] = register($base, 'Nil' . $unique, 'Prats', 2014);

check('S\'assigna un número de dorsal', $bib1 !== '' && ctype_digit($bib1), 'dorsal: ' . $bib1);
check('Els dorsals són consecutius', (int) $bib2 === (int) $bib1 + 1 && (int) $bib3 === (int) $bib2 + 1,
    implode(', ', [$bib1, $bib2, $bib3]));
check('El dorsal té tres xifres', strlen($bib1) >= 3, $bib1);
check('La confirmació dona l\'enllaç del dorsal', $token1 !== '');

$bibPdf = req('GET', $base . '/inscripcio/dorsal/' . $token1, [], ['anon' => true]);
check('Es pot descarregar el dorsal', $bibPdf['status'] === 200 && isPdf($bibPdf['body']), 'estat ' . $bibPdf['status']);
check('El dorsal és un PDF amb contingut', strlen($bibPdf['body']) > 600);
$wrongToken = req('GET', $base . '/inscripcio/dorsal/' . str_repeat('0', 32), [], ['anon' => true]);
check('Un enllaç inventat no descarrega res', $wrongToken['status'] === 404);

$adminBib = req('GET', $base . '/admin/inscripcions/dorsals');
check('El panell descarrega tots els dorsals', $adminBib['status'] === 200 && isPdf($adminBib['body']));
$sample = req('GET', $base . '/admin/inscripcions/dorsal-de-prova');
check('Hi ha un dorsal de prova per al disseny', $sample['status'] === 200 && isPdf($sample['body']));
$assign = req('POST', $base . '/admin/inscripcions/assignar-dorsals', ['_token' => token(req('GET', $base . '/admin/inscripcions')['body'])]);
check('L\'assignació de dorsals pendents funciona', $assign['status'] === 302);
check('La configuració dels dorsals existeix',
    str_contains(text(req('GET', $base . '/admin/configuracio/bibs')['body']), 'Maqueta del dorsal'));

echo "\n== Resultats de la cursa ==\n";
check('Els resultats no són públics fins que es publiquen', req('GET', $base . '/resultats', [], ['anon' => true])['status'] === 404);

$panel = req('GET', $base . '/admin/resultats');
check('Hi ha el panell de resultats', $panel['status'] === 200 && str_contains(text($panel['body']), 'Arribada a meta'));
$csrf = token($panel['body']);

$arrivalHeaders = ['headers' => ['X-Requested-With: XMLHttpRequest']];
$first = req('POST', $base . '/admin/resultats/arribada', ['_token' => $csrf, 'bib' => $bib2], $arrivalHeaders);
$second = req('POST', $base . '/admin/resultats/arribada', ['_token' => $csrf, 'bib' => $bib1], $arrivalHeaders);
$other = req('POST', $base . '/admin/resultats/arribada', ['_token' => $csrf, 'bib' => $bib3], $arrivalHeaders);
$firstJson = json_decode($first['body'], true);
$secondJson = json_decode($second['body'], true);
$otherJson = json_decode($other['body'], true);

check('Registra la primera arribada', ($firstJson['status'] ?? '') === 'ok', $first['body']);
check('La primera arribada és la posició 1', str_contains((string) ($firstJson['message'] ?? ''), 'posició 1'), $firstJson['message'] ?? '');
check('La segona arribada és la posició 2', str_contains((string) ($secondJson['message'] ?? ''), 'posició 2'), $secondJson['message'] ?? '');
check('Cada categoria té la seva numeració', str_contains((string) ($otherJson['message'] ?? ''), 'posició 1'), $otherJson['message'] ?? '');

$duplicate = req('POST', $base . '/admin/resultats/arribada', ['_token' => $csrf, 'bib' => $bib1], $arrivalHeaders);
check('Avisa si el dorsal ja havia arribat', (json_decode($duplicate['body'], true)['status'] ?? '') === 'warning');
$unknown = req('POST', $base . '/admin/resultats/arribada', ['_token' => $csrf, 'bib' => '99999'], $arrivalHeaders);
check('Rebutja dorsals inexistents', (json_decode($unknown['body'], true)['status'] ?? '') === 'error');

$panel = req('GET', $base . '/admin/resultats');
check('El panell mostra la classificació', str_contains($panel['body'], 'Jordi' . $unique) && str_contains($panel['body'], 'Marta' . $unique));

$publish = req('POST', $base . '/admin/resultats/publicar', ['_token' => token($panel['body']), 'enable' => '1']);
check('Es poden publicar els resultats', $publish['status'] === 302);

$public = req('GET', $base . '/resultats', [], ['anon' => true]);
check('La pàgina pública de resultats funciona', $public['status'] === 200);
check('Els resultats surten ordenats per posició',
    strpos($public['body'], 'Jordi' . $unique) < strpos($public['body'], 'Marta' . $unique),
    'el guanyador ha de sortir primer');
check('Els resultats s\'agrupen per categoria', substr_count($public['body'], '<table class="data">') >= 2);
check('Es mostra el número de dorsal', str_contains($public['body'], $bib1));
check('El menú mostra els resultats', str_contains(req('GET', $base . '/', [], ['anon' => true])['body'], '/resultats"'));

echo "\n== Exportacions ==\n";
$byCategory = req('GET', $base . '/admin/resultats/pdf');
check('PDF de totes les categories', $byCategory['status'] === 200 && isPdf($byCategory['body']), 'estat ' . $byCategory['status']);
$arrivalPdf = req('GET', $base . '/admin/resultats/pdf?tipus=arribada');
check('PDF per ordre d\'arribada', $arrivalPdf['status'] === 200 && isPdf($arrivalPdf['body']));
$csv = req('GET', $base . '/admin/resultats/csv');
check('CSV dels resultats', $csv['status'] === 200 && str_contains($csv['headers'], 'text/csv') && str_contains($csv['body'], 'Jordi' . $unique));
$publicPdf = req('GET', $base . '/resultats/pdf', [], ['anon' => true]);
check('Descàrrega pública dels resultats', $publicPdf['status'] === 200 && isPdf($publicPdf['body']));

// Deixa el web com estava
req('POST', $base . '/admin/resultats/publicar', ['_token' => token(req('GET', $base . '/admin/resultats')['body']), 'enable' => '0']);
check('Es poden tornar a amagar els resultats', req('GET', $base . '/resultats', [], ['anon' => true])['status'] === 404);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
