# Muntar la plataforma en un VPS nou

Guia completa per deixar EsportWeb en marxa a `esportweb.cat` (i
`crosescolar.cat`) en un VPS acabat de crear, i per portar-hi el cros de La
Granada que ara funciona pel seu compte.

La plataforma es diu **EsportWeb** i serveix qualsevol cursa o activitat
esportiva. Cada domini que se li posa té la **seva pàgina pública**, amb el seu
text i la seva imatge: `esportweb.cat` parla d'esport en general i
`crosescolar.cat`, de cros escolars. Tota la resta —els clients, la facturació,
el correu i el panell de cada client— és la mateixa per a tots, i es porta des
d'un sol panell de superadministració, a `admin.esportweb.cat`.

Parteix d'un **Ubuntu 24.04 net** amb accés `root` per SSH, com el que dona
Clouding.io. Si el VPS ja porta un tauler (CloudPanel, Plesk…), useu la guia
[`instalacio.md`](instalacio.md) i salteu-vos els apartats 1 i 2 d'aquí.

Al llarg de la guia, canvieu `esportweb.cat` i `crosescolar.cat` pels vostres
dominis si són uns altres. El **primer** de la llista és el principal: és on
viu el panell de superadministració.

---

## El camí curt: instal·lació guiada

Hi ha dues maneres de fer-ho. Aquesta és la de sempre, i és la que va bé si no
voleu pensar en res:

```bash
# 1. Al vostre ordinador: pugeu el paquet de la versió al servidor
scp cros-escolar-1.25.1.zip root@LA-IP-DEL-VPS:/tmp/

# 2. Al servidor
ssh root@LA-IP-DEL-VPS
mkdir -p /opt/cros && unzip -q /tmp/cros-escolar-1.25.1.zip -d /opt/cros
cd /opt/cros && sudo bash tools/instalar-vps.sh
```

L'script pregunta els dominis i el correu, i després ho fa tot sol: instal·la
nginx, MariaDB i PHP, crea les bases de dades i els seus usuaris amb
contrasenyes generades, copia el codi a `/var/www/crosescolar` amb els permisos
que toquen, escriu la configuració de l'nginx amb els vostres dominis, mira que
el DNS apunti aquí i deixa el cron parat.

Al pas del certificat us deixa triar:

| Opció | Què fa | Renovació |
|---|---|---|
| **Per a cada nom** (recomanada) | `certbot --nginx` amb el domini i `admin.`: es valida sol, sense tocar el DNS, i configura l'nginx ell mateix | Automàtica |
| **Comodí** (`*.domini`) | Cobreix tots els cros, presents i futurs, però s'ha de validar posant dos registres TXT al DNS | **Manual**, cada 60-90 dies |
| **Ara no** | Deixa el web per HTTP | — |

Amb la primera, cada cop que doneu d'alta un cros nou heu de tornar a executar
l'ordre afegint-hi el seu subdomini. Amb la segona no cal tocar res mai més,
però us tocarà repetir els registres TXT a cada renovació (si el vostre
proveïdor de DNS té connector per a certbot, aleshores el comodí també es
renova sol: val molt la pena mirar-ho).

> Si trieu «ara no», el web quedarà **sense xifrar**: les contrasenyes del
> panell i les dades de les inscripcions viatjarien a la vista. L'script us ho
> recorda al final i us dona l'ordre exacta per posar-hi el certificat quan
> vulgueu, sense haver de tornar a executar l'instal·lador.

En acabar us dona una adreça com aquesta:

```
https://crosescolar.cat/install-plataforma.php?clau=…
```

Obriu-la i **l'assistent web acaba la feina**: comprova el servidor, us deixa
repassar els dominis, prova les dues connexions a la base de dades, configura el
correu (amb un botó per enviar-vos una prova), crea el vostre compte de
superadministració, engega la plataforma i us diu què queda per fer. En tancar-lo
esborra la clau i les dades que havia deixat l'script.

Després, esborreu l'assistent del servidor:

```bash
rm /var/www/crosescolar/install-plataforma.php
```

Opcions de l'script, per si en necessiteu alguna:

| Opció | Per a què |
| --- | --- |
| `--dominis a.cat,b.com` | no preguntar els dominis |
| `--correu adreça` | adreça per als avisos del certificat |
| `--arrel /camí` | posar el codi en un altre lloc |
| `--sense-paquets` | no tocar res amb apt (si ja ho teniu instal·lat) |
| `--sense-certificat` | deixar el certificat per a més tard |
| `--assaig` | dir què faria, sense tocar res |

L'`--assaig` va bé per veure el pla abans de deixar-lo actuar.

> **Per què dues peces i no una?** Un assistent web l'ha de servir un servidor
> web, i en un VPS acabat de fer encara no n'hi ha cap. Per això la part de
> sistema —que demana ser root— la fa l'script al terminal, i l'assistent web
> només fa la part d'aplicació, que no necessita cap permís especial. Així no
> queda cap porta oberta al servidor quan s'ha acabat d'instal·lar.

---

## El camí llarg: pas a pas a mà

La resta de la guia explica el mateix, ordre per ordre, per si voleu fer-ho
vosaltres o entendre què fa l'script.

## 0. Abans de començar

Al DNS de **cada** domini, apuntant a la IP del VPS:

| Tipus | Nom | Valor |
| --- | --- | --- |
| A | `@` | la IP del VPS |
| A | `*` | la IP del VPS |

El registre comodí (`*`) és el que fa que `elquesigui.crosescolar.cat` arribi al
servidor sense haver de tocar el DNS cada cop que es dona d'alta un client.

També necessitareu, per al certificat, poder **crear registres TXT** al DNS dels
dos dominis (a mà o amb l'API del proveïdor).

## 1. Preparar el servidor

```bash
apt update && apt upgrade -y
apt install -y nginx mariadb-server unzip git certbot \
    php-fpm php-mysql php-mbstring php-curl php-gd php-zip php-xml
php -v                 # ha de dir 8.2 o superior
systemctl enable --now nginx mariadb php*-fpm
```

Anoteu com es diu el sòcol del PHP, que farà falta a l'nginx:

```bash
ls /run/php/          # p. ex. php8.3-fpm.sock
```

**Els límits de pujada.** PHP ve de sèrie amb 2 MB per fitxer i 8 MB per
petició, que és menys que una foto feta amb el mòbil. I el que passa quan es
passen no és un error clar: PHP llença el cos de la petició sencer, el
formulari arriba buit i el navegador ensenya un «la sessió ha caducat» que no
hi té res a veure. Val més apujar-los d'entrada (canvieu `8.3` per la vostra
versió):

```bash
printf 'upload_max_filesize = 32M\npost_max_size = 40M\nmemory_limit = 256M\nmax_execution_time = 120\n' \
  > /etc/php/8.3/fpm/conf.d/99-cros.ini
cp /etc/php/8.3/fpm/conf.d/99-cros.ini /etc/php/8.3/cli/conf.d/99-cros.ini
systemctl reload php8.3-fpm
php -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size"), PHP_EOL;'
```

L'instal·lador guiat ja ho deixa fet; això és per als servidors muntats a mà o
anteriors a la versió 1.31.1.

Un tallafoc mínim:

```bash
apt install -y ufw
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
```

## 2. Bases de dades i usuaris

```bash
mariadb-secure-installation      # poseu contrasenya a root i digueu que sí a tot
```

Ara es creen **dos** usuaris amb feines ben diferents:

- `cros_platform`, que només toca la base de dades de la plataforma (clients,
  instàncies i sol·licituds).
- `cros_admin`, que pot **crear** bases de dades i usuaris: és el que fa servir
  el panell en donar d'alta una instància. No el fa servir res més.

```bash
mariadb -u root -p
```

```sql
CREATE DATABASE cros_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'cros_platform'@'localhost' IDENTIFIED BY 'UNA-CLAU-LLARGA-1';
GRANT ALL PRIVILEGES ON cros_platform.* TO 'cros_platform'@'localhost';

CREATE USER 'cros_admin'@'localhost' IDENTIFIED BY 'UNA-CLAU-LLARGA-2';
GRANT ALL PRIVILEGES ON *.* TO 'cros_admin'@'localhost' WITH GRANT OPTION;

FLUSH PRIVILEGES;
EXIT;
```

Deseu les dues contrasenyes: van al fitxer de configuració del pas següent.

## 3. Posar-hi el codi

Descarregueu el paquet de la versió publicada (**Releases** del repositori,
fitxer `cros-escolar-X.Y.Z.zip`) i pugeu-lo al servidor:

```bash
scp cros-escolar-1.25.0.zip root@LA-IP-DEL-VPS:/tmp/
```

Al servidor:

```bash
mkdir -p /var/www/crosescolar
unzip -q /tmp/cros-escolar-1.25.0.zip -d /var/www/crosescolar
cd /var/www/crosescolar
mkdir -p storage/logs storage/backups storage/imports uploads tenants
chown -R www-data:www-data /var/www/crosescolar
find /var/www/crosescolar -type d -exec chmod 755 {} \;
find /var/www/crosescolar -type f -exec chmod 644 {} \;
chmod -R 775 storage uploads tenants
```

Comproveu que el paquet és el que toca abans de descomprimir-lo:

```bash
sha256sum cros-escolar-1.25.0.zip     # ha de coincidir amb el de la pàgina de la versió
```

## 4. Configurar la plataforma

```bash
cp tenants/platform.php.example tenants/platform.php
nano tenants/platform.php
```

Els valors que cal tocar:

```php
return [
    'base_domain' => 'esportweb.cat',
    'domains' => ['crosescolar.cat'],
    'console' => ['admin'],
    'reserved' => [],

    'db' => [
        'host' => 'localhost', 'port' => 3306,
        'name' => 'cros_platform',
        'user' => 'cros_platform',
        'pass' => 'UNA-CLAU-LLARGA-1',
        'charset' => 'utf8mb4', 'socket' => '',
    ],

    'provision' => [
        'db_host' => 'localhost', 'db_port' => 3306, 'db_socket' => '',
        'admin_user' => 'cros_admin',
        'admin_pass' => 'UNA-CLAU-LLARGA-2',
        'db_prefix' => 'cros_',
        'tenant_from' => 'localhost',
        'tenant_host' => 'localhost',
    ],

    'monitor' => ['web' => true, 'proxy' => ''],
    'backups' => ['dir' => '', 'keep' => 7],

    'mail' => [
        'from_name' => 'Cros Escolar',
        'from_email' => 'no-reply@crosescolar.cat',
        'notify' => 'el-vostre-correu@exemple.cat',
        'transport' => 'smtp',
        'smtp_host' => 'smtp.elvostreproveidor.cat',
        'smtp_port' => 587,
        'smtp_user' => 'no-reply@crosescolar.cat',
        'smtp_pass' => 'LA-CLAU-DEL-CORREU',
        'smtp_secure' => 'tls',
    ],
];
```

El fitxer porta contrasenyes: que només el pugui llegir el servidor web.

```bash
chown www-data:www-data tenants/platform.php
chmod 640 tenants/platform.php
```

> Mentre no existeixi aquest fitxer, el codi funciona com una instal·lació de
> tota la vida (un sol web). És el que l'engega tot.

> **El correu és un de sol per a tota la plataforma.** El que hi poseu aquí (o a
> Configuració → Correu del panell de superadministració, que mana sobre el
> fitxer) és el que fan servir la plataforma i tots els webs de les curses: un
> web nou neix amb «El servidor de correu de la plataforma» com a mètode
> d'enviament. Els seus correus surten amb el nom del web i l'adreça de la
> plataforma, i les respostes van a qui l'organitza. Per això cal que el domini
> de `from_email` tingui ben posats el SPF i el DKIM del vostre proveïdor.
>
> Si un correu no arriba, a la fitxa de la instància, al panell, hi ha els
> últims que s'hi han enviat i si han sortit o no, i el botó per tornar a enviar
> la benvinguda. Els errors també queden a `storage/logs/app-AAAA-MM.log`.

## 5. nginx

```bash
cp /var/www/crosescolar/docs/nginx-plataforma.conf /etc/nginx/sites-available/crosescolar
ln -s /etc/nginx/sites-available/crosescolar /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nano /etc/nginx/sites-available/crosescolar
```

Reviseu-hi tres coses: el `server_name` amb els vostres dominis, el `root`
(`/var/www/crosescolar`) i la línia del **sòcol de PHP**, que ha de coincidir amb
el que heu vist al pas 1 (`fastcgi_pass unix:/run/php/php8.3-fpm.sock;`).

El bloc `map` va al context `http{}`, no dins del `server{}`:

```bash
cat > /etc/nginx/conf.d/cros-map.conf <<'EOF'
map $host $cros_slug {
    default                                                   "";
    "~^(?<sub>[a-z0-9][a-z0-9-]*)\.crosescolar\.(cat|com)$"   $sub;
}

# On són els fitxers pujats de cada amfitrió. La plataforma (el domini pelat,
# «www» i el panell) té els seus, que no pengen de cap client.
map $host $cros_uploads {
    default                                                   "/var/www/crosescolar/uploads";
    "~^www\.crosescolar\.(cat|com)$"                          "/var/www/crosescolar/uploads";
    "~^admin\.crosescolar\.(cat|com)$"                        "/var/www/crosescolar/uploads";
    "~^(?<pujades>[a-z0-9][a-z0-9-]*)\.crosescolar\.(cat|com)$" "/var/www/crosescolar/tenants/$pujades/uploads";
}
EOF
```

(traieu els mateixos `map` del fitxer del lloc, que hi són només com a
recordatori)

Al `server{}`, el bloc de les pujades ha de fer servir aquesta variable:

```nginx
location ^~ /uploads/ {
    alias $cros_uploads/;
    ...
}
```

Sense això, el logotip i la imatge de la portada **de la plataforma** donen 404:
l'nginx els aniria a buscar a la carpeta d'un client que no existeix.

Per poder demanar el certificat, arrenqueu primer **sense** les línies `ssl_` i
amb `listen 80;`:

```bash
nginx -t && systemctl reload nginx
```

## 6. Certificat comodí

Un sol certificat amb els quatre noms. Amb validació DNS-01, que és l'única que
serveix per als comodins:

```bash
certbot certonly --manual --preferred-challenges dns \
  -d crosescolar.cat -d '*.crosescolar.cat' \
  -d crosescolar.com -d '*.crosescolar.com' \
  --agree-tos -m el-vostre-correu@exemple.cat
```

Certbot us demanarà crear un registre TXT `_acme-challenge` a cada domini.
Espereu un parell de minuts entre posar-lo i prémer Enter.

Si el vostre proveïdor de DNS té connector (`certbot-dns-cloudflare`,
`certbot-dns-ovh`…), val més fer-ho amb ell: la renovació serà automàtica. Amb
`--manual` caldrà repetir-ho cada tres mesos.

Ara descomenteu al vhost les línies `listen 443 ssl;` i les `ssl_certificate*`
apuntant a `/etc/letsencrypt/live/crosescolar.cat/`, i recarregueu:

```bash
nginx -t && systemctl reload nginx
```

## 7. Engegar la plataforma

```bash
cd /var/www/crosescolar
sudo -u www-data php tools/platform.php migrar
sudo -u www-data php tools/platform.php usuari "El vostre nom" vos@exemple.cat
```

La segona ordre crea el primer superadministrador i **escriu la contrasenya per
pantalla**: apunteu-la, no es tornarà a veure.

Proveu-ho:

- `https://esportweb.cat` → la pàgina pública d'EsportWeb, encara sense cap web.
- `https://admin.esportweb.cat` → el panell, que demana les credencials.
- `https://crosescolar.cat` → la **seva** pàgina pública, que parla de cros
  escolars. No redirigeix enlloc: és un altre web.
- `https://www.esportweb.cat` → ha de redirigir a `https://esportweb.cat`.
- `https://elquesigui.esportweb.cat` → «aquesta adreça no existeix».

## 8. Feines automàtiques

```bash
crontab -u www-data -e
```

```
*/15 * * * * cd /var/www/crosescolar && php tools/platform.php vigilar
0 3 * * *    cd /var/www/crosescolar && php tools/platform.php copies
30 4 * * *   cd /var/www/crosescolar && php tools/platform.php repassar
15 5 * * 1   cd /var/www/crosescolar && php tools/platform.php purgar --de-veritat
```

I la renovació del certificat, **al cron de root** (no al de `www-data`:
renovar demana permisos que el servidor web no ha de tenir mai):

```bash
crontab -e      # com a root
```

```
17 4 * * *   /var/www/crosescolar/tools/renovar-certificat.sh /var/www/crosescolar
```

L'instal·lador ja hi posa aquesta línia. El guió fa `certbot renew`, recarrega
l'nginx si s'ha renovat res i deixa a `storage/certificat.json` com ha anat,
que és el que llegeix el panell per avisar-vos si un dia falla. Si el
certificat es va demanar amb **validació manual**, el guió ho detecta i no s'hi
entreté: aquell no es pot renovar sense una persona al davant.

## El certificat, vist des del panell

El panell **no renova** el certificat —per renovar-lo cal ser root— però sí que
el vigila. La feina de vigilància (`vigilar`, cada quart d'hora) obre una
connexió TLS als dominis de la plataforma i en llegeix la data de caducitat i
els noms que cobreix. Amb això:

- Al **tauler** hi surt un avís quan queden 21 dies o menys, i un altre si hi ha
  webs de clients que el certificat no cobreix (el cas típic: heu donat d'alta
  un cros nou i el certificat és per noms, no de comodí).
- La superadministració rep un **correu** als 21, 7, 3 i 1 dies, amb l'ordre
  exacta per renovar-lo. Quan es renova, els avisos es tornen a armar sols.
- Des de la consola del servidor:

  ```bash
  sudo -u www-data php tools/platform.php certificat
  ```

  diu quan caduca, qui l'emet, quins noms cobreix i què cal executar per
  renovar-lo o ampliar-lo.

## La portada i els textos legals

Tot el que es veu a la pàgina pública s'edita des del panell de
superadministració, a **Configuració**. Com que hi ha **una pàgina per domini**,
aquests apartats porten a dalt de tot les pestanyes amb els dominis: trieu de
quin n'esteu canviant les coses. Els apartats que no en porten —el correu, els
pagaments, la vigilància— són de tota la plataforma i valen per a tots.

| Apartat | Què s'hi toca | Per domini |
|---|---|---|
| **El servei** | El nom, la frase de la portada, de què parla el web i el text dels botons | sí |
| **La portada** | Imatge de fons del banner i l'opacitat del vel, la frase de sobre i la de sota el títol, el títol del llistat, els tres passos de «Com funciona» i les **preguntes freqüents** | sí |
| **Funcionalitats** | La pàgina `/funcionalitats` i la seva llista | sí |
| **Imatge** | Logotip, icona i colors | sí |
| **Legal i galetes** | Condicions del servei, privadesa, política de galetes i l'avís que surt en entrar | sí |
| **SEO i cercadors** | Descripció per a Google i verificació de Search Console | sí |
| **Altes** | Si s'accepten altes noves i si surt el llistat | sí |
| La resta | Correu, pagaments, suport, vigilància i actualitzacions | no |

### Qui es dona d'alta

L'alta és lliure: qui vol un web omple el formulari de la portada i el té fet
al moment, sense que ningú l'hagi d'aprovar. Tria el subdomini i el domini, i el
panell sempre li queda a `elseusubdomini.eldomini/admin`.

El que fa de porta és el correu. L'únic camí cap al panell acabat de crear és el
botó **«Accedeix al teu Panell d'Administració»** que s'envia a l'adreça que ha
escrit: en prémer-lo hi entra, es posa una contrasenya i, alhora, l'adreça queda
donada per bona. Fins que no ho faci, a **Instàncies** hi surt marcada com a
«Correu per validar».

El web neix amagat i no es paga res fins al dia que es vol fer públic, de manera
que una alta que no arribi enlloc no costa res a ningú. Si convé aturar-ho, a
**Configuració → Altes** es poden tancar les altes noves; el formulari desapareix
de la portada i una crida que arribi igualment tampoc no es desa.

### Com neix la pàgina d'un domini

El primer cop que s'engega, cada domini es queda una còpia pròpia de tots
aquests textos:

- El **domini principal** hereta el que ja hi hagués desat, de manera que un
  servei que ja funcionava es queda igual que estava.
- **La resta** neixen amb el text que els toca. Si el nom del domini surt a
  `app/platform/site_content.php` —ara mateix, `crosescolar`— agafen el seu;
  si no, els textos genèrics d'EsportWeb.

A partir d'aquí mana el panell i aquell fitxer no torna a tocar res. Afegir un
domini al servidor és, doncs, afegir-lo a `tenants/platform.php`, refer el
certificat i entrar al panell: la seva pàgina ja hi és, a punt de reescriure.

Les **preguntes freqüents** s'escriuen en una llista de parelles pregunta i
resposta. Surten a la portada desplegables i, alhora, s'envien als cercadors en
el format que entenen (`FAQPage`), de manera que Google les pot ensenyar obertes
al resultat. Per treure'n una, se li buida la pregunta i es desa.

Els **textos legals** es publiquen a `/condicions`, `/privadesa` i `/galetes`, i
van enllaçats al peu. Porten un esborrany de partida escrit a partir del que fa
de debò aquesta plataforma; admeten els marcadors `{{entitat}}`, `{{nif}}`,
`{{adreca}}`, `{{correu}}` i `{{web}}`, que se substitueixen sols pel que hàgiu
posat al mateix apartat.

> L'esborrany no és un dictamen jurídic: descriu el que el programa fa, i prou.
> Abans de publicar-lo, llegiu-lo i adapteu-lo a com treballeu de debò, i si hi
> ha contracte pel mig, que ho miri qui us porti aquests temes.

L'**avís de galetes** informa que el web només fa servir la galeta tècnica de
sessió. Per a les galetes necessàries, la normativa europea demana informar,
no demanar permís: per això l'avís es tanca i no bloqueja res. **Si algun dia hi
poseu analítica o qualsevol galeta que no sigui necessària, això s'ha de refer**:
caldrà consentiment de debò, amb opcions separades i sense carregar res abans de
tenir-lo.

## El suport als clients

**Suport**, al menú del panell, és la safata de les consultes que obren els
clients des del seu propi panell. Els tiquets no són a la base de dades de cada
client sinó a la de la plataforma: qui els ha d'atendre els vol tots en un lloc,
i no anar-los a buscar web per web.

Què s'hi pot fer:

| On | Què |
|---|---|
| **Suport** | La safata, amb pestanyes per estat, filtre per departament i cercador per número, assumpte, web o correu |
| **Suport → una consulta** | El fil sencer, contestar, deixar-hi una **nota interna** i canviar-ne l'estat, la prioritat i el departament |
| **Suport → Departaments** | Les caselles que tria el client, cadascuna amb la seva adreça d'avisos |
| **Configuració → Opcions del suport** | Obrir o tancar les consultes noves, el text que llegeix el client i l'horari que se li ensenya |

Com es mou un tiquet, sense que ningú hi hagi de pensar: quan **escriu el
client** torna a estar *obert* (és a dir, ens espera); quan **contestem
nosaltres** queda *respost*. Les notes internes no el mouen de lloc i el client
no les veu mai.

Els avisos de consulta nova van a l'adreça del **departament**; si no en té, a
la de **Configuració → Opcions del suport**, i si tampoc, a l'adreça d'avisos
del correu. La resposta arriba al client per correu i li surt al seu panell amb
un distintiu al menú.

Esborrar un departament **no** esborra les seves consultes: es queden sense
departament. Per deixar-ne de fer servir un, val més **desactivar-lo**: així les
que hi havia es queden on eren i els clients ja no el poden triar.

## Cobrar per publicar el web

Donar-se d'alta i preparar el cros no costa res: el client pot trastejar tant com vulgui.
El pagament arriba el dia que vol que el seu web es vegi al públic, i és **un pagament únic
per web**.

Es configura en tres llocs del panell:

| Apartat | Què s'hi posa |
|---|---|
| **Configuració → Pagament d'activació** | Si es cobra, com se'n diu, el preu (amb IVA inclòs), l'IVA i què inclou |
| **Configuració → Stripe de la plataforma** | Les claus amb què cobrem nosaltres. **No són les del client**: cada cros té les seves per cobrar als participants |
| **Configuració → Dades fiscals** | Amb què s'emeten les nostres factures: nom fiscal, NIF, adreça i sèrie |

Mentre el pagament estigui **desactivat**, qualsevol client pot publicar el seu web quan
vulgui. En activar-lo, el panell del client canvia: pot amagar el web sempre, però per
publicar-lo se li demana l'activació i se l'envia a la pantalla que l'explica.

**Pagaments** (al menú) és el llistat de tot el que hem cobrat. De cada pagament es pot
emetre o descarregar la factura, donar-lo per fet si ha arribat per transferència, i tornar
els diners. **Factures** és el llistat de tot el que hem emès, amb la nostra numeració
(`A-2026-0001`).

L'avís de Stripe ha d'apuntar al domini públic:

```
https://crosescolar.cat/pagament/avis
```

És el que mana: si algú tanca la finestra just després de pagar, aquest és l'únic avís que
arriba. Poseu-hi el secret del webhook a *Configuració → Stripe de la plataforma*.

> ### Dos sistemes de facturació que no es toquen
>
> Val la pena tenir-ho clar perquè és el que més fàcilment es barreja:
>
> | | La plataforma | Cada cros |
> |---|---|---|
> | Què cobra | L'activació del web | Inscripcions i tiquets |
> | A qui | Al client (l'AFA, el club) | Als participants |
> | Amb què | El nostre Stripe | La passarel·la que ell triï: Stripe, PayPal o Redsys |
> | On viu | Base de dades de la plataforma | Base de dades del client |
> | Dades fiscals | Les nostres | Les seves |
> | Numeració | `A-2026-0001` | `R-2026-0001` / `F-2026-0001` |
>
> A un rebut d'un cros no hi surt mai cap dada nostra, i a una factura nostra no hi surt
> mai cap dada d'un participant. Hi ha proves automàtiques que ho comproven.

## Que trobin les webs públiques: Google, Bing i els assistents d'IA

Les webs públiques de la plataforma (`esportweb.cat`, `crosescolar.cat`…) ja
surten preparades per als cercadors i per als assistents d'IA. Cada domini té:

- **Dades estructurades** a cada pàgina: qui hi ha al darrere (organització, amb
  el correu i la pàgina de contacte), el web, el servei i el que fa, les curses
  del llistat com a esdeveniments esportius (amb data, poble i adreça real) i les
  preguntes freqüents.
- **`/robots.txt`** amb un grup per als assistents que busquen per respondre
  (ChatGPT, Claude, Perplexity…) i un altre per als que llegeixen per aprendre
  (GPTBot, ClaudeBot, Google-Extended…), i el mapa del web.
- **`/llms.txt`**: un resum de tot el web en text pla —què és, què fa, les
  preguntes freqüents, les curses publicades i on escriure— que és el que
  llegeixen els assistents d'una tirada (format de llmstxt.org).
- **`/sitemap.xml`**, la imatge del banner per quan es comparteix l'enllaç i
  fragments sencers per citar-lo.

Tot surt del que ja hi ha escrit a la configuració de cada domini; no cal
escriure res a part. A **Configuració → SEO i cercadors** es tria quins
assistents hi poden entrar (tots, només els que busquen, o cap).

El que sí que heu de fer un cop, per a cada domini:

1. **Google Search Console** (`search.google.com/search-console`): afegiu la
   propietat `https://esportweb.cat`, trieu la verificació per *etiqueta HTML* i
   enganxeu el codi a **Configuració → SEO i cercadors → Verificació de Google**.
   Després, a *Sitemaps*, envieu `https://esportweb.cat/sitemap.xml`.
2. **Bing Webmaster Tools** (`bing.com/webmasters`): és l'índex on busca ChatGPT
   quan navega. El més ràpid és *Importar des de Google Search Console*; si no,
   verifiqueu-lo amb l'*etiqueta meta* i enganxeu el codi al camp de Bing.
3. Repetiu-ho per a `crosescolar.cat`, que és un web diferent amb el seu text.

Per comprovar que tot hi és:

```bash
curl -s https://esportweb.cat/robots.txt
curl -s https://esportweb.cat/llms.txt
```

I les dades estructurades, amb la prova de resultats enriquits de Google
(`search.google.com/test/rich-results`).

## El formulari de contacte de les webs públiques

Cada web pública de la plataforma (`esportweb.cat`, `crosescolar.cat`…) té una
pàgina **`/contacte`** amb un formulari: nom i cognoms, entitat, correu,
telèfon, missatge, l'acceptació de la política de privadesa i, si es vol, la de
rebre novetats. Són obligatoris el nom, el correu, el missatge i la privadesa.

Els missatges arriben al **Panell de Superadministració → Contacte**, amb la
xifra dels que queden per llegir al menú. Cadascun diu de quin domini ha vingut.
S'hi poden marcar com a responsos, arxivar, cercar i esborrar, i des de la fitxa
es respon per correu amb un clic. Si l'adreça d'avisos del correu està posada,
també hi arriba un correu per cada missatge; responent-lo, la resposta va
directament a qui ha escrit.

Per frenar el correu brossa hi ha quatre filtres, i cap no depèn de serveis de
fora:

- Un **captcha propi**: una imatge amb cinc caràcters que el mateix servidor
  dibuixa amb GD i que serveix un sol cop. Sense GD, passa a ser una suma
  escrita (`Quant fa 4 + 7?`).
- Un camp ocult que només omplen els robots.
- Que no s'enviï en menys de tres segons des que s'ha obert.
- Un màxim de cinc missatges per hora des de la mateixa adreça IP.

No s'envia cap còpia del missatge a qui l'ha escrit: el formulari passaria a
ser una manera d'enviar correus a qualsevol adreça en nom de la plataforma.

Els textos de la pàgina (títol, entradeta i el «gràcies») es canvien per a cada
domini a **Configuració → Contacte**, on també es pot desactivar la pàgina en un
domini concret.

## Escriure als clients: enviaments i llistes

**Enviaments** és el mateix mecanisme que fan servir els cros per escriure a les
famílies —es prepara la llista de destinataris i s'envia per tandes, perquè un
servidor compartit no aguanta centenars de correus de cop—, però amb la gent de
la plataforma.

A qui es pot escriure:

| Destinataris | D'on surten |
|---|---|
| Les persones de contacte dels clients | La fitxa de cada client actiu |
| Les administradores de cada web | El correu de cada instància, només les en marxa o totes |
| Una llista de correu | Les adreces que hi hàgiu posat i que estiguin d'alta |
| Adreces escrites a mà | El que enganxeu al formulari |

Les **llistes** (a *Enviaments → Llistes*) serveixen per a gent que encara no és
clienta de res: una associació de mestres, els contactes d'una fira, les escoles
d'una comarca. S'hi enganxen adreces una per línia i s'accepten les tres maneres
com la gent les té apuntades:

```
anna@example.cat
Pau Soler <pau@example.cat>
marta@example.cat; Marta Vila; AFA Sant Jordi
```

De les columnes, la que sigui una adreça és l'adreça i les altres dues el nom i
l'entitat. Les repetides no es dupliquen: s'aprofiten per completar el que hi
falti. Una adreça es pot **donar de baixa** sense esborrar-la, i llavors deixa de
rebre correus però queda constància que hi era.

La **plantilla** (a *Enviaments → Plantilla*) és la capçalera i el peu que
embolica cada enviament, en HTML. El **marc** —la taula que fa que el correu es
vegi centrat i s'adapti al mòbil— no s'edita a posta: el codi d'un correu que es
vegi bé a l'Outlook, al Gmail i a l'iPhone és d'una altra època i n'hi ha prou
amb una etiqueta mal tancada perquè mig món el vegi escapçat. Tant a la
plantilla com a l'assumpte i al cos hi valen aquests marcadors:

| Marcador | Què hi posa |
|---|---|
| `{{nom}}` | El nom del destinatari |
| `{{entitat}}` | La seva entitat o escola |
| `{{correu}}` | La seva adreça |
| `{{assumpte}}` | L'assumpte del correu |
| `{{plataforma}}` | El nom del servei |
| `{{web}}` | El domini |
| `{{any}}` | L'any actual |

Abans del primer enviament de debò, poseu **Configuració → Correu → Com
s'envia** en **«Assaig: no enviar res»** i feu-ne una prova sencera: tot
funcionarà igual i quedarà al registre, però no sortirà cap correu del servidor.
Si l'allotjament limita quants correus deixa enviar per hora, abaixeu **Correus
per tanda** al mateix apartat.

> El peu del correu és on la normativa espera trobar **qui escriu** i **per què
> el rep qui el rep**. El de fàbrica ja ho diu; si el canvieu, no ho traieu.

## El DNS, vist des del panell

La mateixa vigilància repassa el DNS, i el que més importa és el **comodí**.
Sense ell, els cros que ja hi són funcionen —tenen el seu registre— però cada
cros nou necessitarà que li creeu el subdomini a mà, i fins que no ho feu el seu
web no existirà per a ningú. És una avaria que no es veu fins que es dona d'alta
el client següent.

Al tauler hi surt un avís quan:

- **no hi ha comodí**, amb el registre que cal afegir a punt de copiar;
- alguna adreça de la plataforma **no existeix al DNS**;
- alguna **apunta a un altre servidor**, que sol ser un registre que s'ha quedat
  d'abans.

Les preguntes es fan al DNS de debò i no al sistema a propòsit: l'`/etc/hosts`
del servidor sol tenir el seu propi nom apuntat a `127.0.1.1` i això despista.

```bash
sudo -u www-data php tools/platform.php dns
```

diu a quina IP resol el domini, si hi ha comodí i on va a parar cada adreça.

### Fer que un comodí es renovi sol

Un certificat de comodí demanat amb `--manual` s'ha de renovar a mà cada 60-90
dies. Hi ha dues maneres de deixar-ho automàtic:

- **Connector de DNS**, si el vostre proveïdor té API (Cloudflare, OVH,
  DigitalOcean, Gandi, Hetzner…): s'instal·la `python3-certbot-dns-<proveïdor>`,
  es desa el testimoni de l'API a un fitxer només llegible per root i es demana
  el certificat amb `--dns-<proveïdor>`. A partir d'aquí `certbot renew` el
  renova sol. Amb Cloudflare, que és gratuït i va bé per a això, els passos
  sencers —inclòs com moure-hi la zona sense aturar els webs— són a
  [docs/cloudflare-dns.md](cloudflare-dns.md).
- **Delegació amb acme-dns**, si el proveïdor no té API (és el cas de
  Nominalia). Es crea **un sol CNAME** `_acme-challenge.eldomini` que apunta a
  un compte d'acme-dns, i els registres TXT els posa i els treu el certbot sol a
  cada renovació. Es configura una vegada i no s'hi torna: els passos, amb
  l'acme-dns muntat al vostre propi servidor, són a
  [docs/acme-dns.md](acme-dns.md).

Si cap de les dues us convenç, l'alternativa és deixar el comodí i fer
certificats **per nom** amb `certbot --nginx`, que es renoven sols des del
primer dia; l'única feina és tornar a executar l'ordre quan doneu d'alta un cros
nou, i el panell us avisa quan toca amb l'ordre a punt de copiar.

## 9. Portar-hi el cros de La Granada

El web antic no s'atura en cap moment: es copia, es comprova i només al final es
mou el DNS.

1. **Al web antic**, actualitzeu-lo a la versió 1.25 o posterior (Panell →
   Sistema → Actualitzacions) i aneu a **Sistema → Les meves dades**.
   Descarregueu el ZIP.
2. Si és gros, pugeu-lo al VPS per SSH en comptes de pel navegador:
   ```bash
   scp cros-escolar-la-granada-*.zip root@LA-IP-DEL-VPS:/var/www/crosescolar/storage/imports/
   chown www-data:www-data /var/www/crosescolar/storage/imports/*.zip
   ```
   I mireu que estigui sencer:
   ```bash
   cd /var/www/crosescolar
   sudo -u www-data php tools/platform.php paquet storage/imports/cros-escolar-la-granada-*.zip
   ```
3. **Al panell**, `https://admin.crosescolar.cat` → **Instàncies → Nova
   instància**:
   - Adreça: `lagranada` · domini: el que vulgueu (`.cat` o `.com`).
   - Nom, població i data: s'agafaran del paquet, no cal filar prim.
   - Qui el gestionarà: el correu de qui ja l'administra.
   - **Fitxer de migració**: pugeu el ZIP, o escriviu-ne el nom si l'heu deixat a
     `storage/imports/`.
4. En acabar, obriu `https://lagranada.crosescolar.cat` i comproveu-ho tot:
   inscripcions, resultats, imatges i el panell (qui hi entrava, hi entra amb la
   mateixa contrasenya).
5. Quan estigui bé, al DNS del domini antic poseu un CNAME o una redirecció cap
   a la nova adreça, i aviseu les famílies. El web antic es pot apagar quan
   vulgueu; mentrestant no molesta.

### Portar-lo a una instància que ja existeix

Si la instància ja està feta —perquè el client es va donar d'alta ell mateix i
després us passa les dades del web vell, o perquè una importació no va ser la
que tocava— no cal crear-ne cap de nova. A la **fitxa de la instància** hi ha
**Importar les dades d'un altre web**: s'hi puja el mateix ZIP i s'escriu a
sobre del que hi hagi.

- El web es queda amb **la seva adreça, la seva base de dades i la seva
  configuració**; el que canvia són les dades: inscripcions, resultats, textos i
  fitxers. Els enllaços que apuntaven a l'adreça del paquet es canvien per la
  d'aquest web.
- Cal escriure l'adreça de la instància a la casella de confirmació: és una
  operació que **esborra** el que hi havia.
- Abans de tocar res se'n fa una **còpia de seguretat**, que queda a la llista
  de còpies de la mateixa fitxa. Si la còpia falla, no s'importa res.
- Qui consta com a administrador de la instància hi continua constant encara que
  el paquet no el porti, de manera que no es queda ningú a fora. Si el seu compte
  no era al web vell, se li ha d'enviar l'enllaç per entrar-hi («Enviar-los un
  enllaç per entrar»), perquè no en tindrà contrasenya.

### Tornar a donar d'alta un web donat de baixa

Donar de baixa una instància **no esborra res de seguida**: les dades es guarden
90 dies. Mentre hi siguin, a la fitxa hi ha el botó **Tornar a donar d'alta el
web**, que torna a servir-lo tal com estava, amb les inscripcions i els resultats
que tenia, i treu la data d'esborrat. Passats els 90 dies, o si s'ha esborrat a
mà amb `tools/platform.php`, ja no hi ha res a recuperar i el botó no hi surt.

## 10. Comprovacions finals

```bash
# Els registres del sistema
tail -f /var/www/crosescolar/storage/logs/app-$(date +%Y-%m).log

# Que el correu surt de debò: des del panell d'una instància,
# Sistema → Correus → Enviar una prova.

# Que la vigilància veu les instàncies
sudo -u www-data php tools/platform.php vigilar
sudo -u www-data php tools/platform.php instancies
```

## Problemes típics

| Símptoma | Què mirar |
| --- | --- |
| Error 502 a tot | El sòcol de PHP del vhost no coincideix amb el de `/run/php/` |
| «Aquesta adreça no existeix» al domini principal | Falta `tenants/platform.php` o el `base_domain` no és el que serviu |
| El panell diu que no pot obrir la plataforma | Credencials de `db` incorrectes, o falta `php tools/platform.php migrar` |
| «No s'ha pogut crear la instància: Access denied» | L'usuari de `provision` no pot crear bases de dades |
| Els fitxers pujats d'un client no es veuen | Falta el bloc `map $host $cros_slug` al context `http{}` |
| La imatge de la portada de la plataforma no es veu | Falta el `map $host $cros_uploads` i l'`alias $cros_uploads/;` (pas 5) |
| Una imatge gran «no es desa» i surt «la sessió ha caducat» | Els límits de PHP són els de sèrie: mireu el `99-cros.ini` del pas 1 |
| El paquet de migració no puja pel navegador | Deixeu-lo a `storage/imports/` i importeu-lo pel nom |
| Un subdomini nou dona error de certificat | El certificat no cobreix el comodí d'aquell domini |
| Al panell d'un client no hi surt Suport | Aquell web no va per plataforma, o `tenants/platform.php` no té dades de connexió |
| Els tiquets no arriben per correu | L'adreça del departament o la de Configuració → Opcions del suport; mireu el registre de correus |
| Un client no pot publicar el seu web | Té el pagament d'activació pendent: mireu-ho a Pagaments |
| Els pagaments es queden pendents | L'avís de Stripe no arriba: comproveu `https://<domini>/pagament/avis` i el secret del webhook |

---

# Afegir un domini nou a una plataforma que ja roda

Aquesta és la feina d'afegir `esportweb.cat` a un servidor que ja serveix
`crosescolar.cat`, amb el certificat comodí i la renovació automàtica, tal com
es va fer amb el primer domini. Compteu-hi una hora llarga, la major part
esperant que el DNS es propagui. **Els webs que ja funcionen no s'aturen** si
feu els passos en aquest ordre.

Els exemples fan servir `esportweb.cat` com a domini nou, `/var/www/crosescolar`
com a arrel i `www-data` com a usuari del servidor web. Canvieu-ho pel que
tingueu.

## 0. Abans de res: actualitzeu a 1.34.0 o posterior

L'ordre importa. La primera vegada que s'engega la 1.34.0, el domini que hi
hagi de principal es queda tot el que ja teníeu escrit a la configuració (el
nom, la portada, les preguntes, els textos legals). Si afegíssiu el domini nou
**abans** d'actualitzar, seria ell qui s'ho quedaria i `crosescolar.cat`
naixeria amb els textos de fàbrica.

```bash
cd /var/www/crosescolar
php -r 'echo (require "app/version.php")["version"], "\n";'   # 1.34.0 o més
```

També ho teniu a baix de tot del menú del Panell de Superadministració.

Si encara no hi sou: Panell de Superadministració → Sistema → Actualitzacions →
Comprovar ara. Després entreu a `admin.crosescolar.cat` un cop, que és qui
aplica les migracions de la plataforma, i comproveu que la portada de
`crosescolar.cat` continua dient el que deia.

## 1. El registre del domini i el DNS

`esportweb.cat` ha d'estar registrat i la seva zona, a **Cloudflare** (o al
proveïdor amb API que feu servir per a l'altre domini). Si el domini és nou i
encara no hi és:

1. Cloudflare → **Add a site** → `esportweb.cat` → pla **Free**.
2. Copieu els dos servidors de noms que us doni.
3. Al registrador del domini, canvieu-hi els servidors de noms.

Els registres que ha de tenir, tots tres en **«DNS only»** (núvol gris, mai
taronja: amb el proxy, els comodins no funcionen al pla gratuït i l'nginx deixa
de saber qui entra):

| Tipus | Nom | Valor | Proxy |
|---|---|---|---|
| A | `@` | la IP del servidor | DNS only |
| A | `admin` | la IP del servidor | DNS only |
| A | `*` | la IP del servidor | DNS only |

> El registre `admin` només cal si algun dia voleu que el panell respongui
> també per aquest domini. El panell viu al **domini principal**, i el
> definitiu serà `admin.esportweb.cat` a partir del pas 3.

Comproveu-ho abans de continuar:

```bash
dig +short esportweb.cat A @1.1.1.1
dig +short admin.esportweb.cat A @1.1.1.1
dig +short qualsevolcosa.esportweb.cat A @1.1.1.1   # el comodí
dig +short esportweb.cat NS @1.1.1.1                # han de ser els de Cloudflare
```

Els tres primers han de donar la IP del servidor. Si no, no seguiu: el
certificat fallaria i el web donaria un error de nom.

## 2. El testimoni de l'API per al certificat

El certificat comodí només es pot validar pel DNS, i perquè es renovi sol cal
que el certbot pugui posar els registres ell mateix. Si el domini nou és al
**mateix compte de Cloudflare** que l'altre, podeu ampliar el testimoni que ja
teniu o fer-ne un de nou que cobreixi els dos:

Cloudflare → la vostra icona → **My Profile** → **API Tokens** → **Create
Token** → plantilla **Edit zone DNS** → **Use template**.

- **Permissions**: `Zone` · `DNS` · `Edit`.
- **Zone Resources**: `Include` · `Specific zone` · `esportweb.cat`
  (i, si en feu un de sol per als dos, afegiu-hi també `crosescolar.cat`).

Copieu-lo: només es veu un cop. Al servidor, com a root:

```bash
install -m 600 /dev/null /etc/letsencrypt/cloudflare.ini
printf 'dns_cloudflare_api_token = EL-TESTIMONI\n' > /etc/letsencrypt/cloudflare.ini
chmod 600 /etc/letsencrypt/cloudflare.ini
```

Si ja teníeu el fitxer i el testimoni val per als dos dominis, no cal tocar-hi
res.

## 3. Dir-li a la plataforma que té un domini més

Com a root, editeu `tenants/platform.php`. **El primer de la llista és el
principal**: és on viu el panell de superadministració i és el domini que el
codi fa servir per a les adreces dels correus.

```php
return [
    'base_domain' => 'esportweb.cat',
    'domains' => ['crosescolar.cat'],
    'console' => ['admin'],
    …
];
```

Amb això, el panell passa a ser `admin.esportweb.cat`. `admin.crosescolar.cat`
continua funcionant, però hi mena amb una redirecció.

No cal tocar res més: el domini nou neix amb els textos genèrics d'EsportWeb i
`crosescolar.cat` es queda exactament els seus. Ho podreu comprovar al pas 7.

## 4. nginx

L'nginx ha de servir els dos dominis i saber trobar els fitxers pujats de cada
subdomini. Hi ha dos fitxers a tocar.

**El dels mapes** (`/etc/nginx/conf.d/cros-map.conf`): afegiu el domini nou al
patró. Fixeu-vos que el patró és una alternativa amb `|` i que els punts van
escapats:

```nginx
map $host $cros_slug {
    default "";
    "~^(?<sub>[a-z0-9][a-z0-9-]*)\.(esportweb\.cat|crosescolar\.cat)$" $sub;
}

map $host $cros_uploads {
    default "/var/www/crosescolar/uploads";
    "~^www\.(esportweb\.cat|crosescolar\.cat)$" "/var/www/crosescolar/uploads";
    "~^admin\.(esportweb\.cat|crosescolar\.cat)$" "/var/www/crosescolar/uploads";
    "~^(?<pujades>[a-z0-9][a-z0-9-]*)\.(esportweb\.cat|crosescolar\.cat)$" "/var/www/crosescolar/tenants/$pujades/uploads";
}
```

> Si us salteu el segon mapa, la portada d'`esportweb.cat` buscaria la seva
> imatge dins de la carpeta d'un client i us sortiria un 404. És l'errada més
> típica d'aquest pas.

**El del lloc** (`/etc/nginx/sites-available/crosescolar`): afegiu els noms nous
als dos blocs `server_name`, el del 443 i el del 80:

```nginx
server_name esportweb.cat *.esportweb.cat crosescolar.cat *.crosescolar.cat;
```

I recarregueu:

```bash
nginx -t && systemctl reload nginx
```

## 5. El certificat comodí

Un sol certificat amb els quatre noms. El **`--cert-name` és important**: fa que
substitueixi el que ja teniu en comptes de crear-ne un de nou en una carpeta
diferent, i així l'nginx continua trobant el bo sense haver-hi de tocar res.

Com a root:

```bash
apt install -y python3-certbot-dns-cloudflare   # si no hi era

certbot certonly \
  --dns-cloudflare --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
  --dns-cloudflare-propagation-seconds 30 \
  --cert-name crosescolar.cat \
  --agree-tos \
  -d esportweb.cat -d '*.esportweb.cat' \
  -d crosescolar.cat -d '*.crosescolar.cat'

systemctl reload nginx
```

> Es continua dient `crosescolar.cat` perquè és el nom de la carpeta a
> `/etc/letsencrypt/live/` on apunta l'nginx. És només una etiqueta; el
> certificat cobreix els quatre noms igualment. Si el voleu reanomenar, useu
> `certbot certificates` per veure'l, `--cert-name esportweb.cat` en una
> comanda nova i canvieu les dues línies `ssl_certificate*` del vhost abans de
> recarregar.

## 6. Comprovar que la renovació no demana ningú

Aquest pas és el que dona sentit a tot plegat:

```bash
certbot renew --dry-run
```

Ha d'acabar sol, sense demanar-vos cap registre TXT. I mireu amb quin
autenticador ha quedat:

```bash
certbot certificates | grep -A 5 'crosescolar.cat'
grep authenticator /etc/letsencrypt/renewal/crosescolar.cat.conf
# ha de dir: authenticator = dns-cloudflare
```

La renovació la fa el cron **de root** que ja hi ha posat des de la
instal·lació. El panell no pot renovar res —per renovar cal ser root i el
servidor web no ho és ni ho ha de ser—, de manera que el guió deixa el resultat
en un fitxer i el panell el llegeix:

```bash
crontab -l | grep renovar-certificat
# 17 4 * * *   /var/www/crosescolar/tools/renovar-certificat.sh /var/www/crosescolar
```

Si no hi és, afegiu-l'hi amb `crontab -e` (el de root, no el de `www-data`).
Per provar-lo ara mateix:

```bash
/var/www/crosescolar/tools/renovar-certificat.sh /var/www/crosescolar
cat /var/www/crosescolar/storage/certificat.json
```

I per veure com ho veu el panell, sense esperar la vigilància (fixeu-vos que
cal ser a l'arrel del projecte: la ruta és relativa):

```bash
cd /var/www/crosescolar
sudo -u www-data php tools/platform.php certificat
```

Ha de dir que cobreix els quatre noms, **no** ha de sortir cap línia «ATENCIÓ,
no cobreix», i ha d'acabar dient que **es renova sol**. Si en comptes d'això us
ensenya una ordre de `certbot` per executar a mà, és que la renovació
automàtica encara no ha passat mai o va fallar: mireu-ne el motiu, que us el
diu a sobre.

> Si mai heu de refer el certificat a mà, no us deixeu cap domini: un certificat
> amb la meitat dels noms deixa l'altre domini sense cobrir el dia que es
> renovi. I poseu-hi sempre el `--cert-name` del que ja teniu (`certbot
> certificates` us el diu): sense això, certbot en crea un de nou en una
> carpeta a part i l'nginx continua servint el vell.

## 7. El contingut del web nou

Entreu a `https://admin.esportweb.cat` i aneu a **Configuració**. Els apartats
que fan la pàgina pública porten a dalt les pestanyes dels dos dominis:

| Apartat | Què hi revisareu del domini nou |
|---|---|
| **El servei** | El nom, la frase de la portada, de què va el web i el text dels botons |
| **La portada** | Banner, títol del llistat, els tres passos i les preguntes freqüents |
| **Funcionalitats** | La llista de `/funcionalitats` |
| **Imatge** | Logotip, icona i colors |
| **Legal i galetes** | Condicions, privadesa i galetes |
| **SEO i cercadors** | Descripció per a Google i la verificació de Search Console |
| **Altes** | Si s'hi accepten altes i si surt el llistat |

Trieu la pestanya `esportweb.cat` i repasseu-ho. Ha de venir tot amb el text
genèric d'EsportWeb; si hi veiessiu text del cros, és que us vau saltar el pas
0 i ho podeu corregir aquí mateix.

La resta —clients, instàncies, facturació, correu, suport, enviaments— és la
mateixa per als dos dominis i no té pestanyes.

## 8. Comprovacions finals

```bash
curl -sI https://esportweb.cat/            | head -1   # 200
curl -sI https://www.esportweb.cat/        | head -1   # 301 cap a esportweb.cat
curl -sI https://admin.esportweb.cat/      | head -1   # 302 cap a /acces
curl -sI https://crosescolar.cat/          | head -1   # 200, i el seu text
curl -sI https://elquesigui.esportweb.cat/ | head -1   # 404: no és de ningú
```

I al navegador:

- `https://esportweb.cat` ha de parlar de curses i activitats esportives, i el
  formulari d'alta ha de deixar triar entre els dos dominis al desplegable.
- `https://crosescolar.cat` ha de continuar dient el de sempre.
- Creeu-vos un web de prova des del formulari, mireu que us arribi el correu i
  que el botó us faci entrar al panell. Després esborreu la instància des de
  **Instàncies**.

## Si alguna cosa no va

| Símptoma | Què mirar |
|---|---|
| «Aquesta adreça no existeix» al domini nou | No és a `tenants/platform.php`, o el fitxer té un error de sintaxi: `php -l tenants/platform.php` |
| Error de certificat | El certificat no cobreix el nom: `certbot certificates` i repetiu el pas 5 |
| La imatge de la portada dona 404 | Falta el domini al mapa `$cros_uploads` del pas 4 |
| El panell no respon a `admin.esportweb.cat` | `base_domain` encara no és `esportweb.cat`, o falta el registre A d'`admin` |
| El domini nou ensenya el text del cros | Es va afegir abans d'actualitzar; corregiu-ho a la pestanya del pas 7 |
| `certbot renew --dry-run` demana un TXT | El certificat encara és de validació manual: repetiu el pas 5, que el reescriu amb `dns-cloudflare` |
