<?php
/**
 * Proves dels enviaments de la plataforma: el document de l'editor, com es
 * dibuixa el correu, a qui va segons el domini, la baixa, les versions, la
 * programació, les tandes amb el límit per hora i la pausa quan el servidor
 * de correu no va.
 *
 * Necessiten un MySQL/MariaDB (la base de dades de la plataforma). Si no hi
 * és, les proves s'ometen i no fallen.
 *
 * Ús:
 *   CROS_DB_USER=cros CROS_DB_PASS=cros CROS_DB_PLATFORM=cros_plataforma php tests/mailout.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$db = [
    'host' => getenv('CROS_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('CROS_DB_PORT') ?: 3306),
    'user' => getenv('CROS_DB_USER') ?: '',
    'pass' => getenv('CROS_DB_PASS') ?: '',
    'platform' => getenv('CROS_DB_PLATFORM') ?: '',
];
if ($db['user'] === '' || $db['platform'] === '') {
    echo "Proves dels enviaments omeses: definiu CROS_DB_USER i CROS_DB_PLATFORM.\n";
    exit(0);
}
putenv('CROS_CONFIG');
putenv('CROS_UPLOADS');
putenv('CROS_STORAGE');

// La plataforma llegeix els dominis de la carpeta on hi ha el codi: es fa
// una còpia del codi en una carpeta de prova, amb la configuració seva.
$site = sys_get_temp_dir() . '/cros-enviaments-' . bin2hex(random_bytes(3));
@mkdir($site . '/tenants', 0775, true);
@mkdir($site . '/storage/logs', 0775, true);
exec('cp -r ' . escapeshellarg($root . '/app') . ' ' . escapeshellarg($site . '/app'));
@unlink($site . '/app/config.php');

require $site . '/app/bootstrap.php';

use Cros\Core\Db;
use Cros\Core\Settings;
use Cros\Platform\Mail\Ai;
use Cros\Platform\Mail\Brand;
use Cros\Platform\Mail\Doc;
use Cros\Platform\Mail\Images;
use Cros\Platform\Mail\Renderer;
use Cros\Platform\Mail\RichText;
use Cros\Platform\Mail\Unsubscribe;
use Cros\Platform\Mailout;
use Cros\Platform\Platform;
use Cros\Platform\Site;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

file_put_contents($site . '/tenants/platform.php', "<?php return " . var_export([
    'base_domain' => 'crosescolar.test',
    'domains' => ['esportweb.test'],
    'name' => 'Cros Escolar',
    'console' => ['admin'],
    'mail' => ['from_email' => 'hola@crosescolar.test', 'notify' => 'hola@crosescolar.test', 'transport' => 'log'],
    'monitor' => ['web' => false],
    'db' => [
        'host' => $db['host'], 'port' => $db['port'], 'name' => $db['platform'],
        'user' => $db['user'], 'pass' => $db['pass'], 'charset' => 'utf8mb4', 'socket' => '',
    ],
], true) . ";\n");

$pdo = Platform::boot($site);
Platform::migrate();
foreach (['platform_mailing_recipients', 'platform_mailing_versions', 'platform_mailings', 'platform_unsubscribes',
    'mail_contacts', 'mail_lists', 'instances', 'clients'] as $table) {
    $pdo->exec('DELETE FROM ' . $table);
}
Settings::set('mail_transport', 'log');
Settings::set('site:esportweb.test:site_name', 'EsportWeb');
Settings::set('site:esportweb.test:color_primary', '#123456');
Settings::set('site:crosescolar.test:site_name', 'Cros Escolar');
Settings::set('site:crosescolar.test:color_primary', '#1f6f3f');
Settings::set('site:esportweb.test:mail_hourly_limit', '');
// Cada domini amb el seu remitent i en mode d'assaig, digui el que digui la
// base de dades que han deixat altres proves.
foreach (['crosescolar.test' => 'hola@crosescolar.test', 'esportweb.test' => 'hola@esportweb.test'] as $d => $from) {
    Settings::set('site:' . $d . ':mail_transport', 'log');
    Settings::set('site:' . $d . ':mail_from_email', $from);
}
Settings::forget();

echo "== El document ==\n";
$doc = Doc::clean(['blocks' => [
    ['type' => 'text', 'html' => '<p onclick="x()">Hola <script>alert(1)</script><a href="javascript:alert(1)">aquí</a> <a href="https://{{web}}/x">web</a></p>'],
    ['type' => 'virus', 'html' => 'res'],
    ['type' => 'button', 'text' => 'Entreu', 'href' => 'javascript:alert(1)', 'btnBg' => 'red;background:url(x)'],
    ['type' => 'race', 'title' => 'Cros de la Granada'],
]]);
check('Un tipus de bloc desconegut no hi entra', count($doc['blocks']) === 3);
$html = (string) $doc['blocks'][0]['html'];
check('El text perd els scripts i els atributs', !str_contains($html, 'script') && !str_contains($html, 'onclick'), $html);
check('Un enllaç javascript: no hi queda', !str_contains($html, 'javascript'), $html);
check('Un enllaç amb el camp {{web}} sí', str_contains($html, 'https://{{web}}/x'), $html);
check('Un botó amb un enllaç perillós es queda sense enllaç', $doc['blocks'][1]['href'] === '');
check('Un color mal escrit torna al de partida', !str_contains((string) $doc['blocks'][1]['btnBg'], 'url'));
check('Cada bloc té el seu identificador', $doc['blocks'][0]['id'] !== '' && $doc['blocks'][0]['id'] !== $doc['blocks'][1]['id']);
check('Desar i llegir dona el mateix', Doc::decode(Doc::encode($doc)) == $doc);
check('El text pla del format', RichText::text('<p>Un <strong>dos</strong></p><ul><li>tres</li></ul>') !== '');

echo "\n== El correu dibuixat ==\n";
$doc = Mailout::blankDoc();
$green = (new Renderer([], Brand::of('crosescolar.test')))->html($doc, 'Prova', 'Una línia');
$blue = (new Renderer([], Brand::of('esportweb.test')))->html($doc, 'Prova');
check('Surt de 600 px d\'amplada', str_contains($green, 'max-width:600px'));
check('Porta el text de la safata d\'entrada', str_contains($green, 'Una línia'));
check('La plantilla agafa els colors del domini que envia',
    str_contains($green, '#1f6f3f') && str_contains($blue, '#123456') && !str_contains($green, '#123456'));
check('Sense logotip, la capçalera diu el nom del domini', str_contains($blue, 'EsportWeb'));
check('Sempre hi ha la línia per donar-se de baixa', str_contains($green, 'href="{{baixa}}"'));
$text = (new Renderer([], Brand::of('crosescolar.test')))->text($doc);
check('I la versió de text pla també la porta', str_contains($text, '{{baixa}}'));

echo "\n== La baixa ==\n";
$token = Unsubscribe::token('Familia@Exemple.cat', 7);
$read = Unsubscribe::read($token);
check('L\'enllaç es torna a llegir', $read === ['email' => 'familia@exemple.cat', 'mailing' => 7], json_encode($read));
$tampered = Unsubscribe::token('una-altra@exemple.cat', 7);
check('Canviar l\'adreça de l\'enllaç no serveix', Unsubscribe::read(substr($tampered, 0, -24) . substr($token, -24)) === null);
check('L\'enllaç no porta cap punt (semblaria l\'extensió d\'un fitxer)', !str_contains($token, '.'));
check('Un enllaç tallat no serveix', Unsubscribe::read(substr($token, 0, -3)) === null);
check('L\'adreça de baixa és del domini que envia',
    str_starts_with(Unsubscribe::url('a@b.cat', 'esportweb.test'), 'https://esportweb.test/baixa/'));

echo "\n== A qui va ==\n";
$now = date('Y-m-d H:i:s');
$client = static fn (string $name, string $email): int => Db::insert('clients', [
    'name' => $name, 'contact_name' => 'Contacte ' . $name, 'contact_email' => $email, 'status' => 'active', 'created_at' => $now,
]);
$instance = static fn (?int $clientId, string $slug, ?string $domain, string $email, string $status = 'active'): int => Db::insert('instances', [
    'client_id' => $clientId, 'slug' => $slug, 'domain' => $domain, 'site_name' => 'Web ' . $slug, 'status' => $status,
    'db_name' => 'x_' . $slug, 'admin_email' => $email, 'created_at' => $now,
]);
$a = $client('Escola A', 'a@escola.cat');
$b = $client('Club B', 'b@club.cat');
$c = $client('AFA C', 'c@afa.cat');
$client('Sense web', 'd@res.cat');
$instance($a, 'granada', null, 'admin@granada.cat');           // al domini principal (sense domini escrit)
$instance($b, 'trail', 'esportweb.test', 'admin@trail.cat');
$instance($c, 'vella', 'esportweb.test', 'admin@vella.cat', 'cancelled');
$mailing = ['audience' => 'clients', 'domain' => 'crosescolar.test', 'all_domains' => 0];
check('Els clients d\'un domini: només els que hi tenen un web', array_keys(Mailout::audience($mailing)) === ['a@escola.cat'],
    implode(',', array_keys(Mailout::audience($mailing))));
check('Els d\'un altre domini', array_keys(Mailout::audience(['domain' => 'esportweb.test'] + $mailing)) === ['b@club.cat']);
check('Amb «tots els dominis», tots els clients', count(Mailout::audience(['all_domains' => 1] + $mailing)) === 4);
check('Les administradores d\'un domini', array_keys(Mailout::audience(['audience' => 'instances', 'domain' => 'esportweb.test'] + $mailing)) === ['admin@trail.cat']);
check('Les de tots, també dels webs aturats si es demana',
    count(Mailout::audience(['audience' => 'instances', 'instance_status' => 'all', 'all_domains' => 1] + $mailing)) === 2);
$listId = Db::insert('mail_lists', ['name' => 'Premsa', 'created_at' => $now]);
Db::insert('mail_contacts', ['list_id' => $listId, 'email' => 'premsa@diari.cat', 'name' => 'Redacció', 'active' => 1, 'created_at' => $now]);
check('Una llista no es filtra pel domini', array_keys(Mailout::audience(['audience' => 'list', 'list_id' => $listId] + $mailing)) === ['premsa@diari.cat']);
check('Les adreces escrites a mà tampoc, i sense repetides',
    count(Mailout::audience(['audience' => 'manual', 'manual_emails' => "Anna <anna@x.cat>\nanna@x.cat\nmal escrita\nPere; pere@y.cat; Club"] + $mailing)) === 2);
Unsubscribe::add('a@escola.cat', 'crosescolar.test');
Unsubscribe::add('a@escola.cat', 'crosescolar.test'); // dos cops no peta
check('Qui s\'ha donat de baixa ja no hi és', Mailout::audience($mailing) === []);
check('I es compta a part', Mailout::audienceStats($mailing) === ['count' => 0, 'unsubscribed' => 1]);
Unsubscribe::remove('a@escola.cat');
check('Si torna a voler-ne rebre, hi torna a ser', count(Mailout::audience($mailing)) === 1);

echo "\n== Plantilles, desar i versions ==\n";
$templates = Mailout::templates();
check('Hi ha les plantilles de partida', array_column($templates, 'template_name') === ['Comunicat', 'Novetats', 'Recordatori'],
    implode(',', array_column($templates, 'template_name')));
Mailout::templates();
check('I no es tornen a crear', (int) Db::val("SELECT COUNT(*) FROM platform_mailings WHERE kind = 'template'") === 3);
$id = Mailout::create('Benvinguda, {{nom}}', (int) $templates[0]['id'], 'esportweb.test', null, 'Proves');
$m = Mailout::find($id);
check('Un correu nou parteix de la plantilla', $m['template_name'] === 'Comunicat' && Mailout::doc($m)['blocks'] == Mailout::doc($templates[0])['blocks']);
check('Neix com a esborrany, versió 1', $m['status'] === 'draft' && (int) $m['version'] === 1);
check('Surt del domini triat', Mailout::domain($m) === 'esportweb.test');
$r = Mailout::save($id, ['subject' => 'Benvinguda, {{nom}}'], 1, false, null, 'Proves');
check('Desar sense canvis no fa cap versió', $r['result'] === 'unchanged');
$doc = Mailout::doc($m);
$doc['blocks'][] = ['type' => 'text', 'html' => '<p>Un paràgraf nou per a {{entitat}}.</p>'];
$r = Mailout::save($id, ['doc' => $doc, 'preheader' => 'Hola'], 1, false, null, 'Anna');
check('Desar amb canvis fa la versió 2', $r['result'] === 'saved' && (int) $r['mailing']['version'] === 2);
$r = Mailout::save($id, ['subject' => 'Una altra cosa'], 1, false, null, 'Pere');
check('Desar damunt d\'una versió vella és un conflicte', $r['result'] === 'conflict');
check('I no ha tocat res', Mailout::find($id)['subject'] === 'Benvinguda, {{nom}}');
$r = Mailout::save($id, ['subject' => 'Una altra cosa'], 1, true, null, 'Pere');
check('Forçant-ho, sí', $r['result'] === 'saved' && (int) $r['mailing']['version'] === 3);
$versions = Mailout::versions($id);
check('Es guarden les tres versions, amb qui les ha desat', count($versions) === 3 && $versions[0]['saved_by_name'] === 'Pere');
Mailout::restore($id, (int) $versions[1]['id'], null, 'Anna');
check('Recuperar una versió la desa com una de nova', Mailout::find($id)['subject'] === 'Benvinguda, {{nom}}' && (int) Mailout::find($id)['version'] === 4);
$copy = Mailout::duplicate($id);
check('Duplicar fa un esborrany nou', Mailout::find($copy)['subject'] === 'Benvinguda, {{nom}} (còpia)' && Mailout::find($copy)['status'] === 'draft');
$tpl = Mailout::createTemplate('La meva plantilla', $id);
check('Desar com a plantilla', Mailout::find($tpl, Mailout::TEMPLATE)['template_name'] === 'La meva plantilla');
Mailout::delete($copy);
check('Esborrar-ne un se\'n porta les versions', (int) Db::val('SELECT COUNT(*) FROM platform_mailing_versions WHERE mailing_id = :id', ['id' => $copy]) === 0);

echo "\n== El correu de cada persona ==\n";
$m = Mailout::find($id);
$mail = Mailout::personalize($m, Mailout::parts($m), ['email' => 'b@club.cat', 'name' => 'Berta & Cia', 'entity' => 'Club <B>']);
check('L\'assumpte porta el nom', $mail['subject'] === 'Benvinguda, Berta & Cia', $mail['subject']);
check('Al cos, els camps van escapats', str_contains($mail['html'], 'Club &lt;B&gt;') && !str_contains($mail['html'], 'Club <B>'));
check('El camp {{web}} és el domini que envia', !str_contains($mail['html'], '{{') && str_contains($mail['html'], 'https://esportweb.test'));
check('L\'enllaç de baixa és el d\'aquesta persona', str_contains($mail['html'], htmlspecialchars(Unsubscribe::url('b@club.cat', 'esportweb.test', $id))));
check('I va a la capçalera, amb la baixa d\'un sol clic',
    $mail['headers']['List-Unsubscribe'] === '<' . Unsubscribe::url('b@club.cat', 'esportweb.test', $id) . '>'
    && $mail['headers']['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click');
$legacy = Db::insert('platform_mailings', ['subject' => 'D\'abans', 'body' => '<p>Text <b>antic</b></p>', 'status' => 'draft', 'created_at' => $now]);
$blocks = Mailout::doc(Mailout::find($legacy))['blocks'];
check('Un enviament d\'abans de l\'editor passa a un bloc de text', $blocks[1]['type'] === 'text' && str_contains($blocks[1]['html'], 'antic'));
Mailout::delete($legacy);

echo "\n== Programar i enviar ==\n";
Mailout::setDelivery($id, ['audience' => 'clients', 'domain' => 'esportweb.test', 'all_domains' => 1]);
check('A qui va es desa', (int) Mailout::find($id)['all_domains'] === 1 && Mailout::audienceCount(Mailout::find($id)) === 4);
try {
    Mailout::schedule($id, new DateTimeImmutable('-1 hour'));
    check('Programar al passat no es pot', false);
} catch (RuntimeException $e) {
    check('Programar al passat no es pot', str_contains($e->getMessage(), 'ja ha passat'));
}
Mailout::schedule($id, new DateTimeImmutable('+1 day'));
check('Programat per demà', Mailout::find($id)['status'] === 'scheduled');
check('El cron encara no el comença', Mailout::tick(2)['started'] === 0 && Mailout::find($id)['status'] === 'scheduled');
check('I com que té feina i el cron passa, no avisa', !Mailout::cronStale());
Settings::set('platform_mailout_tick', date('Y-m-d H:i:s', time() - 3600));
check('Si el cron fa una hora que no passa, el tauler avisa', Mailout::cronStale());
Mailout::unschedule($id);
check('Desprogramar el torna a esborrany', Mailout::find($id)['status'] === 'draft' && Mailout::find($id)['scheduled_at'] === null);
Mailout::schedule($id, new DateTimeImmutable('+1 day'));
Db::update('platform_mailings', ['scheduled_at' => date('Y-m-d H:i:s', time() - 60)], 'id = :id', ['id' => $id]);
Settings::set('site:esportweb.test:mail_hourly_limit', '3');
Settings::forget();
check('El límit per hora és de cada domini', Mailout::hourlyLimit('esportweb.test') === 3 && Mailout::hourlyLimit('crosescolar.test') === 0);
$r = Mailout::tick(3);
$m = Mailout::find($id);
check('Quan arriba l\'hora, el cron el comença', $r['started'] === 1 && (int) $m['total'] === 4, json_encode($r));
check('I n\'envia fins al límit per hora', $r['sent'] === 3 && (int) $m['sent'] === 3 && $r['waiting'] === 1, json_encode($r));
check('La resta s\'esperen', $m['status'] === 'sending' && Mailout::recipientCounts($id)['pending'] === 1);
check('Una tanda a mà tampoc no passa del límit', Mailout::sendBatch($id)['waiting'] === true);
$log = (string) @file_get_contents($site . '/storage/logs/app-' . date('Y-m') . '.log');
check('Els correus han sortit amb el nom de cadascú', str_contains($log, 'Benvinguda, Contacte Club B'), substr($log, -400));
check('I des del domini que toca', str_contains($log, '"from":"hola@esportweb.test"') && !str_contains($log, '"from":"hola@crosescolar.test"'));
Db::update('platform_mailing_recipients', ['sent_at' => date('Y-m-d H:i:s', time() - 7200)], 'mailing_id = :id', ['id' => $id]);
$r = Mailout::tick(3);
check('Passada l\'hora, s\'acaba d\'enviar', $r['sent'] === 1 && Mailout::find($id)['status'] === 'sent', json_encode($r));
check('I no l\'envia dos cops a ningú', (int) Mailout::find($id)['sent'] === 4 && Mailout::recipientCounts($id)['sent'] === 4);
$r = Mailout::save($id, ['subject' => 'Ja no'], 0, true);
check('Un enviament ja enviat no es pot canviar', $r['result'] === 'locked');
Mailout::archive($id);
check('Arxivar-lo el treu de la llista', !in_array($id, array_map('intval', array_column(Mailout::mails('tots'), 'id')), true)
    && in_array($id, array_map('intval', array_column(Mailout::mails('arxivats'), 'id')), true));
check('I es compta a la pestanya d\'arxivats', Mailout::counts()['arxivats'] === 1);

echo "\n== Quan el servidor de correu no va ==\n";
Settings::set('site:crosescolar.test:mail_transport', 'smtp');
Settings::set('site:crosescolar.test:smtp_host', '127.0.0.1');
Settings::set('site:crosescolar.test:smtp_port', '1');
Settings::set('site:crosescolar.test:smtp_secure', 'none');
Settings::forget();
$broken = Mailout::create('No arribarà', null, 'crosescolar.test');
Mailout::setDelivery($broken, ['audience' => 'manual', 'domain' => 'crosescolar.test',
    'manual_emails' => "u1@x.cat\nu2@x.cat\nu3@x.cat\nu4@x.cat\nu5@x.cat"]);
check('Començar-lo ara fa la llista', Mailout::start($broken) === 5 && Mailout::find($broken)['status'] === 'sending');
$r = Mailout::sendBatch($broken, 3);
$m = Mailout::find($broken);
check('Si no en surt cap, es posa en pausa sol', $r['failed'] === 3 && $m['status'] === 'paused', json_encode($r) . ' ' . $m['status']);
check('I diu per què', str_contains((string) $m['paused_reason'], 'no n\'ha pogut enviar cap'), (string) $m['paused_reason']);
check('En pausa, el cron no hi envia res', Mailout::tick(2)['failed'] === 0);
Settings::set('site:crosescolar.test:mail_transport', 'log');
Settings::forget();
Mailout::resume($broken);
check('Reprès, continua amb els que faltaven', Mailout::sendBatch($broken)['sent'] === 2 && Mailout::find($broken)['status'] === 'sent');
check('Tornar a provar els fallits', Mailout::retryFailed($broken) === 3 && Mailout::find($broken)['status'] === 'sending');
Mailout::sendBatch($broken);
check('I ara sí que han sortit tots', Mailout::recipientCounts($broken)['sent'] === 5 && Mailout::find($broken)['status'] === 'sent');

$stop = Mailout::create('Aturat', null, 'crosescolar.test');
Mailout::setDelivery($stop, ['audience' => 'manual', 'domain' => 'crosescolar.test', 'manual_emails' => "s1@x.cat\ns2@x.cat\ns3@x.cat"]);
Mailout::start($stop);
Mailout::sendBatch($stop, 1);
Mailout::stop($stop);
check('Aturar-lo: els que faltaven ja no el reben',
    Mailout::find($stop)['status'] === 'cancelled' && Mailout::recipientCounts($stop) ['skipped'] === 2 && Mailout::sendBatch($stop)['sent'] === 0);
$empty = Mailout::create('Ningú', null, 'crosescolar.test');
Mailout::setDelivery($empty, ['audience' => 'manual', 'domain' => 'crosescolar.test', 'manual_emails' => '']);
try {
    Mailout::start($empty);
    check('Sense destinataris no es pot enviar', false);
} catch (RuntimeException $e) {
    check('Sense destinataris no es pot enviar', str_contains($e->getMessage(), 'cap destinatari'));
}
check('Les pestanyes es compten bé', Mailout::counts()['enviats'] === 2 && Mailout::counts()['esborranys'] >= 1, json_encode(Mailout::counts()));

echo "\n== La IA ==\n";
Settings::set('ai_enabled', '1');
Settings::set('ai_anthropic_key', '');
Settings::set('ai_openai_key', '');
Settings::forget();
check('Sense claus, no hi ha cap servei', Ai::textProviders() === [] && !Ai::imageReady());
Settings::set('ai_anthropic_key', 'sk-ant-prova');
Settings::set('ai_openai_key', 'sk-openai-prova');
Settings::set('ai_claude_model', '');
Settings::forget();
check('Les claus es desen xifrades', !str_contains((string) Db::val("SELECT v FROM settings WHERE k = 'ai_anthropic_key'"), 'sk-ant-prova'));
check('Amb les claus, Claude i OpenAI', array_keys(Ai::textProviders()) === ['claude', 'openai'] && Ai::imageReady());
$calls = [];
$reply = [200, '{}'];
Ai::$fake = static function (string $method, string $url, array $headers, string $body) use (&$calls, &$reply): array {
    $calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

    return $reply;
};
$reply = [200, json_encode(['content' => [['type' => 'thinking', 'thinking' => 'x'], ['type' => 'text', 'text' => "Hola, {{nom}}:\n\nJa podeu obrir les inscripcions. <script>x()</script>"]], 'stop_reason' => 'end_turn'])];
$r = Ai::text('claude', 'Explica que ja es poden obrir les inscripcions', ['format' => 'paragraphs', 'tone' => 'proper', 'brand' => Brand::of('esportweb.test'), 'context' => 'Un correu sobre inscripcions']);
$call = $calls[0] ?? ['url' => '', 'headers' => [], 'body' => '{}'];
$sent = json_decode($call['body'], true) ?: [];
check('Claude: a l\'API de missatges', $call['url'] === 'https://api.anthropic.com/v1/messages');
check('Amb la clau i la versió de l\'API', in_array('x-api-key: sk-ant-prova', $call['headers'], true) && in_array('anthropic-version: 2023-06-01', $call['headers'], true));
check('El model per defecte, amb esforç baix i sense «thinking»', ($sent['model'] ?? '') === 'claude-opus-5-5'
    && ($sent['output_config']['effort'] ?? '') === 'low' && !isset($sent['thinking']), $call['body']);
check('I si el rebutja el filtre, que ho torni a provar un altre model',
    ($sent['fallbacks'] ?? '') === 'default' && in_array('anthropic-beta: server-side-fallback-2026-07-01', $call['headers'], true));
check('Les instruccions parlen del servei i del domini que envia', str_contains((string) ($sent['system'] ?? ''), 'EsportWeb (esportweb.test)'));
check('I la petició porta el context del correu', str_contains((string) ($sent['messages'][0]['content'] ?? ''), '<context>'));
check('Només es fa servir el text de la resposta, i net', str_contains($r['html'], '<p>Hola, {{nom}}:</p>') && !str_contains($r['html'], '<script') && !str_contains($r['html'], 'thinking'), $r['html']);
$reply = [200, json_encode(['content' => [], 'stop_reason' => 'refusal'])];
try {
    Ai::text('claude', 'Una cosa', []);
    check('Si Claude s\'hi nega, es diu', false);
} catch (RuntimeException $e) {
    check('Si Claude s\'hi nega, es diu', str_contains($e->getMessage(), 'no ha volgut'));
}
$reply = [401, json_encode(['error' => ['message' => 'invalid x-api-key']])];
try {
    Ai::text('claude', 'Una cosa', []);
    check('Una clau dolenta es diu amb el missatge de l\'API', false);
} catch (RuntimeException $e) {
    check('Una clau dolenta es diu amb el missatge de l\'API', str_contains($e->getMessage(), 'invalid x-api-key'));
}
$reply = [200, json_encode(['content' => [['type' => 'text', 'text' => "**Assumpte:** «Inscripcions obertes»\nResum: Ja podeu obrir-les des del panell"]]])];
$r = Ai::text('claude', 'Assumpte', ['format' => 'subject']);
check('L\'assumpte i el resum, en dues línies netes', $r['text'] === 'Inscripcions obertes' && $r['preheader'] === 'Ja podeu obrir-les des del panell', json_encode($r));
$calls = [];
$reply = [200, json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Obriu les inscripcions']]]]])];
$r = Ai::text('openai', 'Un botó', ['format' => 'button']);
$sent = json_decode($calls[0]['body'] ?? '{}', true) ?: [];
check('OpenAI: a l\'API Responses, amb la seva clau', ($calls[0]['url'] ?? '') === 'https://api.openai.com/v1/responses'
    && in_array('Authorization: Bearer sk-openai-prova', $calls[0]['headers'] ?? [], true) && ($sent['model'] ?? '') === 'gpt-5');
check('I en treu el text', $r['text'] === 'Obriu les inscripcions');
ob_start();
$im = imagecreatetruecolor(30, 20);
imagepng($im);
$png = (string) ob_get_clean();
$calls = [];
$reply = [200, json_encode(['data' => [['b64_json' => base64_encode($png)]]])];
$img = Ai::image('Una sortida de cros', 'landscape', 'illustration', true, [], false, Brand::of('crosescolar.test'));
$sent = json_decode($calls[0]['body'] ?? '{}', true) ?: [];
check('Imatges: a OpenAI, amb la mida i sense text', ($calls[0]['url'] ?? '') === 'https://api.openai.com/v1/images/generations'
    && ($sent['size'] ?? '') === '1536x1024' && str_contains((string) ($sent['prompt'] ?? ''), 'Sense cap text') && str_contains((string) ($sent['prompt'] ?? ''), '#1f6f3f'));
check('I la imatge es desa amb les de l\'editor', str_starts_with($img['src'], 'img:') && Images::file($img['src']) !== '');
$calls = [];
$ref = Ai::prepareRef($png, 'Captura de pantalla.PNG');
Ai::image('Fes servir aquesta foto de fons', 'square', 'photo', true, [$ref, $ref], true);
$multipart = (string) ($calls[0]['body'] ?? '');
check('Amb referències, a l\'edició d\'imatges', ($calls[0]['url'] ?? '') === 'https://api.openai.com/v1/images/edits');
check('I totes les referències amb el mateix nom de camp', substr_count($multipart, 'name="image[]"; filename="captura-de-pantalla.png"') === 2
    && str_contains($multipart, 'name="input_fidelity"'));
Ai::$fake = null;
Settings::set('ai_anthropic_key', '');
Settings::set('ai_openai_key', '');

exec('rm -rf ' . escapeshellarg($site));

echo "\n== Resultat ==\n";
echo "  $passed proves correctes, $failed errors\n";
exit($failed > 0 ? 1 : 0);
