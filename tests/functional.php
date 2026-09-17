<?php
/**
 * Proves funcionals: executen l'aplicació sencera contra el servidor local.
 * Ús:  php -S 127.0.0.1:8123 -t . tests/server.php   i després   php tests/functional.php
 */
declare(strict_types=1);

$base = getenv('CROS_TEST_URL') ?: 'http://127.0.0.1:8123';
$jar = sys_get_temp_dir() . '/cros-test-cookies.txt';
@unlink($jar);
$passed = 0;
$failed = 0;

function req(string $method, string $url, array $data = [], array $options = []): array
{
    global $jar;
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

$tickets = req('GET', $base . '/esmorzar');
check('Pàgina de tiquets', $tickets['status'] === 200 && str_contains($tickets['body'], 'Esmorzar complet'));

echo "\n== Inscripció pública ==\n";
$form = req('GET', $base . '/inscripcio');
$csrf = token($form['body']);
$registration = req('POST', $base . '/inscripcio', [
    '_token' => $csrf,
    'first_name' => 'Laia',
    'last_name' => 'Ferrer Miró',
    'birth_year' => (string) ((int) date('Y') - 9),
    'gender' => 'femeni',
    'category_id' => '',
    'school' => 'Escola La Granada',
    'class_group' => '4t',
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
    'first_name' => 'Laia', 'last_name' => 'Ferrer Miró', 'birth_year' => (string) ((int) date('Y') - 9),
    'tutor_name' => 'Marc Ferrer', 'tutor_email' => 'families@example.test', 'consent_data' => '1',
]);
check('Evita inscripcions duplicades', $duplicate['status'] === 302 && !str_contains($duplicate['headers'], 'confirmada'));

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
$shopForm = req('GET', $base . '/esmorzar');
$checkout = req('POST', $base . '/esmorzar', [
    '_token' => token($shopForm['body']),
    'name' => 'Núria Casals',
    'email' => 'nuria@example.test',
    'phone' => '600111222',
    'terms' => '1',
    'qty' => [1 => 1],
]);
check('La compra no peta amb credencials invàlides', $checkout['status'] === 302 && str_contains($checkout['headers'], '/esmorzar'), 'estat ' . $checkout['status']);
$shopAfter = req('GET', $base . '/esmorzar');
check('Informa l\'usuari de l\'error de pagament', str_contains($shopAfter['body'], 'alert--error'));

$closeForm = req('GET', $base . '/admin/configuracio/tickets');
req('POST', $base . '/admin/configuracio/tickets', [
    '_token' => token($closeForm['body']),
    'tickets_title' => 'Tiquets per a l\'esmorzar',
    'tickets_deadline' => '2026-10-02',
    'tickets_max_per_order' => '20',
]);
$closedShop = req('GET', $base . '/esmorzar');
check('Tanca la venda si es desactiva', str_contains($closedShop['body'], 'venda anticipada de tiquets ja està tancada') || str_contains($closedShop['body'], 'notice-box'));
$validToken = token(req('GET', $base . '/els-meus-tiquets')['body']); // token vàlid d'un altre formulari
$blockedCheckout = req('POST', $base . '/esmorzar', ['_token' => $validToken, 'name' => 'X', 'email' => 'x@example.test', 'terms' => '1', 'qty' => [1 => 1]]);
check('Rebutja compres amb la venda tancada', $blockedCheckout['status'] === 302 && str_contains($blockedCheckout['headers'], '/esmorzar'), 'estat ' . $blockedCheckout['status']);
check('No crea cap comanda amb la venda tancada', str_contains(req('GET', $base . '/admin/comandes?q=x%40example.test')['body'], 'Cap comanda amb aquests filtres'));
req('POST', $base . '/admin/configuracio/tickets', [
    '_token' => token(req('GET', $base . '/admin/configuracio/tickets')['body']),
    'tickets_enabled' => '1',
    'tickets_title' => 'Tiquets per a l\'esmorzar',
    'tickets_deadline' => '2026-10-02',
    'tickets_max_per_order' => '20',
]);

echo "\n== Exportacions ==\n";
$csv = req('GET', $base . '/admin/comandes/exportar');
check('Exportació CSV de comandes', $csv['status'] === 200 && str_contains($csv['headers'], 'text/csv'));
$csv2 = req('GET', $base . '/admin/inscripcions/exportar');
check('Exportació CSV d\'inscripcions', $csv2['status'] === 200 && str_contains($csv2['body'], 'Ferrer'));

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
