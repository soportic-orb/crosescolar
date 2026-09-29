<?php
/**
 * El text de cada pàgina pública, segons el domini per on s'hi entra.
 *
 * El nucli de la plataforma és EsportWeb i parla de curses i activitats
 * esportives en general: els seus textos són els de l'esquema
 * (app/platform/config_schema.php). Aquí hi ha només el que ha de dir una
 * altra cosa en un domini concret, que és el que es copia a la configuració
 * del domini el primer cop que s'engega.
 *
 * Un cop copiats, qui mana és el Panell de Superadministració: aquest fitxer
 * no torna a tocar res. Serveix per néixer, no per manar.
 *
 * Els dominis que no surten aquí neixen amb els textos genèrics d'EsportWeb.
 */
declare(strict_types=1);

$cros = [
        'site_name' => 'Cros Escolar',
        'platform_tagline' => 'El web del vostre cros escolar, a punt en una hora.',
        'platform_activity' => 'cros escolars',
        'platform_activity_one' => 'el vostre cros',
        'platform_cta_label' => 'Crea el web del teu cros',
        'platform_nav_directory' => 'Cros escolars',
        'platform_footer_text' => 'La plataforma per als cros escolars de les escoles, AFA i clubs: inscripcions, dorsals i resultats, cadascú a la seva adreça.',
        'platform_slug_example' => 'elvostrecros',
        'platform_hero_eyebrow' => 'Per a escoles, AFA i clubs',
        'platform_hero_lead' => 'Inscripcions en línia, dorsals en PDF, resultats per categories i correus a les famílies. Sense límits d\'inscrits ni de transaccions, i tot amb la vostra imatge i la vostra adreça.',
        'platform_directory_title' => 'Els cros escolars que ja hi corren',
        'platform_directory_lead' => 'Cliqueu-ne un i aneu al seu web, amb les inscripcions, els recorreguts i els resultats.',
        'platform_step1_title' => 'Creeu el compte',
        'platform_step1_text' => 'Trieu l\'adreça, deixeu-nos un correu i entreu al panell. És gratuït i no cal que ningú us doni permís.',
        'platform_step2_title' => 'Ompliu el web',
        'platform_step2_text' => 'Dades de la cursa, recorreguts, categories, premis i reglament. En una hora ho teniu a punt.',
        'platform_step3_title' => 'Publiqueu-lo',
        'platform_step3_text' => 'Un sol pagament i el web queda obert al públic, amb les inscripcions en marxa.',
        'features_title' => 'Tot el que necessita un cros escolar',
        'features_intro' => 'Un web propi per al vostre cros, amb les inscripcions, els dorsals, els resultats i els cobraments al mateix lloc. El munteu en una hora i nosaltres en portem el manteniment.',
        'features_closing' => 'Si hi ha res que no hi veieu, pregunteu-nos-ho: la plataforma creix amb el que ens demanen els cros que ja hi són.',
        'platform_faqs' => '[{"q":"Qu\\u00e8 costa?","a":"Crear el compte i preparar el web no costa res. Nom\\u00e9s es paga un cop, el dia que el voleu fer p\\u00fablic. \\u00c9s un pagament \\u00fanic per cros i no hi ha quotes ni renovacions."},{"q":"Hi ha l\\u00edmits?","a":"No. Ni d\'inscrits, ni de transaccions, ni de correus. Pagueu un cop pel web i el feu servir tant com calgui."},{"q":"Quant es triga a tenir-lo?","a":"Una hora ben feta. Creeu el compte, ompliu les dades del cros i publiqueu. No hi ha cap tr\\u00e0mit ni cap espera."},{"q":"Podem cobrar les inscripcions?","a":"S\\u00ed. Es connecta Stripe, PayPal o el TPV de Redsys amb les vostres claus, i els diners van directament al compte de l\'entitat. Els rebuts i les factures s\'emeten a nom vostre."},{"q":"Podem fer servir el nostre domini?","a":"De moment cada cros viu en un subdomini nostre (elvostrecros.crosescolar.cat). Si teniu domini propi, escriviu-nos i ho mirem."},{"q":"Qui \\u00e9s l\'amo de les dades?","a":"Vosaltres. Des del vostre panell us podeu descarregar en qualsevol moment totes les inscripcions, els resultats i la configuraci\\u00f3 del web en un sol fitxer."}]',
        'features_list' => '[{"icon":"users","title":"Inscripcions en l\\u00ednia","text":"Formulari propi amb categories per any de naixement, consentiments, av\\u00eds per correu i \\u00abLes meves inscripcions\\u00bb perqu\\u00e8 cada fam\\u00edlia es corregeixi les dades sense trucar a ning\\u00fa."},{"icon":"flag","title":"Dorsals a punt d\'imprimir","text":"Numeraci\\u00f3 autom\\u00e0tica per categoria i dorsals generats sobre la vostra maqueta en PDF, a punt per a la impressora i per enviar-los per correu."},{"icon":"trophy","title":"Resultats el mateix dia","text":"Entrada d\'arribades per dorsal, classificaci\\u00f3 per categories, publicaci\\u00f3 al web i exportaci\\u00f3 en PDF i full de c\\u00e0lcul."},{"icon":"euro","title":"Cobraments amb targeta","text":"Inscripcions i tiquets cobrats amb Stripe, PayPal o el TPV de Redsys, amb el rebut o la factura a nom de la vostra entitat."},{"icon":"mail","title":"Correus a les fam\\u00edlies","text":"Recordatoris i avisos amb editor visual, per tandes i sense repetir-ne cap si l\'enviament s\'atura a mitges."},{"icon":"globe","title":"El vostre web, ben indexat","text":"Adre\\u00e7a pr\\u00f2pia amb certificat, portada, recorreguts, categories, premis i reglament, amb les metadades i el mapa del web que esperen els cercadors."},{"icon":"qr","title":"Punt de rec\\u00e0rrega","text":"Venda de tiquets d\'esmorzar amb codi QR i validaci\\u00f3 el mateix dia des del m\\u00f2bil."},{"icon":"refresh","title":"Manteniment incl\\u00f2s","text":"Actualitzacions, c\\u00f2pies de seguretat i vigil\\u00e0ncia del web, i un apartat de suport al vostre panell per escriure\'ns quan calgui."}]',
];

return [
    // La clau pot ser el domini sencer o només el nom, sense extensió:
    // així crosescolar.cat, .com i el que es faci servir a les proves
    // diuen tots el mateix sense haver-los d'enumerar.
    'crosescolar' => $cros,
];
