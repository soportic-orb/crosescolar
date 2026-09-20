<?php
/**
 * Proves funcionals: executen l'aplicació sencera contra el servidor local.
 * Ús:  php -S 127.0.0.1:8123 -t . tests/server.php   i després   php tests/functional.php
 */
declare(strict_types=1);

$base = getenv('CROS_TEST_URL') ?: 'http://127.0.0.1:8123';
$jar = sys_get_temp_dir() . '/cros-test-cookies.txt';
$adminJar = $jar;
@unlink($jar);
@unlink(sys_get_temp_dir() . '/cros-test-anon.txt');
$passed = 0;
$failed = 0;
$unique = 'P' . substr(bin2hex(random_bytes(3)), 0, 5); // fa que cada execució sigui independent

function req(string $method, string $url, array $data = [], array $options = []): array
{
    global $jar;
    // Amb «anon» la petició es fa sense la sessió d'administració (visitant anònim).
    $jar = !empty($options['anon']) ? sys_get_temp_dir() . '/cros-test-anon.txt' : $GLOBALS['adminJar'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $options['follow'] ?? false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, isset($options['raw']) ? $options['raw'] : http_build_query($data));
    }
    if (!empty($options['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
    }
    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}

function token(string $html): string
{
    return preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

/** Text de la pàgina amb les entitats HTML descodificades (apòstrofs, accents…). */
function text(string $html): string
{
    return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_from(string $base): string
{
    return token(req('GET', $base . '/admin')['body']);
}

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  OK   $name\n";
    } else {
        $failed++;
        echo "  FALLA $name" . ($detail !== '' ? " → $detail" : '') . "\n";
    }
}

echo "\n== Pàgines públiques ==\n";
$home = req('GET', $base . '/');
check('La portada carrega', $home['status'] === 200);
check('Mostra el compte enrere', str_contains($home['body'], 'data-countdown'));
check('Mostra els recorreguts', str_contains($home['body'], 'Circuit mitjà'));
check('Mostra els patrocinadors', str_contains($home['body'], 'Ajuntament de La Granada'));
check('Mostra el programa', str_contains($home['body'], 'Lliurament de premis'));

$categories = req('GET', $base . '/categories-i-premis');
check('Categories i premis', $categories['status'] === 200 && str_contains($categories['body'], 'Benjamí'));
check('Premis visibles', str_contains($categories['body'], 'Trofeus per categoria'));
check('El codi intern de la categoria no es publica',
    !preg_match('#<div class="text-soft" style="font-size:.85rem">(BEN|ALE|INF)</div>#', $categories['body']));

$tickets = req('GET', $base . '/punt-de-recarrega');
check('Pàgina del punt de recàrrega', $tickets['status'] === 200 && str_contains($tickets['body'], 'Esmorzar complet'));
check('La pàgina es diu «punt de recàrrega»', str_contains(text($tickets['body']), 'Punt de recàrrega'));
$vella = req('GET', $base . '/esmorzar');
check('L\'adreça antiga redirigeix a la nova',
    $vella['status'] === 301 && str_contains($vella['headers'], '/punt-de-recarrega'), 'estat ' . $vella['status']);

echo "\n== Menú principal ==\n";
$menu = req('GET', $base . '/', [], ['anon' => true]);
preg_match('#<nav class="nav".*?</nav>#s', $menu['body'], $nav);
$nav = text($nav[0] ?? '');
check('El botó del menú diu «Inscriu-te!»', str_contains($nav, 'Inscriu-te!') && str_contains($nav, '/inscripcio"'), $nav);
check('El menú porta a «Les meves inscripcions»', str_contains($nav, 'Les meves inscripcions'));
check('El menú porta al punt de recàrrega des del peu', str_contains(text($menu['body']), 'Punt de recàrrega'));
check('El contacte ja no surt al menú', !str_contains($nav, 'Contacte'), $nav);
check('...però sí al peu de pàgina', str_contains(text($menu['body']), 'Formulari de contacte'));

echo "\n== Imatge del web ==\n";
check('Les tipografies se serveixen des del mateix servidor',
    str_contains($menu['body'], 'css/fonts.css') && !str_contains($menu['body'], 'fonts.googleapis.com'));
check('Els fitxers de lletra hi són',
    is_file(__DIR__ . '/../assets/fonts/cabin-sketch-700-latin.woff2')
    && is_file(__DIR__ . '/../assets/fonts/dm-sans-latin.woff2'));
check('La portada porta motius de vinya', substr_count($menu['body'], 'hero__motifs') === 1
    && substr_count($menu['body'], '<circle') > 5, 'motius a la franja inferior');
check('Hi ha el separador de fulles', str_contains($menu['body'], 'divider-vines'));
$header = req('GET', $base . '/categories-i-premis', [], ['anon' => true]);
check('Les capçaleres interiors també en porten', str_contains($header['body'], 'page-header__vines'));

echo "\n== Inscripció pública ==\n";
$form = req('GET', $base . '/inscripcio');
$csrf = token($form['body']);
preg_match('#<select id="gender".*?</select>#s', $form['body'], $genderField);
check('El gènere només ofereix femení i masculí',
    substr_count($genderField[0] ?? '', '<option') === 3
    && str_contains($genderField[0] ?? '', 'Femení') && str_contains($genderField[0] ?? '', 'Masculí')
    && !str_contains($genderField[0] ?? '', 'Altre'), $genderField[0] ?? 'sense camp');
preg_match('#<select id="class_group".*?</select>#s', $form['body'], $courseField);
check('El curs es tria d\'una llista amb els nou cursos de l\'escola',
    substr_count($courseField[0] ?? '', '<option') === 10
    && str_contains($courseField[0] ?? '', 'Infantil 1er')
    && str_contains($courseField[0] ?? '', 'Primària 6è'), $courseField[0] ?? 'sense camp');
$registration = req('POST', $base . '/inscripcio', [
    '_token' => $csrf,
    'first_name' => 'Laia',
    'last_name' => 'Ferrer ' . $unique,
    'birth_year' => (string) ((int) date('Y') - 9),
    'gender' => 'femeni',
    'category_id' => '',
    'school' => 'Escola La Granada',
    'class_group' => 'Primària 4rt',
    'tutor_name' => 'Marc Ferrer',
    'tutor_email' => 'families@example.test',
    'tutor_phone' => '600000000',
    'consent_data' => '1',
]);
check('La inscripció es desa', $registration['status'] === 302 && str_contains($registration['headers'], '/inscripcio/confirmada/'), 'estat ' . $registration['status']);
preg_match('#/inscripcio/confirmada/([A-Z0-9-]+)#', $registration['headers'], $m);
$done = req('GET', $base . '/inscripcio/confirmada/' . ($m[1] ?? 'X'));
check('Pàgina de confirmació', $done['status'] === 200 && str_contains($done['body'], 'Laia'));
check('Assigna la categoria per any', str_contains($done['body'], 'Aleví'), 'categoria assignada automàticament');

$duplicate = req('POST', $base . '/inscripcio', [
    '_token' => token(req('GET', $base . '/inscripcio')['body']),
    'first_name' => 'Laia', 'last_name' => 'Ferrer ' . $unique, 'birth_year' => (string) ((int) date('Y') - 9),
    'tutor_name' => 'Marc Ferrer', 'tutor_email' => 'families@example.test', 'consent_data' => '1',
]);
check('Evita inscripcions duplicades', $duplicate['status'] === 302 && !str_contains($duplicate['headers'], 'confirmada'));

// Un enviament fabricat a mà no pot desar valors que no són al formulari.
req('POST', $base . '/inscripcio', [
    '_token' => token(req('GET', $base . '/inscripcio')['body']),
    'first_name' => 'Nil', 'last_name' => 'Fora ' . $unique, 'birth_year' => (string) ((int) date('Y') - 10),
    'gender' => 'altre', 'class_group' => 'Batxillerat',
    'tutor_name' => 'Marc Ferrer', 'tutor_email' => 'families@example.test', 'consent_data' => '1',
]);

echo "\n== Seguretat ==\n";
$noCsrf = req('POST', $base . '/inscripcio', ['first_name' => 'X', 'last_name' => 'Y']);
check('Rebutja formularis sense token CSRF', $noCsrf['status'] === 419, 'estat ' . $noCsrf['status']);
$adminRedirect = req('GET', $base . '/admin/comandes');
check('El panell requereix sessió', $adminRedirect['status'] === 302 && str_contains($adminRedirect['headers'], '/admin/acces'));
$badLogin = req('POST', $base . '/admin/acces', ['_token' => token(req('GET', $base . '/admin/acces')['body']), 'email' => 'admin@example.test', 'password' => 'incorrecta']);
check('Rebutja credencials incorrectes', $badLogin['status'] === 302 && str_contains($badLogin['headers'], '/admin/acces'));

echo "\n== Panell d'administració ==\n";
$login = req('POST', $base . '/admin/acces', [
    '_token' => token(req('GET', $base . '/admin/acces')['body']),
    'email' => 'admin@example.test',
    'password' => 'provaprova',
]);
check('Accés correcte al panell', $login['status'] === 302);
$dashboard = req('GET', $base . '/admin');
check('Tauler accessible', $dashboard['status'] === 200 && str_contains($dashboard['body'], 'Recaptat'));

$newSponsor = req('GET', $base . '/admin/contingut/patrocinadors/nou');
$created = req('POST', $base . '/admin/contingut/patrocinadors/nou', [
    '_token' => token($newSponsor['body']),
    'name' => 'Celler de Prova SL',
    'url' => 'https://exemple.test',
    'tier' => 'principal',
    'description' => 'Vins del Penedès',
    'sort_order' => '5',
    'active' => '1',
]);
check('Crea un patrocinador', $created['status'] === 302);
$list = req('GET', $base . '/admin/contingut/patrocinadors');
check('Apareix al llistat', str_contains($list['body'], 'Celler de Prova SL'));
check('Apareix a la portada', str_contains(req('GET', $base . '/')['body'], 'Celler de Prova SL'));

$settingsForm = req('GET', $base . '/admin/configuracio/general');
$saved = req('POST', $base . '/admin/configuracio/general', [
    '_token' => token($settingsForm['body']),
    'site_name' => 'Cros Escolar La Granada',
    'site_tagline' => 'Corrent entre vinyes',
    'event_date' => '2026-10-04',
    'event_time' => '09:30',
    'pretty_urls' => '1',
]);
check('Desa la configuració', $saved['status'] === 302);
check('El nou lema surt al web', str_contains(req('GET', $base . '/')['body'], 'Corrent entre vinyes'));

$xss = req('POST', $base . '/admin/configuracio/home', [
    '_token' => token(req('GET', $base . '/admin/configuracio/home')['body']),
    'countdown_enabled' => '1',
    'intro_title' => 'La cursa del poble',
    'intro_text' => '<p>Text correcte</p><script>alert(1)</script><a href="javascript:alert(2)">enllaç</a>',
]);
$homeAfter = req('GET', $base . '/');
check('Neteja l\'HTML perillós', !str_contains($homeAfter['body'], '<script>alert(1)') && !str_contains($homeAfter['body'], 'javascript:alert(2)'));
check('Conserva l\'HTML permès', str_contains($homeAfter['body'], 'Text correcte'));

echo "\n== Venda manual i tiquets ==\n";
$orderForm = req('GET', $base . '/admin/comandes/nova');
$order = req('POST', $base . '/admin/comandes/nova', [
    '_token' => token($orderForm['body']),
    'name' => 'Família Rovira',
    'email' => 'rovira@example.test',
    'qty' => [1 => 2, 3 => 1],
    'method' => 'cash',
    'mark_paid' => '1',
]);
check('Crea la comanda manual', $order['status'] === 302 && preg_match('#/admin/comandes/(\d+)#', $order['headers']) === 1);
preg_match('#/admin/comandes/(\d+)#', $order['headers'], $om);
$orderId = (int) ($om[1] ?? 0);
$orderPage = req('GET', $base . '/admin/comandes/' . $orderId);
check('Detall de la comanda', $orderPage['status'] === 200 && str_contains($orderPage['body'], 'Família Rovira'));
preg_match_all('/<td class="mono">(T[A-Z0-9]{9})<\/td>/', $orderPage['body'], $tm);
$ticketCodes = $tm[1] ?? [];
check('Genera un tiquet per unitat', count($ticketCodes) === 3, count($ticketCodes) . ' tiquets');
check('Import correcte (14,00 €)', str_contains($orderPage['body'], '14,00'));

preg_match('#/tiquets/([a-f0-9]{48})#', $orderPage['body'], $tokm);
$publicToken = $tokm[1] ?? '';
$publicPage = req('GET', $base . '/tiquets/' . $publicToken);
check('Pàgina pública de tiquets', $publicPage['status'] === 200 && str_contains($publicPage['body'], $ticketCodes[0] ?? 'X'));
$print = req('GET', $base . '/tiquets/' . $publicToken . '/imprimir');
check('Versió imprimible amb QR', $print['status'] === 200 && str_contains($print['body'], 'data:image/png;base64,'));
$qr = req('GET', $base . '/qr/' . ($ticketCodes[0] ?? 'X'));
check('Imatge QR del tiquet', $qr['status'] === 200 && str_starts_with($qr['body'], "\x89PNG"));

$lookupForm = req('GET', $base . '/els-meus-tiquets');
preg_match('/Comanda ([A-Z0-9-]+)</', $orderPage['body'], $cm);
$orderCode = $cm[1] ?? '';
$lookup = req('POST', $base . '/els-meus-tiquets', [
    '_token' => token($lookupForm['body']),
    'code' => $orderCode,
    'email' => 'rovira@example.test',
]);
check('Consulta de tiquets per codi i correu', $lookup['status'] === 302 && str_contains($lookup['headers'], '/tiquets/' . $publicToken));
$wrongLookup = req('POST', $base . '/els-meus-tiquets', [
    '_token' => token(req('GET', $base . '/els-meus-tiquets')['body']),
    'code' => $orderCode,
    'email' => 'altre@example.test',
]);
check('Rebutja consultes amb dades incorrectes', !str_contains($wrongLookup['headers'], '/tiquets/' . $publicToken));

echo "\n== Validació de tiquets ==\n";
$scanner = req('GET', $base . '/admin/validacio');
$validate = req('POST', $base . '/admin/validacio', ['_token' => token($scanner['body']), 'code' => $ticketCodes[0] ?? ''],
    ['headers' => ['X-Requested-With: XMLHttpRequest']]);
$result = json_decode($validate['body'], true);
check('Valida un tiquet correcte', ($result['status'] ?? '') === 'ok', $validate['body']);
$again = req('POST', $base . '/admin/validacio', ['_token' => token($scanner['body']), 'code' => $ticketCodes[0] ?? ''],
    ['headers' => ['X-Requested-With: XMLHttpRequest']]);
$resultAgain = json_decode($again['body'], true);
check('Avisa si el tiquet ja s\'ha validat', ($resultAgain['status'] ?? '') === 'warning');
$unknown = req('POST', $base . '/admin/validacio', ['_token' => token($scanner['body']), 'code' => 'TZZZZZZZZZ'],
    ['headers' => ['X-Requested-With: XMLHttpRequest']]);
check('Rebutja codis inexistents', (json_decode($unknown['body'], true)['status'] ?? '') === 'error');

echo "\n== Compra amb Stripe (credencials falses) ==\n";
// El web va en mode informatiu; per provar la botiga s'activa la venda en línia.
req('POST', $base . '/admin/configuracio/tickets', [
    '_token' => token(req('GET', $base . '/admin/configuracio/tickets')['body']),
    'tickets_public_mode' => 'sale',
    'tickets_enabled' => '1',
    'tickets_title' => 'Tiquets del punt de recàrrega',
    'tickets_deadline' => '2026-10-02',
    'tickets_max_per_order' => '20',
]);
$shopForm = req('GET', $base . '/punt-de-recarrega');
check('Amb la venda activada apareix la botiga', str_contains($shopForm['body'], 'name="qty['));
$checkout = req('POST', $base . '/punt-de-recarrega', [
    '_token' => token($shopForm['body']),
    'name' => 'Núria Casals',
    'email' => 'nuria@example.test',
    'phone' => '600111222',
    'terms' => '1',
    'qty' => [1 => 1],
]);
check('La compra no peta amb credencials invàlides', $checkout['status'] === 302 && str_contains($checkout['headers'], '/punt-de-recarrega'), 'estat ' . $checkout['status']);
$shopAfter = req('GET', $base . '/punt-de-recarrega');
check('Informa l\'usuari de l\'error de pagament', str_contains($shopAfter['body'], 'alert--error'));

$closeForm = req('GET', $base . '/admin/configuracio/tickets');
req('POST', $base . '/admin/configuracio/tickets', [
    '_token' => token($closeForm['body']),
    // Sense «tickets_enabled»: la venda queda tancada tot i ser en mode botiga.
    'tickets_public_mode' => 'sale',
    'tickets_title' => 'Tiquets del punt de recàrrega',
    'tickets_deadline' => '2026-10-02',
    'tickets_max_per_order' => '20',
]);
$closedShop = req('GET', $base . '/punt-de-recarrega');
check('Tanca la venda si es desactiva', str_contains($closedShop['body'], 'venda anticipada de tiquets ja està tancada') || str_contains($closedShop['body'], 'notice-box'));
$validToken = token(req('GET', $base . '/els-meus-tiquets')['body']); // token vàlid d'un altre formulari
$blockedCheckout = req('POST', $base . '/punt-de-recarrega', ['_token' => $validToken, 'name' => 'X', 'email' => 'x@example.test', 'terms' => '1', 'qty' => [1 => 1]]);
check('Rebutja compres amb la venda tancada', $blockedCheckout['status'] === 302 && str_contains($blockedCheckout['headers'], '/punt-de-recarrega'), 'estat ' . $blockedCheckout['status']);
check('No crea cap comanda amb la venda tancada', str_contains(req('GET', $base . '/admin/comandes?q=x%40example.test')['body'], 'Cap comanda amb aquests filtres'));
req('POST', $base . '/admin/configuracio/tickets', [
    '_token' => token(req('GET', $base . '/admin/configuracio/tickets')['body']),
    'tickets_public_mode' => 'info',
    'tickets_enabled' => '1',
    'tickets_title' => 'Tiquets del punt de recàrrega',
    'tickets_deadline' => '2026-10-02',
    'tickets_max_per_order' => '20',
]);
check('El web torna al mode informatiu', !str_contains(req('GET', $base . '/punt-de-recarrega', [], ['anon' => true])['body'], 'name="qty['));

echo "\n== Exportacions ==\n";
$csv = req('GET', $base . '/admin/comandes/exportar');
check('Exportació CSV de comandes', $csv['status'] === 200 && str_contains($csv['headers'], 'text/csv'));
$csv2 = req('GET', $base . '/admin/inscripcions/exportar');
check('Exportació CSV d\'inscripcions', $csv2['status'] === 200 && str_contains($csv2['body'], 'Ferrer'));
check('El curs triat es desa', str_contains($csv2['body'], 'Primària 4rt'));
check('Descarta el gènere i el curs que no són del formulari',
    str_contains($csv2['body'], 'Fora ' . $unique)   // la inscripció sí que s'ha desat
    && !str_contains($csv2['body'], 'Batxillerat') && !str_contains($csv2['body'], 'altre'));

echo "\n== Inscripcions sense formulari en línia ==\n";
$regForm = req('GET', $base . '/admin/configuracio/registrations');
check('Hi ha l\'opció de desactivar el formulari', str_contains(text($regForm['body']), 'Formulari d\'inscripció en línia actiu'));
req('POST', $base . '/admin/configuracio/registrations', [
    '_token' => token($regForm['body']),
    // Sense «registrations_enabled»: el formulari queda desactivat.
    'registrations_title' => 'Inscripció a la cursa',
    'registrations_closed_text' => '<p>Enguany les inscripcions es fan a la secretaria de l\'escola.</p>',
    'registrations_closed_link_label' => 'Full d\'inscripció (PDF)',
    'registrations_closed_link_url' => 'https://exemple.test/full.pdf',
]);
$closedPage = req('GET', $base . '/inscripcio', [], ['anon' => true]);
check('Es mostra el text informatiu', str_contains(text($closedPage['body']), 'secretaria de l\'escola'), 'estat ' . $closedPage['status']);
check('No hi ha cap formulari d\'inscripció', !str_contains($closedPage['body'], 'name="first_name"'));
check('No hi surten els blocs del formulari', !str_contains($closedPage['body'], 'Cal omplir un formulari per cada participant'));
check('El botó opcional apareix si es configura', str_contains(text($closedPage['body']), 'Full d\'inscripció (PDF)'));
$blockedRegistration = req('POST', $base . '/inscripcio', [
    '_token' => token(req('GET', $base . '/els-meus-tiquets', [], ['anon' => true])['body']),
    'first_name' => 'Prova', 'last_name' => 'Tancada', 'birth_year' => '2015',
    'tutor_name' => 'Prova', 'tutor_email' => 'tancada@example.test', 'consent_data' => '1',
], ['anon' => true]);
check('No s\'accepten inscripcions amb el formulari desactivat', $blockedRegistration['status'] === 302
    && !str_contains($blockedRegistration['headers'], 'confirmada'), 'estat ' . $blockedRegistration['status']);
check('La comprovació no ha creat cap inscripció',
    !str_contains(req('GET', $base . '/admin/inscripcions?q=tancada%40example.test')['body'], 'Tancada'));
check('La portada convida a consultar com inscriure\'s',
    str_contains(text(req('GET', $base . '/', [], ['anon' => true])['body']), 'Com inscriure-s\'hi'));

req('POST', $base . '/admin/configuracio/registrations', [
    '_token' => token(req('GET', $base . '/admin/configuracio/registrations')['body']),
    'registrations_enabled' => '1',
    'registrations_notify' => '1',
    'registrations_selfservice' => '1',
    'registrations_closed_link_label' => '',
    'registrations_closed_link_url' => '',
]);
$openPage = req('GET', $base . '/inscripcio', [], ['anon' => true]);
check('En reactivar-lo torna a sortir el formulari', str_contains($openPage['body'], 'name="first_name"'));

echo "\n== Web en preparació ==\n";
$soonForm = req('GET', $base . '/admin/configuracio/coming_soon');
check('Hi ha la secció de configuració', $soonForm['status'] === 200 && str_contains($soonForm['body'], 'Amagar el web al públic'));
req('POST', $base . '/admin/configuracio/coming_soon', [
    '_token' => token($soonForm['body']),
    'coming_soon' => '1',
    'coming_soon_title' => 'Aviat publicarem el web',
    'coming_soon_text' => '<p>Estem preparant el web del cros.</p>',
    'coming_soon_countdown' => '1',
    'coming_soon_contact' => '1',
]);

$anonHome = req('GET', $base . '/', [], ['anon' => true]);
check('El visitant veu l\'avís a la portada', str_contains($anonHome['body'], 'Aviat publicarem el web'), 'estat ' . $anonHome['status']);
check('El visitant no veu el contingut del web', !str_contains($anonHome['body'], 'Programa de la jornada'));
check('L\'avís no s\'indexa', str_contains($anonHome['body'], 'noindex'));
check('El visitant no veu la botiga de tiquets', !str_contains(req('GET', $base . '/punt-de-recarrega', [], ['anon' => true])['body'], 'Tria els teus tiquets'));
check('El visitant no veu les categories', !str_contains(req('GET', $base . '/categories-i-premis', [], ['anon' => true])['body'], 'Benjamí'));
check('robots.txt bloqueja la indexació', str_contains(req('GET', $base . '/robots.txt', [], ['anon' => true])['body'], 'Disallow: /'));
check('L\'accés al panell continua disponible', str_contains(req('GET', $base . '/admin/acces', [], ['anon' => true])['body'], 'Accés al panell'));

$adminHome = req('GET', $base . '/');
check('L\'administració continua veient el web', str_contains($adminHome['body'], 'Programa de la jornada'));
check('L\'administració veu l\'avís de mode amagat', str_contains($adminHome['body'], 'Web en preparació'));

$soonWebhook = req('POST', $base . '/stripe/webhook', [], [
    'anon' => true, 'raw' => '{}', 'headers' => ['Content-Type: application/json', 'Stripe-Signature: t=1,v1=0'],
]);
check('El webhook de Stripe no queda bloquejat', $soonWebhook['status'] === 400, 'estat ' . $soonWebhook['status']);

$publish = req('POST', $base . '/admin/properament', ['_token' => csrf_from($base), 'enable' => '0']);
check('El commutador ràpid torna a publicar el web', $publish['status'] === 302);
check('El visitant torna a veure el web', str_contains(req('GET', $base . '/', [], ['anon' => true])['body'], 'Programa de la jornada'));

echo "\n== Webhook de Stripe ==\n";
$payload = json_encode([
    'id' => 'evt_test_1',
    'type' => 'checkout.session.completed',
    'data' => ['object' => [
        'id' => 'cs_test_webhook_1',
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_1',
        'client_reference_id' => $orderCode,
        'metadata' => ['order_code' => $orderCode],
    ]],
]);
$secret = 'whsec_test_secret_de_prova';
$timestamp = time();
$signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
$badSignature = req('POST', $base . '/stripe/webhook', [], [
    'raw' => $payload,
    'headers' => ['Content-Type: application/json', 'Stripe-Signature: t=' . $timestamp . ',v1=' . str_repeat('0', 64)],
]);
check('Rebutja webhooks amb signatura incorrecta', $badSignature['status'] === 400);
$goodSignature = req('POST', $base . '/stripe/webhook', [], [
    'raw' => $payload,
    'headers' => ['Content-Type: application/json', 'Stripe-Signature: t=' . $timestamp . ',v1=' . $signature],
]);
check('Accepta webhooks signats correctament', $goodSignature['status'] === 200, $goodSignature['body']);

echo "\n== Resultat ==\n";
echo "  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
