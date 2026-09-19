# Registre de canvis

El format segueix [Keep a Changelog](https://keepachangelog.com/ca/1.1.0/)
i el versionatge semàntic.

## [1.8.0] — 2026-09-19

### Canviat
- Les **medalles dels guanyadors** es configuren ara **a cada categoria** i no en
  general: a Categories → editar hi ha l'interruptor de medalles i el nombre de
  **participants premiats**. Així es poden premiar tres corredors a unes categories i
  cinc a unes altres, o no posar-ne a cap. En actualitzar, totes les categories reben
  els valors que hi havia a la configuració general, de manera que el web es veu igual.
- El llistat de categories del panell mostra una columna **Premiats**.

## [1.7.0] — 2026-09-19

### Afegit
- **Medalles dels guanyadors**: a Configuració → Categories i premis es pot activar o
  desactivar que les primeres posicions de cada categoria surtin marcades amb una
  medalla a la classificació, i indicar **quants guanyadors** n'hi ha (els tres
  primers, els quatre primers…). Les tres primeres porten els colors d'or, plata i
  bronze; de la quarta en endavant, el verd de la cursa.
- Els formularis del panell admeten camps que només apareixen quan n'hi ha un altre
  d'activat, com el nombre de guanyadors.

## [1.6.1] — 2026-09-19

### Canviat
- Nova **icona del cros**: el corredor amb les línies de velocitat que es veu al
  distintiu de la capçalera, al botó d'inscripció, al panell i a la pàgina d'avís.

## [1.6.0] — 2026-09-19

### Canviat
- **Motius de vinya**: les ratlles i rodones que decoraven la franja inferior de la
  portada i les capçaleres interiors són ara **fulles de parra i carrassos de raïm**,
  dibuixats a mida. S'hi afegeix un separador de fulles entre seccions de la portada i
  dues icones noves (`leaf` i `grape`) que es poden triar als blocs de la portada.
- **Tipografies noves**: titulars amb **Cabin Sketch** i textos amb **DM Sans**. Els
  fitxers s'inclouen al web i se serveixen des del mateix servidor: no es fa cap
  petició a Google i el web funciona igual sense connexió a internet. El panell manté
  la tipografia de text, però no la dels titulars, per llegir-s'hi millor.

## [1.5.0] — 2026-09-19

### Canviat
- L'**esmorzar popular** passa a dir-se **punt de recàrrega**: és el servei de bar on
  els participants recuperen l'energia gastada durant la cursa. Canvien els textos del
  web, del panell i dels correus, i la pàgina passa a ser `/punt-de-recarrega`;
  l'adreça antiga `/esmorzar` hi redirigeix perquè els enllaços ja publicats continuïn
  funcionant. Els textos que l'organització ja hagi redactat es respecten: només es
  reescriuen els que encara tenien el valor per defecte.
- Al menú principal, el botó destacat ara diu **«Inscriu-te!»**, s'hi afegeix
  **«Les meves inscripcions»** i **«Contacte»** queda només al peu de pàgina.

## [1.4.0] — 2026-09-19

### Afegit
- **«Les meves inscripcions»**: les famílies hi entren escrivint la seva adreça
  electrònica i el **codi de sis xifres** que hi reben, sense cap contrasenya. Hi veuen
  tots els participants que han inscrit amb aquella adreça, en poden **modificar les
  dades** (nom, any de naixement —que recalcula la categoria—, gènere, escola, curs,
  talla, contacte, observacions i consentiment d'imatge) i descarregar-ne els dorsals.
  El codi val 15 minuts, només serveix un cop i a la base de dades només se'n desa el
  resum. L'apartat es pot desactivar des de Configuració → Inscripcions.

- **Orientació del dorsal**: a Configuració → Dorsals es pot dir si el dorsal és
  vertical o horitzontal. Amb «la mateixa que la maqueta» surt amb la mida del PDF
  pujat; si en marqueu una altra, la maqueta es gira per omplir la pàgina (per als
  dissenys apaïsats desats en un PDF vertical). Hi ha també un **gir de la maqueta**
  (90°, 180°, 270°) per quan surt de costat o del revés, i el panell indica quina mida
  té la maqueta i quina tindrà el dorsal.

### Canviat
- Al formulari d'inscripció, el **gènere** només ofereix femení i masculí, i el **curs**
  es tria d'una llista amb els nou cursos de l'escola (Infantil 1er–3r i Primària
  1er–6è) en comptes d'escriure'l a mà. El panell fa servir les mateixes opcions i, si
  una inscripció antiga en porta una altra, la manté per no perdre-la en desar.
- El menú ja no repeteix l'enllaç **«Inscripció»**: el botó destacat del menú hi porta.

### Corregit
- La integració contínua no tenia poppler instal·lat i les comprovacions que llegeixen
  el text dels PDF generats se saltaven en silenci.

## [1.3.0] — 2026-09-18

### Afegit
- **Dorsals dels participants**: cada inscripció rep un número enter (001, 002…) i un
  enllaç privat per descarregar el dorsal en PDF, que s'inclou al correu de confirmació
  (amb l'opció de baixar en un sol fitxer tots els dorsals d'una mateixa família).
- **Disseny dels dorsals**: es puja una maqueta en PDF i es col·loquen el número, el nom
  i la categoria indicant posició en mil·límetres, mida, color, alineació i negreta, amb
  un botó per generar un dorsal de prova.
- **Resultats de la cursa**: pantalla de registre d'arribades per número de dorsal que
  assigna la posició dins de cada categoria, amb correcció manual de l'ordre.
- **Pàgina pública de resultats** per categories (publicable amb un clic) i exportacions
  en **PDF per categoria, PDF per ordre d'arribada i CSV**.
- **Motor de PDF propi**, sense dependències externes, que també sap fer servir un PDF
  existent com a fons dels dorsals.

### Canviat
- El web públic ja no ven tiquets de l'esmorzar: la pàgina passa a ser informativa amb
  els preus. La venda en línia es pot tornar a activar des de Configuració → Esmorzar, i
  el panell manté tota la gestió de tiquets, comandes i validació.
- El botó destacat del menú és ara **«Inscripcions al cros»** i porta a la pàgina
  d'inscripció.

## [1.2.0] — 2026-09-17

### Canviat
- **Inscripcions**: quan el formulari en línia està desactivat (o s'ha passat la data
  de tancament), la pàgina mostra **només el text informatiu**, sense el formulari ni
  els blocs laterals que hi feien referència.
- L'opció del panell es diu ara «Formulari d'inscripció en línia actiu» i explica què
  passa en desactivar-la; el text informatiu té el seu propi camp ben identificat.
- La portada adapta els enllaços: el botó diu «Com inscriure-s'hi» i la fitxa de dades
  pràctiques enllaça amb la pàgina d'inscripció.

### Afegit
- Botó opcional sota el text informatiu (text i enllaç configurables) per apuntar a un
  PDF, a un formulari extern o a qualsevol altra pàgina.

## [1.1.1] — 2026-09-17

### Corregit
- **Comprovar actualitzacions donava «El servidor d'actualitzacions ha respost amb el
  codi 404»** quan el dipòsit encara no tenia cap versió publicada: l'API de GitHub
  respon 404 a `releases/latest` en aquest cas. Ara el panell consulta la llista de
  versions i ho mostra com un avís informatiu amb les instruccions per publicar-ne una.
- Missatges d'error entenedors segons el problema real: dipòsit no trobat, accés
  denegat, límit de consultes superat, manifest inexistent o resposta que no és JSON.
- La pàgina d'actualitzacions mostra l'origen configurat i un resum d'estat correcte
  («Cap versió publicada a l'origen», «Esteu al dia», «Versió X disponible»).

### Afegit
- Suport per descarregar paquets de dipòsits privats de GitHub amb token (fa servir
  l'adreça de l'API i no envia el token al servidor de fitxers).
- `tests/updates.php`: 17 comprovacions del sistema d'actualitzacions, incloses les
  respostes reals de l'API de GitHub.

## [1.1.0] — 2026-09-17

### Afegit
- **Mode «web en preparació»**: una opció amaga el web al públic i hi mostra l'avís
  «Aviat publicarem el web» (títol, text i imatge de fons configurables, amb data,
  compte enrere i dades de contacte opcionals).
  - Les persones amb sessió iniciada al panell continuen veient el web complet, amb
    una barra superior que recorda l'estat i permet publicar-lo amb un sol clic.
  - El panell, la validació de tiquets i el webhook de Stripe no queden bloquejats.
  - Mentre està actiu, el web no s'indexa (`noindex` i `robots.txt` restrictiu).
- Botó de publicació ràpida al tauler del panell.

### Corregit
- El menú de navegació ja no es parteix en dues línies en pantalles d'entre 960 i
  1240 píxels.

## [1.0.1] — 2026-09-17

### Corregit
- **L'assistent es quedava aturat en prémer «Instal·lar»**: com que els formularis
  s'enviaven a una adreça que ja contenia `?pas=2`, el pas de l'URL tenia prioritat
  sobre el del formulari i l'assistent tornava a mostrar la mateixa pantalla un cop
  i un altre. Ara mana sempre el pas enviat pel formulari i els formularis apunten
  explícitament a `install.php`.
- Els continguts d'exemple tornen a estar marcats per defecte i es respecta la
  decisió de l'usuari si els desmarca.
- L'instal·lador executa la feina en sis fases curtes i independents, de manera que
  no pot excedir el temps màxim d'execució del servidor ni quedar-se aturat.
- L'instal·lador ja no fa servir la sessió: un doble clic al botó «Instal·lar» no pot
  bloquejar la petició esperant el fitxer de sessió (el botó també es desactiva sol).
- Totes les fases són repetibles: una instal·lació interrompuda es pot reprendre
  sense duplicar dades.
- Els errors fatals durant la instal·lació es mostren per pantalla i es registren a
  `storage/logs/install-AAAA-MM-DD.log`, amb la durada de cada fase.
- Les connexions a la base de dades tenen un temps d'espera de 10 s a l'instal·lador
  i de 15 s a l'aplicació, en comptes d'esperar indefinidament.

### Afegit
- Camp per indicar el sòcol Unix de MySQL i pista automàtica quan falla la connexió.
- El pas 2 mostra la versió del servidor i avisa si l'usuari no pot crear taules.
- El pas 1 mostra els límits de PHP (temps d'execució, memòria i mida de pujada).

## [1.0.0] — 2026-09-17

Primera versió del web del Cros Escolar La Granada.

### Afegit
- Portada amb banner configurable, compte enrere, blocs destacats, programa,
  galeria, preguntes freqüents i ubicació.
- Recorreguts amb mapes de Wikiloc (càrrega diferida) i descàrrega de GPX.
- Pàgina de categories i premis amb horaris de sortida.
- Venda de tiquets de l'esmorzar amb Stripe Checkout, webhook signat,
  generació de tiquets amb codi QR i correu de confirmació.
- Consulta pública dels tiquets comprats i versió imprimible.
- Formulari d'inscripció a les curses amb assignació automàtica de categoria.
- Panell d'administració complet: continguts, patrocinadors, comandes,
  inscripcions, validació de tiquets per QR, usuaris i configuració.
- Instal·lador automàtic amb comprovació de requisits i continguts d'exemple.
- Sistema d'actualitzacions OTA amb verificació SHA-256, còpies de seguretat
  i migracions de base de dades.
- Proves funcionals automatitzades (45 comprovacions) sobre SQLite.
