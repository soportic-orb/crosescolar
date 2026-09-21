<?php
/**
 * Proves del circuit de cursa: punt de recàrrega informatiu, dorsals i resultats.
 *
 * Ús:  CROS_TEST_FRESH=1 php tests/env.php
 *      php -S 127.0.0.1:8123 -t . tests/server.php &   i després   php tests/race.php
 *
 * Cal la base de dades acabada de crear: la bateria compta arribades i medalles,
 * i les d'una execució anterior falsejarien els recomptes.
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

/**
 * Llegeix tots els camps d'un formulari del panell perquè, en desar-lo, no
 * s'esborri el que no es toca (els botons desactivats no s'envien).
 */
function formData(string $html): array
{
    $data = [];
    preg_match_all('/<input[^>]*>/i', $html, $inputs);
    foreach ($inputs[0] as $tag) {
        if (!preg_match('/name="([^"]+)"/', $tag, $name)) {
            continue;
        }
        if (str_ends_with($name[1], '[]')) {
            continue; // llistes (recorreguts i voltes): les posa qui crida la funció
        }
        if (preg_match('/type="(checkbox|file|radio)"/i', $tag, $type)) {
            if (strtolower($type[1]) === 'checkbox' && str_contains($tag, 'checked')) {
                $data[$name[1]] = '1';
            }
            continue;
        }
        preg_match('/value="([^"]*)"/', $tag, $value);
        $data[$name[1]] = html_entity_decode($value[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    preg_match_all('#<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>#s', $html, $areas, PREG_SET_ORDER);
    foreach ($areas as $area) {
        $data[$area[1]] = html_entity_decode($area[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    preg_match_all('#<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>#s', $html, $selects, PREG_SET_ORDER);
    foreach ($selects as $select) {
        if (str_ends_with($select[1], '[]')) {
            continue;
        }
        if (preg_match('/<option value="([^"]*)"[^>]*selected/', $select[2], $option)) {
            $data[$select[1]] = html_entity_decode($option[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $data;
}

/** Desa la configuració d'un grup canviant només el que s'indica. */
function saveSettings(string $base, string $group, array $changes): array
{
    $form = req('GET', $base . '/admin/configuracio/' . $group);
    return req('POST', $base . '/admin/configuracio/' . $group, array_merge(formData($form['body']), $changes));
}

/** Identificador d'una categoria a partir del seu nom. */
function categoryId(string $base, string $name): int
{
    $list = req('GET', $base . '/admin/contingut/categories');
    preg_match_all('#<tr[^>]*data-id="(\d+)"(.*?)</tr>#s', $list['body'], $rows, PREG_SET_ORDER);
    foreach ($rows as $row) {
        if (str_contains(text($row[2]), $name)) {
            return (int) $row[1];
        }
    }
    return 0;
}

/** Desa una categoria canviant només el que s'indica. */
function saveCategory(string $base, int $id, array $changes): array
{
    $form = req('GET', $base . '/admin/contingut/categories/' . $id);
    return req('POST', $base . '/admin/contingut/categories/' . $id, array_merge(formData($form['body']), $changes));
}

/** Quantes medalles hi ha a la classificació pública. */
function medals(string $html): int
{
    return substr_count($html, '<circle cx="12" cy="15" r="5"/>');
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
        'consent_rules' => '1',
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

echo "\n== El punt de recàrrega només informa ==\n";
$shop = req('GET', $base . '/punt-de-recarrega', [], ['anon' => true]);
check('La pàgina del punt de recàrrega carrega', $shop['status'] === 200);
check('Mostra els tiquets i el preu', str_contains($shop['body'], 'Esmorzar complet') && str_contains($shop['body'], '6,00'));
check('No hi ha formulari de compra', !str_contains($shop['body'], 'name="qty['));
check('No hi ha botó de pagament', !str_contains($shop['body'], 'Pagar amb targeta'));
check('Convida a inscriure\'s a la cursa', str_contains(text($shop['body']), 'Inscripcions al cros'));

$home = req('GET', $base . '/', [], ['anon' => true]);
check('El botó destacat del menú porta a les inscripcions',
    str_contains(text($home['body']), 'Inscriu-te!') && str_contains($home['body'], '/inscripcio"'));

$blocked = req('POST', $base . '/punt-de-recarrega', [
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

echo "\n== Es pot treure la descàrrega del dorsal ==\n";
check('Hi ha l\'opció a la configuració de dorsals',
    str_contains(text(req('GET', $base . '/admin/configuracio/bibs')['body']), 'Les famílies poden descarregar el dorsal'));

$off = saveSettings($base, 'bibs', ['bib_public_download' => '0']);
check('Es pot desactivar', $off['status'] === 302);
check('L\'enllaç del dorsal deixa d\'existir',
    req('GET', $base . '/inscripcio/dorsal/' . $token1, [], ['anon' => true])['status'] === 404);
check('I el de tots els dorsals també',
    req('GET', $base . '/inscripcio/dorsals/' . $token1, [], ['anon' => true])['status'] === 404);
$done = req('GET', $base . '/inscripcio/confirmada/' . $code1, [], ['anon' => true]);
check('La confirmació no ofereix descarregar-lo',
    !str_contains($done['body'], '/inscripcio/dorsal/') && !str_contains(text($done['body']), 'Descarregar el dorsal'),
    'no n\'ha de quedar rastre');
check('...però el número del dorsal es continua veient', str_contains($done['body'], $bib1));
check('El panell sí que el pot descarregar',
    req('GET', $base . '/admin/inscripcions/dorsals')['status'] === 200);

saveSettings($base, 'bibs', ['bib_public_download' => '1']);
check('En tornar-ho a activar, l\'enllaç torna a servir',
    req('GET', $base . '/inscripcio/dorsal/' . $token1, [], ['anon' => true])['status'] === 200);
check('I la confirmació el torna a oferir',
    str_contains(req('GET', $base . '/inscripcio/confirmada/' . $code1, [], ['anon' => true])['body'], '/inscripcio/dorsal/'));

echo "\n== Dorsals des del panell ==\n";
$newForm = req('GET', $base . '/admin/inscripcions/nova');
check('El formulari diu quin dorsal tocarà', str_contains($newForm['body'], 'assigna sol el següent lliure'));

$expected = (int) $bib3 + 1;
$manual = req('POST', $base . '/admin/inscripcions/nova', [
    '_token' => token($newForm['body']),
    // Sense número de dorsal: l'ha d'assignar el sistema.
    'first_name' => 'Ona' . $unique, 'last_name' => 'Ferrer', 'birth_year' => '2016',
    'tutor_name' => 'Marta Ferrer', 'tutor_email' => 'ona' . strtolower($unique) . '@example.test',
    'status' => 'confirmed', 'consent_data' => '1',
]);
preg_match('#/admin/inscripcions/(\d+)#', $manual['headers'], $m);
$manualId = (int) ($m[1] ?? 0);
check('Es pot inscriure algú des del panell', $manual['status'] === 302 && $manualId > 0, $manual['headers']);

$fitxa = req('GET', $base . '/admin/inscripcions/' . $manualId);
check('El panell li assigna el dorsal següent',
    preg_match('#name="bib_number" value="' . $expected . '"#', $fitxa['body']) === 1,
    'esperat ' . $expected);
check('També li dona l\'enllaç privat del dorsal',
    req('GET', $base . '/admin/inscripcions/' . $manualId . '/dorsal')['status'] === 200);

$taken = req('POST', $base . '/admin/inscripcions/' . $manualId, array_merge(formData($fitxa['body']), ['bib_number' => $bib1]));
check('No deixa repetir un dorsal', $taken['status'] === 200 && str_contains(text($taken['body']), 'ja és d\'un altre participant'),
    'estat ' . $taken['status']);
check('I el dorsal no canvia',
    preg_match('#name="bib_number" value="' . $expected . '"#', req('GET', $base . '/admin/inscripcions/' . $manualId)['body']) === 1);

$free = $expected + 500;
$changed = req('POST', $base . '/admin/inscripcions/' . $manualId, array_merge(formData($fitxa['body']), ['bib_number' => (string) $free]));
check('Es pot canviar a un número lliure', $changed['status'] === 302);
check('El número nou queda desat',
    preg_match('#name="bib_number" value="' . $free . '"#', req('GET', $base . '/admin/inscripcions/' . $manualId)['body']) === 1);

[, $bib4] = register($base, 'Bru' . $unique, 'Roig', 2016);
check('Les inscripcions del web continuen la numeració', (int) $bib4 === $free + 1, $bib4 . ' després de ' . $free);

$emptied = req('POST', $base . '/admin/inscripcions/' . $manualId,
    array_merge(formData(req('GET', $base . '/admin/inscripcions/' . $manualId)['body']), ['bib_number' => '']));
check('Si es buida el camp, se n\'hi posa un de nou', $emptied['status'] === 302
    && preg_match('#name="bib_number" value="(\d+)"#', req('GET', $base . '/admin/inscripcions/' . $manualId)['body'], $after) === 1
    && (int) $after[1] > 0, 'ha de tenir dorsal igualment');

echo "\n== Recorreguts i voltes ==\n";
$categoria = categoryId($base, 'Aleví');
$fitxaCat = req('GET', $base . '/admin/contingut/categories/' . $categoria);
check('La fitxa de la categoria té el camp de recorreguts i voltes',
    str_contains($fitxaCat['body'], 'data-laps') && str_contains($fitxaCat['body'], 'courses_laps[]'));
check('Hi surt el recorregut que ja tenia', substr_count($fitxaCat['body'], 'selected') >= 1);

$courseIds = [];
preg_match_all('#<select[^>]*name="courses\[\]".*?</select>#s', $fitxaCat['body'], $selects);
preg_match_all('#<option value="(\d+)"#', $selects[0][0] ?? '', $options);
$courseIds = array_map('intval', $options[1] ?? []);
check('Hi ha més d\'un recorregut per triar', count($courseIds) >= 2, implode(',', $courseIds));

$saved = req('POST', $base . '/admin/contingut/categories/' . $categoria, array_merge(
    formData($fitxaCat['body']),
    ['courses' => [$courseIds[0], $courseIds[1]], 'courses_laps' => ['1', '2']]
));
check('Es poden desar dos recorreguts', $saved['status'] === 302, 'estat ' . $saved['status']);

$public = req('GET', $base . '/categories-i-premis', [], ['anon' => true]);
check('El web mostra els dos recorreguts en ordre',
    preg_match('#2 voltes#', $public['body']) === 1 && substr_count($public['body'], ' + ') >= 1,
    'composició del recorregut');
check('Amb una sola volta també ho diu', str_contains($public['body'], '1 volta'), 'ha de dir «1 volta»');

$reopened = req('GET', $base . '/admin/contingut/categories/' . $categoria);
preg_match_all('#<select[^>]*name="courses\[\]".*?</select>#s', $reopened['body'], $rows);
check('Es tornen a carregar les dues files', count($rows[0]) === 3, count($rows[0]) . ' files (2 + la buida)');
check('Les voltes es conserven', preg_match('#name="courses_laps\[\]" value="2"#', $reopened['body']) === 1);

// L'ordre és el que s'envia: es giren i s'ha de veure girat.
req('POST', $base . '/admin/contingut/categories/' . $categoria, array_merge(
    formData($reopened['body']),
    ['courses' => [$courseIds[1], $courseIds[0]], 'courses_laps' => ['2', '1']]
));
$afterSwap = req('GET', $base . '/admin/contingut/categories/' . $categoria);
preg_match_all('#<select[^>]*name="courses\[\]".*?</select>#s', $afterSwap['body'], $swapped);
preg_match('#<option value="(\d+)" selected#', $swapped[0][0] ?? '', $firstNow);
check('Es pot canviar l\'ordre dels recorreguts', (int) ($firstNow[1] ?? 0) === $courseIds[1],
    'primer ara: ' . ($firstNow[1] ?? '—'));

// Deixa la categoria amb un sol recorregut, com estava.
req('POST', $base . '/admin/contingut/categories/' . $categoria, array_merge(
    formData($afterSwap['body']),
    ['courses' => [$courseIds[1]], 'courses_laps' => ['1']]
));
check('Es pot tornar a deixar amb un de sol',
    substr_count(req('GET', $base . '/admin/contingut/categories/' . $categoria)['body'], 'name="courses[]"') === 2);

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

echo "\n== Medalles per categoria ==\n";
$alevi = categoryId($base, 'Aleví');
$infantil = categoryId($base, 'Infantil');
check('Es troben les categories al panell', $alevi > 0 && $infantil > 0, $alevi . '/' . $infantil);
check('La fitxa de la categoria porta l\'opció de medalles',
    str_contains(text(req('GET', $base . '/admin/contingut/categories/' . $alevi)['body']), 'Marcar els guanyadors amb medalla'));
check('El llistat de categories mostra els premiats',
    str_contains(text(req('GET', $base . '/admin/contingut/categories')['body']), 'Premiats'));
// Hi ha dos classificats a la categoria aleví i un a la infantil.
check('Per defecte els tres primers porten medalla', medals($public['body']) === 3, (string) medals($public['body']));

saveCategory($base, $alevi, ['medals' => '1', 'winners' => '1']);
$one = req('GET', $base . '/resultats', [], ['anon' => true]);
check('Amb un sol premiat, la categoria només en marca un', medals($one['body']) === 2, (string) medals($one['body']));
check('El segon continua sortint amb la posició', str_contains($one['body'], '<strong>2</strong>'));

saveCategory($base, $alevi, ['medals' => '0', 'winners' => '3']);
$off = req('GET', $base . '/resultats', [], ['anon' => true]);
check('Es poden treure les medalles d\'una categoria', medals($off['body']) === 1, (string) medals($off['body']));
check('Les altres categories no queden afectades', medals($off['body']) === 1);
check('Al llistat hi diu que no en té',
    str_contains(text(req('GET', $base . '/admin/contingut/categories')['body']), 'Sense medalles'));

saveCategory($base, $infantil, ['medals' => '0', 'winners' => '3']);
check('Sense cap categoria amb medalles no en surt cap',
    medals(req('GET', $base . '/resultats', [], ['anon' => true])['body']) === 0);

saveCategory($base, $alevi, ['medals' => '1', 'winners' => '5']);
saveCategory($base, $infantil, ['medals' => '1', 'winners' => '3']);
check('En tornar-les a activar, tots els classificats en porten',
    medals(req('GET', $base . '/resultats', [], ['anon' => true])['body']) === 3);
check('Els altres camps de la categoria no s\'han perdut',
    str_contains(req('GET', $base . '/categories-i-premis', [], ['anon' => true])['body'], '1.000 m'));
saveCategory($base, $alevi, ['medals' => '1', 'winners' => '3']);

echo "\n== Anys de la categoria ==\n";
$prebenjami = categoryId($base, 'Prebenjamí');
check('Es troba la categoria petita', $prebenjami > 0);
saveCategory($base, $prebenjami, ['year_from' => '2020', 'year_to' => '2020']);
$publicCats = text(req('GET', $base . '/categories-i-premis', [], ['anon' => true])['body']);
check('Amb un sol any, la web no en mostra dos',
    !str_contains($publicCats, '2020–2020') && preg_match('#Prebenjamí.*?2020#s', $publicCats) === 1);
check('El panell tampoc',
    !str_contains(text(req('GET', $base . '/admin/contingut/categories')['body']), '2020–2020'));
check('El formulari d\'inscripció tampoc',
    !str_contains(text(req('GET', $base . '/inscripcio', [], ['anon' => true])['body']), '2020–2020'));

saveCategory($base, $prebenjami, ['year_from' => '2020', 'year_to' => '2021']);
check('Amb dos anys diferents, es mostren tots dos',
    str_contains(text(req('GET', $base . '/categories-i-premis', [], ['anon' => true])['body']), '2020–2021'));

echo "\n== Premi «Primer local» ==\n";

/** La fila d'un participant dins d'una taula de resultats. */
function resultRow(string $html, string $name): string
{
    if (preg_match_all('#<tr>(.*?)</tr>#s', $html, $matches)) {
        foreach ($matches[1] as $row) {
            if (str_contains($row, $name)) {
                return $row;
            }
        }
    }
    return '';
}

/** Identificador de l'arribada d'un participant, llegit del panell de resultats. */
function resultRowId(string $html, string $name): int
{
    if (preg_match_all('#<tr>(.*?)</tr>#s', $html, $matches)) {
        foreach ($matches[1] as $row) {
            if (str_contains($row, $name) && preg_match('#/admin/resultats/(\d+)/premi-local#', $row, $id)) {
                return (int) $id[1];
            }
        }
    }
    return 0;
}

check('La fitxa de la categoria porta el premi local',
    str_contains(text(req('GET', $base . '/admin/contingut/categories/' . $alevi)['body']), 'Premi «Primer local»'));
check('El nom del premi i l\'escola es configuren a Categories i premis',
    str_contains(text(req('GET', $base . '/admin/configuracio/categories')['body']), 'Nom del premi local'));

$panel = req('GET', $base . '/admin/resultats');
check('El panell hi té una columna per marcar-lo', str_contains(text($panel['body']), 'Primer local'));
check('Assenyala qui és de l\'escola del poble', str_contains(text($panel['body']), 'title="Escola del poble">local'));

$jordiResult = resultRowId($panel['body'], 'Jordi' . $unique);
$martaResult = resultRowId($panel['body'], 'Marta' . $unique);
check('Es troben les arribades al panell', $jordiResult > 0 && $martaResult > 0, $jordiResult . '/' . $martaResult);

$marked = req('POST', $base . '/admin/resultats/' . $jordiResult . '/premi-local',
    ['_token' => token($panel['body']), 'enable' => '1']);
check('Es marca el guanyador', $marked['status'] === 302);
$publicLocal = text(req('GET', $base . '/resultats', [], ['anon' => true])['body']);
check('El premi surt al costat del seu nom',
    str_contains(resultRow($publicLocal, 'Jordi' . $unique), 'Primer local'),
    resultRow($publicLocal, 'Jordi' . $unique));
check('Només el porta una persona', substr_count($publicLocal, 'Primer local') === 1, (string) substr_count($publicLocal, 'Primer local'));

// Marcar-ne un altre allibera l'anterior: només n'hi pot haver un per categoria.
req('POST', $base . '/admin/resultats/' . $martaResult . '/premi-local',
    ['_token' => token(req('GET', $base . '/admin/resultats')['body']), 'enable' => '1']);
$publicLocal = text(req('GET', $base . '/resultats', [], ['anon' => true])['body']);
check('En marcar-ne un altre, el primer el perd', substr_count($publicLocal, 'Primer local') === 1);
check('I ara el porta qui toca',
    str_contains(resultRow($publicLocal, 'Marta' . $unique), 'Primer local')
    && !str_contains(resultRow($publicLocal, 'Jordi' . $unique), 'Primer local'));

// El CSV se l'emporta.
$csvLocal = req('GET', $base . '/admin/resultats/csv');
check('El CSV porta la columna del premi', str_contains($csvLocal['body'], 'Premi local'));
check('I qui l\'ha guanyat', preg_match('#Marta' . $unique . '[^\n]*Primer local#', $csvLocal['body']) === 1);

// Desmarcar-lo el treu del web.
req('POST', $base . '/admin/resultats/' . $martaResult . '/premi-local',
    ['_token' => token(req('GET', $base . '/admin/resultats')['body']), 'enable' => '0']);
check('Es pot desmarcar',
    !str_contains(text(req('GET', $base . '/resultats', [], ['anon' => true])['body']), 'Primer local'));

// Una categoria que no el dona no l'ensenya enlloc.
saveCategory($base, $alevi, ['local_prize' => '0']);
$panelOff = req('GET', $base . '/admin/resultats');
check('Sense premi a la categoria, no hi ha res per marcar',
    resultRowId($panelOff['body'], 'Jordi' . $unique) === 0);
$catPage = text(req('GET', $base . '/categories-i-premis', [], ['anon' => true])['body']);
check('I la pàgina de categories tampoc l\'anuncia en aquesta categoria',
    !str_contains(resultRow($catPage, 'Aleví'), 'Primer local') && str_contains($catPage, 'Primer local'),
    'les altres categories l\'han de conservar');

saveCategory($base, $alevi, ['local_prize' => '1']);
check('En tornar-lo a activar, la pàgina de categories l\'anuncia',
    str_contains(text(req('GET', $base . '/categories-i-premis', [], ['anon' => true])['body']), 'Primer local'));

// El nom del premi es pot canviar.
saveSettings($base, 'categories', ['prizes_local_label' => 'Primer del poble']);
req('POST', $base . '/admin/resultats/' . $jordiResult . '/premi-local',
    ['_token' => token(req('GET', $base . '/admin/resultats')['body']), 'enable' => '1']);
check('El nom del premi es pot canviar',
    str_contains(text(req('GET', $base . '/resultats', [], ['anon' => true])['body']), 'Primer del poble'));
saveSettings($base, 'categories', ['prizes_local_label' => 'Primer local']);

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
