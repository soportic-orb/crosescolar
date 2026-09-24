<?php
/**
 * Eina de consola de la plataforma.
 *
 *   php tools/platform.php migrar                     posa al dia la base de dades
 *   php tools/platform.php usuari "Nom" correu [clau] crea o actualitza un superadministrador
 *   php tools/platform.php instancies                 llista les instàncies
 *   php tools/platform.php repassar                   actualitza les dades de totes
 *   php tools/platform.php actualitzar                aplica els canvis pendents a totes
 *   php tools/platform.php vigilar [--sense-web]      mira que totes responguin i avisa dels canvis
 *   php tools/platform.php purgar [--de-veritat]      esborra les baixes que ja han passat els 90 dies
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Aquesta eina només funciona per línia d'ordres.\n");
}

$root = dirname(__DIR__);
require $root . '/app/core/Tenancy.php';
require $root . '/app/bootstrap.php';

use Cros\Core\Db;
use Cros\Platform\Console;
use Cros\Platform\Health;
use Cros\Platform\Instance;
use Cros\Platform\Platform;
use Cros\Platform\Provisioner;

if (!Platform::configured($root)) {
    exit("No hi ha cap plataforma configurada: copieu tenants/platform.php.example a tenants/platform.php.\n");
}

try {
    Platform::boot($root);
} catch (Throwable $e) {
    exit('No s\'ha pogut obrir la base de dades de la plataforma: ' . $e->getMessage() . "\n");
}

$command = $argv[1] ?? 'ajuda';

switch ($command) {
    case 'migrar':
        $done = Platform::migrate();
        echo $done
            ? "Migracions aplicades:\n  " . implode("\n  ", $done) . "\n"
            : "La base de dades ja estava al dia.\n";
        break;

    case 'usuari':
        $name = $argv[2] ?? '';
        $email = $argv[3] ?? '';
        $password = $argv[4] ?? '';
        if ($name === '' || $email === '') {
            exit("Ús: php tools/platform.php usuari \"Nom i cognoms\" correu@exemple.cat [contrasenya]\n");
        }
        if ($password === '') {
            $password = Provisioner::password(14);
            $generated = true;
        }
        $id = Console::save($name, $email, $password);
        echo "Usuari #$id desat.\n  Correu: $email\n";
        if (!empty($generated)) {
            echo "  Contrasenya: $password\n  (Apunteu-la: no es tornarà a veure.)\n";
        }
        break;

    case 'instancies':
        $rows = Instance::all();
        if (!$rows) {
            echo "Cap instància.\n";
            break;
        }
        foreach ($rows as $row) {
            printf(
                "%-20s %-28s %-16s %5d inscrits  %s\n",
                $row['slug'],
                mb_substr((string) $row['site_name'], 0, 28),
                Instance::label((string) $row['status']),
                (int) $row['registrations'],
                (string) ($row['event_date'] ?? '')
            );
        }
        break;

    case 'repassar':
        $ok = 0;
        $failed = [];
        foreach (Instance::all() as $row) {
            if (Instance::sync((int) $row['id'], $root)) {
                $ok++;
            } else {
                $failed[] = (string) $row['slug'];
            }
        }
        echo "Instàncies repassades: $ok\n";
        if ($failed) {
            echo "No s'han pogut llegir: " . implode(', ', $failed) . "\n";
        }
        break;

    case 'actualitzar':
        $pending = Instance::outdated();
        if (!$pending) {
            echo "Totes les instàncies ja tenen la versió " . app_version() . ".\n";
            break;
        }
        foreach ($pending as $row) {
            $result = Instance::upgrade((int) $row['id'], $root);
            if (!$result['ok']) {
                echo '  ' . $row['slug'] . ": ERROR " . $result['error'] . "\n";
                continue;
            }
            echo '  ' . $row['slug'] . ': ' . ($result['applied']
                ? count($result['applied']) . ' canvi(s) aplicats'
                : 'ja estava al dia') . "\n";
        }
        echo "Fet.\n";
        break;

    case 'vigilar':
        $report = Health::run($root, in_array('--sense-web', $argv, true) ? false : null);
        echo $report['checked'] . " instàncies mirades.\n";
        if ($report['broke']) {
            echo "  Han caigut: " . implode(', ', $report['broke']) . "\n";
        }
        if ($report['recovered']) {
            echo "  Han tornat: " . implode(', ', $report['recovered']) . "\n";
        }
        if ($report['failing']) {
            echo "  No responen: " . implode(', ', $report['failing']) . "\n";
        } else {
            echo "  Totes responen.\n";
        }
        break;

    case 'purgar':
        $real = in_array('--de-veritat', $argv, true);
        $rows = Db::all(
            "SELECT * FROM instances WHERE status = 'cancelled' AND purge_at IS NOT NULL AND purge_at <= :today",
            ['today' => date('Y-m-d')]
        );
        if (!$rows) {
            echo "Cap instància per esborrar.\n";
            break;
        }
        foreach ($rows as $row) {
            echo ($real ? 'Esborrant ' : 'S\'esborraria ') . $row['slug'] . ' (' . $row['db_name'] . ")\n";
            if ($real) {
                Provisioner::purge((int) $row['id'], $root);
            }
        }
        if (!$real) {
            echo "\nNo s'ha tocat res. Torneu-hi amb --de-veritat per esborrar-les.\n";
        }
        break;

    default:
        echo "Ordres: migrar · usuari · instancies · repassar · actualitzar · vigilar · purgar\n";
}
