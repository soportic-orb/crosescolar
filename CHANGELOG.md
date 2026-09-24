# Registre de canvis

El format segueix [Keep a Changelog](https://keepachangelog.com/ca/1.1.0/)
i el versionatge semàntic.

## [1.27.0] — 2026-09-24

### Afegit
- **El panell de la plataforma s'actualitza sol** (Sistema → Actualitzacions):
  comprova si hi ha versió nova, la instal·la fent còpia abans, deixa pujar un
  paquet a mà i guarda les còpies del sistema, que es poden descarregar. Mentre
  dura l'actualització, tots els webs que serveix diuen que tornen de seguida, i
  en acabar només es posa al dia la base de dades de la plataforma: les dels
  clients es fan a part, amb el botó d'actualitzar-les totes.
- **La plataforma té configuració pròpia**, editable des del seu panell i desada
  a la seva base de dades: nom del servei, frase i presentació de la portada,
  logotip, icona i colors, correu sencer (inclòs SMTP, amb un botó per enviar-se
  una prova), si s'accepten sol·licituds noves i què es diu quan no, si es mostra
  el llistat de cros, la vigilància i quantes còpies es guarden de cada client.
- Els colors i el logotip es veuen tant al panell com a la pàgina pública, i les
  sol·licituds tancades no s'accepten ni que algú enviï el formulari pel seu compte.
- El que continua al fitxer `tenants/platform.php` —dominis i bases de dades— surt
  a la pantalla de configuració perquè se sàpiga on és, però no s'hi edita.

### Corregit
- **Una vista podia quedar-se en blanc si rebia una variable que es deia `file`**:
  el nom xocava amb el que fa servir el renderitzador per obrir la plantilla. Ara
  no es poden trepitjar.
- L'esquema de configuració de la plataforma podia quedar-se actiu en treballar
  amb la base de dades d'un client i escriure-hi camps que no són seus. Cada base
  de dades torna a tenir el seu.
- En actualitzar, els valors per defecte de la plataforma ja no trepitgen el que
  va escriure qui la va instal·lar (el correu, per exemple).

## [1.26.1] — 2026-09-24

### Corregit
- **L'script d'instal·lació es moria en silenci en arribar a les bases de dades.**
  Les contrasenyes es generaven amb `tr … | head`, i `head` tanca el tub tan bon
  punt en té prou: `tr` rep un SIGPIPE i, amb les opcions estrictes que fa servir
  l'script, allò n'hi havia prou per aturar-ho tot sense dir ni piu, just després
  d'haver instal·lat el programari i copiat el codi. Ara es generen d'una manera
  que no pot fallar així.
- El sòcol del PHP es buscava amb el mateix parany; ara es mira la carpeta
  directament.
- Bateria de proves nova (`tests/instalador.sh`) que executa les funcions de
  l'script amb les mateixes opcions estrictes i comprova que l'assaig arriba al
  final: és la que hauria destapat tot això.

## [1.26.0] — 2026-09-24

### Afegit
- **Instal·lació guiada en un servidor nou**, en dues peces que es passen la
  feina. `sudo bash tools/instalar-vps.sh` deixa el VPS a punt: pregunta els
  dominis, instal·la nginx, MariaDB i PHP, crea les bases de dades i els usuaris
  amb contrasenyes generades, copia el codi amb els permisos que toquen, escriu la
  configuració de l'nginx amb els vostres dominis, mira que el DNS hi apunti,
  demana el certificat comodí, deixa el cron parat i obre l'assistent web.
- **L'assistent web** (`install-plataforma.php`) acaba la part d'aplicació: repassa
  el servidor, deixa revisar els dominis, prova les dues connexions a la base de
  dades —inclòs que l'usuari d'altes pugui crear-ne de noves—, configura el correu
  amb un botó per enviar-se una prova, crea el compte de superadministració, engega
  la plataforma i diu què queda per fer. Hi entra qui porta la clau d'un sol ús que
  dona l'script, i en tancar-lo esborra la clau i les dades que aquell havia deixat.
- L'script es pot provar abans amb `--assaig`, que diu què faria sense tocar res
  —i que no demana ser root, justament perquè no en toca cap—,
  i porta opcions per a servidors que ja tenen part de la feina feta
  (`--sense-paquets`, `--sense-certificat`, `--dominis`, `--arrel`).
- Bateria de proves nova (`tests/setup.php`) que recorre l'assistent sencer contra
  un MySQL real, com ho faria qui instal·la: des de la clau fins a la plataforma en
  marxa i l'assistent tancat.

## [1.25.1] — 2026-09-24

### Canviat
- **El codi d'un web que forma part d'una plataforma ja no el pot actualitzar el
  client**: la còpia del codi és compartida per tots els cros i, si l'actualitzés
  un, l'actualitzaria a tothom. L'apartat d'actualitzacions desapareix del seu
  panell i, si s'hi arriba a mà, contesta que d'això se n'encarrega qui administra
  la plataforma. Una instal·lació d'un sol web continua actualitzant-se com sempre.

### Corregit
- La configuració d'nginx de la plataforma deia que l'arrel del web era una carpeta
  `public/` que aquest projecte no té. Ara diu la bona i, de passada, tanca també
  `tools/`, `docs/` i l'instal·lador, que en una plataforma no pinten res.

### Afegit
- Guia per muntar la plataforma en un VPS acabat de crear
  ([`docs/vps.md`](docs/vps.md)): servidor, bases de dades, certificat comodí per
  als dos dominis, feines del cron i com portar-hi un cros que ja existia.

## [1.25.0] — 2026-09-24

### Afegit
- **Un mateix codi pot servir molts cros**: el sistema mira per on entra la petició
  i decideix què hi ha de sortir. `granada.crosescolar.com` és el web d'aquell cros,
  amb la seva configuració, les seves dades i els seus fitxers; el domini sol és la
  pàgina pública de la plataforma; `admin.` n'és el panell; i un subdomini que no és
  de ningú contesta que aquella adreça no existeix, sense ensenyar el web d'un altre.
- Una instància es pot **aturar temporalment** (un fitxer `suspended` a la seva
  carpeta): el web diu que no està disponible i torna quan s'esborra.
- Els subdominis es comproven abans de donar-los: lletres, números i guions, ni
  massa curts ni massa llargs, i mai un dels reservats (`www`, `admin`, `correu`,
  `api`, `cdn`…). Un nom que vulgui sortir de la seva carpeta no arriba enlloc.
- Configuració d'nginx per al servidor de la plataforma
  ([`docs/nginx-plataforma.conf`](docs/nginx-plataforma.conf)), amb el domini comodí
  i els fitxers pujats de cada instància.
- Sense el fitxer `tenants/platform.php`, res d'això s'engega: una instal·lació de
  sempre continua funcionant exactament igual.
- **La plataforma té la seva pròpia base de dades**, a part de les dels clients, amb
  els clients, les seves instàncies, les sol·licituds, els comptes de
  superadministració i el registre d'activitat. Les dades de cada cros no hi són:
  la plataforma només sap quins webs existeixen i de qui són.
- Una instància es pot **aturar, tornar a engegar i donar de baixa** des del codi, i
  quan es dona de baixa es guarda la data en què s'esborraran les dades (90 dies).
- La plataforma es pot **posar al dia llegint cada instància**: com es diu el web,
  quan és la cursa, quanta gent hi ha inscrita i si ja l'han publicat. Mentre ho fa
  no perd la seva pròpia connexió.
- **El llistat públic de cros** surt d'aquí: només els que ja han publicat el web i
  volen sortir-hi, amb les curses que vénen primer i les passades al final.
- **La pàgina pública de la plataforma** (`crosescolar.cat`): el llistat dels cros que
  ja hi corren —cada targeta porta al seu subdomini—, com funciona i el formulari per
  demanar-ne un de nou, amb el botó **«Crea la web per al teu cros»** al menú.
- En enviar el formulari, la sol·licitud queda desada amb un número i surten **dos
  correus**: la confirmació a qui l'ha demanada i l'avís a la superadministració.
- El formulari comprova el subdomini abans d'acceptar-lo, no deixa que una adreça
  n'enviï més de tres al dia i té un parany per a robots.
- **La plataforma pot tenir més d'un domini** (`crosescolar.cat` i
  `crosescolar.com`, per exemple): qui demana un cros tria en quin el vol, i des de
  la seva fitxa se li pot canviar després sense tocar res més. Tots els dominis
  serveixen tot, però cada web té una adreça bona i prou: les altres hi menen amb
  una redirecció permanent, i el mateix fan el `www` i els dominis secundaris de la
  pàgina pública i del panell. Un subdomini és d'un sol client encara que hi hagi
  dos dominis, de manera que equivocar-se de terminació porta igualment al cros que
  es buscava.
- **El panell de superadministració** (`admin.crosescolar.cat`), amb els seus propis
  comptes, a part dels administradors de cada cros: tauler amb les instàncies i les
  sol·licituds pendents, llistes de sol·licituds, instàncies i clients, la fitxa de
  cadascuna i el registre d'activitat.
- **Una instància es crea amb un botó**: el panell li fa la base de dades, un usuari
  de base de dades que només hi pot entrar a ella, la carpeta, la instal·lació del
  cros i el correu amb les claus per a qui el gestionarà. Triga menys d'un segon.
  Si alguna cosa falla pel camí no queda res a mitges: es desfà el que s'hagi fet.
- El web d'un client **neix amagat**: el publica ell quan ho té a punt, i llavors
  surt al llistat de la portada.
- **D'una sol·licitud a una instància sense tornar a escriure res**: des de la fitxa
  de la sol·licitud s'obre el formulari d'alta ja omplert, i en crear-la queden
  lligades la sol·licitud, la fitxa del client i la instància.
- Una sol·licitud es pot **desestimar amb un motiu**, que s'envia per correu a qui
  la va enviar.
- Des de la fitxa d'una instància es pot **aturar, tornar a engegar, donar de baixa**
  (escrivint-ne el nom, perquè no passi sense voler) i **actualitzar-ne les dades**
  llegint el seu web.
- **Actualitzar totes les instàncies d'un cop**: el panell avisa de quantes no tenen
  l'última versió i les posa totes al dia amb un botó (o una per una, des de la seva
  fitxa). Només aplica els canvis d'estructura pendents; no toca cap dada.
- **«Les meves dades»** al panell de cada cros: qui el gestiona es descarrega tot el
  que hi ha en un ZIP —un full de càlcul per cada llista, una còpia completa de la
  base de dades i els fitxers pujats—, tantes vegades com vulgui i sense demanar-ho
  a ningú. El fitxer no es queda al servidor.
- **Enllaços d'accés d'un sol ús**: la plataforma no desa cap contrasenya de ningú.
  Qui estrena un cros rep un enllaç (val set dies) per entrar i triar-se la seva; des
  de la fitxa se'n pot enviar un de nou si es perd (dues hores) i qui manté la
  plataforma pot entrar a donar suport amb un que val vint minuts. Serveixen una sola
  vegada, caduquen i al web del client només se'n desa l'empremta.
- Entrar a donar suport **queda apuntat al registre del web del client**: sempre pot
  veure que algú de la plataforma hi ha entrat, quan i per què.
- **Vigilància de les instàncies** (`php tools/platform.php vigilar`, cada quart
  d'hora al cron): mira que la base de dades de cada cros respongui i que la pàgina
  s'obri. Quan un cau —o quan torna— surt un correu, una sola vegada, i el panell ho
  ensenya a dalt de tot amb el motiu i des de quina hora.
- **Un cros que ja existia es pot portar a la plataforma sencer**: el ZIP de «Les
  meves dades» és alhora el paquet de migració —porta una fitxa (`migracio.json`)
  que diu què és, de quina versió i de quin web, amb l'empremta de la còpia de la
  base de dades— i, en crear una instància, es puja al panell i la instància neix
  amb tot a dins: inscripcions, resultats, textos, configuració, fitxers i comptes
  del panell amb les seves contrasenyes. Els enllaços que apuntaven a l'adreça
  antiga es canvien per la nova i el web d'origen no es toca.
- Un paquet d'una versió antiga es posa al dia sol en importar-lo, perquè la còpia
  porta també per quina versió anava el cros.
- Els paquets grossos, que no passen pel navegador, es poden deixar a
  `storage/imports/` del servidor i importar-los pel nom. Amb
  `php tools/platform.php paquet <fitxer.zip>` es pot mirar què porta abans de
  tocar res.
- **Còpies de seguretat de cada client** (`php tools/platform.php copies`, de
  matinada al cron): el mateix ZIP que el client es pot descarregar, desat al servidor
  a la carpeta del seu subdomini i guardant-ne les set últimes. A la fitxa surten amb
  data i mida, se'n pot fer una a l'instant i descarregar-ne qualsevol; si una
  instància fa tres dies que no es copia, el tauler ho avisa.
- **Repàs abans de la cursa** al panell de cada cros: quan falten menys de 45 dies,
  el tauler ensenya què encara no està a punt —data i hora, lloc i mapa, categories,
  recorreguts, web publicat, correus que surten de debò, tancament de la inscripció i
  maqueta del dorsal—, amb un botó per anar a cada cosa. Desapareix sol quan passa la
  cursa o quan no queda res.
- **Eina de consola de la plataforma** (`php tools/platform.php`): posar al dia la
  base de dades, crear superadministradors, llistar i repassar instàncies i esborrar
  les baixes que ja han passat els 90 dies, actualitzar-les totes, vigilar-les i
  fer-ne les còpies.
- **L'instal·lador es pot cridar des del codi**: tota la feina de posar en marxa un
  web (escriure la configuració, crear les taules, el compte d'administració, la
  configuració inicial i els continguts d'exemple) passa a la classe
  `Cros\Core\Installer`. El formulari d'`install.php` només és una manera de
  cridar-la; el panell de la plataforma en podrà cridar una altra per donar d'alta
  la instància d'un client sense cap formulari pel mig.
- Cada instal·lació pot tenir el seu fitxer de configuració i la seva carpeta de
  treball on convingui, i qui crea una instància no perd la connexió ni la
  configuració amb què estava treballant.
- **Les carpetes de dades es poden posar fora del codi**: amb les variables
  d'entorn `CROS_UPLOADS` i `CROS_STORAGE` s'indica on van els fitxers pujats i el
  que el sistema escriu mentre funciona (registres, còpies i temporals). Sense
  indicar res, tot continua exactament on era, de manera que cap instal·lació
  existent no nota el canvi.
- És el primer pas perquè diverses instal·lacions puguin compartir la mateixa còpia
  del codi, cadascuna amb les seves dades i el seu subdomini.
- Bateria de proves nova (`tests/tenants.php`) que comprova que dues instal·lacions
  amb el mateix codi no es trepitgen els fitxers ni els registres.
- La còpia de la base de dades es parteix caràcter a caràcter en comptes de per
  punt i coma: un text amb un punt i coma a final de línia, amb una ratlla que
  comenci per dos guions o amb línies en blanc arriba exactament igual.
- Bateria de proves nova (`tests/readiness.php`) per al repàs previ a la cursa.
- Bateria de proves nova (`tests/console.php`) que dona d'alta instàncies de debò
  contra un MySQL real i les serveix: accés al panell, alta, enllaços d'un sol ús,
  publicació, actualització, vigilància, còpies de seguretat, descàrrega de dades,
  aturada, baixa i esborrat definitiu.

## [1.24.0] — 2026-09-23

### Afegit
- **Barra d'avís a dalt de tot**: nou apartat *Configuració → Avisos*. S'activa quan
  convingui, s'hi escriu el missatge i es trien el **color de fons** i el **color del
  text**. Pot portar un enllaç amb el text que vulgueu —una pàgina del web o una de fora—
  que s'obre en una finestra nova, i els visitants la poden tancar amb una creu: un cop
  tancada no torna a sortir fins que canvieu el missatge. Surt a totes les pàgines, sobre
  el menú. Una barra sense missatge no es mostra.
- **Cartell emergent a la portada**: al mateix apartat es puja una **imatge** que surt
  damunt de la portada en obrir-la. En clicar-la pot obrir l'**enllaç** que li poseu, en
  una finestra nova. Es tanca amb la creu, clicant-hi fora o amb Esc, i per defecte només
  es veu **un cop per visita**. Sense imatge no surt res, i la descripció de la imatge es
  pot escriure per a qui no la pugui veure.
- Els apartats de configuració poden tenir **títols que separen els blocs**, i el d'avisos
  ja els porta.

### Seguretat
- Les adreces dels dos avisos es comproven abans de publicar-les: una que no sigui una
  adreça de debò (per exemple `javascript:…`) no arriba mai a l'enllaç.

## [1.23.0] — 2026-09-21

### Canviat
- **Tots els textos del panell s'editen amb l'editor visual**, el mateix dels enviaments de
  correu: el que s'escriu es veu tal com quedarà, amb negreta, cursiva, títols, llistes i
  enllaços a la barra de dalt. Fins ara calia escriure-hi etiquetes HTML a mà. Afecta els
  22 camps de text amb format del panell: la portada, el punt de recàrrega, les categories
  i premis, el reglament, les inscripcions (introducció, requadre «Recorda», text de
  tancament i de confirmació), els resultats, l'avís legal, la privacitat, el text del web
  en preparació i les descripcions dels recorreguts, els premis, els blocs destacats i les
  preguntes freqüents.
- El botó **&lt;/&gt; HTML** de cada camp continua deixant veure i editar el codi, i sense
  JavaScript el camp segueix sent la casella de text de sempre, de manera que res no depèn
  del navegador. El text enganxat d'un altre web entra sense format i el que es desa passa
  pel mateix filtre de seguretat d'abans.
- Cada camp manté l'alçada que tenia configurada (el reglament surt alt; una introducció
  curta, baixa) i clicar-ne el títol porta directament a on s'escriu.

## [1.22.0] — 2026-09-21

### Afegit
- **Avís abans de descarregar el dorsal**: en prémer «Descarregar el dorsal» (a la
  confirmació de la inscripció i a *Les meves inscripcions*) surt un quadre que explica com
  s'ha d'imprimir el document —en un full **DIN A4** vertical i a mida real— i que hi ha
  **dos dorsals iguals** que cal separar **retallant el full per la línia de punts**. El
  text es redacta sol segons el disseny configurat: si el dorsal no es pot partir en dos,
  diu que són dues còpies en dos fulls i no parla de retallar. El correu de confirmació
  porta la mateixa explicació.

### Corregit
- **Els botons ja no es toquen**: les files de botons del web públic (les meves
  inscripcions, confirmació de la inscripció, resultats, categories, contacte…) es
  dibuixaven sense separació perquè l'estil `.flex` només existia al panell. Ara hi ha
  aire entre els botons i també entre les files quan n'hi ha més d'una.

## [1.21.0] — 2026-09-21

### Afegit
- **Les famílies poden anul·lar una inscripció**: a *Les meves inscripcions*, cada
  participant té el botó **Anul·lar la inscripció**, que demana confirmació abans de fer
  res. La inscripció **no s'esborra**: es queda al panell amb l'estat **Anul·lada**, amb
  el dia i l'hora de la baixa i amb qui la va fer (la família o l'organització), i
  **conserva el seu número de dorsal**, que ja no es donarà mai a ningú altre —així un
  dorsal imprès no pot acabar en dues mans diferents. La família en rep un correu de
  confirmació i l'organització, l'avís corresponent.
- **Filtre d'estat al llistat d'inscripcions**: *Sense les anul·lades* (com es veu per
  defecte), *Totes* o *Només les anul·lades*. Les anul·lades hi surten marcades i
  apagades, i la capçalera en diu quantes n'hi ha.

### Canviat
- Un participant anul·lat no surt als **dorsals per imprimir** del panell, la seva família
  ja no en pot descarregar el PDF, no entra als **correus massius** i no compta als
  totals de participants per categoria ni als comptadors del tauler.
- **A meta**, passar un dorsal anul·lat ja no apunta cap arribada: el panell avisa que la
  inscripció està anul·lada i recorda que, si finalment corre, cal tornar-la a activar.
- Les dades d'una inscripció anul·lada ja no es poden modificar des del web.
- La fitxa del panell explica la diferència entre **anul·lar** (el dorsal queda reservat) i
  **esborrar** (treu la fila i el número pot tornar a circular), i en permet la
  reactivació posant l'estat a *Confirmada*.
- L'exportació CSV d'inscripcions porta una columna nova, **Anul·lada el**.

## [1.20.0] — 2026-09-21

Aquesta versió recull tot el que s'ha fet des de la 1.15.1.

### Afegit
- **Menú del web configurable**: nou apartat *Continguts → Menú del web*. S'hi tria quins
  apartats surten al menú públic, s'endrecen amb fletxes i se'ls pot canviar el nom. Els
  que depenen d'una altra cosa —els *Resultats* fins que no es publiquen, *Els meus
  tiquets* sense venda en línia— hi surten marcats com a «ara no es veu» i apareixen sols
  quan toca. El botó «Inscriu-te!» continua sempre al final. Els apartats que abans no hi
  eren (*Reglament*, *Punt de recàrrega*, *Preguntes freqüents*, *Contacte*, *Inscripció*)
  ara s'hi poden afegir sense tocar codi. En actualitzar, el menú queda exactament com estava.
- **L'escola al dorsal**: a *Configuració → Dorsals* hi ha un quart camp, l'**escola o
  club** que la família escriu a la inscripció, amb les mateixes opcions que el número, el
  nom i la categoria (posició, mida, color, alineació i negreta). Ve desactivat: en
  activar-lo, apareixen les posicions i es pot col·locar amb el botó «Dorsal de prova».
  Si una inscripció no porta escola, al seu dorsal no s'hi dibuixa res.
- **Imprimir els resultats d'una categoria**: a *Resultats* hi ha un selector **Imprimir**
  que genera el PDF de la categoria triada (o de totes) directament, sense haver de
  filtrar abans la pantalla. El selector **Veure**, que filtra el llistat, queda a part i
  ara va etiquetat.
- **Confeti en confirmar la inscripció**: en acabar d'inscriure algú, la pàgina de
  confirmació celebra el moment amb una pluja de confeti dels colors del web. Dura uns
  segons i desapareix sola. Es pot apagar a *Configuració → Inscripcions*, i qui tingui
  activat «reduir el moviment» al seu dispositiu no el veu mai. Fet sense cap llibreria
  externa: no afegeix cap pes ni cap connexió a tercers.
- El motor de PDF sap dibuixar **línies de punts i cercles**, que és el que fa falta per a
  la marca de retallar.

### Canviat
- **El dorsal que es descarrega la família porta dues còpies**: un full A4 vertical amb el
  mateix dorsal a dalt i a baix, apaïsats, separats per una línia de punts amb unes
  tisores i el text «Retalleu per aquí». Cada còpia se centra dins de la seva meitat i la
  línia passa per una franja en blanc, de manera que els dos dorsals queden ben separats
  encara que el disseny arribi fins a la vora. Amb un sol full en tenen un per al pit i un
  de recanvi. Si hi ha més d'un participant, cadascun té el seu full. Si el dorsal ocupa un
  A4 sencer i no es pot partir el full, se'n fan dues còpies en dos fulls.
- El que s'imprimeix des del panell no canvia: un dorsal per pàgina, o dos de diferents
  per full amb l'opció «Dos dorsals per full A4».
- **El PDF de resultats diu clarament de quina categoria és**: la categoria surt a la
  franja verda de dalt i com a títol gran, amb el gènere i els anys de naixement quan en
  té («Aleví femení (2016–2017)»), i just a sota hi consta quanta gent hi ha
  classificada. També a les pàgines de continuació.
- El nom del fitxer PDF dels resultats porta el nom de la categoria en lloc del seu número
  intern.

## [1.15.1] — 2026-09-21

### Afegit
- Al peu de pàgina, al costat de l'avís legal i la privacitat, hi surt **«Desenvolupat per
  Octavi Rodríguez»**, amb el nom enllaçat al seu correu. El nom i l'adreça es canvien a
  *Configuració → SEO i legal*; si es deixa el nom buit, la línia no es mostra.

## [1.15.0] — 2026-09-21

### Corregit
- **La categoria automàtica no mirava el gènere.** En inscriure's triant «assignar
  automàticament», el sistema agafava la primera categoria que quadrava amb l'any de
  naixement, de manera que amb categories separades per gènere una participant podia
  acabar a la categoria masculina. Ara s'hi busca primer la categoria del seu gènere; si
  no n'hi ha cap, una de mixta; i si tampoc, la inscripció queda per assignar en lloc de
  posar-hi la que no toca. En modificar la inscripció, canviar el gènere també recalcula
  la categoria.
- **Després d'inscriure algú, «Les meves inscripcions» hi entra directament**, sense
  demanar cap codi: qui acaba d'omplir el formulari pot corregir-hi el que calgui. Només
  s'hi veu el que s'ha inscrit des d'aquell navegador; per veure la resta d'inscripcions
  de l'adreça continua fent falta el codi, perquè escriure una adreça en un formulari no
  demostra que sigui teva.

### Afegit
- **Dos dorsals per full A4**: nova opció a *Configuració → Dorsals*. Cada full vertical
  porta dos dorsals apaïsats, un a dalt i un a baix, i es gasta la meitat de paper. Només
  s'aplica quan el dorsal hi cap (un A5 apaïsat o més petit).

### Canviat
- La pàgina de confirmació d'inscripció ja no té el botó del punt de recàrrega.

## [1.14.0] — 2026-09-21

### Canviat
- **La categoria del dorsal porta els anys**: on abans hi deia només «Infantil», ara hi
  surten el nom, el gènere (si la categoria no és mixta) i els anys de naixement —
  *Infantil masculí (2013–2014)*, *Prebenjamí femení (2020)*. Si el nom de la categoria ja
  diu el gènere, no es repeteix, i si ja acaba amb un parèntesi els anys hi van a
  continuació amb un punt volat, per no encadenar dos parèntesis.
- **Categories d'un sol any**: quan l'any inicial i el final són el mateix, ara es mostra
  un cop (*2020*) en lloc de repetir-lo (*2020–2020*). Val per a la pàgina de categories,
  el formulari d'inscripció, el llistat del panell i el dorsal.

## [1.13.0] — 2026-09-21

### Afegit
- **Dorsals amb el nom sol**: a *Configuració → Dorsals* hi ha la casella **«Només el nom,
  sense cognoms»**. Amb ella activada, al dorsal hi surt «Laia» en lloc de «Laia Ferrer
  Miró»; va bé quan el dorsal és petit o quan es vol que s'hi llegeixi de lluny.
- **Correus a les persones inscrites**: nou apartat **Enviaments** al panell. S'hi escriu
  el tema i el cos amb un **editor visual** (negreta, cursiva, títols, llistes i enllaços,
  amb un botó per veure i tocar l'HTML) i es tria a qui va: totes les persones inscrites,
  només algunes categories o unes adreces escrites a mà. També es pot limitar a les
  inscripcions confirmades; les anul·lades no en reben mai cap.
- **Una adreça, un correu**: una família amb diversos fills inscrits rep un sol missatge.
  Al cos s'hi poden escriure els marcadors `{{tutor}}`, `{{participants}}`, `{{dorsals}}`,
  `{{cursa}}` i `{{data}}`, que se substitueixen a cada enviament.
- **Enviament per tandes** amb barra de progrés, pensat per a allotjaments compartits: si
  es talla, es continua des d'on era i no es repeteix cap correu. En acabar, el botó
  «Enviar-lo a les inscripcions noves» escriu només a qui s'ha inscrit després.
- **Vista prèvia i correu de prova** abans d'enviar-lo a ningú, i llistat de destinataris
  amb l'estat de cadascun.
- **Mode d'assaig del correu** (*Configuració → Correu electrònic → Mètode d'enviament*):
  no surt res del servidor però tot queda registrat com si s'hagués enviat, per poder
  assajar un enviament sencer sense escriure a ningú.
- **Correus per tanda** configurable (20 per defecte), per si l'allotjament en limita el
  nombre per hora.

## [1.12.0] — 2026-09-21

### Afegit
- **Reglament de la cursa**: nou apartat *Configuració → Reglament* amb el text del
  reglament, que es publica a `/reglament` i s'enllaça des del peu de pàgina. Al
  formulari d'inscripció hi ha la casella **«Accepto el reglament de la cursa»**, el text
  de la qual obre el reglament en una pestanya nova; l'acceptació queda desada amb la
  inscripció. Es pot deixar de demanar sense retirar la pàgina.
- **Premi «Primer local»**: cada categoria pot premiar el primer classificat de l'escola
  del poble. S'activa a *Categories → editar* i el guanyador es marca a mà des de
  *Resultats*, un per categoria; el panell assenyala amb l'etiqueta *local* qui hi pot
  optar. El premi surt a la classificació pública, al CSV i al PDF de la categoria. El
  nom del premi i quina és l'escola del poble es configuren a *Configuració → Categories
  i premis*.
- **Avís legal i política de privacitat redactats**: textos complets a partir del que fa
  realment aquest web (qui organitza, quines dades es demanen, qui hi accedeix, quant es
  guarden i quins drets hi ha). Hi admeten marcadors — `{{entitat}}`, `{{cursa}}`,
  `{{poble}}`, `{{correu}}`, `{{telefon}}` i `{{web}}` — que se substitueixen sols, de
  manera que no es queden antics quan canvia el contacte.

### Corregit
- El **botó «Inscriu-te!»** de la capçalera tenia el text gairebé negre sobre el fons
  vermell, perquè el color del menú li passava al davant. Ara el text és blanc i, en
  passar-hi per sobre, el botó enfosqueix el vermell en comptes de tornar-se verd clar.

### Canviat
- El formulari d'inscripció ja no demana **la talla de samarreta** ni **el curs**. Els
  camps desapareixen del web, de «Les meves inscripcions», de la fitxa del panell, dels
  correus i de les exportacions.
- L'entitat responsable per defecte passa a ser l'**AFA Jacint Verdaguer de La Granada**,
  i la política de privacitat ja no parla de pagaments amb Stripe: aquest web no en fa cap.

## [1.11.0] — 2026-09-21

### Corregit
- **El mapa de la ubicació no obria el punt correcte.** L'enllaç del mapa era un text fix
  que no tenia res a veure amb l'adreça configurada a *Dades de la cursa*, i els camps de
  latitud i longitud no s'utilitzaven enlloc: canviéssiu el que canviéssiu, el mapa
  sempre obria el mateix lloc. Ara el punt surt de les coordenades configurades i
  l'enllaç el marca amb `mlat`/`mlon` i hi centra el mapa.

### Afegit
- **El punt del mapa es pot enganxar.** Als camps de latitud i longitud s'hi pot posar un
  enllaç d'OpenStreetMap o de Google Maps, una adreça `geo:` o les dues coordenades
  juntes: el panell en treu el punt i omple els dos camps. També s'hi accepta la coma
  decimal i la lletra de l'hemisferi (`1,7135 E`).
- **Mapa a la portada**: amb les coordenades posades, a «Com arribar-hi» hi surt el mapa
  amb el punt marcat. Com el de Wikiloc, no es connecta a OpenStreetMap fins que el
  visitant hi fa clic.
- **Sense coordenades, el mapa cerca l'adreça** de la cursa, de manera que el botó «Obre
  el mapa» sempre porta on toca. A la pàgina de contacte l'adreça també hi fa d'enllaç.
- **El requadre «Recorda» de la pàgina d'inscripció és editable**: a Configuració →
  Inscripcions s'hi canvia el títol i el contingut (amb HTML bàsic) i es pot amagar.

### Canviat
- Els camps del mapa han passat de *Portada* a **Dades de la cursa**, al costat de
  l'adreça a què corresponen.
- En actualitzar, les **opcions noves** d'una versió s'inicialitzen soles amb el seu
  valor per defecte; les que ja estaven escrites no es toquen.
- L'adreça d'exemple apuntava a un carrer que no existeix al mapa: ara és la del camp
  municipal. Només canvia si encara teníeu el text d'exemple.

## [1.10.0] — 2026-09-21

### Afegit
- **La descàrrega del dorsal es pot desactivar**: a Configuració → Dorsals hi ha
  l'interruptor «Les famílies poden descarregar el dorsal». Si es desactiva, l'enllaç
  desapareix de la pàgina de confirmació, de «Les meves inscripcions» i dels correus, i
  les adreces de descàrrega deixen de respondre. El número del dorsal es continua veient
  i, des del panell, els dorsals es poden imprimir igualment.

## [1.9.2] — 2026-09-20

### Canviat
- El **codi de la categoria** (BEN, ALE…) ja no surt a la pàgina pública de categories:
  és una referència interna i es continua veient al panell.

## [1.9.1] — 2026-09-20

### Canviat
- A la pàgina de categories, les **voltes sempre s'indiquen**, també quan només n'hi ha
  una: «1 volta a Circuit petit». Abans, amb un sol recorregut, només hi sortia el nom.
- La fitxa d'un recorregut diu, a cada categoria que hi corre, quantes voltes hi fa.

## [1.9.0] — 2026-09-20

### Afegit
- **Diversos recorreguts per categoria, amb voltes**: una cursa pot ser, per exemple,
  1 volta al circuit A i 2 voltes al circuit B. A Categories → editar s'hi afegeixen els
  recorreguts que calgui, s'hi indiquen les voltes de cadascun i s'ordenen amb les
  fletxes. El web en mostra la composició i la fitxa de cada recorregut llista totes les
  categories que hi passen. En actualitzar, cada categoria manté el recorregut que tenia
  amb una volta.
- Si la **distància** d'una categoria es deixa en blanc, al web s'hi calcula la suma dels
  recorreguts per les seves voltes.

## [1.8.1] — 2026-09-20

### Corregit
- Les inscripcions fetes **des del panell** no rebien número de dorsal ni enllaç privat
  per descarregar-lo: calia assignar-los després. Ara el número s'assigna sol, com a
  les inscripcions del web, agafant el següent lliure.
- Escriure a mà un dorsal que ja tenia un altre participant donava un error del
  servidor. Ara el formulari avisa («El dorsal 007 ja és d'un altre participant») i no
  desa res. Si es buida el camp, se n'hi posa un de nou.

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
