#!/usr/bin/env bash
#
# Renovació del certificat, per al cron de root.
#
# El panell no pot renovar res: per renovar un certificat cal ser root, i el
# servidor web no ho és ni ho ha de ser. Això ho fa aquest guió, que va al cron
# de root i que després deixa el resultat en un fitxer que el panell llegeix.
#
#   0 4 * * *  /var/www/crosescolar/tools/renovar-certificat.sh
#
# Funciona amb qualsevol certificat que certbot pugui renovar sol: els de
# «certbot --nginx» i els de comodí amb connector de DNS o amb acme-dns. Un
# comodí amb validació manual no es pot renovar sense una persona al davant;
# en aquest cas el guió no s'hi entreté i ho diu, i el panell avisa pel seu
# compte quan s'acosta la data.
set -uo pipefail

ARREL="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
ESTAT="$ARREL/storage/certificat.json"
USUARI_WEB="${CROS_USUARI_WEB:-www-data}"

if [ "$(id -u)" != "0" ]; then
    echo "Aquest guió l'ha d'executar root (va al cron de root)." >&2
    exit 1
fi

apunta() {
    # Es desa com ha anat perquè el panell ho pugui ensenyar.
    mkdir -p "$(dirname "$ESTAT")"
    cat > "$ESTAT" <<JSON
{
    "quan": "$(date '+%Y-%m-%d %H:%M:%S')",
    "resultat": "$1",
    "detall": $(printf '%s' "$2" | tail -c 400 | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))' 2>/dev/null || echo '""')
}
JSON
    chown "$USUARI_WEB:$USUARI_WEB" "$ESTAT" 2>/dev/null || true
    chmod 640 "$ESTAT" 2>/dev/null || true
}

if ! command -v certbot >/dev/null; then
    apunta "sense-certbot" "El servidor no té certbot instal·lat."
    echo "No hi ha certbot en aquest servidor." >&2
    exit 1
fi

# Els certificats amb validació manual demanen posar registres TXT a mà: no es
# poden renovar des d'un cron i no val la pena ni intentar-ho.
if grep -rqs 'authenticator = manual' /etc/letsencrypt/renewal/ \
   && ! grep -rqs 'manual_auth_hook' /etc/letsencrypt/renewal/; then
    apunta "manual" "El certificat es va demanar amb validació manual: s'ha de renovar a mà."
    echo "El certificat és de validació manual: renoveu-lo a mà abans que caduqui." >&2
    exit 0
fi

SORTIDA="$(certbot renew --quiet --deploy-hook 'systemctl reload nginx' 2>&1)"
CODI=$?
if [ "$CODI" = "0" ]; then
    apunta "ok" "${SORTIDA:-Res a renovar encara.}"
else
    apunta "error" "$SORTIDA"
    echo "$SORTIDA" >&2
fi
exit "$CODI"
