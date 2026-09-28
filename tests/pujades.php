<?php
/**
 * Proves dels límits de pujada.
 *
 * El cas que aquestes proves cobreixen és el que més mal fa i menys es veu:
 * quan una pujada passa de «post_max_size», PHP descarta el cos de la petició
 * sencer i deixa $_POST i $_FILES buits. El testimoni del formulari hi anava a
 * dins, així que la comprovació CSRF salta i qui ho enviava es troba un «la
 * sessió ha caducat» que no hi té res a veure —i el formulari, sense desar.
 *
 * Ús:  php tests/pujades.php
 */
declare(strict_types=1);

require __DIR__ . '/env.php';

use Cros\Core\Uploader;

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  OK   ' : '  FALLA ') . $name . ($ok || $detail === '' ? '' : " → $detail") . "\n";
}

/** Deixa els superglobals com estaven abans de la prova. */
$posar = static function (string $method, array $post, array $files, ?int $length): void {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $post;
    $_FILES = $files;
    if ($length === null) {
        unset($_SERVER['CONTENT_LENGTH']);
    } else {
        $_SERVER['CONTENT_LENGTH'] = (string) $length;
    }
};

echo "\n== Petició descartada per massa gran ==\n";

$posar('POST', [], [], 12582912);
check('Un POST buit amb cos de 12 MB és una pujada descartada', Uploader::postTooBig());

$posar('POST', ['_token' => 'x'], [], 400);
check('Un POST normal no ho és', !Uploader::postTooBig());

$posar('POST', [], ['imatge' => ['error' => UPLOAD_ERR_OK]], 12582912);
check('Amb fitxers rebuts tampoc', !Uploader::postTooBig());

$posar('GET', [], [], 0);
check('Una visita normal tampoc', !Uploader::postTooBig());

// Un POST buit de debò (un formulari sense cap camp) no porta cos: sense
// aquesta condició, qualsevol petició rara semblaria una pujada avortada.
$posar('POST', [], [], 0);
check('Un POST sense cos no compta', !Uploader::postTooBig());

$posar('POST', [], [], null);
check('Ni un POST sense capçalera de mida', !Uploader::postTooBig());

$posar('GET', [], [], null);

echo "\n== El límit que s'ensenya ==\n";

$limit = Uploader::serverLimit();
check('El límit és un nombre de bytes positiu', $limit > 0, (string) $limit);
check('I no passa del que accepta l\'aplicació', $limit <= Uploader::MAX_BYTES,
    $limit . ' > ' . Uploader::MAX_BYTES);

$upload = (string) ini_get('upload_max_filesize');
$post = (string) ini_get('post_max_size');
check('No promet més del que deixa el php.ini',
    $limit <= 1048576 * max(1, (int) $upload) && $limit <= 1048576 * max(1, (int) $post),
    "upload=$upload post=$post límit=$limit");

check('S\'explica en MB', (bool) preg_match('/^\d+ MB$/', Uploader::serverLimitLabel()),
    Uploader::serverLimitLabel());

echo "\n" . ($failed === 0
    ? "Totes les proves passen ($passed).\n"
    : "$failed proves fallen de " . ($passed + $failed) . ".\n");

exit($failed === 0 ? 0 : 1);
