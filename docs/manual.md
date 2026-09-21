# Manual del panell d'administració

Guia pràctica per a l'equip de l'AFA. Accés: `https://cros.afalagranada.cat/admin`

## Rols

| Rol | Pot fer |
|---|---|
| **Administrador** | Tot, incloent-hi credencials de Stripe, correu, usuaris i actualitzacions |
| **Editor** | Continguts, comandes, inscripcions i validació de tiquets |

## Web en preparació («Aviat publicarem el web»)

Mentre prepareu els continguts podeu mantenir el web amagat:

1. **Configuració → Web en preparació** i activeu *Amagar el web al públic*.
2. Personalitzeu el títol, el text i, si voleu, una imatge de fons.

A partir d'aquell moment:

- Qualsevol visitant veu només l'avís, amb la data de la cursa, el compte enrere i
  el contacte (podeu amagar aquests dos blocs).
- **Vosaltres continueu veient el web sencer** sempre que tingueu la sessió iniciada
  al panell. A dalt de tot hi apareix una barra recordant que el web està amagat,
  amb un botó per publicar-lo a l'instant.
- El panell, la validació de tiquets i el webhook de Stripe continuen funcionant.
- El web no s'indexa als cercadors mentre estigui amagat.

Per publicar-lo: botó **Publicar el web ara** (a la barra superior o al tauler) o
desactiveu l'opció a la configuració.

## Abans de la cursa

### 0. Dades de la cursa i mapa de la sortida
**Configuració → Dades de la cursa**
- *Lloc de sortida*, *adreça* i *població*: surten al web, als correus i als llistats.
- *Latitud* i *longitud de la sortida*: el punt exacte que marca el mapa. La manera més
  senzilla d'omplir-los és anar a [openstreetmap.org](https://www.openstreetmap.org),
  buscar el lloc, fer-hi **clic dret → «Mostra l'adreça»** i copiar els dos números.
  També podeu **enganxar directament un enllaç** d'OpenStreetMap o de Google Maps a
  qualsevol dels dos camps: el panell en treu les coordenades i omple tots dos.
- *Enllaç del mapa*: opcional, per si feu servir un mapa propi.
- Si deixeu les coordenades i l'enllaç **buits**, el botó «Obre el mapa» cerca l'adreça
  que hi ha escrita més amunt.

Amb les coordenades posades, a la portada hi surt el mapa amb el punt marcat (només es
connecta a OpenStreetMap quan el visitant hi fa clic) i el botó obre el mapa centrat
sobre la sortida.

### 1. El menú del web
**Menú del web** (al menú del panell, dins de *Continguts*) decideix **quins apartats
surten al menú** del web i **en quin ordre**:

- La casella **Visible** els fa sortir o els amaga.
- Les fletxes **↑ ↓** els endrecen. Si hi canvieu un nom o una casella i premeu una
  fletxa, no es perd res: es desa tot alhora.
- A **Nom al menú** hi podeu posar un altre títol (per exemple, *Contacte* → *Parla amb
  nosaltres*). Si el deixeu buit, s'hi posa el de sempre.

Alguns apartats porten l'etiqueta **«ara no es veu»**: existeixen, però depenen d'una
altra cosa — els *Resultats* només surten quan estan publicats, i *Els meus tiquets*
només amb la venda en línia activada. Podeu deixar-los marcats i apareixeran sols quan
toqui.

El botó **«Inscriu-te!»** hi és sempre, al final del menú, i no es pot treure des d'aquí.

### 2. Personalitzar la portada
**Configuració → Portada**
- *Imatge del banner*: fotografia horitzontal (1920×1080). Si no n'hi ha cap es mostra
  el fons il·lustrat de vinyes.
- *Etiqueta superior*, *títol* i *subtítol* del banner.
- Botons principals i compte enrere.
- Textos de cada secció: introducció, recorreguts, programa, galeria, ubicació i patrocinadors.

### 3. Recorreguts amb Wikiloc
**Recorreguts → Afegir**
- Enganxeu l'**URL de Wikiloc** de la ruta (per exemple
  `https://ca.wikiloc.com/rutes-senderisme/cros-la-granada-123456789`). El número
  s'extreu automàticament i el mapa apareix al web.
- Opcionalment pugeu el **fitxer GPX** perquè la gent se'l pugui descarregar.
- Indiqueu distància (en metres), desnivell i tipus de terreny.

> Els mapes de Wikiloc només es carreguen quan la persona hi fa clic: així la pàgina
> va més ràpida i no es comparteixen dades amb tercers sense consentiment.

### 4. Categories i premis
**Categories** — nom, anys de naixement, hora de sortida, distància i recorregut assignat.
Si la categoria és d'un sol any, poseu el mateix any als dos camps: al web i als dorsals
hi sortirà una sola vegada (*2020*, no *2020–2020*).
**Premis** — targetes que es mostren a la pàgina de categories.

### 5. Patrocinadors
**Patrocinadors → Afegir**: nom, logotip (PNG o SVG amb fons transparent), enllaç i tipus
(institucional, principal, col·laborador). Arrossegueu les files per canviar-ne l'ordre.

### 6. Inscripcions: amb formulari o només informació

**Configuració → Inscripcions** decideix què veu la gent a la pàgina d'inscripció:

- **Formulari d'inscripció en línia actiu**: es mostra el formulari complet i les
  inscripcions arriben a **Inscripcions** del panell (amb exportació a CSV).
- **Desactivat**: la pàgina mostra **només el text informatiu** que hi escriviu, sense
  cap formulari. És l'opció per fer les inscripcions a l'escola, en paper o amb un
  altre servei. Opcionalment podeu afegir-hi un botó (per exemple cap a un PDF amb el
  full d'inscripció o cap a un formulari extern).

El formulari també es tanca sol quan es passa la **data de tancament**; deixeu la data
buida si no en voleu posar cap. En tots dos casos, si algú prova d'enviar el formulari
tot i estar tancat, la inscripció es rebutja.

Quan algú acaba d'inscriure's, la pàgina de confirmació fa un **efecte de confeti** per
celebrar-ho. Es pot apagar amb *Confeti en confirmar la inscripció*; qui tingui activat
«reduir el moviment» al mòbil o a l'ordinador no el veu, encara que estigui activat.

Al costat del formulari hi ha el **requadre de recordatoris** («Recorda»), amb el que
convé tenir present abans d'inscriure's. El títol i el contingut s'editen a
**Configuració → Inscripcions** (*Títol del requadre* i *Contingut del requadre*, amb
HTML bàsic: llistes, negretes i enllaços), i l'interruptor *Mostrar el requadre de
recordatoris* el treu de la pàgina si no el voleu.

### 6.1. «Les meves inscripcions»

Les famílies poden revisar i corregir les dades que van posar sense haver d'escriure-us.
A **Les meves inscripcions** del web escriuen la seva adreça electrònica, reben un
**codi de sis xifres** i, un cop escrit, veuen tots els participants que han inscrit amb
aquella adreça. De cadascun poden canviar el nom, l'any de naixement (la categoria es
torna a calcular sola), el gènere, l'escola, el contacte, les observacions i el
consentiment d'imatge, descarregar-ne el dorsal i, si cal, anul·lar la inscripció.

El codi val 15 minuts i només serveix un cop. **No** es poden canviar des del web
l'adreça de contacte ni el número de dorsal: aquestes coses les feu vosaltres des del
panell.

#### Anul·lar una inscripció

Amb el botó **Anul·lar la inscripció** una família pot donar de baixa un participant
que finalment no correrà. Abans de fer-ho el web els demana que ho confirmin i, un cop
fet, en rebeu un avís per correu (si teniu posada l'adreça d'avisos).

La inscripció **no s'esborra**: es queda al panell amb l'estat **Anul·lada**, amb el dia
i l'hora de la baixa i amb el seu **número de dorsal**, que d'aquesta manera no es donarà
mai a ningú altre. Un participant anul·lat no surt als dorsals per imprimir, la seva
família ja no en pot descarregar el PDF, no rep els correus massius i, si algú li passa
el dorsal per meta, el panell avisa que està anul·lat en comptes d'apuntar l'arribada.
Tampoc es compta als totals de participants per categoria.

Al llistat d'inscripcions el filtre **Sense les anul·lades / Totes / Només les anul·lades**
decideix quines veieu; per defecte queden amagades. Per recuperar-ne una, obriu la fitxa,
poseu l'estat a **Confirmada** i deseu: torna a ser una inscripció normal amb el mateix
dorsal de sempre.

> Anul·lar no és el mateix que **Esborrar**. Esborrar treu la fila de la base de dades i
> el número de dorsal pot acabar sent d'algú altre; anul·lar el reserva per sempre.

Si preferiu que ningú no pugui tocar les seves dades, desactiveu
**Configuració → Inscripcions → «Les meves inscripcions» actiu** i la pàgina desapareix
(amb ella, també la possibilitat d'anul·lar-se).

### 6.2. Recorreguts i voltes de cada categoria

Una cursa pot fer més d'un recorregut: per exemple, **1 volta al circuit A i 2 voltes al
circuit B**. A **Categories → editar → Recorreguts i voltes** s'hi afegeixen tants
recorreguts com calgui amb el botó **«+ Afegir un recorregut»**, s'hi posen les voltes
de cadascun i s'ordenen amb les fletxes ↑ ↓ (la primera fila és el primer tram). El
botó **×** el treu.

Al web, la columna **Recorregut** de la pàgina de categories mostra la composició
(«1 volta a Circuit mitjà + 2 voltes a Circuit llarg») i cada recorregut enllaça amb la
seva fitxa, on ara hi surten totes les categories que hi passen amb les voltes que hi
fan. Les voltes sempre s'hi indiquen, també quan només n'hi ha una. Si deixeu la
**distància** de la categoria en blanc, al web s'hi calcula la suma dels recorreguts per
les voltes.

### 7. Dorsals dels participants

Cada inscripció rep automàticament un **número de dorsal** (001, 002, 003…) i les
famílies el poden descarregar en PDF des del correu de confirmació.

Si els dorsals els voleu imprimir i repartir vosaltres, desactiveu **«Les famílies poden
descarregar el dorsal»** a Configuració → Dorsals: l'enllaç desapareix del web, de «Les
meves inscripcions» i dels correus de confirmació, i qui provi d'obrir un enllaç antic
es trobarà que ja no existeix. El número del dorsal es continua veient, i des del panell
els podeu imprimir igualment.

**Configuració → Dorsals** permet dissenyar-los:

1. Pugeu la **maqueta en PDF** (el disseny amb els logotips, fet amb Canva, Illustrator,
   Word…). Si el PDF té més d'una pàgina, indiqueu quina voleu.
2. Digueu si el dorsal és **vertical o horitzontal**. Amb «la mateixa que la maqueta»
   surt amb la mida i l'orientació del PDF que heu pujat, que és el més habitual. Si en
   marqueu una altra, la maqueta es gira per omplir la pàgina: serveix per als dissenys
   apaïsats que el programa de disseny ha desat en un PDF vertical. Si la maqueta surt
   de costat o del revés, corregiu-ho amb **Gir de la maqueta**. A dalt del formulari hi
   diu quina mida té la maqueta i quina tindrà el dorsal.
3. Indiqueu on van el **número**, el **nom**, la **categoria** i l'**escola**: posició X i
   Y en mil·límetres des de la cantonada superior esquerra, mida de lletra, color,
   alineació i negreta. Qualsevol dels quatre camps es pot amagar; l'escola ve
   desactivada. Si canvieu
   l'orientació, reviseu aquestes posicions: el dorsal ja no té la mateixa forma.
   Amb **«Només el nom, sense cognoms»** al dorsal hi surt «Laia» en lloc de «Laia
   Ferrer Miró»: va bé quan el dorsal és petit o quan voleu que s'hi llegeixi de lluny.
   A la línia de la categoria hi surten el nom, el gènere (si la categoria no és mixta) i
   els anys de naixement: *Infantil masculí (2013–2014)*, *Prebenjamí femení (2020)*.
4. Premeu **«Veure un dorsal de prova»** per comprovar com queda abans d'imprimir.

Amb **«Dos dorsals per full A4»** cada full vertical en porta dos, un a dalt i un a baix,
i es gasta la meitat de paper. Només s'aplica si el dorsal hi cap — un A5 apaïsat
(210×148 mm) o més petit; si és més gran, se'n continua fent un per full. Comproveu amb el
dorsal de prova que el número, el nom i la categoria quedin dins del dorsal: si alguna
posició se'n surt, al full de dos es ficaria dins de l'altre dorsal.

**El dorsal que es descarrega la família** és diferent del que imprimiu vosaltres: porta
**dues còpies del mateix dorsal** en un full A4 vertical, una a dalt i una a baix, amb una
línia de punts i unes tisores al mig. Així, amb un sol full, en tenen un per al pit i un
de recanvi (o un per davant i un per darrere). Si tenen més d'un fill inscrit, cada
participant té el seu full. Quan el dorsal és massa gran per partir el full (un A4
sencer), se'n fan dues còpies en dos fulls, sense línia de retallar.

Per imprimir-los tots: **Inscripcions → Dorsals en PDF** (es pot filtrar per categoria).
Aquí sí que va un dorsal per pàgina, o dos de diferents per full si heu activat l'opció. Cada fitxa d'inscripció també té el seu botó de descàrrega.

El número s'assigna **sol** en inscriure's, tant si ho fa la família pel web com si el
feu vosaltres des de **Inscripcions → Nova**: el sistema agafa el següent lliure (001,
002, 003…). Al formulari del panell hi diu quin tocarà; si hi escriviu un número, es
respecta.

Per canviar-ne un, **Inscripcions → editar → Número de dorsal**: s'admet qualsevol
número que no tingui cap altre participant; si ja és d'algú, el formulari avisa i no
desa res. Si buideu el camp, se n'hi posa un de nou automàticament. El botó
**«Assignar dorsals»** dona número a les inscripcions antigues que encara no en
tinguin.

### 8. Punt de recàrrega (tiquets)
**Tipus de tiquet**: nom, descripció, preu, existències i màxim per comanda.
Deixeu les existències en blanc per no limitar-les.
El **punt de recàrrega** és el servei de bar de la cursa: on els participants recuperen
l'energia gastada corrent. **Configuració → Punt de recàrrega** decideix què es veu al web:

- **Només informació** (opció per defecte): el web explica el punt de recàrrega i els preus, però
  no s'hi pot comprar. Els tiquets es venen presencialment.
- **Informació i venda en línia**: s'activa la botiga amb pagament per targeta.

En tots dos casos, el panell manté la gestió de tiquets, comandes, venda manual i
validació amb codi QR.

## Durant la venda

**Comandes** mostra totes les compres amb el seu estat:

| Estat | Significat |
|---|---|
| Pendent | S'ha iniciat el pagament però encara no s'ha confirmat |
| Pagada | Pagament confirmat; els tiquets ja s'han enviat |
| Cancel·lada | Anul·lada manualment o per caducitat de la sessió de pagament |
| Retornada | S'ha fet la devolució per Stripe |

Des de la fitxa d'una comanda podeu **reenviar el correu**, **marcar com a pagada**
(vendes en efectiu), **cancel·lar** o **fer la devolució**.

**Venda manual**: per registrar tiquets pagats en efectiu o per transferència.
Si marqueu «Marcar com a pagada» es generen els tiquets a l'instant.

## El dia de la cursa

### Resultats: arribades a meta

**Resultats** és la pantalla per anar registrant qui va arribant (va bé en una tauleta
o un mòbil):

1. Escriviu el **número de dorsal** i premeu Retorn. No cal cronometrar: el sistema
   assigna la posició dins de la categoria del participant per ordre d'arribada.
2. A la dreta hi veureu les últimes arribades; si us equivoqueu, podeu esborrar-les.
3. A la classificació podeu **pujar o baixar** qualsevol participant amb les fletxes si
   cal corregir l'ordre. Les posicions es recalculen soles.

Quan els resultats siguin definitius, premeu **«Publicar al web»**: apareixeran a
`/resultats`, ordenats per categoria, i al menú del web.

Les medalles es decideixen **a cada categoria**, no en general: a **Categories →
editar** hi ha l'interruptor **«Marcar els guanyadors amb medalla»** i, si l'activeu, el
camp **«Participants premiats»** (els tres primers, els quatre primers…). Així podeu
premiar tres corredors a les categories petites i cinc a les grans, o no posar medalles
a la cursa de famílies. Les tres primeres posicions porten els colors d'or, plata i
bronze, i de la quarta en endavant, el verd de la cursa. Al llistat de categories hi ha
una columna **Premiats** que ho resumeix.

#### Premi «Primer local»

A més de les medalles, cada categoria pot donar un premi al **primer classificat de
l'escola del poble**. S'activa a **Categories → editar → «Premi «Primer local»»**, i el
nom del premi i quina és l'escola del poble es configuren a **Configuració → Categories i
premis**.

Aquest premi **el marqueu vosaltres a mà**, perquè qui és «del poble» no sempre es
dedueix del nom que s'ha escrit a la inscripció. A **Resultats**, dins de cada categoria
que el dona, hi ha una columna amb un botó **Marcar** a cada fila; les persones de
l'escola configurada porten l'etiqueta *local* per trobar-les de seguida. Només el pot
tenir una persona per categoria: si en marqueu una altra, la primera el perd. El premi
surt al costat del seu nom a la classificació pública, al CSV i al peu del PDF de la
categoria.

**Imprimir una categoria.** A la barra de la classificació hi ha dos selectors: **Veure**
filtra el que es mostra a la pantalla, i **Imprimir** tria què va al PDF — una categoria
o totes. El PDF s'obre en una pestanya nova i porta la categoria **ben visible a dalt**:
a la franja verda i com a títol gran, amb els anys de naixement i el gènere si la
categoria en té («Aleví femení (2016–2017)»), i just a sota quanta gent hi ha
classificada. El nom del fitxer també ho diu, per no confondre'ls a l'hora d'imprimir.

**Exportacions** (botons de la mateixa pantalla):

| Botó | Què genera |
|---|---|
| PDF per categories | Un llistat per categoria, de la primera a l'última posició |
| PDF d'aquesta categoria | Només la categoria escollida |
| PDF per ordre d'arribada | Tots els participants, del primer al últim a creuar la meta |
| CSV | Full de càlcul amb totes les dades |


### Validació de tiquets
**Validar tiquets** (funciona bé en un mòbil):
1. Premeu *Activar la càmera* i enfoqueu el codi QR del tiquet.
2. El resultat es mostra a l'instant:
   - **Verd**: tiquet vàlid, ja queda marcat com a utilitzat.
   - **Groc**: el tiquet ja s'havia validat abans (mostra quan).
   - **Vermell**: codi inexistent, anul·lat o comanda no pagada.
3. Si no hi ha càmera disponible, escriviu el codi (comença per `T`) al formulari.

Si us equivoqueu, des de la fitxa de la comanda podeu **restablir** un tiquet.

### Llistes de participants
**Inscripcions → CSV** genera un full de càlcul amb totes les inscripcions
(compatible amb Excel i LibreOffice) per imprimir les llistes de sortida.

## Després de la cursa

- **Galeria**: pugeu les fotografies de la jornada.
- **Configuració → Punt de recàrrega**: tanqueu la venda de tiquets.
- **Sistema → Actualitzacions**: feu una còpia de seguretat per arxivar l'edició.

## Correus a les persones inscrites

**Enviaments** (al menú, dins de *Participants*) serveix per escriure a tothom qui s'ha
inscrit: recordatoris abans de la cursa, canvis d'horari, l'avís que ja hi ha els
resultats…

1. **Nou enviament**. Poseu-hi el **tema** (el que es llegeix a la safata d'entrada) i
   escriviu el **cos** amb l'editor visual: negreta, cursiva, títols, llistes i enllaços.
   El botó **&lt;/&gt; HTML** deixa veure i tocar el codi per a qui s'hi vulgui posar.
2. **Trieu els destinataris**: totes les persones inscrites, només algunes categories, o
   unes adreces escrites a mà (útil per al voluntariat). També podeu decidir si hi entren
   només les inscripcions confirmades o també les pendents; les anul·lades no reben mai res.
3. **Reviseu-lo**: la **vista prèvia** mostra com quedarà amb les dades d'una família real,
   i **enviar-ne una prova** us el fa arribar a vosaltres.
4. **Preparar l'enviament** fixa la llista de destinataris. A partir d'aquí el correu ja no
   es pot editar, però encara no s'ha enviat res.
5. **Enviar ara**. Els correus surten per tandes, amb una barra de progrés. Si es talla
   (tanqueu el navegador, cau la connexió), torneu-hi i continueu des d'on era: **no es
   repeteix cap correu**.

**Una adreça, un correu.** Una família amb tres fills inscrits rep un sol missatge, amb els
tres noms. Al cos del correu hi podeu escriure marcadors que se substitueixen a cada enviament:

| Marcador | Què hi posa |
|---|---|
| `{{tutor}}` | El nom de la persona de contacte |
| `{{participants}}` | Els noms dels participants d'aquella família |
| `{{dorsals}}` | Els seus números de dorsal |
| `{{cursa}}` | El nom de la cursa |
| `{{data}}` | La data de la cursa |

Quan s'acaba, el botó **«Enviar-lo a les inscripcions noves»** busca qui s'ha inscrit
després i només escriu a aquestes adreces.

> **Abans del primer enviament de debò**, poseu **Configuració → Correu electrònic →
> Mètode d'enviament** en **«Assaig: no enviar res»** i feu-ne una prova sencera: tot
> funcionarà igual i quedarà al registre de correus, però no sortirà cap missatge del
> servidor. Recordeu tornar-ho a deixar com estava.

Si l'allotjament limita quants correus es poden enviar per hora, abaixeu **Correus per
tanda** a la mateixa pantalla de configuració (per defecte, 20).

## Reglament de la cursa

**Configuració → Reglament** conté el text del reglament, que es publica a `/reglament` i
s'enllaça des del peu de pàgina (**La cursa**). Hi trobareu un reglament ja redactat amb
dotze apartats —participació, inscripcions, dorsals, horaris, recorregut, premis,
acompanyants, salut, canvis i suspensió, dades i acceptació—: llegiu-lo i adapteu-lo a com
feu la cursa.

Amb **«Demanar que s'accepti en inscriure's»** activat, al formulari d'inscripció hi
apareix la casella **«Accepto el reglament de la cursa»** (el text es pot canviar), que
és obligatòria i que enllaça al reglament: qui hi faci clic l'obre en una pestanya nova
sense perdre el que ja ha escrit. L'acceptació queda desada amb la inscripció i la veieu a
la fitxa del participant i a l'exportació CSV.

Si desactiveu la casella, la pàgina del reglament continua publicada i enllaçada, però no
es demana acceptar-lo. El text admet els mateixos marcadors que els textos legals
(`{{entitat}}`, `{{cursa}}`, `{{poble}}`, `{{correu}}`, `{{telefon}}`, `{{web}}`).

> Les inscripcions fetes abans d'activar la casella queden marcades com a acceptades: es
> van fer quan encara no existia.

## Textos legals

**Configuració → SEO i legal** conté l'**avís legal** i la **política de privacitat** que
es publiquen a `/avis-legal` i `/privacitat`. Hi trobareu uns textos ja redactats a partir
del que fa aquest web: qui organitza la cursa, quines dades es demanen a la inscripció,
qui hi té accés, quant de temps es guarden i quins drets té la gent.

Dins d'aquests dos textos podeu escriure marcadors, que se substitueixen sols en
publicar-se:

| Marcador | Què hi posa |
|---|---|
| `{{entitat}}` | L'entitat responsable |
| `{{cursa}}` | El nom del web |
| `{{poble}}` | La població |
| `{{correu}}` | El correu de contacte, ja enllaçat |
| `{{telefon}}` | El telèfon de contacte |
| `{{web}}` | L'adreça del web |

Així, quan canvieu el correu o el telèfon a **Dades de la cursa**, els textos legals no es
queden antics.

> Els textos diuen que el web **no fa cap cobrament en línia**. Si algun dia activeu el
> pagament amb targeta a **Configuració → Pagaments (Stripe)**, reviseu-los: caldrà
> explicar-hi quines dades hi intervenen. I, com qualsevol text legal, val la pena que el
> llegeixi algú de l'entitat abans de publicar-lo.

## Consells

- Els camps de text amb HTML admeten `<p>`, `<strong>`, `<em>`, `<ul>`, `<li>`,
  `<a>`, `<h2>` i `<h3>`. Qualsevol codi perillós s'elimina automàticament.
- Totes les llistes es poden **reordenar arrossegant** les files.
- **Sistema → Registre** mostra qui ha fet cada canvi.
- **Sistema → Correus** mostra els correus enviats i permet fer una prova d'enviament.
