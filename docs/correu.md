# Correu: un servidor per domini, i el SPF, el DKIM i el DMARC

Cada domini de la plataforma envia pel seu servidor de correu:

| Qui envia | Per on surt | Amb quina adreça |
|---|---|---|
| La pàgina pública i el panell de `crosescolar.cat` | el servidor de `crosescolar.cat` | la de `crosescolar.cat` |
| Els webs de les curses `*.crosescolar.cat` | el servidor de `crosescolar.cat` | la de `crosescolar.cat`, amb el nom de cada web |
| La pàgina pública i el panell d'`esportweb.cat` | el servidor d'`esportweb.cat` | la d'`esportweb.cat` |
| Els webs de les curses `*.esportweb.cat` | el servidor d'`esportweb.cat` | la d'`esportweb.cat`, amb el nom de cada web |

Els correus d'un web de cursa surten, per exemple, com a
`Cros Escola Sant Jordi <no-reply@crosescolar.cat>`, i les respostes van a qui
l'organitza. Els subdominis de les curses **no** envien amb adreça pròpia: per
això n'hi ha prou de preparar els dos dominis, i no cal tocar res per a cada web
nou.

## 1. Configurar els dos servidors al panell

**Panell de Superadministració → Configuració → Correu.** A dalt hi ha una
pestanya per domini. Per a cadascun:

- **Adreça de qui envia**: una bústia del mateix domini, per exemple
  `no-reply@crosescolar.cat`. Ha de ser una adreça que el servidor SMTP us deixi
  fer servir (normalment, la mateixa del compte).
- **Com s'envia**: *Servidor SMTP*.
- **Servidor, port, usuari, contrasenya i xifratge**: els que us doni el
  proveïdor de correu d'aquell domini (587 amb TLS, o 465 amb SSL).
- **On arriben els avisos**: on voleu rebre les sol·licituds i els missatges
  de contacte d'aquell domini.
- Deseu amb **«Desar i enviar-me una prova»**: envia una prova a l'adreça
  d'avisos d'aquell domini. Comproveu que arriba.

Mentre un domini no té res escrit, fa servir el que hi havia per a tota la
plataforma. Així, en actualitzar, tot continua sortint com abans fins que
configureu el segon servidor.

## 2. Els tres registres DNS de cada domini

Es fan a la zona DNS de cada domini (Nominalia, Cloudflare o on la tingueu).
Tots tres són registres **TXT** (el DKIM, de vegades, és **CNAME**).

### SPF: quins servidors poden enviar en nom del domini

Un sol registre TXT a l'arrel del domini (`@`):

```
crosescolar.cat.   TXT   "v=spf1 include:<el-del-vostre-proveïdor> ~all"
```

El `include:` us el dona el proveïdor. Alguns exemples habituals:

| Proveïdor | Què hi va |
|---|---|
| Google Workspace | `include:_spf.google.com` |
| Microsoft 365 | `include:spf.protection.outlook.com` |
| Brevo | `include:spf.brevo.com` |
| Mailgun | `include:mailgun.org` |
| Amazon SES (eu-west-1) | `include:amazonses.com` |
| Nominalia (correu del domini) | el que surti al seu panell de correu |

Regles importants:

- **Només un registre SPF per domini.** Si ja n'hi ha un (per exemple, perquè
  el domini ja rep correu amb Google), no en creeu un altre: afegiu-hi el nou
  `include:` dins del mateix, abans del `~all`.
- Comenceu amb `~all` (marca com a sospitós el que no surti dels servidors
  autoritzats). Quan tot vagi bé unes setmanes, podeu passar a `-all`.
- Un SPF no pot fer més de 10 consultes DNS: no hi acumuleu `include:` que no
  feu servir.

Feu el mateix a `esportweb.cat` amb **el seu** proveïdor, que pot ser el
mateix o un altre.

### DKIM: la signatura dels correus

El genera el proveïdor de correu, no vosaltres. Al seu panell busqueu «DKIM»,
«Autenticació del domini» o «Domain authentication», indiqueu el domini, i us
donarà un o dos registres per copiar. Tenen aquesta forma:

```
<selector>._domainkey.crosescolar.cat.   TXT    "v=DKIM1; k=rsa; p=MIIBIjANBgkq..."
```

o bé, en serveis com Brevo, Mailgun o SES:

```
<selector>._domainkey.crosescolar.cat.   CNAME  <selector>.dkim.<proveïdor>.
```

- Copieu el nom i el valor **exactament** com els dona el proveïdor. Molts
  panells DNS hi afegeixen el domini sols: si el proveïdor diu
  `s1._domainkey`, escriviu només això al camp del nom.
- Una clau de 2048 bits és llarga. Si el panell la parteix en trossos, és
  normal; no hi afegiu espais.
- Després, **activeu el DKIM al panell del proveïdor** (molts hi tenen un botó
  «Verificar» o «Activar» que només funciona quan ja veuen el registre).
- A Cloudflare, els CNAME del DKIM han d'anar **sense proxy** (núvol gris,
  «DNS only»).

Cada domini té el seu DKIM: el de `crosescolar.cat` no serveix per a
`esportweb.cat`.

### DMARC: què s'ha de fer amb el que no quadra

Un registre TXT a `_dmarc`:

```
_dmarc.crosescolar.cat.   TXT   "v=DMARC1; p=none; rua=mailto:dmarc@crosescolar.cat; adkim=r; aspf=r"
```

- Comenceu amb `p=none`: no bloqueja res, però us arriben informes diaris a
  l'adreça de `rua` de qui envia en nom del domini. Feu servir una bústia que
  existeixi.
- Quan als informes només hi surtin els vostres servidors i tot passi (unes
  dues o tres setmanes), canvieu a `p=quarantine` i, més endavant, a
  `p=reject`.
- Gmail i Yahoo demanen DMARC a qui envia molt correu; amb `p=none` ja n'hi ha
  prou per complir.

## 3. Comprovar que funciona

Els canvis de DNS poden trigar de minuts a unes hores.

1. **Des d'un terminal:**
   ```bash
   dig +short TXT crosescolar.cat                    # hi ha d'haver un sol "v=spf1 ..."
   dig +short TXT <selector>._domainkey.crosescolar.cat   # o CNAME
   dig +short TXT _dmarc.crosescolar.cat
   ```
   I el mateix per a `esportweb.cat`.
2. **Amb un correu de veritat:** des del panell d'un web de cursa d'aquell
   domini, a **Correus → Enviar correu de prova**, envieu-ne una a una bústia de
   Gmail. Obriu-lo i, al menú dels tres punts, **«Mostra l'original»**: hi ha de dir `SPF: PASS`, `DKIM: PASS` i
   `DMARC: PASS`, i el domini de la signatura ha de ser el mateix que el de
   l'adreça de qui envia.
3. **Amb una nota:** [mail-tester.com](https://www.mail-tester.com) us dona una
   adreça; envieu-hi una prova des d'un web de cursa (al seu panell, Correus →
   Enviar correu de prova) i us en diu la puntuació i què falla.

Feu les tres comprovacions per a cada domini, i una prova des d'un web de cursa
de cada domini.

## 4. Si un correu no arriba

- A la fitxa de la instància, al Panell de Superadministració, hi ha els últims
  correus que se li han enviat i si han sortit o no, amb la resposta del
  servidor quan n'hi ha cap que falla.
- Al panell del web de la cursa, **Correus** en mostra el registre.
- Al servidor, `storage/logs/app-AAAA-MM.log` (i el de cada web, a
  `tenants/<web>/storage/logs/`) hi apunta l'adreça de qui envia i l'error.
- Si diu que ha sortit i no arriba: correu brossa, i reviseu el SPF, el DKIM i
  el DMARC d'aquell domini amb el punt 3.

## 5. Si no arriben a Outlook, Hotmail o Microsoft 365

Microsoft és el més estricte. Un correu que Gmail accepta, Microsoft el pot
rebutjar o enviar al correu brossa. Per saber per què, cal el **missatge de
devolució** (el «no s'ha pogut lliurar»), que Microsoft envia a l'adreça de qui
envia, la que hi ha a Configuració → Correu.

1. **Que l'adreça de qui envia sigui una bústia que existeixi** i que algú
   llegeixi. Si és `no-reply@…` i no existeix, les devolucions es perden i no
   hi ha manera de saber què passa.
2. **Activeu el DKIM** del domini. A Nominalia: Àrea de client → el domini →
   EMAIL → ACCIONES → DKIM → On. Microsoft hi dona molt de pes. Nominalia hi
   posa dos CNAME, `key-nomi001._domainkey` i `key-nomi002._domainkey`, que
   apunten a `securemail.pro`. El que signa és el primer; el segon pot no
   tenir encara cap clau al darrere (és el de recanvi). Per comprovar-ho:
   `dig +short TXT key-nomi001._domainkey.crosescolar.cat` ha de tornar un
   `v=DKIM1; …`. Que el registre hi sigui no vol dir que se signi: només se
   signa el que surt pel servidor SMTP de Nominalia amb una bústia del domini,
   i ho confirma la capçalera del correu rebut (punt 3).
3. **Envieu una prova** des del panell d'un web de cursa a una bústia
   d'Outlook o Hotmail i mireu què torna. Els codis més habituals:

| Codi a la devolució | Què vol dir | Què cal fer |
|---|---|---|
| `550 5.7.1 … (S3150)` | L'adreça IP del servidor de correu és a la llista de bloqueig de Microsoft. Passa sovint amb els servidors compartits dels allotjaments. | Passeu la devolució al suport del proveïdor de correu (Nominalia) perquè en demani la retirada a Microsoft, o feu servir un servei d'enviament que no hi sigui. |
| `550 5.7.512` | El remitent (`From`) no compleix la norma. | Arreglat a la 1.45.0: ara els noms amb comes o parèntesis van entre cometes. |
| `550 5.7.515` | Microsoft demana SPF, DKIM i DMARC correctes. | Reviseu els tres registres (punt 2 i punt 3). |
| `550 5.7.606`–`5.7.649` | L'adreça IP està bloquejada a Microsoft 365. | Demaneu-ne la retirada a [sender.office.com](https://sender.office.com) amb l'adreça IP que surt a la devolució. |
| No torna res, però no arriba | Ha anat al correu brossa o a la quarantena de l'organització. | Que la persona miri «Correu brossa». A Microsoft 365, l'administrador de la seva organització ho pot veure a la quarantena. |

Si el correu arriba però va al correu brossa, que la persona el marqui com a
«No és correu brossa». També ajuda que el DKIM i el DMARC passin i que
l'adreça de qui envia sigui sempre la mateixa.

## 6. Els enviaments del panell (butlletins i comunicats)

**Panell de Superadministració → Enviaments.** Són els correus que la
plataforma escriu als seus clients: un comunicat, les novetats del mes, un
recordatori abans de la temporada. No tenen res a veure amb els correus de
servei (la benvinguda, els enllaços d'accés, el suport), que surten sols.

### Com es fa un correu

1. **Nou enviament**: l'assumpte, el domini des d'on surt i una plantilla base
   (o en blanc). El correu en fa una còpia: si després es canvia la plantilla,
   el correu no canvia.
2. **L'editor**: els blocs de l'esquerra s'arrosseguen al correu, el text
   s'escriu a sobre i les opcions del bloc triat (colors, imatges, enllaços,
   espais, què es veu al mòbil) són a la dreta. **Ctrl+S** desa i **Ctrl+Z**
   desfà. Cada «Desa» guarda una versió, que es pot mirar i recuperar des de
   **⋯ → Historial de versions**.
3. Els colors que es deixen «del domini» i el logotip són els de la pàgina
   pública del domini que envia: el mateix correu surt amb els colors de
   `crosescolar.cat` o amb els d'`esportweb.cat`.
4. **Camps personals**: `{{nom}}`, `{{entitat}}`, `{{correu}}`, `{{web}}`,
   `{{plataforma}}` i `{{any}}` es poden posar al text, a l'assumpte i als
   enllaços (botó **{ } Camps**). Cada persona el rep amb les seves dades.
5. **Previsualitza** ensenya el correu a l'ordinador i al mòbil, i sense
   imatges. **⋯ → Envia'm una prova** l'envia a la vostra adreça, pel servidor
   del domini triat.

Hi ha dos blocs de la plataforma: **Cursa**, que copia un web del directori
(nom, data, població, imatge de portada i enllaç), i **Novetat**, per explicar
una funcionalitat nova.

### Enviar-lo o programar-lo

**Envia o programa…** demana tres coses:

- **A qui va**: les administradores dels webs, el contacte dels clients, una
  llista de correu o adreces escrites a mà.
- **Des de quin domini**: el servidor de correu, el remitent i els colors. Per
  defecte, les administradores i els clients són **només els del domini triat**;
  traient la marca, va als de tots els dominis. Les llistes i les adreces
  escrites a mà no es filtren mai pel domini.
- **Quan**: ara, o un dia i una hora.

Abans de confirmar diu quanta gent el rebrà. Un cop comença a sortir, el correu
ja no es pot canviar (es pot duplicar per fer-ne un altre).

### Qui l'envia de debò: el cron

Les tandes les envia la feina `enviaments`, que ha de passar **cada cinc
minuts** (vegeu la secció de feines automàtiques de `docs/vps.md`):

```
*/5 * * * *  cd /var/www/crosescolar && php tools/platform.php enviaments
```

No cal tenir el panell obert. Si hi ha correus programats o a mig enviar i la
feina fa més de vint minuts que no passa, el tauler i la llista d'enviaments
avisen. Mentrestant, des del seguiment de cada enviament es poden enviar tandes
a mà.

- **Correus per hora**: a **Configuració → Correu**, cada domini pot tenir un
  límit (el que admeti el seu proveïdor: Mailjet, Nominalia…). En arribar-hi,
  la resta s'esperen a l'hora següent. Amb 0 no n'hi ha.
- **Pausa automàtica**: si en una tanda no en surt cap (el servidor de correu
  no va o rebutja la clau), l'enviament es posa en pausa amb l'error. Un cop
  arreglat, **Reprèn**. Els que han fallat es poden tornar a provar.
- **Estats**: esborrany, programat, s'està enviant, en pausa, enviat i aturat.
  Els acabats es poden arxivar perquè no facin nosa a la llista.

### La baixa

Cada correu porta, a sota del peu, l'enllaç per donar-se de baixa, i també la
capçalera `List-Unsubscribe` amb la baixa d'un sol clic que demanen Gmail i
Yahoo als enviaments grans. Obrir l'enllaç no dona de baixa ningú: demana
confirmació, perquè els filtres de Microsoft obren tots els enllaços d'un correu
per mirar si són de fiar. Qui es dona de baixa no rep cap més enviament del
panell, sigui de la llista que sigui; els correus de servei li continuen
arribant.

### La IA de l'editor

**Configuració → Intel·ligència artificial**: la clau d'Anthropic (per escriure
textos amb Claude) i la d'OpenAI (textos i imatges; Claude no fa imatges). Es
desen xifrades. **Desar i provar la IA** fa una crida curta a cada servei per
comprovar que la clau funciona.

A l'editor, **✨ IA** escriu un text, un títol, un botó o l'assumpte i el resum,
o crea una imatge (també a partir d'imatges de referència). El resultat es pot
afegir com a bloc nou o substituir el del bloc triat, i sempre s'ha de revisar
abans d'enviar el correu. El que s'hi escriu va al servei d'IA: no hi poseu
dades personals de ningú.
