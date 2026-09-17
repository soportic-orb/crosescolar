# Manual del panell d'administració

Guia pràctica per a l'equip de l'AFA. Accés: `https://cros.afalagranada.cat/admin`

## Rols

| Rol | Pot fer |
|---|---|
| **Administrador** | Tot, incloent-hi credencials de Stripe, correu, usuaris i actualitzacions |
| **Editor** | Continguts, comandes, inscripcions i validació de tiquets |

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

### 5. Tiquets de l'esmorzar
**Tipus de tiquet**: nom, descripció, preu, existències i màxim per comanda.
Deixeu les existències en blanc per no limitar-les.
**Configuració → Esmorzar**: obrir o tancar la venda, data límit i textos.

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
