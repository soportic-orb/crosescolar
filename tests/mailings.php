<?php
/**
 * Proves dels enviaments de correu a les persones inscrites.
 *
 * Ús:  php -S 127.0.0.1:8123 -t . tests/server.php &   i després   php tests/mailings.php
 *
 * Durant la prova el correu es posa en mode d'assaig: no surt res del servidor,
 * però tot queda registrat com si s'hagués enviat.
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Models\Mailing;
use Cros\Models\Registration;

$base = getenv('CROS_TEST_URL') ?: 'http://127.0.0.1:8123';
$jar = sys_get_temp_dir() . '/cros-mailings.txt';
@unlink($jar);
$passed = 0;
$failed = 0;
$unique = 'M' . substr(bin2hex(random_bytes(3)), 0, 5);

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
        CURLOPT_TIMEOUT => 40,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    if (!empty($options['ajax'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);
    }
    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
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

/** Crea un enviament i en torna l'identificador. */
function createMailing(string $base, array $fields): int
{
    $form = req('GET', $base . '/admin/enviaments/nou');
    $post = req('POST', $base . '/admin/enviaments/nou', array_merge([
        '_token' => token($form['body']),
        'audience' => 'all',
        'reg_status' => 'confirmed',
    ], $fields));

    return preg_match('#/admin/enviaments/(\d+)#', $post['headers'], $m) ? (int) $m[1] : 0;
}

/** Envia tandes fins que no en quedi cap. */
function sendAll(string $base, int $id, int $max = 20): array
{
    $last = [];
    for ($i = 0; $i < $max; $i++) {
        $page = req('GET', $base . '/admin/enviaments/' . $id);
        $response = req('POST', $base . '/admin/enviaments/' . $id . '/tanda',
            ['_token' => token($page['body'])], ['ajax' => true]);
        $last = json_decode($response['body'], true) ?: [];
        if (!empty($last['done'])) {
            break;
        }
    }

    return $last;
}

/* ------------------------------------------------------- Preparació */
$mailBefore = (string) Settings::get('mail_transport', 'mail');
$batchBefore = (string) Settings::get('mail_batch_size', '20');
Settings::set('mail_transport', 'log'); // assaig: no surt cap correu del servidor
Settings::set('mail_batch_size', '2');  // tandes petites per veure que en fa més d'una

// Tres famílies i quatre participants: la primera hi té dos fills.
$family = [];
foreach ([['Laia', 1], ['Pau', 1], ['Roc', 2], ['Ona', 3]] as [$name, $house]) {
    $family[] = Registration::create([
        'first_name' => $name . $unique, 'last_name' => 'Prova',
        'birth_year' => (string) ((int) date('Y') - 9), 'gender' => 'femeni', 'category_id' => '',
        'school' => 'Escola La Granada', 'tutor_name' => 'Tutor ' . $house,
        'tutor_email' => 'casa' . $house . strtolower($unique) . '@example.test',
        'tutor_phone' => '', 'notes' => '', 'consent_data' => 1, 'consent_image' => 0, 'consent_rules' => 1,
    ]);
}
// Dues categories només per a la prova: així els participants d'altres bateries
// no s'hi colen i els números quadren tant si s'executa sola com amb les altres.
$catMost = Db::insert('categories', [
    'name' => 'Proves ' . $unique, 'code' => 'PR' . $unique, 'sort_order' => 900, 'active' => 0,
]);
$catSolo = Db::insert('categories', [
    'name' => 'Proves soles ' . $unique, 'code' => 'PS' . $unique, 'sort_order' => 901, 'active' => 0,
]);
foreach ([0, 1, 2] as $index) {
    Db::update('registrations', ['category_id' => $catMost], 'id = :id', ['id' => (int) $family[$index]['id']]);
}
Db::update('registrations', ['category_id' => $catSolo], 'id = :id', ['id' => (int) $family[3]['id']]);
$bothCategories = ['audience' => 'category', 'categories' => [$catMost, $catSolo]];

echo "\n== Accés al panell ==\n";
$login = req('POST', $base . '/admin/acces', [
    '_token' => token(req('GET', $base . '/admin/acces')['body']),
    'email' => 'admin@example.test',
    'password' => 'provaprova',
]);
check('Sessió iniciada', $login['status'] === 302, 'estat ' . $login['status']);
$list = req('GET', $base . '/admin/enviaments');
check('Hi ha la pantalla d\'enviaments', $list['status'] === 200 && str_contains(text($list['body']), 'Enviaments de correu'));
check('El menú lateral hi porta', str_contains($list['body'], '/admin/enviaments"'));

echo "\n== El formulari ==\n";
$form = req('GET', $base . '/admin/enviaments/nou');
check('Hi ha el camp del tema', str_contains($form['body'], 'name="subject"'));
check('Hi ha l\'editor visual',
    str_contains($form['body'], 'data-editor') && str_contains($form['body'], 'data-command="bold"'));
check('L\'editor deixa posar títols i llistes',
    str_contains($form['body'], 'data-command="h2"') && str_contains($form['body'], 'data-command="ul"'));
check('I veure l\'HTML', str_contains($form['body'], 'data-command="source"'));
check('Hi ha el selector de destinataris',
    substr_count($form['body'], 'name="audience"') === 3
    && str_contains($form['body'], 'name="categories[]"'));
check('Explica els marcadors', str_contains(text($form['body']), '{{participants}}'));

echo "\n== Validacions ==\n";
$empty = req('POST', $base . '/admin/enviaments/nou', [
    '_token' => token($form['body']), 'subject' => '', 'body' => '', 'audience' => 'all',
]);
check('No deixa desar-lo sense tema', str_contains(text($empty['body']), 'Cal posar un assumpte'));
check('Ni sense cos', str_contains(text($empty['body']), 'El correu no pot anar buit'));
$noCategory = req('POST', $base . '/admin/enviaments/nou', [
    '_token' => token($form['body']), 'subject' => 'Prova', 'body' => '<p>Hola</p>', 'audience' => 'category',
]);
check('Ni per categories sense triar-ne cap', str_contains(text($noCategory['body']), 'Trieu almenys una categoria'));

echo "\n== Destinataris sense repetir adreces ==\n";
$id = createMailing($base, $bothCategories + [
    'subject' => 'Tot a punt ' . $unique,
    'body' => '<p>Hola {{tutor}}!</p><p>{{participants}} amb el dorsal {{dorsals}}.</p><script>alert(1)</script>',
]);
check('L\'enviament es desa', $id > 0, 'no s\'ha pogut crear');
$show = req('GET', $base . '/admin/enviaments/' . $id);
check('La fitxa s\'obre', $show['status'] === 200);
$mailing = Mailing::find($id);
check('Neteja l\'HTML perillós', !str_contains((string) $mailing['body'], '<script>'));
$audience = Mailing::audience($mailing);
$expected = ['casa1' . strtolower($unique) . '@example.test',
             'casa2' . strtolower($unique) . '@example.test',
             'casa3' . strtolower($unique) . '@example.test'];
foreach ($expected as $address) {
    check('Hi és ' . $address, isset($audience[$address]));
}
check('Una família amb dos fills rep un sol correu',
    count(array_intersect_key($audience, array_flip($expected))) === 3);
check('I hi surten els dos noms',
    str_contains($audience[$expected[0]]['participants'] ?? '', 'Laia' . $unique)
    && str_contains($audience[$expected[0]]['participants'] ?? '', 'Pau' . $unique),
    $audience[$expected[0]]['participants'] ?? '');

echo "\n== Vista prèvia i prova ==\n";
$preview = req('GET', $base . '/admin/enviaments/' . $id . '/vista-previa');
check('La vista prèvia s\'obre', $preview['status'] === 200);
check('Els marcadors se substitueixen',
    !str_contains($preview['body'], '{{') && str_contains($preview['body'], 'Tutor 1'),
    'han de sortir les dades del primer destinatari');
check('I porta el disseny dels correus', str_contains($preview['body'], setting('site_name', '')));
$test = req('POST', $base . '/admin/enviaments/' . $id . '/prova', [
    '_token' => token($show['body']), 'email' => 'prova' . strtolower($unique) . '@example.test',
]);
check('S\'envia una prova', $test['status'] === 302);
check('I queda al registre de correus',
    (int) Db::val('SELECT COUNT(*) FROM email_log WHERE recipient = :r AND subject LIKE :s',
        ['r' => 'prova' . strtolower($unique) . '@example.test', 's' => '[PROVA]%'], 0) === 1);

echo "\n== Enviament per tandes ==\n";
$show = req('GET', $base . '/admin/enviaments/' . $id);
$start = req('POST', $base . '/admin/enviaments/' . $id . '/preparar', ['_token' => token($show['body'])]);
check('Es prepara l\'enviament', $start['status'] === 302);
$mailing = Mailing::find($id);
check('Hi consten els tres destinataris', (int) $mailing['total'] === 3, (string) $mailing['total']);
check('I queda a punt d\'enviar-se', $mailing['status'] === 'sending', (string) $mailing['status']);
check('Ja no es pot editar',
    str_contains(req('GET', $base . '/admin/enviaments/' . $id . '/editar')['headers'], '/admin/enviaments/' . $id));

$page = req('GET', $base . '/admin/enviaments/' . $id);
$first = json_decode(req('POST', $base . '/admin/enviaments/' . $id . '/tanda',
    ['_token' => token($page['body'])], ['ajax' => true])['body'], true) ?: [];
check('La primera tanda respecta la mida', ($first['sent'] ?? 0) === 2, json_encode($first));
check('I en queda una per enviar', ($first['pending'] ?? -1) === 1, json_encode($first));
check('Encara no ha acabat', empty($first['done']));

$last = sendAll($base, $id);
check('Acaba l\'enviament', !empty($last['done']), json_encode($last));
$mailing = Mailing::find($id);
check('S\'han enviat els tres correus', (int) $mailing['sent'] === 3, (string) $mailing['sent']);
check('Cap error', (int) $mailing['failed'] === 0);
check('L\'enviament consta com a fet', $mailing['status'] === 'sent' && $mailing['finished_at'] !== null);
check('Cada família n\'ha rebut un de sol',
    (int) Db::val('SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id AND status = :s',
        ['id' => $id, 's' => 'sent'], 0) === 3);
check('I el correu ha arribat a la primera família',
    (int) Db::val('SELECT COUNT(*) FROM email_log WHERE recipient = :r AND subject = :s',
        ['r' => $expected[0], 's' => 'Tot a punt ' . $unique], 0) === 1);

echo "\n== No es repeteix cap correu ==\n";
$page = req('GET', $base . '/admin/enviaments/' . $id);
$again = req('POST', $base . '/admin/enviaments/' . $id . '/preparar', ['_token' => token($page['body'])]);
check('Avisa que no hi ha ningú nou',
    str_contains(text(req('GET', $base . '/admin/enviaments/' . $id)['body']), 'No hi ha cap inscripció nova'));
check('I no es torna a enviar res',
    (int) Db::val('SELECT COUNT(*) FROM email_log WHERE recipient = :r AND subject = :s',
        ['r' => $expected[0], 's' => 'Tot a punt ' . $unique], 0) === 1);
check('L\'enviament continua constant com a fet', Mailing::find($id)['status'] === 'sent');

// Una inscripció nova sí que el rep.
$late = Registration::create([
    'first_name' => 'Tard' . $unique, 'last_name' => 'Prova', 'category_id' => (string) $catMost,
    'birth_year' => (string) ((int) date('Y') - 9), 'gender' => 'masculi',
    'school' => 'Escola La Granada', 'tutor_name' => 'Tutor 4',
    'tutor_email' => 'casa4' . strtolower($unique) . '@example.test',
    'tutor_phone' => '', 'notes' => '', 'consent_data' => 1, 'consent_image' => 0, 'consent_rules' => 1,
]);
$page = req('GET', $base . '/admin/enviaments/' . $id);
req('POST', $base . '/admin/enviaments/' . $id . '/preparar', ['_token' => token($page['body'])]);
sendAll($base, $id);
$mailing = Mailing::find($id);
check('Una inscripció nova sí que rep el correu', (int) $mailing['sent'] === 4, (string) $mailing['sent']);
check('I les altres continuen amb un de sol',
    (int) Db::val('SELECT COUNT(*) FROM email_log WHERE recipient = :r AND subject = :s',
        ['r' => $expected[0], 's' => 'Tot a punt ' . $unique], 0) === 1);

echo "\n== Triar els destinataris ==\n";
$byCategory = createMailing($base, [
    'subject' => 'Categoria ' . $unique,
    'body' => '<p>Només per a una categoria.</p>',
    'audience' => 'category',
    'categories' => [$catSolo],
]);
check('Es desa un enviament per categoria', $byCategory > 0);
$audience = Mailing::audience(Mailing::find($byCategory));
check('Només hi entren les d\'aquella categoria', count($audience) === 1, (string) count($audience));
check('I és qui toca', isset($audience[$expected[2]]), implode(', ', array_keys($audience)));

$manual = createMailing($base, [
    'subject' => 'Voluntariat ' . $unique,
    'body' => '<p>Ens veiem dissabte.</p>',
    'audience' => 'manual',
    'manual_emails' => "un{$unique}@example.test, dos{$unique}@example.test\nun{$unique}@example.test  no-es-una-adreça",
]);
$audience = Mailing::audience(Mailing::find($manual));
check('Les adreces escrites a mà no es repeteixen', count($audience) === 2, implode(', ', array_keys($audience)));
check('I les que no són adreces es descarten',
    !str_contains(implode(' ', array_keys($audience)), 'no-es-una'));

echo "\n== Esborrar ==\n";
$page = req('GET', $base . '/admin/enviaments');
$delete = req('POST', $base . '/admin/enviaments/' . $manual . '/esborrar', ['_token' => token($page['body'])]);
check('S\'esborra l\'enviament', $delete['status'] === 302 && Mailing::find($manual) === null);
check('I també els seus destinataris',
    (int) Db::val('SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id', ['id' => $manual], 0) === 0);

/* ------------------------------------------------------- Neteja */
foreach ([$id, $byCategory] as $mailingId) {
    Mailing::delete($mailingId);
}
Db::delete('registrations', "tutor_email LIKE :e", ['e' => '%' . strtolower($unique) . '@example.test']);
Db::delete('categories', 'id IN (:a, :b)', ['a' => $catMost, 'b' => $catSolo]);
Db::delete('email_log', "recipient LIKE :e", ['e' => '%' . strtolower($unique) . '@example.test']);
Settings::set('mail_transport', $mailBefore);
Settings::set('mail_batch_size', $batchBefore);

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
