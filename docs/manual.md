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

### 1. Personalitzar la portada
**Configuració → Portada**
- *Imatge del banner*: fotografia horitzontal (1920×1080). Si no n'hi ha cap es mostra
  el fons il·lustrat de vinyes.
- *Etiqueta superior*, *títol* i *subtítol* del banner.
- Botons principals i compte enrere.
- Textos de cada secció: introducció, recorreguts, programa, galeria, ubicació i patrocinadors.

### 2. Recorreguts amb Wikiloc
**Recorreguts → Afegir**
- Enganxeu l'**URL de Wikiloc** de la ruta (per exemple
  `https://ca.wikiloc.com/rutes-senderisme/cros-la-granada-123456789`). El número
  s'extreu automàticament i el mapa apareix al web.
- Opcionalment pugeu el **fitxer GPX** perquè la gent se'l pugui descarregar.
- Indiqueu distància (en metres), desnivell i tipus de terreny.

> Els mapes de Wikiloc només es carreguen quan la persona hi fa clic: així la pàgina
> va més ràpida i no es comparteixen dades amb tercers sense consentiment.

### 3. Categories i premis
**Categories** — nom, anys de naixement, hora de sortida, distància i recorregut assignat.
**Premis** — targetes que es mostren a la pàgina de categories.

### 4. Patrocinadors
**Patrocinadors → Afegir**: nom, logotip (PNG o SVG amb fons transparent), enllaç i tipus
(institucional, principal, col·laborador). Arrossegueu les files per canviar-ne l'ordre.

### 5. Inscripcions: amb formulari o només informació

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

### 5.1. «Les meves inscripcions»

Les famílies poden revisar i corregir les dades que van posar sense haver d'escriure-us.
A **Les meves inscripcions** del web escriuen la seva adreça electrònica, reben un
**codi de sis xifres** i, un cop escrit, veuen tots els participants que han inscrit amb
aquella adreça. De cadascun poden canviar el nom, l'any de naixement (la categoria es
torna a calcular sola), el gènere, l'escola, el curs, la talla, el contacte, les
observacions i el consentiment d'imatge, i descarregar-ne el dorsal.

El codi val 15 minuts i només serveix un cop. **No** es poden canviar des del web
l'adreça de contacte, el número de dorsal ni l'estat de la inscripció: aquestes coses
les feu vosaltres des del panell.

Si preferiu que ningú no pugui tocar les seves dades, desactiveu
**Configuració → Inscripcions → «Les meves inscripcions» actiu** i la pàgina desapareix.

### 6. Dorsals dels participants

Cada inscripció rep automàticament un **número de dorsal** (001, 002, 003…) i les
famílies el poden descarregar en PDF des del correu de confirmació.

**Configuració → Dorsals** permet dissenyar-los:

1. Pugeu la **maqueta en PDF** (el disseny amb els logotips, fet amb Canva, Illustrator,
   Word…). Si el PDF té més d'una pàgina, indiqueu quina voleu.
2. Indiqueu on van el **número**, el **nom** i la **categoria**: posició X i Y en
   mil·límetres des de la cantonada superior esquerra, mida de lletra, color,
   alineació i negreta. Qualsevol dels tres camps es pot amagar.
3. Premeu **«Veure un dorsal de prova»** per comprovar com queda abans d'imprimir.

Per imprimir-los tots: **Inscripcions → Dorsals en PDF** (un dorsal per pàgina, i es pot
filtrar per categoria). Cada fitxa d'inscripció també té el seu botó de descàrrega.

Si canvieu un número a mà, useu **Inscripcions → editar → Número de dorsal**. El botó
**«Assignar dorsals»** dona número a les inscripcions que encara no en tinguin.

### 7. Tiquets de l'esmorzar
**Tipus de tiquet**: nom, descripció, preu, existències i màxim per comanda.
Deixeu les existències en blanc per no limitar-les.
**Configuració → Esmorzar** decideix què es veu al web:

- **Només informació** (opció per defecte): el web explica l'esmorzar i els preus, però
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
- **Configuració → Esmorzar**: tanqueu la venda de tiquets.
- **Sistema → Actualitzacions**: feu una còpia de seguretat per arxivar l'edició.

## Consells

- Els camps de text amb HTML admeten `<p>`, `<strong>`, `<em>`, `<ul>`, `<li>`,
  `<a>`, `<h2>` i `<h3>`. Qualsevol codi perillós s'elimina automàticament.
- Totes les llistes es poden **reordenar arrossegant** les files.
- **Sistema → Registre** mostra qui ha fet cada canvi.
- **Sistema → Correus** mostra els correus enviats i permet fer una prova d'enviament.
