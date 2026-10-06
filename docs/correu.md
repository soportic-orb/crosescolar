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
