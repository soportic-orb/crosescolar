# Muntar la plataforma en un VPS nou

Guia completa per deixar el servei en marxa a `crosescolar.cat` (i
`crosescolar.com`) en un VPS acabat de crear, i per portar-hi el cros de La
Granada que ara funciona pel seu compte.

Parteix d'un **Ubuntu 24.04 net** amb accés `root` per SSH, com el que dona
Clouding.io. Si el VPS ja porta un tauler (CloudPanel, Plesk…), useu la guia
[`instalacio.md`](instalacio.md) i salteu-vos els apartats 1 i 2 d'aquí.

Al llarg de la guia, canvieu `crosescolar.cat` i `crosescolar.com` pels vostres
dominis si són uns altres.

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
    'base_domain' => 'crosescolar.cat',
    'domains' => ['crosescolar.com'],
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
EOF
```

(traieu el mateix `map` del fitxer del lloc, que hi és només com a recordatori)

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

- `https://crosescolar.cat` → la pàgina pública, encara sense cap cros.
- `https://admin.crosescolar.cat` → el panell, que demana les credencials.
- `https://crosescolar.com` → ha de redirigir a `https://crosescolar.cat`.
- `https://elquesigui.crosescolar.cat` → «aquesta adreça no existeix».

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
| El paquet de migració no puja pel navegador | Deixeu-lo a `storage/imports/` i importeu-lo pel nom |
| Un subdomini nou dona error de certificat | El certificat no cobreix el comodí d'aquell domini |
