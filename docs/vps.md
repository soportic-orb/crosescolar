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

I la renovació del certificat, si heu fet servir un connector de DNS:

```
20 3 * * *   certbot renew --quiet --deploy-hook "systemctl reload nginx"
```

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
