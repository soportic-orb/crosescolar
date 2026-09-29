<?php
/**
 * Proves dels cobraments del cros: la capa de passarel·les, els imports i els
 * rebuts i factures que s'emeten a nom de l'entitat organitzadora.
 *
 * El que més importa d'aquí és l'última part: que un document del cros no
 * porti mai cap dada de qui manté la plataforma. Són dos sistemes de
 * facturació separats i no s'han de barrejar per cap escletxa.
 *
 * Ús:  php tests/cobraments.php
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Models\Billing;
use Cros\Models\Payment;
use Cros\Payments\Gateways;
use Cros\Payments\RedsysGateway;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/** Deixa la configuració com estava en acabar. */
$before = [];
foreach ([
    'payments_gateway', 'billing_document', 'billing_entity', 'billing_nif', 'billing_address',
    'billing_town', 'billing_postcode', 'billing_province', 'billing_email', 'billing_tax_rate',
    'billing_series_receipt', 'billing_series_invoice', 'redsys_merchant_code', 'redsys_key',
    'redsys_mode', 'paypal_client_id', 'paypal_secret', 'stripe_mode', 'stripe_pk_test', 'stripe_sk_test',
] as $key) {
    $before[$key] = (string) Settings::get($key, '');
}
$set = static function (array $values): void {
    foreach ($values as $key => $value) {
        Settings::set($key, (string) $value);
    }
    Settings::load(true);
};

Db::conn()->exec('DELETE FROM payment_items');
Db::conn()->exec('DELETE FROM payments');
Db::conn()->exec('DELETE FROM billing_documents');
Db::conn()->exec('DELETE FROM billing_counters');

echo "\n== Quina passarel·la mana ==\n";
$set(['payments_gateway' => '', 'stripe_pk_test' => '', 'stripe_sk_test' => '']);
check('Sense triar-ne cap, no es cobra res', !Gateways::ready() && Gateways::active() === null);

$set(['payments_gateway' => 'stripe']);
check('Triada però sense claus, tampoc', !Gateways::ready(), 'diu que sí i no n\'hi ha');
check('Però el panell sap quina s\'ha triat', Gateways::chosen() === 'stripe');

$set(['stripe_mode' => 'test', 'stripe_pk_test' => 'pk_test_x', 'stripe_sk_test' => 'sk_test_x']);
check('Amb les claus, ja es pot cobrar', Gateways::ready() && Gateways::active() === 'stripe');
check('I diu que va en proves', Gateways::module('stripe')::testing());

$set(['payments_gateway' => 'redsys', 'redsys_mode' => 'test',
      'redsys_merchant_code' => '999008881', 'redsys_key' => base64_encode(str_repeat('k', 24))]);
check('Se\'n pot posar una altra', Gateways::active() === 'redsys');
check('I només n\'hi ha una d\'activa alhora', count(array_filter(
    Gateways::all(), static fn (array $g): bool => $g['chosen'])) === 1);

echo "\n== Els imports ==\n";
$set(['billing_tax_rate' => '0']);
$payment = Payment::create([
    'concept' => 'order', 'reference_id' => 0,
    'payer_name' => 'Anna Duran', 'payer_email' => 'ANNA@example.cat',
], [
    ['description' => 'Esmorzar complet', 'qty' => 3, 'unit_price_cents' => 450],
    ['description' => 'Beguda', 'qty' => 1, 'unit_price_cents' => 150],
]);
check('El total surt dels articles', (int) $payment['total_cents'] === 1500, (string) $payment['total_cents']);
check('El correu es desa en minúscules', $payment['payer_email'] === 'anna@example.cat');
check('Amb un codi que es pot dir per telèfon',
    (bool) preg_match('/^P-\d{4}-\d{4}$/', (string) $payment['code']), (string) $payment['code']);
check('I un testimoni llarg per a l\'enllaç', strlen((string) $payment['token']) === 48);
check('Sense IVA, no se\'n desglossa cap', (int) $payment['tax_cents'] === 0);

$set(['billing_tax_rate' => '21']);
$ambIva = Payment::create([
    'concept' => 'order', 'payer_name' => 'Pau Soler', 'payer_email' => 'pau@example.cat',
], [['description' => 'Samarreta', 'qty' => 1, 'unit_price_cents' => 1210]]);
check('Amb IVA, el preu segueix sent el que es cobra', (int) $ambIva['total_cents'] === 1210);
check('I se\'n desglossa cap enrere',
    (int) $ambIva['tax_cents'] === 210 && (int) $ambIva['subtotal_cents'] === 1000,
    $ambIva['subtotal_cents'] . ' + ' . $ambIva['tax_cents']);
$set(['billing_tax_rate' => '0']);

echo "\n== Rebut o factura ==\n";
$set([
    'billing_document' => 'invoice', 'billing_entity' => '', 'billing_nif' => '',
    'billing_address' => '', 'billing_town' => '', 'billing_postcode' => '',
]);
check('Sense dades fiscals no es pot facturar', !Billing::complete());
check('I encara que es demani factura, s\'emet rebut', Billing::documentType() === 'receipt');
check('El panell diu què falta', count(Billing::missing()) === 5, implode(', ', Billing::missing()));

$set([
    'billing_entity' => 'AFA Escola Sant Jordi', 'billing_nif' => 'G12345678',
    'billing_address' => 'Carrer Major, 1', 'billing_town' => 'Sitges', 'billing_postcode' => '08870',
    'billing_province' => 'Barcelona', 'billing_email' => 'afa@example.cat',
    'billing_series_receipt' => 'R', 'billing_series_invoice' => 'F',
]);
check('Amb totes les dades, ja es pot', Billing::complete() && Billing::documentType() === 'invoice');

echo "\n== La numeració ==\n";
$pagat = Payment::markPaid($payment);
$document = Billing::document((int) $pagat['id']);
check('En cobrar, s\'emet el document sol', $document !== null);
check('Amb la sèrie de les factures',
    (string) ($document['type'] ?? '') === 'invoice' && str_starts_with((string) ($document['full_number'] ?? ''), 'F-'),
    (string) ($document['full_number'] ?? ''));
check('I el primer número és l\'1', (int) ($document['number'] ?? 0) === 1, (string) ($document['number'] ?? ''));

$segon = Payment::markPaid($ambIva);
$document2 = Billing::document((int) $segon['id']);
check('El següent és el 2 i no en salta cap', (int) ($document2['number'] ?? 0) === 2, (string) ($document2['number'] ?? ''));
check('Els números no es repeteixen',
    (string) $document['full_number'] !== (string) $document2['full_number']);

$abans = Billing::document((int) $pagat['id']);
Payment::markPaid(Payment::find((int) $pagat['id']) ?? []);
check('Cobrar dues vegades no emet cap document nou',
    (int) Db::val('SELECT COUNT(*) FROM billing_documents WHERE payment_id = :id', ['id' => $pagat['id']], 0) === 1);
check('Ni li canvia el número',
    (string) (Billing::document((int) $pagat['id'])['full_number'] ?? '') === (string) $abans['full_number']);

echo "\n== El document, per dins ==\n";
// Dades de la plataforma que NO han de sortir per enlloc.
$plataforma = ['Soportic SL', 'B99887766', 'crosescolar.cat'];
$pdf = Billing::pdf($abans);
check('El PDF es genera', str_starts_with($pdf, '%PDF-'), substr($pdf, 0, 8));
// El text d'un PDF va comprimit: cal treure'l de debò per poder-hi buscar res.
$fitxer = sys_get_temp_dir() . '/cros-document-' . bin2hex(random_bytes(3)) . '.pdf';
file_put_contents($fitxer, $pdf);
$text = trim((string) @shell_exec('pdftotext ' . escapeshellarg($fitxer) . ' - 2>/dev/null'));
@unlink($fitxer);
if ($text === '') {
    // Sense pdftotext no es pot mirar què hi diu: el text d'un PDF va
    // comprimit i buscar-hi cadenes a pèl donaria resultats falsos, que és
    // pitjor que no mirar-ho. S'omet i es diu.
    echo "  (sense pdftotext: les comprovacions del contingut del document s'ometen)\n";
} else {
    check('Hi surt l\'entitat organitzadora', str_contains($text, 'AFA Escola Sant Jordi'));
    check('I el seu NIF', str_contains($text, 'G12345678'));
    check('I a nom de qui va', str_contains($text, 'Anna Duran'));
    foreach ($plataforma as $dada) {
        check('No hi surt cap dada de la plataforma (' . $dada . ')', !str_contains($text, $dada));
    }
}
check('El fitxer es diu com el document',
    Billing::filename($abans) === 'factura-' . strtolower((string) $abans['full_number']) . '.pdf',
    Billing::filename($abans));

// Les dades fiscals es congelen el dia que s'emet el document.
$set(['billing_entity' => 'AFA amb un nom nou']);
$congelat = json_decode((string) Billing::document((int) $pagat['id'])['issuer'], true);
check('Les dades del document no canvien si després es toca la configuració',
    (string) ($congelat['entity'] ?? '') === 'AFA Escola Sant Jordi', (string) ($congelat['entity'] ?? ''));

echo "\n== La signatura del TPV ==\n";
$set(['payments_gateway' => 'redsys', 'redsys_merchant_code' => '999008881',
      'redsys_key' => base64_encode(str_repeat('k', 24)), 'redsys_mode' => 'test']);
$tpv = Payment::create(['concept' => 'order', 'payer_name' => 'Marta Vila', 'payer_email' => 'marta@example.cat'],
    [['description' => 'Inscripció', 'qty' => 1, 'unit_price_cents' => 500]]);
$ordre = RedsysGateway::begin($tpv, Payment::items((int) $tpv['id']));
check('El TPV demana un formulari, no una adreça', ($ordre['mode'] ?? '') === 'form');
check('Cap a la pantalla de proves', str_contains((string) $ordre['url'], 'sis-t.redsys.es'));
check('Amb els tres camps de sempre',
    isset($ordre['fields']['Ds_SignatureVersion'], $ordre['fields']['Ds_MerchantParameters'], $ordre['fields']['Ds_Signature']));
$params = json_decode((string) base64_decode((string) $ordre['fields']['Ds_MerchantParameters'], true), true);
check('L\'import va en cèntims', (string) ($params['DS_MERCHANT_AMOUNT'] ?? '') === '500');
check('El número de comanda comença amb quatre xifres',
    (bool) preg_match('/^\d{4}[A-Z0-9]{8}$/', (string) ($params['DS_MERCHANT_ORDER'] ?? '')),
    (string) ($params['DS_MERCHANT_ORDER'] ?? ''));
check('I hi va la referència per retrobar el cobrament',
    (string) ($params['DS_MERCHANT_MERCHANTDATA'] ?? '') === (string) $tpv['code']);
check('De la petició se\'n sap treure el cobrament',
    RedsysGateway::findOrder((string) $ordre['fields']['Ds_MerchantParameters']) === (string) $tpv['code']);

// La resposta del TPV, feta amb la mateixa clau: ha de quadrar.
$resposta = base64_encode((string) json_encode([
    'Ds_Order' => $params['DS_MERCHANT_ORDER'], 'Ds_Response' => '0000',
    'Ds_AuthorisationCode' => '123456', 'Ds_Amount' => '500',
    'Ds_MerchantData' => $tpv['code'],
]));
check('I de la resposta del TPV, també',
    RedsysGateway::findOrder($resposta) === (string) $tpv['code']);
$signada = (new ReflectionMethod(RedsysGateway::class, 'sign'));
$signada->setAccessible(true);
$bona = $signada->invoke(null, (string) $params['DS_MERCHANT_ORDER'], $resposta);
$tpv = Payment::find((int) $tpv['id']) ?? $tpv;
$ok = RedsysGateway::check($tpv, ['Ds_MerchantParameters' => $resposta, 'Ds_Signature' => $bona]);
check('Una resposta ben signada es dona per cobrada', $ok['status'] === 'paid', $ok['detail']);
$dolenta = RedsysGateway::check($tpv, ['Ds_MerchantParameters' => $resposta, 'Ds_Signature' => 'bWVudGlkYQ==']);
check('I una de mal signada, no', $dolenta['status'] === 'failed', $dolenta['detail']);
$denegada = base64_encode((string) json_encode([
    'Ds_Order' => $params['DS_MERCHANT_ORDER'], 'Ds_Response' => '0190', 'Ds_AuthorisationCode' => '',
]));
$no = RedsysGateway::check($tpv, [
    'Ds_MerchantParameters' => $denegada,
    'Ds_Signature' => $signada->invoke(null, (string) $params['DS_MERCHANT_ORDER'], $denegada),
]);
check('Un codi de denegació es llegeix com a fallit', $no['status'] === 'failed', $no['detail']);

echo "\n== Tornar els diners ==\n";
$sensePassarela = Payment::create(['concept' => 'order', 'payer_name' => 'X', 'payer_email' => 'x@example.cat'],
    [['description' => 'Cosa', 'qty' => 1, 'unit_price_cents' => 100]]);
$resultat = Payment::refund($sensePassarela, 0);
check('Un cobrament pendent no es pot tornar', !$resultat['ok'], $resultat['error']);

$set($before);
echo "\n" . ($failed === 0
    ? "Totes les proves passen ($passed).\n"
    : "$failed proves fallen de " . ($passed + $failed) . ".\n");
exit($failed === 0 ? 0 : 1);
