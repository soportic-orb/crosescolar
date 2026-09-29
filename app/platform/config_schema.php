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

    'home' => [
        'title' => 'La portada',
        'icon' => 'home',
        'description' => 'El que es veu a crosescolar.cat: el banner de dalt, com funciona i les preguntes freqüents.',
        'fields' => [
            'platform_hero_image' => ['label' => 'Imatge de fons del banner', 'type' => 'image', 'folder' => 'plataforma', 'max_width' => 2000, 'default' => '',
                'help' => 'La que es veu darrere del títol de la portada. Apaïsada i ampla (1600×900 o més). Si no n\'hi ha cap, el banner queda amb el color fosc de sempre.'],
            'platform_hero_overlay' => ['label' => 'Opacitat del vel del banner (%)', 'type' => 'number', 'default' => '72', 'min' => 0, 'max' => 95,
                'help' => 'El vel fosc que va sobre la imatge perquè el text es llegeixi. Com més alt, més fosca queda la imatge.'],
            'platform_hero_eyebrow' => ['label' => 'Frase petita de sobre el títol', 'type' => 'text', 'default' => 'Per a escoles, AFA i clubs'],
            'platform_hero_lead' => ['label' => 'Frase de sota el títol', 'type' => 'textarea', 'rows' => 2,
                'default' => 'Inscripcions en línia, dorsals en PDF, resultats per categories i correus a les famílies. Tot amb la vostra imatge i a la vostra adreça.'],
            'platform_steps_show' => ['label' => 'Mostrar «Com funciona»', 'type' => 'bool', 'default' => '1'],
            'platform_steps_title' => ['label' => 'Títol de «Com funciona»', 'type' => 'text', 'default' => 'Com funciona', 'show_if' => 'platform_steps_show'],
            'platform_step1_title' => ['label' => 'Pas 1: títol', 'type' => 'text', 'default' => 'Empleneu la sol·licitud', 'show_if' => 'platform_steps_show'],
            'platform_step1_text' => ['label' => 'Pas 1: text', 'type' => 'textarea', 'rows' => 2, 'default' => 'Dades de l\'entitat i de la persona que ho gestionarà.', 'show_if' => 'platform_steps_show'],
            'platform_step2_title' => ['label' => 'Pas 2: títol', 'type' => 'text', 'default' => 'La revisem', 'show_if' => 'platform_steps_show'],
            'platform_step2_text' => ['label' => 'Pas 2: text', 'type' => 'textarea', 'rows' => 2, 'default' => 'Us escrivim si ens falta alguna cosa. Us responem en 48 hores feineres.', 'show_if' => 'platform_steps_show'],
            'platform_step3_title' => ['label' => 'Pas 3: títol', 'type' => 'text', 'default' => 'Rebeu les claus', 'show_if' => 'platform_steps_show'],
            'platform_step3_text' => ['label' => 'Pas 3: text', 'type' => 'textarea', 'rows' => 2, 'default' => 'L\'adreça del vostre web i l\'accés per començar a preparar-lo.', 'show_if' => 'platform_steps_show'],
            'platform_faqs_show' => ['label' => 'Mostrar les preguntes freqüents', 'type' => 'bool', 'default' => '1'],
            'platform_faqs_title' => ['label' => 'Títol de les preguntes', 'type' => 'text', 'default' => 'Preguntes freqüents', 'show_if' => 'platform_faqs_show'],
            'platform_faqs' => ['label' => 'Preguntes i respostes', 'type' => 'faqs', 'show_if' => 'platform_faqs_show',
                'default' => '[{"q":"Qu\u00e8 costa?","a":"Demanar el web \u00e9s gratu\u00eft i no hi ha cap pagament en l\u00ednia. Si el vostre cros necessita alguna cosa a mida, en parlem abans."},{"q":"Quant es triga a tenir-lo?","a":"Un cop rebuda la sol\u00b7licitud us responem en 48 hores feineres. El web queda a punt el mateix dia que el donem d\u0027alta; la resta \u00e9s omplir-lo amb les vostres dades."},{"q":"Podem fer servir el nostre domini?","a":"De moment cada cros viu en un subdomini nostre (elvostrecros.crosescolar.cat). Si teniu domini propi, escriviu-nos i ho mirem."},{"q":"Qui \u00e9s l\u0027amo de les dades?","a":"Vosaltres. Des del vostre panell us podeu descarregar en qualsevol moment totes les inscripcions, els resultats i la configuraci\u00f3 del web en un sol fitxer."}]',
                'help' => 'Surten a la portada en una llista desplegable, i també s\'envien als cercadors en el format que entenen, de manera que Google les pot ensenyar obertes al resultat.'],
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
            'mail_batch_size' => ['label' => 'Correus per tanda', 'type' => 'number', 'default' => '20',
                'help' => 'Els enviaments es fan a trossos per no saturar el servidor. Amb SMTP propi s\'hi pot pujar; amb la funció del sistema, val més deixar-ho baix.'],
        ],
    ],

    'seo' => [
        'title' => 'SEO i cercadors',
        'icon' => 'globe',
        'description' => 'Com es veu la portada de la plataforma a Google.',
        'fields' => [
            'platform_meta_description' => ['label' => 'Descripció per a cercadors', 'type' => 'textarea', 'rows' => 2, 'default' => '',
                'help' => 'Les dues línies que Google ensenya sota el títol. Si es deixa buit, es fa servir la frase de la portada.'],
            'google_verification' => ['label' => 'Verificació de Google Search Console', 'type' => 'text', 'default' => '',
                'help' => 'A Search Console, trieu «Etiqueta HTML» i enganxeu aquí només el codi de dins de content="…".'],
            'platform_noindex' => ['label' => 'Demanar als cercadors que no indexin la portada', 'type' => 'bool', 'default' => '0',
                'help' => 'Els webs dels clients no en depenen: cadascun ho decideix al seu panell.'],
        ],
    ],

    'legal' => [
        'title' => 'Legal i galetes',
        'icon' => 'file',
        'description' => 'Condicions del servei, privadesa, galetes i l\'avís que surt en entrar.',
        'fields' => [
            'platform_legal_entity' => ['label' => 'Entitat o empresa responsable', 'type' => 'text', 'default' => '',
                'help' => 'El nom sencer de qui presta el servei i respon de les dades. Surt als tres textos.'],
            'platform_legal_nif' => ['label' => 'NIF', 'type' => 'text', 'default' => ''],
            'platform_legal_address' => ['label' => 'Domicili', 'type' => 'text', 'default' => ''],
            'platform_terms' => ['label' => 'Condicions del servei', 'type' => 'html', 'rows' => 18,
                'default' => '<h2>Qui som</h2><p>Aquest web i el servei que s\'hi ofereix són titularitat de <strong>{{entitat}}</strong>, amb domicili a {{adreca}} i NIF {{nif}}. Podeu contactar-nos a {{correu}}.</p><p>Anomenem «la plataforma» el conjunt del servei, «el web de cada cros» la instància que es dona a cada entitat, i «l\'entitat» l\'escola, AFA, club o ajuntament que organitza un cros i contracta el servei.</p><h2>Què oferim</h2><p>La plataforma dona a cada entitat un web propi per a la seva cursa, amb inscripcions en línia, dorsals en PDF, resultats per categories, enviaments de correu a les famílies i un panell de gestió. Cada web viu en un subdomini nostre i el gestiona l\'entitat.</p><p>El servei es presta tal com està descrit al web en el moment de la sol·licitud. Podem afegir-hi millores i canviar-ne detalls de funcionament; si un canvi treu alguna cosa que fèieu servir, us ho direm abans.</p><h2>Com es dona d\'alta</h2><p>L\'alta es demana des del formulari de la portada. Rebuda la sol·licitud, la revisem i responem en 48 hores feineres. Podem no acceptar una sol·licitud si les dades no són certes, si l\'activitat no té res a veure amb curses escolars o populars, o si no podem prestar el servei en condicions.</p><p>En donar d\'alta un web creem un compte per a la persona que l\'ha de gestionar. Aquesta persona és responsable de guardar-se les credencials i de qui hi dona accés dins de la seva entitat.</p><h2>Què heu de fer vosaltres</h2><ul><li>Fer servir el web per a allò que és: organitzar i difondre el vostre cros.</li><li>Publicar-hi continguts que siguin vostres o que tingueu dret a publicar, i que no vulnerin drets de ningú.</li><li>Complir la normativa de protecció de dades amb les persones que s\'inscriuen a la vostra cursa: informar-les, demanar el consentiment quan calgui i atendre\'n els drets.</li><li>No intentar accedir a webs d\'altres entitats ni a parts del sistema que no us pertoquen.</li></ul><h2>Les dades són vostres</h2><p>Les inscripcions, els resultats i els continguts que pugeu al vostre web són de la vostra entitat. Nosaltres els allotgem i els tractem només per prestar-vos el servei. Des del vostre panell us els podeu descarregar en qualsevol moment, sencers i en formats oberts, sense demanar-nos permís.</p><h2>Disponibilitat i còpies</h2><p>Procurem que el servei estigui sempre disponible, però no podem garantir que no hi hagi interrupcions: hi ha manteniments, actualitzacions i avaries. Fem còpies de seguretat diàries de cada web i les guardem uns quants dies.</p><p>Quan hàgim de fer una parada prevista, mirarem de fer-la fora de les dates de curses i d\'avisar-vos-en.</p><h2>Preu</h2><p>Demanar el web és gratuït i no hi ha cap pagament en línia en aquest web. Si el servei per a la vostra entitat té cost, us el direm per escrit abans de donar-vos d\'alta i no us cobrarem res que no hàgiu acceptat.</p><h2>Fins quan</h2><p>El servei dura mentre el vulgueu. Si voleu plegar, aviseu-nos i us donem les vostres dades abans de tancar el web. Nosaltres podem tancar un web si es fa servir per a coses il·lícites, si perjudica la resta d\'entitats de la plataforma o si l\'entitat desapareix; en aquest cas també us donarem les dades.</p><p>Quan un web es dona de baixa, les seves dades es guarden 90 dies per si hi ha marxa enrere, i després s\'esborren.</p><h2>Responsabilitat</h2><p>Responem del que depèn de nosaltres: que el servei funcioni raonablement i que les vostres dades estiguin ben guardades. No responem del contingut que publiqui cada entitat al seu web, ni de les decisions que prengui sobre la seva cursa, ni dels danys que vinguin d\'un ús incorrecte del servei.</p><h2>Canvis en aquestes condicions</h2><p>Si les canviem, actualitzarem aquest text i, si el canvi és rellevant, us ho farem saber per correu. La data de l\'última versió surt al final.</p><h2>Llei i jutjats</h2><p>Aquestes condicions es regeixen per la legislació espanyola i catalana. Per a qualsevol qüestió, les parts se sotmeten als jutjats i tribunals que corresponguin per llei.</p>',
                'help' => 'Es publica a /condicions. Podeu escriure-hi {{entitat}}, {{nif}}, {{adreca}}, {{correu}} i {{web}}: en publicar-se s\'hi posen les dades d\'aquí sobre.'],
            'platform_privacy' => ['label' => 'Política de privadesa', 'type' => 'html', 'rows' => 18,
                'default' => '<h2>Qui és el responsable</h2><p>El responsable del tractament és <strong>{{entitat}}</strong>, NIF {{nif}}, amb domicili a {{adreca}}. Per a qualsevol qüestió sobre les vostres dades, escriviu-nos a {{correu}}.</p><h2>Dos papers diferents</h2><p>Convé distingir-ho des del començament:</p><ul><li><strong>De les dades de qui ens demana un web o ens escriu</strong> (el vostre nom, el de l\'entitat i el vostre correu) en som <strong>responsables</strong> nosaltres.</li><li><strong>De les dades de les persones que s\'inscriuen a un cros</strong> n\'és responsable <strong>l\'entitat organitzadora</strong>. Nosaltres només les allotgem i les tractem seguint les seves instruccions: som <strong>encarregats del tractament</strong>. Si us heu inscrit a una cursa i voleu exercir els vostres drets, adreceu-vos a l\'entitat que l\'organitza; el seu web en té les dades de contacte.</li></ul><h2>Quines dades tractem i per què</h2><p><strong>Sol·licituds d\'alta.</strong> Nom de l\'entitat, NIF, població, web actual, nom, càrrec, correu i telèfon de la persona de contacte, i el que ens expliqueu al formulari. Les fem servir per estudiar la sol·licitud, respondre-us i, si tirem endavant, donar-vos d\'alta el web.</p><p><strong>Comptes de gestió.</strong> Nom i correu de les persones que gestionen cada web, i un registre de quan hi entren i què hi fan, per seguretat i per poder-vos ajudar si alguna cosa no va bé.</p><p><strong>Correu.</strong> Els avisos que us enviem (claus d\'accés, incidències, canvis del servei) i un registre del que s\'ha enviat.</p><p><strong>Dades tècniques.</strong> El servidor desa registres d\'accés amb l\'adreça IP i la data, per seguretat i per detectar errades.</p><h2>Amb quina base legal</h2><ul><li><strong>L\'execució del servei</strong> que ens heu demanat, i els passos previs a contractar-lo.</li><li><strong>El vostre consentiment</strong>, en enviar-nos el formulari.</li><li><strong>L\'interès legítim</strong> a garantir la seguretat del servei i a deixar constància del que s\'hi fa.</li></ul><h2>Quant de temps les guardem</h2><p>Les sol·licituds que no tiren endavant, un any. Les dades dels comptes i dels webs actius, mentre duri el servei. En donar de baixa un web, 90 dies més, i després s\'esborren. Els registres tècnics, com a màxim un any.</p><h2>A qui les comuniquem</h2><p>No venem ni cedim dades a ningú amb finalitats comercials. Hi tenen accés únicament:</p><ul><li>Qui presta el servei per part nostra, per fer-hi el manteniment.</li><li>L\'empresa que allotja el servidor i el servei de correu que fem servir per enviar els avisos, com a encarregats nostres i seguint les nostres instruccions.</li><li>Les administracions públiques, quan una llei ho exigeixi.</li></ul><p>Els servidors són a la Unió Europea.</p><h2>Els vostres drets</h2><p>Podeu demanar-nos accedir a les vostres dades, rectificar-les, suprimir-les, limitar-ne o oposar-vos al tractament, i demanar-ne la portabilitat. També podeu retirar el consentiment quan vulgueu, sense que això afecti el que s\'hagi fet abans.</p><p>Per exercir-los, escriviu-nos a {{correu}} dient quin dret voleu exercir. Us respondrem tan aviat com puguem. Si considereu que no us hem atès com toca, podeu reclamar davant l\'Autoritat Catalana de Protecció de Dades (<a href="https://apdcat.gencat.cat" target="_blank" rel="noopener">apdcat.gencat.cat</a>) o l\'Agència Espanyola de Protecció de Dades.</p><h2>Seguretat</h2><p>Apliquem les mesures tècniques i organitzatives raonables: connexió xifrada, contrasenyes guardades amb xifratge irreversible, accés al panell amb credencials i registre d\'activitat, còpies de seguretat diàries i separació de les dades de cada entitat en bases de dades diferents.</p><h2>Galetes</h2><p>Aquest web fa servir una <strong>única galeta pròpia i tècnica</strong>, necessària per mantenir la sessió i protegir els formularis. No hi ha galetes de publicitat ni de seguiment, ni fem analítica de visites. Ho teniu explicat a la <a href="/galetes">política de galetes</a>.</p><h2>Canvis</h2><p>Si canviem la manera de tractar les dades, actualitzarem aquest text i, si el canvi és rellevant, us ho direm.</p>',
                'help' => 'Es publica a /privadesa. Admet els mateixos marcadors.'],
            'platform_cookies' => ['label' => 'Política de galetes', 'type' => 'html', 'rows' => 12,
                'default' => '<h2>Què és una galeta</h2><p>Una galeta és un fitxer petit que un web desa al vostre navegador. Serveixen per recordar coses entre pàgina i pàgina: que heu iniciat la sessió, per exemple. Algunes són imprescindibles perquè el web funcioni i d\'altres serveixen per mesurar visites o per fer publicitat.</p><h2>Quines fem servir</h2><p>En aquest web, <strong>només una</strong>:</p><table><tr><th>Nom</th><th>Qui la posa</th><th>Per a què</th><th>Quant dura</th></tr><tr><td><code>cros_session</code></td><td>Nosaltres</td><td>Mantenir la sessió i protegir els formularis de suplantacions (CSRF)</td><td>Fins que tanqueu el navegador</td></tr></table><p>És una galeta <strong>tècnica i necessària</strong>: sense ella els formularis no es podrien enviar amb seguretat. Per aquest motiu no cal el vostre consentiment, només informar-vos-en, que és el que fa aquesta pàgina i l\'avís que surt en entrar-hi.</p><p>A més, el navegador pot desar al vostre dispositiu una marca (<code>cros_avis_galetes</code>) que recorda que ja heu llegit l\'avís, per no tornar-vos-el a ensenyar. No és una galeta ni surt del vostre navegador.</p><h2>El que no fem servir</h2><p>No hi ha galetes de publicitat, ni de xarxes socials, ni d\'analítica. No us seguim entre webs ni fem perfils. Les tipografies i la resta de recursos se serveixen des del nostre propi servidor, de manera que navegar per aquí no envia dades a tercers.</p><h2>Els webs dels cros</h2><p>Cada cros té el seu web, amb la mateixa galeta tècnica. Si una entitat hi activa alguna eina d\'analítica, ho ha d\'explicar al seu web i demanar el consentiment que calgui: aquella decisió és seva, no nostra.</p><h2>Com esborrar-les</h2><p>Podeu esborrar les galetes i la resta de dades desades des de la configuració del vostre navegador, i també bloquejar-les. Si bloquegeu la galeta de sessió, els formularis d\'aquest web deixaran de funcionar.</p>',
                'help' => 'Es publica a /galetes. Si algun dia hi poseu analítica, aquest text i l\'avís de sota s\'han de refer: caldrà demanar consentiment de debò.'],
            'platform_cookie_banner' => ['label' => 'Mostrar l\'avís de galetes', 'type' => 'bool', 'default' => '1'],
            'platform_cookie_text' => ['label' => 'Text de l\'avís', 'type' => 'textarea', 'rows' => 3, 'show_if' => 'platform_cookie_banner',
                'default' => 'Aquest web fa servir una sola galeta tècnica, necessària per mantenir la sessió i protegir els formularis. No fem analítica ni publicitat, i no us seguim enlloc.'],
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

    'support' => [
        'title' => 'Opcions del suport',
        'icon' => 'mail',
        'description' => 'L\'apartat de suport que veuen els clients al seu panell.',
        'fields' => [
            'support_enabled' => ['label' => 'Els clients poden obrir tiquets', 'type' => 'bool', 'default' => '1',
                'help' => 'Si es desactiva, l\'apartat desapareix del panell de cada cros i només es poden mirar els tiquets que ja hi ha.'],
            'support_intro' => ['label' => 'Què es diu a dalt de l\'apartat', 'type' => 'textarea', 'rows' => 3,
                'default' => 'Expliqueu-nos què us passa i us contestarem tan aviat com puguem. Si és sobre una inscripció concreta, digueu-ne el nom i el dorsal.'],
            'support_notify' => ['label' => 'On arriben els avisos de tiquet nou', 'type' => 'email', 'default' => '',
                'help' => 'Si es deixa buit, van a l\'adreça d\'avisos del correu. Cada departament pot tenir-ne una de pròpia.'],
            'support_hours' => ['label' => 'Horari que s\'ensenya al client', 'type' => 'text', 'default' => 'De dilluns a divendres, de 9 a 18 h.'],
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
