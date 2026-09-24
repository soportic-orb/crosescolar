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
| `granada.crosescolar.com` | el web d'aquell cros |
| `crosescolar.com` i `www.` | la pàgina pública de la plataforma |
| `admin.crosescolar.com` | el panell de superadministració |
| un subdomini sense instal·lar | una pàgina que diu que l'adreça no existeix |

Cada instància viu a `tenants/<subdomini>/` amb el seu `config.php`, la seva
carpeta `uploads/` i la seva `storage/`, i té la seva pròpia base de dades. El codi
mira la capçalera `Host`, en treu el subdomini i apunta les tres coses abans
d'arrencar; a partir d'aquí l'aplicació funciona com sempre.

Per aturar una instància temporalment, es crea un fitxer buit
`tenants/<subdomini>/suspended`: el web contesta que no està disponible i es torna
a engegar esborrant-lo.

Al servidor calen tres coses: un registre **A** per al domini i un de **comodí**
`*.crosescolar.com`, un certificat de **Let's Encrypt amb comodí** (validació
DNS-01) i el bloc d'nginx de [`docs/nginx-plataforma.conf`](nginx-plataforma.conf),
que serveix els fitxers pujats de cada instància des de la seva carpeta.

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
