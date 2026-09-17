<?php
/**
 * Genera el paquet d'actualització (ZIP) i el manifest per al sistema OTA.
 *
 * Ús:
 *   php tools/build-release.php                      Paquet de la versió actual
 *   php tools/build-release.php --version=1.1.0      Puja la versió i genera el paquet
 *   php tools/build-release.php --url=https://...    Base de descàrrega per al manifest
 *   php tools/build-release.php --notes="Novetats"   Notes de la versió
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$options = getopt('', ['version::', 'url::', 'notes::', 'out::']);

$versionFile = $root . '/app/version.php';
$current = require $versionFile;
$version = $options['version'] ?? $current['version'];
$minPhp = $current['min_php'] ?? '8.0.0';

if (!preg_match('/^\d+\.\d+\.\d+$/', (string) $version)) {
    fwrite(STDERR, "La versió ha de tenir el format X.Y.Z\n");
    exit(1);
}

// Actualitza app/version.php si cal
if ($version !== $current['version']) {
    $contents = "<?php\n/**\n * Versió de l'aplicació. L'actualitzador OTA compara aquest valor amb el\n"
        . " * manifest remot per decidir si hi ha una versió nova disponible.\n */\nreturn "
        . var_export(['version' => $version, 'released' => date('Y-m-d'), 'min_php' => $minPhp], true) . ";\n";
    file_put_contents($versionFile, $contents);
    echo "Versió actualitzada a $version\n";
}

$distDir = $options['out'] ?? ($root . '/dist');
if (!is_dir($distDir) && !mkdir($distDir, 0775, true) && !is_dir($distDir)) {
    fwrite(STDERR, "No s'ha pogut crear la carpeta $distDir\n");
    exit(1);
}

$zipName = 'cros-escolar-' . $version . '.zip';
$zipPath = $distDir . '/' . $zipName;
@unlink($zipPath);

$excludedTop = ['.git', '.github', 'dist', 'node_modules', 'tests', 'storage', 'uploads', '.gitignore'];
$excludedFiles = ['app/config.php'];

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "No s'ha pogut crear el ZIP\n");
    exit(1);
}

$count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($current) use ($root, $excludedTop) {
            $relative = str_replace('\\', '/', substr($current->getPathname(), strlen($root) + 1));
            return !in_array(explode('/', $relative)[0], $excludedTop, true);
        }
    )
);
foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
    if ($item->isDir() || in_array($relative, $excludedFiles, true)) {
        continue;
    }
    $zip->addFile($item->getPathname(), $relative);
    $count++;
}

// Carpetes d'escriptura buides (es conserven les de la instal·lació existent)
foreach (['storage/logs', 'storage/backups', 'storage/tmp', 'uploads/sponsors', 'uploads/media', 'uploads/documents'] as $dir) {
    $zip->addEmptyDir($dir);
    $zip->addFromString($dir . '/.gitkeep', '');
}
$zip->close();

$hash = hash_file('sha256', $zipPath);
$baseUrl = rtrim((string) ($options['url'] ?? 'https://cros.afalagranada.cat/actualitzacions'), '/');

$manifest = [
    'version' => $version,
    'released' => date('Y-m-d'),
    'min_php' => $minPhp,
    'zip_url' => $baseUrl . '/' . $zipName,
    'sha256' => $hash,
    'notes' => (string) ($options['notes'] ?? 'Versió ' . $version . ' del web del Cros Escolar La Granada.'),
];
file_put_contents($distDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

printf("Paquet creat: %s (%d fitxers, %.1f KB)\nSHA-256: %s\nManifest: %s/manifest.json\n",
    $zipPath, $count, filesize($zipPath) / 1024, $hash, $distDir);
