<?php
/**
 * Fitxer de configuració d'exemple.
 * L'instal·lador (install.php) genera app/config.php a partir d'aquest fitxer.
 * No cal editar-lo a mà si feu servir l'instal·lador.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'cros',
        'user'    => 'cros',
        'pass'    => '',
        'charset' => 'utf8mb4',
        'socket'  => '',
    ],
    // Clau de xifratge (32 bytes en base64). Es genera durant la instal·lació.
    'app_key'  => '',
    // URL base del web, sense barra final. Buit = detecció automàtica.
    'base_url' => '',
    // Mostra els errors per pantalla (només per a desenvolupament).
    'debug'    => false,
    // Zona horària
    'timezone' => 'Europe/Madrid',
];
