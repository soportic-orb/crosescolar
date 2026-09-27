<?php
/**
 * Encaminador per al servidor integrat de PHP quan el web que es prova és una
 * còpia en una altra carpeta (les proves del panell de la plataforma, per
 * exemple).
 *
 * Sense encaminador, el servidor integrat de PHP 8.2 mira si l'adreça acaba en
 * una extensió coneguda i, si el fitxer no hi és, respon 404 sense passar per
 * l'index.php. Això deixava «/sitemap.xml» i «/robots.txt» fora de les proves
 * tot i que a nginx funcionen. Amb encaminador, tot passa per l'aplicació, que
 * és el que fa el servidor de debò.
 */
declare(strict_types=1);

$root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), '/');
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

// Els fitxers que hi són de debò (fulls d'estil, imatges, l'assistent) se
// serveixen tal com estan.
if ($path !== '/' && !str_contains($path, '..') && is_file($root . $path)) {
    return false;
}

require $root . '/index.php';
