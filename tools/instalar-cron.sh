#!/usr/bin/env bash
#
# Posa les feines automàtiques de la plataforma al cron.
#
# El panell no pot tocar el cron: ho ha de fer root, una vegada. Aquest guió hi
# deixa les feines que toquen, amb la carpeta d'aquest servidor:
#
#   sudo bash /var/www/crosescolar/tools/instalar-cron.sh
#
# Es pot tornar a fer tantes vegades com calgui: treu les línies de la
# plataforma que ja hi havia i hi posa les d'ara, sense tocar-ne cap altra.
# Serveix per als servidors instal·lats abans que hi hagués alguna feina nova
# (els enviaments de correu, per exemple).
#
# Opcions:
#   --assaig        només ensenya què hi posaria, sense tocar res
#   --usuari NOM    l'usuari del servidor web (si no, el de la carpeta storage)
#   --sense-provar  no fa la primera passada dels enviaments en acabar
set -euo pipefail

ASSAIG=0
PROVAR=1
ARREL=""
USUARI_WEB="${CROS_USUARI_WEB:-}"
while [ $# -gt 0 ]; do
    case "$1" in
        --assaig) ASSAIG=1; shift ;;
        --sense-provar) PROVAR=0; shift ;;
        --usuari) USUARI_WEB="${2:-}"; shift 2 ;;
        -h|--help) sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) ARREL="$1"; shift ;;
    esac
done
ARREL="${ARREL:-$(cd "$(dirname "$0")/.." && pwd)}"
ARREL="${ARREL%/}"
if [ -z "$USUARI_WEB" ]; then
    USUARI_WEB="$(stat -c %U "$ARREL/storage" 2>/dev/null || echo www-data)"
    case "$USUARI_WEB" in root|UNKNOWN|"") USUARI_WEB="www-data" ;; esac
fi

# Les feines del servidor web. Si se n'afegeix una, va aquí i prou: l'instal·lador
# també fa servir aquest guió.
feines_web() {
    cat <<EOF
*/5 * * * *  cd $ARREL && php tools/platform.php enviaments >/dev/null 2>&1
*/15 * * * * cd $ARREL && php tools/platform.php vigilar >/dev/null 2>&1
0 3 * * *    cd $ARREL && php tools/platform.php copies >/dev/null 2>&1
30 4 * * *   cd $ARREL && php tools/platform.php repassar >/dev/null 2>&1
15 5 * * 1   cd $ARREL && php tools/platform.php purgar --de-veritat >/dev/null 2>&1
EOF
}

# La renovació del certificat va al cron de root, no al del servidor web:
# renovar demana permisos que el web no ha de tenir mai.
feina_root() {
    echo "17 4 * * *   $ARREL/tools/renovar-certificat.sh $ARREL >/dev/null 2>&1"
}

if [ "$ASSAIG" = "1" ]; then
    echo "Al cron de $USUARI_WEB:"
    feines_web
    echo
    echo "Al cron de root:"
    feina_root
    exit 0
fi

if [ "$(id -u)" != "0" ]; then
    echo "Aquest guió l'ha d'executar root:  sudo bash $ARREL/tools/instalar-cron.sh" >&2
    exit 1
fi
if [ ! -f "$ARREL/tools/platform.php" ]; then
    echo "A $ARREL no hi ha la plataforma. Digueu-me la carpeta:  bash instalar-cron.sh /var/www/crosescolar" >&2
    exit 1
fi
if ! command -v crontab >/dev/null 2>&1; then
    echo "Aquest servidor no té cron. Instal·leu-lo (apt install cron) i torneu-ho a provar." >&2
    exit 1
fi
if ! id "$USUARI_WEB" >/dev/null 2>&1; then
    echo "L'usuari $USUARI_WEB no existeix. Digueu quin és el del servidor web amb --usuari." >&2
    exit 1
fi

CRON="$(mktemp)"
trap 'rm -f "$CRON"' EXIT

crontab -u "$USUARI_WEB" -l 2>/dev/null | grep -v 'tools/platform.php' > "$CRON" || true
feines_web >> "$CRON"
crontab -u "$USUARI_WEB" "$CRON"

crontab -l 2>/dev/null | grep -v 'renovar-certificat.sh' > "$CRON" || true
feina_root >> "$CRON"
crontab "$CRON"
chmod +x "$ARREL/tools/renovar-certificat.sh" 2>/dev/null || true

echo "Fet. Al cron de $USUARI_WEB hi ha $(crontab -u "$USUARI_WEB" -l | grep -c 'tools/platform.php') feines de la plataforma:"
echo "  enviaments cada 5 minuts, vigilància cada quart, còpies a les 3,"
echo "  repàs a les 4:30 i purga els dilluns. La renovació del certificat, al de root."

# La primera passada dels enviaments, ara mateix: el que estigui programat i ja
# toqui surt sense esperar el cron, i el panell deixa d'avisar.
if [ "$PROVAR" = "1" ]; then
    echo
    echo "Primera passada dels enviaments:"
    if su -s /bin/sh -c "cd '$ARREL' && php tools/platform.php enviaments -v" "$USUARI_WEB"; then
        echo "D'ara endavant ho fa el cron sol."
    else
        echo "No ha anat bé. Proveu-ho a mà per veure l'error:" >&2
        echo "  cd $ARREL && sudo -u $USUARI_WEB php tools/platform.php enviaments -v" >&2
    fi
fi
