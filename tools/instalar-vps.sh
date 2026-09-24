#!/usr/bin/env bash
#
# Cros Escolar — instal·lació guiada en un servidor nou.
#
# Deixa el VPS a punt de dalt a baix: programari, bases de dades, codi, nginx,
# certificat i feines automàtiques. En acabar obre l'assistent web, que acaba la
# part de l'aplicació (correu, superadministrador i posada en marxa).
#
#   sudo bash tools/instalar-vps.sh
#
# Opcions:
#   --arrel CAMÍ        on va el codi (per defecte /var/www/crosescolar)
#   --dominis a.cat,b.com   els dominis, sense preguntar
#   --correu adreça     adreça per als avisos del certificat
#   --sense-paquets     no instal·la res amb apt
#   --sense-certificat  no demana el certificat (ja el demanareu vosaltres)
#   --assaig            diu què faria, sense tocar res
#
# Ha de córrer com a root en un Ubuntu o Debian.
set -euo pipefail

ARREL="/var/www/crosescolar"
DOMINIS=""
CORREU=""
PAQUETS=1
CERTIFICAT=1
ASSAIG=0
USUARI_WEB="www-data"

while [ $# -gt 0 ]; do
    case "$1" in
        --arrel) ARREL="$2"; shift 2 ;;
        --dominis) DOMINIS="$2"; shift 2 ;;
        --correu) CORREU="$2"; shift 2 ;;
        --sense-paquets) PAQUETS=0; shift ;;
        --sense-certificat) CERTIFICAT=0; shift ;;
        --assaig) ASSAIG=1; shift ;;
        -h|--ajuda) sed -n '2,24p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opció desconeguda: $1"; exit 1 ;;
    esac
done

verd()  { printf '\033[32m%s\033[0m\n' "$*"; }
groc()  { printf '\033[33m%s\033[0m\n' "$*"; }
roig()  { printf '\033[31m%s\033[0m\n' "$*"; }
titol() { printf '\n\033[1m── %s ─────────────────────────────\033[0m\n' "$*"; }
fes()   { if [ "$ASSAIG" = "1" ]; then echo "   (assaig) $*"; else eval "$@"; fi; }
clau()  { tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 28; }

[ "$(id -u)" = "0" ] || { roig "Executeu-ho com a root: sudo bash $0"; exit 1; }

ORIGEN="$(cd "$(dirname "$0")/.." && pwd)"
[ -f "$ORIGEN/index.php" ] && [ -d "$ORIGEN/app/platform" ] || {
    roig "No trobo el codi del cros. Descomprimiu el paquet i executeu l'script des de dins."
    exit 1
}

printf '\n\033[1mInstal·lació del Cros Escolar en un servidor nou\033[0m\n'
echo "Codi: $ORIGEN"
[ -r /etc/os-release ] && . /etc/os-release && echo "Sistema: ${PRETTY_NAME:-desconegut}"

# ------------------------------------------------------------------ preguntes

titol "1/8 · Els dominis"
if [ -z "$DOMINIS" ]; then
    echo "El primer és el principal: hi viuran la pàgina pública i el panell."
    echo "Si en teniu més d'un, separeu-los amb comes (crosescolar.cat,crosescolar.com)."
    read -rp "   Dominis: " DOMINIS
fi
IFS=',' read -ra LLISTA <<< "$DOMINIS"
NOMS=""; PATRO=""; CERT_ARGS=()
for d in "${LLISTA[@]}"; do
    d="$(echo "$d" | tr -d ' ' | tr '[:upper:]' '[:lower:]')"
    [[ "$d" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]] || { roig "«$d» no és un domini."; exit 1; }
    NOMS="$NOMS $d *.$d"
    PATRO="$PATRO|${d//./\\.}"
    CERT_ARGS+=(-d "$d" -d "*.$d")
    DOMINIS_NETS="${DOMINIS_NETS:-}$d "
done
PATRO="${PATRO#|}"
PRINCIPAL="$(echo "$DOMINIS_NETS" | awk '{print $1}')"
echo "   Principal: $PRINCIPAL"

if [ -z "$CORREU" ] && [ "$CERTIFICAT" = "1" ]; then
    read -rp "   Adreça per als avisos del certificat: " CORREU
fi

IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
titol "2/8 · El DNS"
if command -v getent >/dev/null; then
    for d in $DOMINIS_NETS; do
        # Si el DNS encara no hi és, getent falla: no és cap error, només
        # vol dir que això encara no està fet.
        RESOLT="$(getent ahostsv4 "$d" 2>/dev/null | awk 'NR==1{print $1}' || true)"
        PROVA="$(getent ahostsv4 "prova-cros.$d" 2>/dev/null | awk 'NR==1{print $1}' || true)"
        if [ "$RESOLT" = "$IP" ] && [ "$PROVA" = "$IP" ]; then
            verd "   $d i *.$d apunten aquí ($IP)."
        else
            groc "   Atenció: $d → ${RESOLT:-res} · *.$d → ${PROVA:-res} · aquest servidor és $IP"
            groc "   Cal un registre A per al domini i un altre per a «*» abans del certificat."
        fi
    done
fi

# ------------------------------------------------------------------ paquets

titol "3/8 · El programari"
if [ "$PAQUETS" = "1" ]; then
    export DEBIAN_FRONTEND=noninteractive
    fes "apt-get update -qq"
    fes "apt-get install -y -qq nginx mariadb-server unzip curl ca-certificates cron certbot \
        php-fpm php-mysql php-mbstring php-curl php-gd php-zip php-xml"
    verd "   Instal·lat."
else
    echo "   (no es toca)"
fi

PHP_VERSIO="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo '')"
[ -n "$PHP_VERSIO" ] || { roig "No hi ha PHP al servidor."; exit 1; }
SOCOL="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || echo "/run/php/php${PHP_VERSIO}-fpm.sock")"
echo "   PHP $PHP_VERSIO · sòcol $SOCOL"
fes "systemctl enable --now nginx mariadb cron >/dev/null 2>&1 || true"
fes "systemctl enable --now php${PHP_VERSIO}-fpm >/dev/null 2>&1 || true"

# ------------------------------------------------------------------ el codi

titol "4/8 · El codi"
if [ "$ORIGEN" != "$ARREL" ]; then
    fes "mkdir -p '$ARREL'"
    fes "cp -a '$ORIGEN/.' '$ARREL/'"
    if [ "$ASSAIG" = "0" ]; then verd "   Copiat a $ARREL"; fi
else
    echo "   Ja és a $ARREL"
fi
fes "mkdir -p '$ARREL/storage/logs' '$ARREL/storage/backups' '$ARREL/storage/imports' '$ARREL/uploads' '$ARREL/tenants'"
fes "chown -R $USUARI_WEB:$USUARI_WEB '$ARREL'"
fes "find '$ARREL' -type d -exec chmod 755 {} +"
fes "find '$ARREL' -type f -exec chmod 644 {} +"
fes "chmod -R 775 '$ARREL/storage' '$ARREL/uploads' '$ARREL/tenants'"

# ------------------------------------------------------------- base de dades

titol "5/8 · Les bases de dades"
BD="cros_platform"; U_BD="cros_platform"; U_ADMIN="cros_admin"
if [ "$ASSAIG" = "1" ]; then
    CLAU_BD="(assaig)"; CLAU_ADMIN="(assaig)"
    echo "   (assaig) crearia $BD, $U_BD i $U_ADMIN"
else
    MYSQL="$(command -v mariadb || command -v mysql || true)"
    [ -n "$MYSQL" ] || { roig "No hi ha cap client de MariaDB."; exit 1; }
    "$MYSQL" -u root -e "SELECT 1" >/dev/null 2>&1 || {
        roig "No puc entrar al MariaDB com a root."
        echo "   Si ja té contrasenya, creeu a mà la base de dades i els usuaris (vegeu docs/vps.md)"
        echo "   i torneu-hi amb --sense-paquets."
        exit 1
    }
    CLAU_BD="$(clau)"; CLAU_ADMIN="$(clau)"
    "$MYSQL" -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$BD\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$U_BD'@'localhost' IDENTIFIED BY '$CLAU_BD';
ALTER USER '$U_BD'@'localhost' IDENTIFIED BY '$CLAU_BD';
GRANT ALL PRIVILEGES ON \`$BD\`.* TO '$U_BD'@'localhost';
CREATE USER IF NOT EXISTS '$U_ADMIN'@'localhost' IDENTIFIED BY '$CLAU_ADMIN';
ALTER USER '$U_ADMIN'@'localhost' IDENTIFIED BY '$CLAU_ADMIN';
GRANT ALL PRIVILEGES ON *.* TO '$U_ADMIN'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
    verd "   Base de dades «$BD», l'usuari que la fa servir i el que crearà les dels clients."
fi

# --------------------------------------------------------------------- nginx

titol "6/8 · nginx"
VHOST="/etc/nginx/sites-available/crosescolar"
if [ "$ASSAIG" = "0" ]; then
    cat > /etc/nginx/conf.d/cros-map.conf <<EOF
# El subdomini de la petició, per trobar els fitxers pujats de cada instància.
map \$host \$cros_slug {
    default "";
    "~^(?<sub>[a-z0-9][a-z0-9-]*)\.($PATRO)\$" \$sub;
}
EOF
    cat > "$VHOST" <<EOF
# Generat per tools/instalar-vps.sh.
server {
    listen 80;
    listen [::]:80;
    server_name$NOMS;

    root $ARREL;
    index index.php;

    location ^~ /.well-known/acme-challenge/ { root /var/www/html; }

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ^~ /uploads/ {
        alias $ARREL/tenants/\$cros_slug/uploads/;
        location ~ \.(php|phtml|phar)\$ { deny all; return 404; }
        add_header X-Content-Type-Options "nosniff" always;
        expires 30d;
        try_files \$uri =404;
    }

    location ^~ /app/     { deny all; return 404; }
    location ^~ /storage/ { deny all; return 404; }
    location ^~ /tenants/ { deny all; return 404; }
    location ^~ /tools/   { deny all; return 404; }
    location ^~ /docs/    { deny all; return 404; }
    location ~ /\.(git|env|htaccess) { deny all; return 404; }

    location ~* ^/assets/.*\.(css|js|svg|woff2?|png|jpe?g|webp)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:$SOCOL;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    client_max_body_size 64M;
}
EOF
    mkdir -p /var/www/html
    ln -sf "$VHOST" /etc/nginx/sites-enabled/crosescolar
    rm -f /etc/nginx/sites-enabled/default
    nginx -t >/dev/null && systemctl reload nginx
    verd "   Serveix$NOMS per HTTP."
else
    echo "   (assaig) escriuria $VHOST"
fi

# ---------------------------------------------------------------- certificat

titol "7/8 · El certificat"
if [ "$CERTIFICAT" = "0" ] || [ "$ASSAIG" = "1" ]; then
    echo "   (es salta)"
elif [ -f "/etc/letsencrypt/live/$PRINCIPAL/fullchain.pem" ]; then
    verd "   Ja n'hi ha un per a $PRINCIPAL."
else
    echo "   Cal un certificat amb comodí (*.$PRINCIPAL), i això només es pot validar pel DNS:"
    echo "   certbot us demanarà crear un registre TXT «_acme-challenge» a cada domini."
    read -rp "   El demanem ara? [S/n]: " RESPOSTA
    if [ "${RESPOSTA:-S}" != "n" ] && [ "${RESPOSTA:-S}" != "N" ]; then
        certbot certonly --manual --preferred-challenges dns --agree-tos \
            ${CORREU:+-m "$CORREU"} "${CERT_ARGS[@]}" || groc "   certbot no ha acabat; podeu tornar-hi més tard."
    fi
fi

if [ -f "/etc/letsencrypt/live/$PRINCIPAL/fullchain.pem" ] && [ "$ASSAIG" = "0" ]; then
    if ! grep -q 'listen 443' "$VHOST" && ! command -v python3 >/dev/null; then
        groc "   Hi ha el certificat, però no tinc python3 per reescriure el vhost."
        groc "   Afegiu-hi a mà «listen 443 ssl;» i les línies ssl_certificate de"
        groc "   /etc/letsencrypt/live/$PRINCIPAL/, i feu «systemctl reload nginx»."
    elif ! grep -q 'listen 443' "$VHOST"; then
        python3 - "$VHOST" "$PRINCIPAL" <<'PY'
import sys
vhost, domini = sys.argv[1], sys.argv[2]
http = open(vhost).read()
noms = [l for l in http.splitlines() if l.strip().startswith('server_name')][0]
ssl = (http
       .replace('    listen 80;', '    listen 443 ssl;')
       .replace('    listen [::]:80;', '    listen [::]:443 ssl;')
       .replace('    root ', f'    ssl_certificate     /etc/letsencrypt/live/{domini}/fullchain.pem;\n'
                             f'    ssl_certificate_key /etc/letsencrypt/live/{domini}/privkey.pem;\n    root ', 1))
redireccio = ("\n# Tot el trànsit sense xifrar va a HTTPS.\nserver {\n    listen 80;\n"
              "    listen [::]:80;\n" + noms + "\n"
              "    location ^~ /.well-known/acme-challenge/ { root /var/www/html; }\n"
              "    location / { return 301 https://$host$request_uri; }\n}\n")
open(vhost, 'w').write(ssl + redireccio)
PY
        nginx -t >/dev/null && systemctl reload nginx && verd "   El web ja va per HTTPS."
    fi
    fes "systemctl enable --now certbot.timer >/dev/null 2>&1 || true"
fi

# ------------------------------------------------------- feines i assistent

titol "8/8 · Feines automàtiques i assistent"
if [ "$ASSAIG" = "0" ]; then
    CRON="$(mktemp)"
    crontab -u "$USUARI_WEB" -l 2>/dev/null | grep -v 'tools/platform.php' > "$CRON" || true
    cat >> "$CRON" <<EOF
*/15 * * * * cd $ARREL && php tools/platform.php vigilar >/dev/null 2>&1
0 3 * * *    cd $ARREL && php tools/platform.php copies >/dev/null 2>&1
30 4 * * *   cd $ARREL && php tools/platform.php repassar >/dev/null 2>&1
15 5 * * 1   cd $ARREL && php tools/platform.php purgar --de-veritat >/dev/null 2>&1
EOF
    crontab -u "$USUARI_WEB" "$CRON"
    rm -f "$CRON"
    verd "   Vigilància cada quart, còpies a les 3, repàs a les 4:30 i purga els dilluns."

    TESTIMONI="$(tr -dc 'a-f0-9' < /dev/urandom | head -c 48)"
    cat > "$ARREL/tenants/instalacio.json" <<EOF
{
    "arrel": "$ARREL",
    "php": "$PHP_VERSIO",
    "socket_php": "$SOCOL",
    "ip": "$IP",
    "dominis": [$(printf '"%s",' $DOMINIS_NETS | sed 's/,$//')],
    "db": {
        "host": "localhost", "port": 3306, "name": "$BD",
        "user": "$U_BD", "pass": "$CLAU_BD"
    },
    "provision": { "admin_user": "$U_ADMIN", "admin_pass": "$CLAU_ADMIN" }
}
EOF
    chown "$USUARI_WEB:$USUARI_WEB" "$ARREL/tenants/instalacio.json"
    chmod 600 "$ARREL/tenants/instalacio.json"
    echo -n "$TESTIMONI" > "$ARREL/storage/instal-plataforma.token"
    chown "$USUARI_WEB:$USUARI_WEB" "$ARREL/storage/instal-plataforma.token"
    chmod 600 "$ARREL/storage/instal-plataforma.token"

    ESQUEMA="http"
    [ -f "/etc/letsencrypt/live/$PRINCIPAL/fullchain.pem" ] && ESQUEMA="https"
    printf '\n'
    verd "Tot a punt. Obriu aquesta adreça i acabeu la instal·lació:"
    printf '\n    \033[1m%s://%s/install-plataforma.php?clau=%s\033[0m\n\n' "$ESQUEMA" "$PRINCIPAL" "$TESTIMONI"
    echo "Si el DNS encara no apunta aquí, useu http://${IP}/install-plataforma.php?clau=$TESTIMONI"
    echo "L'assistent us demanarà el correu, el superadministrador i ho engegarà tot."
else
    echo "   (assaig) escriuria el cron i obriria l'assistent"
fi
