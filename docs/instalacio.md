# Instal·lació a CloudPanel

Guia completa per posar en marxa el web del Cros Escolar La Granada en un VPS
compartit amb CloudPanel (nginx + PHP-FPM).

## 1. Crear el lloc

1. A CloudPanel: **Sites → Add Site → Create a PHP Site**.
2. Domini: `cros.afalagranada.cat` · Tipus: *Generic PHP* · Versió de PHP: **8.2 o superior**.
3. Anoteu l'usuari del lloc (per exemple `cros-afa`): els fitxers aniran a
   `/home/cros-afa/htdocs/cros.afalagranada.cat`.

## 2. Crear la base de dades

1. **Databases → Add Database**.
2. Nom: `cros`, usuari: `cros`, contrasenya: genereu-ne una de segura i deseu-la.
3. Cotejament recomanat: `utf8mb4_unicode_ci`.

## 3. Pujar els fitxers

Per SFTP o des del gestor de fitxers de CloudPanel, copieu **tot el contingut** del
projecte a l'arrel del lloc (no dins d'una subcarpeta):

```
/home/cros-afa/htdocs/cros.afalagranada.cat/
├── index.php
├── install.php
├── app/  assets/  uploads/  storage/  docs/  tools/
```

També podeu fer-ho per línia d'ordres:

```bash
cd /home/cros-afa/htdocs/cros.afalagranada.cat
unzip cros-escolar-1.0.0.zip
chown -R cros-afa:cros-afa .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 uploads storage
```

### Les carpetes de dades poden anar en una altra banda

Per defecte, els fitxers que es pugen (`uploads/`) i el que el sistema escriu
mentre funciona (`storage/`: registres, còpies de seguretat i temporals) van dins
la carpeta del codi, i amb una instal·lació normal no cal fer-hi res.

Si convé tenir-les fora —perquè diverses instal·lacions comparteixin la mateixa
còpia del codi, o per posar les dades en un disc a part—, s'indiquen amb dues
variables d'entorn:

```nginx
fastcgi_param CROS_UPLOADS /var/dades/elmeucros/uploads;
fastcgi_param CROS_STORAGE /var/dades/elmeucros/storage;
```

Les dues carpetes han de ser escrivibles pel PHP i **no** han de quedar
accessibles des del web (excepte `uploads/`, que s'ha de servir a `/uploads`).
Si no s'hi diu res, tot continua com sempre.

## 4. Configurar nginx

A **Sites → cros.afalagranada.cat → Vhost**, afegiu dins del bloc `server { … }`
els blocs de [`docs/nginx.conf`](nginx.conf). Els imprescindibles són:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ^~ /app/     { deny all; return 404; }
location ^~ /storage/ { deny all; return 404; }
client_max_body_size 32M;
```

Deseu i CloudPanel recarregarà nginx automàticament.

> Si no podeu tocar la configuració d'nginx, entreu al panell i desactiveu
> **Configuració → Dades de la cursa → URLs amigables**: el web funcionarà igualment
> amb adreces del tipus `/index.php/punt-de-recarrega`.

## 5. Executar l'instal·lador

1. Obriu `https://cros.afalagranada.cat/install.php`.
2. **Pas 1** — comprovació de requisits (PHP, extensions i permisos). Hi veureu també
   el temps màxim d'execució i la memòria del servidor.
3. **Pas 2** — dades de la base de dades creada al punt 2. Si la connexió falla,
   proveu `127.0.0.1` en comptes de `localhost` (o a l'inrevés) o indiqueu el camí
   del sòcol Unix al camp corresponent.
4. **Pas 3** — nom del web, data de la cursa i compte d'administració.
   Deixeu marcada l'opció de continguts d'exemple per començar amb una estructura
   completa que després podreu editar.

   En prémer **Instal·lar**, la feina es reparteix en sis passos curts
   (configuració → taules → compte → opcions → continguts → final), cadascun en una
   petició independent. Així la instal·lació no pot excedir el temps màxim d'execució
   del servidor i, si algun pas falla, veureu exactament quin.
5. **Pas 4** — esborreu `install.php`:

```bash
rm /home/cros-afa/htdocs/cros.afalagranada.cat/install.php
```

## 6. Certificat SSL

A **Sites → SSL/TLS → Let's Encrypt**, genereu el certificat per a
`cros.afalagranada.cat` (i `www` si l'utilitzeu). Un cop actiu, activeu la redirecció
a HTTPS des de CloudPanel.

## 7. Primers passos al panell

1. Entreu a `https://cros.afalagranada.cat/admin`.
2. **Configuració → Portada**: pugeu el banner (1920×1080 recomanat) i ajusteu els textos.
3. **Configuració → Aparença**: logotip, icona del navegador i colors.
4. **Patrocinadors**: pugeu els logotips (PNG o SVG amb fons transparent).
5. **Recorreguts**: enganxeu l'URL de cada ruta de Wikiloc i, si voleu, el fitxer GPX.
6. **Configuració → Pagaments**: credencials de Stripe ([guia](stripe.md)).
7. **Configuració → Correu**: remitent i, si cal, servidor SMTP.

## Plataforma multi-instància (diversos cros amb el mateix codi)

Amb el fitxer `tenants/platform.php` (copieu-hi `tenants/platform.php.example`), la
mateixa còpia del codi atén tots els subdominis d'un domini:

| Adreça | Què s'hi serveix |
| --- | --- |
| `granada.crosescolar.cat` | el web d'aquell cros |
| `crosescolar.cat` i `www.` | la pàgina pública de la plataforma |
| `admin.crosescolar.cat` | el panell de superadministració |
| un subdomini sense instal·lar | una pàgina que diu que l'adreça no existeix |

### Més d'un domini

La plataforma pot tenir-ne uns quants: `base_domain` és el principal i a
`domains` s'hi posen els altres.

```php
'base_domain' => 'crosescolar.cat',
'domains' => ['crosescolar.com'],
```

Tots els dominis serveixen tot, però cada web té una adreça bona i prou: el
client tria el domini quan demana el cros —o se li canvia després des de la seva
fitxa— i les adreces dels altres dominis hi menen amb una redirecció permanent.
El mateix passa amb la pàgina pública i el panell, que viuen al domini principal,
i amb el `www`. Els subdominis són d'un sol amo: `granada` no el poden tenir dos
clients, ni que fos cadascun en un domini, perquè qui s'equivoqui de terminació hi
arribi igualment.

Al servidor cal el registre comodí i el certificat de **cada** domini; vegeu
[`docs/nginx-plataforma.conf`](nginx-plataforma.conf).

Cada instància viu a `tenants/<subdomini>/` amb el seu `config.php`, la seva
carpeta `uploads/` i la seva `storage/`, i té la seva pròpia base de dades. El codi
mira la capçalera `Host`, en treu el subdomini i apunta les tres coses abans
d'arrencar; a partir d'aquí l'aplicació funciona com sempre.

Per aturar una instància temporalment, es crea un fitxer buit
`tenants/<subdomini>/suspended`: el web contesta que no està disponible i es torna
a engegar esborrant-lo.

Al servidor calen tres coses per a cada domini: un registre **A** per al domini i
un de **comodí** (`*.crosescolar.cat`), un certificat de **Let's Encrypt amb
comodí** (validació DNS-01, que és l'única que serveix per als comodins) i el bloc
d'nginx de [`docs/nginx-plataforma.conf`](nginx-plataforma.conf), que serveix els
fitxers pujats de cada instància des de la seva carpeta.

### Posar en marxa el panell de superadministració

El panell viu a `admin.crosescolar.com` i té els seus propis usuaris, que no
tenen res a veure amb els administradors de cada cros. Un cop copiat i omplert
`tenants/platform.php`:

```bash
php tools/platform.php migrar
php tools/platform.php usuari "Nom i cognoms" adreca@crosescolar.com
```

La segona ordre crea el primer superadministrador i escriu la contrasenya per
pantalla (si no se n'indica cap, se'n genera una). A partir d'aquí ja s'hi entra
per `https://admin.crosescolar.com`.

Per donar d'alta instàncies des del panell cal el bloc `provision` del fitxer de
configuració: hi va un usuari de MySQL que pugui **crear bases de dades i
usuaris**, perquè cada client té la seva. En crear una instància, el panell fa
tot el camí de cop: base de dades, usuari amb permisos només sobre aquella base,
carpeta a `tenants/`, instal·lació del cros i correu amb les claus a qui el
gestionarà. El web neix en mode «en preparació»: el publica el client quan ho
tingui a punt. Si alguna cosa falla pel camí, no queda res a mitges.

Altres ordres de la mateixa eina:

| Ordre | Què fa |
| --- | --- |
| `php tools/platform.php instancies` | llista les instàncies i el seu estat |
| `php tools/platform.php repassar` | actualitza inscrits, data i estat de publicació de totes |
| `php tools/platform.php actualitzar` | aplica els canvis pendents de la versió nova a totes |
| `php tools/platform.php vigilar` | mira que totes responguin i avisa dels canvis |
| `php tools/platform.php copies` | fa la còpia de seguretat de totes |
| `php tools/platform.php paquet <fitxer>` | diu què porta un paquet de migració |
| `php tools/platform.php purgar` | diu quines baixes ja han passat els 90 dies |
| `php tools/platform.php purgar --de-veritat` | les esborra de debò (base de dades i carpeta) |

Va bé posar `repassar` al cron un cop al dia, i `purgar --de-veritat` un cop a la
setmana:

```
*/15 * * * * cd /var/www/crosescolar && php tools/platform.php vigilar
0 3 * * *    cd /var/www/crosescolar && php tools/platform.php copies
30 4 * * *   cd /var/www/crosescolar && php tools/platform.php repassar
15 5 * * 1   cd /var/www/crosescolar && php tools/platform.php purgar --de-veritat
```

La vigilància mira, de cada cros en marxa, que la seva base de dades respongui i
que la pàgina s'obri. Quan un cau —o quan torna— surt un correu a l'adreça
d'avisos, **una sola vegada**: ni silenci, ni el mateix correu cada quart. Al
panell surt a dalt de tot amb el motiu i des de quina hora. Amb
`--sense-web` només es mira la base de dades (útil si el servidor no es pot
consultar a si mateix per fora).

### Entrar al panell d'un client

La plataforma no desa cap contrasenya de ningú. Quan s'estrena un cros, a qui el
gestionarà li arriba un **enllaç d'un sol ús** (val set dies) per entrar i triar-se
la contrasenya. Des de la fitxa de la instància es pot:

- **Enviar-los un enllaç per entrar**, si l'han perdut (val dues hores).
- **Entrar-hi per donar suport**, que obre el seu panell amb un enllaç que val vint
  minuts. Queda apuntat al registre del seu web, de manera que el client sempre pot
  veure que algú de la plataforma hi ha entrat i quan.

Tots els enllaços serveixen una sola vegada i al seu web només se'n desa l'empremta:
qui miri la base de dades no en treu cap enllaç que funcioni.

### Portar un cros que ja existia

Un cros que ja funciona pel seu compte —una instal·lació de tota la vida— es pot
passar a la plataforma sense perdre res i sense aturar-lo:

1. **Al web antic**, actualitzeu-lo a la versió 1.25 o posterior (Sistema →
   Actualitzacions) i aneu a **Sistema → Les meves dades**. Descarregueu el ZIP:
   aquest mateix fitxer és el paquet de migració.
2. **Al panell de la plataforma**, obriu **Instàncies → Nova instància**, poseu-hi
   el subdomini i el domini que tindrà, i pugeu el fitxer al camp **«Fitxer de
   migració»**. Si el paquet és massa gros per al navegador, deixeu-lo a
   `storage/imports/` del servidor i escriviu-ne només el nom al camp del costat.
3. Ja està. La instància no es crea buida: hi arriben les inscripcions, els
   resultats, els textos, la configuració i els fitxers pujats, i **qui entrava al
   panell hi continua entrant amb la mateixa contrasenya**. Els enllaços i les
   imatges que apuntaven a l'adreça antiga es canvien per la nova.

El web antic **no es toca**: continua funcionant mentre no el tanqueu, de manera
que es pot comprovar que tot ha arribat bé abans de moure el DNS. Si el paquet fa
mala cara, `php tools/platform.php paquet <fitxer.zip>` diu què porta a dins
(cros, adreça, versió, taules, registres i fitxers) sense tocar res.

Dues coses a tenir en compte:

- El paquet porta la taula de control de versions, de manera que el cros importat
  conserva per quina versió anava i, en entrar, se li apliquen només els canvis que
  li faltin. Un cros d'una versió antiga es posa al dia sol.
- Si el paquet supera el que admet el PHP del servidor, o bé es puja
  `upload_max_filesize` i `post_max_size` (i `client_max_body_size` a l'nginx), o
  bé es fa servir la via de `storage/imports/`, que no té cap límit.

### Còpies de seguretat

`php tools/platform.php copies` fa, de cada client, el mateix ZIP que ell es pot
descarregar del seu panell —fulls de càlcul, còpia de la base de dades i fitxers
pujats— i el desa a `storage/backups/<subdomini>/`. Cada client té la seva carpeta:
no es barreja mai res de dos cros diferents. Es guarden les set últimes de cadascun
(es canvia amb `backups.keep`) i, amb `backups.dir`, es poden desar en un altre disc
o en un volum muntat.

A la fitxa de cada instància surten les còpies que hi ha, amb data i mida, se'n pot
fer una a l'instant i descarregar-ne qualsevol. Si alguna instància fa més de tres
dies que no es copia, el tauler ho avisa: normalment vol dir que el cron ha deixat
de funcionar.

Les instàncies donades de baixa també es copien mentre es guardin les dades (els 90
dies), que és justament quan és més probable que algú les demani.

### Actualitzar totes les instàncies

Com que el codi és un de sol, en actualitzar-lo ja el tenen tots els clients; el que
va per instància és la seva base de dades. Al panell, a **Instàncies**, surt un avís
amb quantes n'hi ha que encara no tenen l'última versió i un botó per posar-les totes
al dia d'un cop (o una per una, des de la seva fitxa). Fa el mateix que
`php tools/platform.php actualitzar` i no toca cap dada dels clients: només hi aplica
els canvis d'estructura que els falten.

## Resolució de problemes

| Símptoma | Solució |
|---|---|
| Error 404 a totes les pàgines menys la portada | Falta el bloc `try_files` d'nginx, o desactiveu les URLs amigables |
| «No s'ha pogut escriure app/config.php» | `chmod 775 app` i torneu a executar l'instal·lador |
| Les imatges pujades no es veuen | `chmod -R 775 uploads` i comproveu el propietari del lloc |
| No arriben els correus | Configureu SMTP a **Configuració → Correu** i proveu-ho a **Sistema → Correus** |
| Pàgina en blanc | Reviseu `storage/logs/app-AAAA-MM.log` |
| **L'instal·lador es queda aturat en prémer «Instal·lar»** | Vegeu l'apartat següent |

### Si l'instal·lador es queda aturat

Cada pas de la instal·lació queda registrat a `storage/logs/install-AAAA-MM-DD.log`
amb l'hora i la durada. Obriu aquest fitxer: l'última línia indica en quin pas s'ha
quedat.

```
[10:42:01] Inici de la fase «schema»
[10:42:01] Fi de la fase «schema» {"ms":62}
[10:42:02] Inici de la fase «admin»      ← s'ha quedat aquí
```

Causes habituals i solució:

| Causa | Com es detecta | Solució |
|---|---|---|
| Doble clic al botó «Instal·lar» | La segona petició queda bloquejada esperant la sessió | El botó ara es desactiva automàticament; refresqueu i torneu-hi |
| Temps màxim d'execució baix (`max_execution_time`) | El registre s'atura enmig d'una fase | Ja no hauria de passar: cada fase és una petició curta. Si passa, pugeu `max_execution_time` a 120 a CloudPanel (PHP → Settings) |
| `fastcgi_read_timeout` d'nginx massa baix | Error 504 al navegador | Afegiu `fastcgi_read_timeout 120;` al vhost |
| Base de dades que no respon | La fase `config` triga 10 s i dona error de connexió | Comproveu l'amfitrió: `localhost` (sòcol) o `127.0.0.1` (TCP) |
| Falten permisos a `app/` o `storage/` | Error explícit a la pantalla | `chmod 775 app storage` i repetiu |

La instal·lació es pot **repetir tantes vegades com calgui**: cap pas duplica dades.
Si voleu començar del tot de nou, esborreu `app/config.php` i
`storage/installed.lock`, i buideu la base de dades.

## Còpies de seguretat

Des de **Sistema → Actualitzacions → Crear còpia ara** es genera un ZIP amb tots els
fitxers i un bolcat SQL complet (`database.sql`). Es conserven les 5 còpies més recents
a `storage/backups/` i es poden descarregar des del panell.
