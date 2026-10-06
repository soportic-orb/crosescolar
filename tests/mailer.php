<?php
/**
 * Proves de com es construeix un correu: que el remitent, l'assumpte i la
 * resta de capçaleres compleixin la norma.
 *
 * Els servidors de Microsoft (Outlook, Hotmail, Microsoft 365) són els més
 * estrictes: un remitent mal escrit, que d'altres deixen passar, allà fa que
 * el correu es rebutgi. Per això hi ha un servidor SMTP de prova que recull
 * el missatge sencer tal com sortiria i es mira línia per línia.
 *
 * Ús:  php tests/mailer.php
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Mailer;
use Cros\Core\Settings;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

echo "== El remitent ==\n";
check('Un nom senzill va tal qual',
    Mailer::address('hola@crosescolar.cat', 'Cros Escolar') === 'Cros Escolar <hola@crosescolar.cat>');
check('Un nom amb coma va entre cometes (si no, semblarien dues adreces)',
    Mailer::address('hola@crosescolar.cat', 'Cros Escolar, La Granada') === '"Cros Escolar, La Granada" <hola@crosescolar.cat>');
check('Amb parèntesis i punts, també',
    Mailer::address('a@b.cat', 'Cursa (5a ed.)') === '"Cursa (5a ed.)" <a@b.cat>');
check('Les cometes de dins del nom es protegeixen',
    Mailer::address('a@b.cat', 'El "Cros", 2026') === '"El \"Cros\", 2026" <a@b.cat>');
$accents = Mailer::address('hola@crosescolar.cat', 'Cros Escolar de la Granada del Penedès');
check('Un nom amb accents va codificat', str_starts_with($accents, '=?UTF-8?B?') && str_ends_with($accents, ' <hola@crosescolar.cat>'), $accents);
check('I es pot tornar a llegir igual',
    mb_decode_mimeheader(substr($accents, 0, (int) strrpos($accents, ' <'))) === 'Cros Escolar de la Granada del Penedès');
$barreja = Mailer::address('a@b.cat', 'Cros, Penedès');
check('Amb coma i accents, la coma no queda fora de la codificació',
    !str_contains(substr($barreja, 0, (int) strrpos($barreja, ' <')), ',')
    && mb_decode_mimeheader(substr($barreja, 0, (int) strrpos($barreja, ' <'))) === 'Cros, Penedès', $barreja);
check('Sense nom, només l\'adreça', Mailer::address('hola@crosescolar.cat', '') === 'hola@crosescolar.cat');
check('Un salt de línia al nom no pot afegir capçaleres',
    !str_contains(Mailer::address('a@b.cat', "Nom\r\nBcc: algu@x.cat"), "\n"));

echo "\n== L'assumpte ==\n";
check('Un assumpte sense accents va tal qual', Mailer::encodeHeader('Prova de correu', 9) === 'Prova de correu');
$llarg = 'Inscripció confirmada — Cros Escolar de la Granada del Penedès, edició de tardor de 2026';
$codificat = Mailer::encodeHeader($llarg, strlen('Subject: '));
$linies = explode("\r\n", 'Subject: ' . $codificat);
check('Un assumpte llarg es parteix en trossos', count($linies) > 1, $codificat);
check('Cap tros no passa dels 76 caràcters', max(array_map('strlen', $linies)) <= 76,
    implode(' | ', array_map('strlen', $linies)));
check('Els trossos de continuació comencen amb un espai',
    count(array_filter(array_slice($linies, 1), static fn (string $l): bool => $l[0] !== ' ')) === 0);
check('I es torna a llegir igual', mb_decode_mimeheader($codificat) === $llarg);

echo "\n== El missatge sencer, tal com surt per SMTP ==\n";
// Un servidor SMTP de prova, en PHP perquè no calgui res més: accepta una
// connexió, ho contesta tot amb un «d'acord» i desa la conversa.
$capture = sys_get_temp_dir() . '/cros-smtp-' . getmypid() . '.txt';
@unlink($capture);
$port = 25000 + (getmypid() % 5000);
$server = <<<'PHP'
$port = (int) $argv[1];
$out = $argv[2];
$srv = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if (!$srv) { exit(1); }
file_put_contents($out . '.ready', '1');
$c = stream_socket_accept($srv, 20);
if (!$c) { exit(1); }
$log = '';
$say = function (string $line) use ($c) { fwrite($c, $line . "\r\n"); };
$say('220 prova ESMTP');
$data = false;
while (($line = fgets($c)) !== false) {
    if ($data) {
        if ($line === ".\r\n") { $data = false; $say('250 OK'); continue; }
        $log .= 'D:' . $line;
        continue;
    }
    $log .= 'C:' . $line;
    $cmd = strtoupper(substr(trim($line), 0, 4));
    if ($cmd === 'EHLO') { fwrite($c, "250-prova\r\n250 OK\r\n"); }
    elseif ($cmd === 'DATA') { $say('354 Endavant'); $data = true; }
    elseif ($cmd === 'QUIT') { $say('221 Adeu'); break; }
    else { $say('250 OK'); }
}
file_put_contents($out, $log);
PHP;
$proc = proc_open([PHP_BINARY, '-r', $server, (string) $port, $capture], [], $pipes);
for ($i = 0; $i < 50 && !is_file($capture . '.ready'); $i++) {
    usleep(100000);
}

Settings::prime([
    'mail_transport' => 'smtp',
    'smtp_host' => '127.0.0.1',
    'smtp_port' => (string) $port,
    'smtp_secure' => 'none',
    'smtp_user' => '',
    'mail_from_email' => 'no-reply@crosescolar.cat',
    'mail_from_name' => 'Cros Escolar, La Granada',
    'mail_reply_to' => '',
]);
$ok = Mailer::send('familia@outlook.com', $llarg, '<p>Hola</p>', ['bcc' => 'copia@crosescolar.cat']);
proc_close($proc);
$conversa = (string) @file_get_contents($capture);
@unlink($capture);
@unlink($capture . '.ready');
check('El correu surt', $ok, Mailer::lastError());

$missatge = '';
foreach (explode("\n", $conversa) as $linia) {
    if (str_starts_with($linia, 'D:')) {
        $missatge .= substr($linia, 2) . "\n";
    }
}
$capcaleres = substr($missatge, 0, (int) strpos($missatge, "\r\n\r\n"));
check('El remitent va entre cometes',
    str_contains($capcaleres, 'From: "Cros Escolar, La Granada" <no-reply@crosescolar.cat>'), $capcaleres);
check('La còpia oculta no surt a les capçaleres', !preg_match('/^Bcc:/mi', $capcaleres));
check('Però sí que arriba a qui l\'ha de rebre', str_contains($conversa, 'C:RCPT TO:<copia@crosescolar.cat>'));
check('L\'identificador és del domini de qui envia',
    (bool) preg_match('/^Message-ID: <[0-9a-f]+@crosescolar\.cat>/m', $capcaleres), $capcaleres);
check('L\'assumpte va partit com mana la norma',
    (bool) preg_match('/^Subject: =\?UTF-8\?B\?[^\r\n]+\r\n =\?UTF-8\?B\?/m', $capcaleres), $capcaleres);
$totes = explode("\n", rtrim($missatge, "\n"));
check('Totes les línies acaben amb CRLF',
    count(array_filter($totes, static fn (string $l): bool => $l !== '' && !str_ends_with($l, "\r"))) === 0);
check('Cap línia no passa dels 998 caràcters', max(array_map('strlen', $totes)) <= 998);
check('El sobre diu el mateix remitent que la capçalera',
    str_contains($conversa, 'C:MAIL FROM:<no-reply@crosescolar.cat>'));

echo "\n== Resultat ==\n  $passed proves correctes, $failed errors\n\n";
exit($failed === 0 ? 0 : 1);
