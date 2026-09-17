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
> amb adreces del tipus `/index.php/esmorzar`.

## 5. Executar l'instal·lador

1. Obriu `https://cros.afalagranada.cat/install.php`.
2. **Pas 1** — comprovació de requisits (PHP, extensions i permisos).
3. **Pas 2** — dades de la base de dades creada al punt 2.
4. **Pas 3** — nom del web, data de la cursa i compte d'administració.
   Deixeu marcada l'opció de continguts d'exemple per començar amb una estructura
   completa que després podreu editar.
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

## Resolució de problemes

| Símptoma | Solució |
|---|---|
| Error 404 a totes les pàgines menys la portada | Falta el bloc `try_files` d'nginx, o desactiveu les URLs amigables |
| «No s'ha pogut escriure app/config.php» | `chmod 775 app` i torneu a executar l'instal·lador |
| Les imatges pujades no es veuen | `chmod -R 775 uploads` i comproveu el propietari del lloc |
| No arriben els correus | Configureu SMTP a **Configuració → Correu** i proveu-ho a **Sistema → Correus** |
| Pàgina en blanc | Reviseu `storage/logs/app-AAAA-MM.log` |

## Còpies de seguretat

Des de **Sistema → Actualitzacions → Crear còpia ara** es genera un ZIP amb tots els
fitxers i un bolcat SQL complet (`database.sql`). Es conserven les 5 còpies més recents
a `storage/backups/` i es poden descarregar des del panell.
