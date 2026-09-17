<?php
/**
 * Esquema dels textos i opcions configurables des del panell d'administració.
 * Els formularis del panell es generen automàticament a partir d'aquest fitxer.
 *
 * Tipus disponibles: text, textarea, html, email, url, tel, number, money, date,
 * time, color, bool, select, image, file, password.
 */
return [
    'general' => [
        'title' => 'Dades de la cursa',
        'icon' => 'calendar',
        'description' => 'Informació bàsica de l\'esdeveniment que es mostra a tot el web.',
        'fields' => [
            'site_name' => ['label' => 'Nom del web', 'type' => 'text', 'default' => 'Cros Escolar La Granada'],
            'site_tagline' => ['label' => 'Lema', 'type' => 'text', 'default' => 'Córrer entre vinyes a l\'Alt Penedès'],
            'event_date' => ['label' => 'Data de la cursa', 'type' => 'date', 'default' => '2026-10-04'],
            'event_time' => ['label' => 'Hora d\'inici', 'type' => 'time', 'default' => '09:30'],
            'event_place' => ['label' => 'Lloc de sortida', 'type' => 'text', 'default' => 'Zona esportiva de La Granada'],
            'event_address' => ['label' => 'Adreça', 'type' => 'text', 'default' => 'Carrer de l\'Esport, s/n — 08792 La Granada (Alt Penedès)'],
            'event_town' => ['label' => 'Població', 'type' => 'text', 'default' => 'La Granada, Alt Penedès'],
            'organizer' => ['label' => 'Organitza', 'type' => 'text', 'default' => 'AFA Escola La Granada'],
            'collaborators' => ['label' => 'Amb la col·laboració de', 'type' => 'text', 'default' => 'Ajuntament de La Granada i Consell Esportiu de l\'Alt Penedès'],
            'contact_email' => ['label' => 'Correu de contacte', 'type' => 'email', 'default' => 'cros@afalagranada.cat'],
            'contact_phone' => ['label' => 'Telèfon de contacte', 'type' => 'tel', 'default' => ''],
            'social_instagram' => ['label' => 'Instagram (URL)', 'type' => 'url', 'default' => ''],
            'social_facebook' => ['label' => 'Facebook (URL)', 'type' => 'url', 'default' => ''],
            'social_whatsapp' => ['label' => 'Grup de WhatsApp (URL)', 'type' => 'url', 'default' => ''],
            'pretty_urls' => ['label' => 'URLs amigables', 'type' => 'bool', 'default' => '1', 'help' => 'Desactiveu-ho només si el servidor no té la reescriptura d\'URL activada.'],
            'analytics_id' => ['label' => 'Identificador d\'analítica', 'type' => 'text', 'default' => '', 'help' => 'Opcional. Codi de Google Analytics 4 (G-XXXXXXX) o Matomo.'],
        ],
    ],

    'coming_soon' => [
        'title' => 'Web en preparació',
        'icon' => 'eye',
        'description' => 'Amaga el web al públic i mostra només un avís. Les persones amb sessió iniciada al panell continuen veient el web complet.',
        'fields' => [
            'coming_soon' => ['label' => 'Amagar el web al públic', 'type' => 'bool', 'default' => '0',
                'help' => 'Mentre estigui activat, qualsevol visitant veurà l\'avís «Aviat publicarem el web».'],
            'coming_soon_title' => ['label' => 'Títol de l\'avís', 'type' => 'text', 'default' => 'Aviat publicarem el web'],
            'coming_soon_text' => ['label' => 'Text de l\'avís', 'type' => 'html', 'rows' => 5,
                'default' => '<p>Estem preparant el web del Cros Escolar La Granada amb tota la informació de la cursa, els recorreguts i la venda de tiquets de l\'esmorzar.</p><p>Torneu-hi ben aviat!</p>'],
            'coming_soon_image' => ['label' => 'Imatge de fons', 'type' => 'image', 'default' => '',
                'help' => 'Opcional. Si no n\'hi ha cap, es fa servir la del banner de la portada o el fons il·lustrat de vinyes.'],
            'coming_soon_countdown' => ['label' => 'Mostrar la data i el compte enrere', 'type' => 'bool', 'default' => '1'],
            'coming_soon_contact' => ['label' => 'Mostrar el contacte i les xarxes', 'type' => 'bool', 'default' => '1'],
        ],
    ],

    'home' => [
        'title' => 'Portada',
        'icon' => 'home',
        'description' => 'Banner principal i textos de la pàgina d\'inici.',
        'fields' => [
            'hero_image' => ['label' => 'Imatge del banner', 'type' => 'image', 'default' => '', 'help' => 'Recomanat 1920×1080 px. Si no n\'hi ha cap, es mostra el fons il·lustrat de vinyes.'],
            'hero_badge' => ['label' => 'Etiqueta superior', 'type' => 'text', 'default' => 'X Cros Escolar'],
            'hero_title' => ['label' => 'Títol del banner', 'type' => 'text', 'default' => 'Cros Escolar La Granada'],
            'hero_subtitle' => ['label' => 'Subtítol del banner', 'type' => 'textarea', 'rows' => 2, 'default' => 'Una matinal d\'esport i festa entre les vinyes del Penedès per a totes les edats.'],
            'hero_cta_label' => ['label' => 'Botó principal (text)', 'type' => 'text', 'default' => 'Compra els tiquets de l\'esmorzar'],
            'hero_cta_url' => ['label' => 'Botó principal (enllaç)', 'type' => 'text', 'default' => '/esmorzar'],
            'hero_cta2_label' => ['label' => 'Botó secundari (text)', 'type' => 'text', 'default' => 'Categories i premis'],
            'hero_cta2_url' => ['label' => 'Botó secundari (enllaç)', 'type' => 'text', 'default' => '/categories-i-premis'],
            'countdown_enabled' => ['label' => 'Mostrar compte enrere', 'type' => 'bool', 'default' => '1'],
            'intro_title' => ['label' => 'Títol de la introducció', 'type' => 'text', 'default' => 'La cursa del poble'],
            'intro_text' => ['label' => 'Text de la introducció', 'type' => 'html', 'rows' => 6, 'default' => '<p>El Cros Escolar de La Granada és una matinal esportiva oberta a infants, joves i famílies. Un recorregut entre vinyes, camins i carrers del poble per gaudir de l\'esport en equip, tant si competeixes com si només vens a passar-ho bé.</p><p>En acabar les curses hi haurà lliurament de premis i esmorzar popular a la zona esportiva.</p>'],
            'courses_title' => ['label' => 'Títol dels recorreguts', 'type' => 'text', 'default' => 'Els recorreguts'],
            'courses_intro' => ['label' => 'Text dels recorreguts', 'type' => 'textarea', 'rows' => 3, 'default' => 'Tots els circuits estan senyalitzats i controlats per voluntariat. Pots consultar-los al mapa de Wikiloc i descarregar el track per al teu rellotge.'],
            'schedule_title' => ['label' => 'Títol del programa', 'type' => 'text', 'default' => 'Programa de la jornada'],
            'schedule_intro' => ['label' => 'Text del programa', 'type' => 'textarea', 'rows' => 2, 'default' => 'Els horaris són orientatius i poden variar lleugerament segons la participació.'],
            'gallery_title' => ['label' => 'Títol de la galeria', 'type' => 'text', 'default' => 'Edicions anteriors'],
            'location_title' => ['label' => 'Títol de la ubicació', 'type' => 'text', 'default' => 'Com arribar-hi'],
            'location_text' => ['label' => 'Text de la ubicació', 'type' => 'textarea', 'rows' => 3, 'default' => 'La Granada és a 5 minuts de Vilafranca del Penedès. Hi ha aparcament gratuït a la zona esportiva i estació de Rodalies (R4) a 10 minuts a peu.'],
            'map_embed' => ['label' => 'Mapa (enllaç d\'OpenStreetMap o Google Maps)', 'type' => 'url', 'default' => 'https://www.openstreetmap.org/?mlat=41.3778&mlon=1.7203#map=16/41.3778/1.7203'],
            'map_lat' => ['label' => 'Latitud', 'type' => 'text', 'default' => '41.3778'],
            'map_lng' => ['label' => 'Longitud', 'type' => 'text', 'default' => '1.7203'],
            'sponsors_title' => ['label' => 'Títol dels patrocinadors', 'type' => 'text', 'default' => 'Amb el suport de'],
            'sponsors_intro' => ['label' => 'Text dels patrocinadors', 'type' => 'textarea', 'rows' => 2, 'default' => 'Gràcies a les entitats i empreses que fan possible el cros escolar.'],
        ],
    ],

    'tickets' => [
        'title' => 'Esmorzar i tiquets',
        'icon' => 'ticket',
        'description' => 'Venda de tiquets per a l\'esmorzar popular.',
        'fields' => [
            'tickets_enabled' => ['label' => 'Venda de tiquets oberta', 'type' => 'bool', 'default' => '1'],
            'tickets_title' => ['label' => 'Títol de la pàgina', 'type' => 'text', 'default' => 'Tiquets per a l\'esmorzar'],
            'tickets_intro' => ['label' => 'Introducció', 'type' => 'html', 'rows' => 5, 'default' => '<p>En acabar les curses farem l\'esmorzar popular a la zona esportiva. Compra els tiquets per avançat i ajuda\'ns a calcular les existències: així evitem cues i malbaratament.</p>'],
            'tickets_deadline' => ['label' => 'Data límit de venda', 'type' => 'date', 'default' => '2026-10-02', 'help' => 'Després d\'aquesta data la botiga es tanca automàticament.'],
            'tickets_max_per_order' => ['label' => 'Màxim d\'unitats per comanda', 'type' => 'number', 'default' => '20'],
            'tickets_info' => ['label' => 'Informació pràctica', 'type' => 'html', 'rows' => 4, 'default' => '<ul><li>Els tiquets es poden recollir el mateix dia a la carpa de l\'AFA.</li><li>Cal presentar el codi QR des del mòbil o imprès.</li><li>Hi haurà opcions vegetarianes i sense gluten.</li></ul>'],
            'tickets_terms' => ['label' => 'Condicions de compra', 'type' => 'html', 'rows' => 4, 'default' => '<p>La compra de tiquets és una col·laboració amb l\'AFA de l\'Escola La Granada. En cas de suspensió de l\'esdeveniment es retornarà l\'import íntegre.</p>'],
            'tickets_closed_text' => ['label' => 'Text quan la venda està tancada', 'type' => 'html', 'rows' => 3, 'default' => '<p>La venda anticipada de tiquets ja està tancada. El mateix dia de la cursa en podreu comprar a la carpa de l\'AFA fins a exhaurir existències.</p>'],
            'tickets_success_text' => ['label' => 'Text de confirmació de compra', 'type' => 'html', 'rows' => 3, 'default' => '<p>Gràcies per col·laborar amb l\'AFA! Rebràs els tiquets per correu electrònic. També els pots consultar sempre que vulguis des d\'aquesta pàgina.</p>'],
            'tickets_email_intro' => ['label' => 'Text del correu de confirmació', 'type' => 'textarea', 'rows' => 3, 'default' => 'Gràcies per comprar els tiquets de l\'esmorzar del Cros Escolar La Granada. Presenta aquest correu o el codi QR de cada tiquet a la carpa de l\'AFA.'],
        ],
    ],

    'categories' => [
        'title' => 'Categories i premis',
        'icon' => 'trophy',
        'description' => 'Textos de la pàgina de categories, horaris i premis.',
        'fields' => [
            'categories_title' => ['label' => 'Títol de la pàgina', 'type' => 'text', 'default' => 'Categories i premis'],
            'categories_intro' => ['label' => 'Introducció', 'type' => 'html', 'rows' => 4, 'default' => '<p>Hi ha una cursa per a cada edat, des de P3 fins a les curses d\'adults i famílies. Consulta la teva categoria, l\'hora de sortida i la distància del recorregut.</p>'],
            'categories_notes' => ['label' => 'Notes finals', 'type' => 'html', 'rows' => 4, 'default' => '<p>Totes les persones participants rebran un obsequi de record. Cal ser al lloc de sortida 10 minuts abans de l\'hora indicada.</p>'],
            'prizes_title' => ['label' => 'Títol dels premis', 'type' => 'text', 'default' => 'Premis'],
            'prizes_intro' => ['label' => 'Introducció dels premis', 'type' => 'textarea', 'rows' => 3, 'default' => 'El lliurament de premis es farà a l\'escenari de la zona esportiva en acabar l\'última cursa.'],
        ],
    ],

    'registrations' => [
        'title' => 'Inscripcions',
        'icon' => 'users',
        'description' => 'Formulari d\'inscripció a les curses.',
        'fields' => [
            'registrations_enabled' => ['label' => 'Inscripcions obertes', 'type' => 'bool', 'default' => '1'],
            'registrations_title' => ['label' => 'Títol', 'type' => 'text', 'default' => 'Inscripció a la cursa'],
            'registrations_intro' => ['label' => 'Introducció', 'type' => 'html', 'rows' => 4, 'default' => '<p>La inscripció és gratuïta per a l\'alumnat de l\'escola i per a totes les persones que vulguin participar. Empleneu un formulari per cada participant.</p>'],
            'registrations_close_at' => ['label' => 'Data de tancament', 'type' => 'date', 'default' => '2026-10-02'],
            'registrations_closed_text' => ['label' => 'Text quan estan tancades', 'type' => 'html', 'rows' => 3, 'default' => '<p>El termini d\'inscripció en línia s\'ha tancat. El mateix dia de la cursa hi haurà inscripcions presencials fins a 30 minuts abans de cada sortida.</p>'],
            'registrations_success_text' => ['label' => 'Text de confirmació', 'type' => 'html', 'rows' => 3, 'default' => '<p>Inscripció rebuda! Us hem enviat un correu de confirmació amb les dades. Ens veiem el dia de la cursa.</p>'],
            'registrations_notify' => ['label' => 'Avisar per correu de cada inscripció', 'type' => 'bool', 'default' => '1'],
            'registrations_consent' => ['label' => 'Text del consentiment de dades', 'type' => 'textarea', 'rows' => 3, 'default' => 'Accepto que les dades facilitades s\'utilitzin únicament per gestionar la participació al Cros Escolar La Granada.'],
            'registrations_image_consent' => ['label' => 'Text del consentiment d\'imatge', 'type' => 'textarea', 'rows' => 3, 'default' => 'Autoritzo la publicació d\'imatges de l\'esdeveniment als canals de l\'AFA i de l\'escola.'],
        ],
    ],

    'payments' => [
        'title' => 'Pagaments (Stripe)',
        'icon' => 'card',
        'description' => 'Credencials de Stripe per cobrar els tiquets. Les claus es desen xifrades.',
        'admin_only' => true,
        'fields' => [
            'stripe_mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'test', 'options' => ['test' => 'Proves (test)', 'live' => 'Producció (live)']],
            'stripe_pk_test' => ['label' => 'Clau pública de proves', 'type' => 'text', 'default' => '', 'placeholder' => 'pk_test_...'],
            'stripe_sk_test' => ['label' => 'Clau secreta de proves', 'type' => 'password', 'secret' => true, 'default' => '', 'placeholder' => 'sk_test_...'],
            'stripe_webhook_test' => ['label' => 'Secret del webhook (proves)', 'type' => 'password', 'secret' => true, 'default' => '', 'placeholder' => 'whsec_...'],
            'stripe_pk_live' => ['label' => 'Clau pública de producció', 'type' => 'text', 'default' => '', 'placeholder' => 'pk_live_...'],
            'stripe_sk_live' => ['label' => 'Clau secreta de producció', 'type' => 'password', 'secret' => true, 'default' => '', 'placeholder' => 'sk_live_...'],
            'stripe_webhook_live' => ['label' => 'Secret del webhook (producció)', 'type' => 'password', 'secret' => true, 'default' => '', 'placeholder' => 'whsec_...'],
            'payments_currency' => ['label' => 'Moneda', 'type' => 'select', 'default' => 'EUR', 'options' => ['EUR' => 'Euro (€)']],
            'payments_descriptor' => ['label' => 'Concepte a l\'extracte bancari', 'type' => 'text', 'default' => 'CROS LA GRANADA', 'help' => 'Màxim 22 caràcters.'],
        ],
    ],

    'email' => [
        'title' => 'Correu electrònic',
        'icon' => 'mail',
        'description' => 'Enviament dels correus de confirmació i avisos.',
        'admin_only' => true,
        'fields' => [
            'mail_transport' => ['label' => 'Mètode d\'enviament', 'type' => 'select', 'default' => 'mail', 'options' => ['mail' => 'Funció mail() del servidor', 'smtp' => 'Servidor SMTP']],
            'mail_from_name' => ['label' => 'Nom del remitent', 'type' => 'text', 'default' => 'Cros Escolar La Granada'],
            'mail_from_email' => ['label' => 'Adreça del remitent', 'type' => 'email', 'default' => ''],
            'mail_reply_to' => ['label' => 'Respondre a', 'type' => 'email', 'default' => ''],
            'mail_admin_notify' => ['label' => 'Avisos a l\'organització', 'type' => 'email', 'default' => '', 'help' => 'Adreça que rep una còpia de cada comanda i inscripció.'],
            'smtp_host' => ['label' => 'Servidor SMTP', 'type' => 'text', 'default' => ''],
            'smtp_port' => ['label' => 'Port SMTP', 'type' => 'number', 'default' => '587'],
            'smtp_secure' => ['label' => 'Xifratge', 'type' => 'select', 'default' => 'tls', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Cap']],
            'smtp_user' => ['label' => 'Usuari SMTP', 'type' => 'text', 'default' => ''],
            'smtp_pass' => ['label' => 'Contrasenya SMTP', 'type' => 'password', 'secret' => true, 'default' => ''],
        ],
    ],

    'appearance' => [
        'title' => 'Aparença',
        'icon' => 'palette',
        'description' => 'Colors, logotip i elements visuals.',
        'fields' => [
            'logo' => ['label' => 'Logotip', 'type' => 'image', 'default' => ''],
            'favicon' => ['label' => 'Icona del navegador', 'type' => 'image', 'default' => ''],
            'color_primary' => ['label' => 'Color principal', 'type' => 'color', 'default' => '#2f6b3c'],
            'color_secondary' => ['label' => 'Color secundari', 'type' => 'color', 'default' => '#7ba05b'],
            'color_accent' => ['label' => 'Color d\'accent', 'type' => 'color', 'default' => '#c8552b'],
            'hero_overlay' => ['label' => 'Opacitat del vel del banner (%)', 'type' => 'number', 'default' => '55'],
            'vine_pattern' => ['label' => 'Mostrar il·lustració de vinyes', 'type' => 'bool', 'default' => '1'],
            'footer_text' => ['label' => 'Text del peu', 'type' => 'textarea', 'rows' => 3, 'default' => 'Cros Escolar La Granada — organitzat per l\'AFA de l\'Escola La Granada amb la col·laboració de l\'Ajuntament de La Granada.'],
        ],
    ],

    'seo' => [
        'title' => 'SEO i legal',
        'icon' => 'globe',
        'description' => 'Metadades per a cercadors i textos legals.',
        'fields' => [
            'meta_description' => ['label' => 'Descripció per a cercadors', 'type' => 'textarea', 'rows' => 2, 'default' => 'Cros Escolar La Granada: cursa popular entre vinyes de l\'Alt Penedès per a totes les edats. Inscripcions, recorreguts i tiquets de l\'esmorzar.'],
            'og_image' => ['label' => 'Imatge per a xarxes socials', 'type' => 'image', 'default' => ''],
            'legal_entity' => ['label' => 'Entitat responsable', 'type' => 'text', 'default' => 'AFA Escola La Granada'],
            'legal_notice' => ['label' => 'Avís legal', 'type' => 'html', 'rows' => 8, 'default' => '<p>Aquest lloc web és titularitat de l\'AFA de l\'Escola La Granada, entitat sense ànim de lucre que organitza el Cros Escolar de La Granada.</p>'],
            'privacy_text' => ['label' => 'Política de privacitat', 'type' => 'html', 'rows' => 10, 'default' => '<p>Les dades personals recollides mitjançant els formularis d\'inscripció i de compra de tiquets s\'utilitzen exclusivament per organitzar el Cros Escolar La Granada i no se cedeixen a tercers, tret de les obligacions legals i dels serveis necessaris per al pagament (Stripe).</p><p>Podeu exercir els drets d\'accés, rectificació i supressió escrivint a l\'adreça de contacte.</p>'],
        ],
    ],

    'updates' => [
        'title' => 'Actualitzacions',
        'icon' => 'refresh',
        'description' => 'Origen de les actualitzacions automàtiques (OTA).',
        'admin_only' => true,
        'fields' => [
            'update_manifest_url' => ['label' => 'URL del manifest', 'type' => 'url', 'default' => 'https://api.github.com/repos/soportic-orb/crosescolar/releases/latest', 'help' => 'Admet el format propi (manifest.json) i l\'API de versions de GitHub.'],
            'update_token' => ['label' => 'Token d\'accés', 'type' => 'password', 'secret' => true, 'default' => '', 'help' => 'Només cal per a dipòsits privats.'],
            'update_auto_check' => ['label' => 'Comprovar automàticament', 'type' => 'bool', 'default' => '1'],
            'update_backup' => ['label' => 'Fer còpia de seguretat abans d\'actualitzar', 'type' => 'bool', 'default' => '1'],
        ],
    ],
];
