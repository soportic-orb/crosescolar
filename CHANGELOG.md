# Registre de canvis

El format segueix [Keep a Changelog](https://keepachangelog.com/ca/1.1.0/)
i el versionatge semàntic.

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
