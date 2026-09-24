<?php
/**
 * Què es pot configurar de la plataforma des del seu panell.
 *
 * Té la mateixa forma que l'esquema d'un cros (app/settings_schema.php) i es
 * desa igual, a la taula «settings» de la base de dades de la plataforma. El
 * que no hi és —els dominis, les bases de dades i qui pot crear-ne— continua
 * al fitxer tenants/platform.php: són coses que han de funcionar abans que hi
 * hagi cap base de dades a punt.
 */
declare(strict_types=1);

return [
    'general' => [
        'title' => 'El servei',
        'icon' => 'settings',
        'description' => 'Com es diu la plataforma i què hi veu qui hi arriba.',
        'fields' => [
            'site_name' => ['label' => 'Nom del servei', 'type' => 'text', 'default' => 'Cros Escolar',
                'help' => 'Surt al panell, a la pàgina pública i als correus.'],
            'platform_tagline' => ['label' => 'Frase de la portada', 'type' => 'text', 'default' => 'El web del vostre cros escolar, a punt en un moment.'],
            'platform_intro' => ['label' => 'Text de presentació', 'type' => 'html', 'rows' => 5, 'default' => '',
                'help' => 'Si es deixa buit, la portada ensenya el text de sempre.'],
            'platform_contact_email' => ['label' => 'Adreça de contacte pública', 'type' => 'email', 'default' => '',
                'help' => 'La que es dona a qui vol preguntar alguna cosa abans de demanar un cros.'],
        ],
    ],

    'appearance' => [
        'title' => 'Imatge',
        'icon' => 'image',
        'description' => 'El logotip i els colors de la plataforma.',
        'fields' => [
            'platform_logo' => ['label' => 'Logotip', 'type' => 'image', 'folder' => 'plataforma', 'max_width' => 600, 'default' => '',
                'help' => 'Es veu a la portada i a dalt del panell. PNG o SVG amb fons transparent.'],
            'platform_favicon' => ['label' => 'Icona de la pestanya', 'type' => 'image', 'folder' => 'plataforma', 'max_width' => 180, 'default' => ''],
            'color_primary' => ['label' => 'Color principal', 'type' => 'color', 'default' => '#1f4f5f',
                'help' => 'El del panell i els botons.'],
            'platform_color_accent' => ['label' => 'Color d\'acompanyament', 'type' => 'color', 'default' => '#2f7d84'],
            'platform_color_dark' => ['label' => 'Color fosc', 'type' => 'color', 'default' => '#16303d',
                'help' => 'El fons de la capçalera de la pàgina pública.'],
        ],
    ],

    'mail' => [
        'title' => 'Correu',
        'icon' => 'mail',
        'description' => 'D\'on surten els avisos i les claus que s\'envien als clients.',
        'fields' => [
            'mail_from_name' => ['label' => 'Nom de qui envia', 'type' => 'text', 'default' => 'Cros Escolar'],
            'mail_from_email' => ['label' => 'Adreça de qui envia', 'type' => 'email', 'default' => ''],
            'mail_reply_to' => ['label' => 'Adreça per a les respostes', 'type' => 'email', 'default' => ''],
            'mail_admin_notify' => ['label' => 'On arriben els avisos', 'type' => 'email', 'default' => '',
                'help' => 'Sol·licituds noves i webs que deixen de respondre.'],
            'mail_transport' => ['label' => 'Com s\'envia', 'type' => 'select', 'default' => 'mail',
                'options' => ['mail' => 'Funció mail() del servidor', 'smtp' => 'Servidor SMTP', 'log' => 'Assaig: no enviar res']],
            'smtp_host' => ['label' => 'Servidor SMTP', 'type' => 'text', 'default' => '', 'show_if' => 'mail_transport:smtp'],
            'smtp_port' => ['label' => 'Port', 'type' => 'number', 'default' => '587', 'show_if' => 'mail_transport:smtp'],
            'smtp_user' => ['label' => 'Usuari', 'type' => 'text', 'default' => '', 'show_if' => 'mail_transport:smtp'],
            'smtp_pass' => ['label' => 'Contrasenya', 'type' => 'password', 'default' => '', 'show_if' => 'mail_transport:smtp'],
            'smtp_secure' => ['label' => 'Xifratge', 'type' => 'select', 'default' => 'tls', 'show_if' => 'mail_transport:smtp',
                'options' => ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Cap']],
        ],
    ],

    'requests' => [
        'title' => 'Sol·licituds',
        'icon' => 'mail',
        'description' => 'El formulari de qui vol el web del seu cros.',
        'fields' => [
            'platform_requests_open' => ['label' => 'Acceptar sol·licituds noves', 'type' => 'bool', 'default' => '1',
                'help' => 'Si es desactiva, el formulari desapareix de la portada.'],
            'platform_requests_closed_text' => ['label' => 'Què es diu quan estan tancades', 'type' => 'textarea', 'rows' => 3,
                'default' => 'Ara mateix no donem altes noves. Escriviu-nos i us avisarem quan tornem a obrir.',
                'show_if' => '!platform_requests_open'],
            'platform_directory' => ['label' => 'Ensenyar el llistat de cros a la portada', 'type' => 'bool', 'default' => '1'],
        ],
    ],

    'monitor' => [
        'title' => 'Vigilància i còpies',
        'icon' => 'refresh',
        'description' => 'Com es miren les instàncies i quantes còpies se\'n guarden.',
        'fields' => [
            'platform_monitor_web' => ['label' => 'Mirar també que la pàgina s\'obri', 'type' => 'bool', 'default' => '1',
                'help' => 'Si es desactiva, només es comprova la base de dades de cada cros.'],
            'platform_backups_keep' => ['label' => 'Còpies que es guarden de cada client', 'type' => 'number', 'default' => '7'],
        ],
    ],

    'updates' => [
        'title' => 'Actualitzacions',
        'icon' => 'refresh',
        'description' => 'D\'on surten les versions noves del sistema.',
        'fields' => [
            'update_manifest_url' => ['label' => 'Adreça del manifest', 'type' => 'url', 'default' => '',
                'help' => 'El fitxer manifest.json de les versions publicades.'],
            'update_token' => ['label' => 'Testimoni d\'accés', 'type' => 'password', 'default' => '',
                'help' => 'Només cal si el lloc de les versions demana identificar-se.'],
            'update_auto_check' => ['label' => 'Comprovar-ho sol un cop al dia', 'type' => 'bool', 'default' => '1'],
        ],
    ],
];
