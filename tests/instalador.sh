#!/usr/bin/env bash
#
# Proves de l'script d'instal·lació del servidor.
#
# No instal·la res: comprova la sintaxi, que l'assaig arribi al final i que les
# funcions que fan feina de debò no es morin amb les opcions estrictes de bash
# («set -euo pipefail»), que és el que fa que un error passi desapercebut.
set -uo pipefail

SCRIPT="$(cd "$(dirname "$0")/.." && pwd)/tools/instalar-vps.sh"
CORRECTES=0
ERRORS=0

check() {
    if [ "$2" = "0" ]; then
        CORRECTES=$((CORRECTES + 1)); echo "  OK   $1"
    else
        ERRORS=$((ERRORS + 1)); echo "  FALLA $1${3:+ → $3}"
    fi
}

echo
echo "== L'script d'instal·lació =="

bash -n "$SCRIPT" >/dev/null 2>&1
check "La sintaxi és correcta" "$?"

# Les funcions, amb les mateixes opcions estrictes que fa servir l'script.
FUNCIONS="$(sed -n '/^clau()/,/^}/p' "$SCRIPT")"
SORTIDA="$(bash -euo pipefail -c "$FUNCIONS
CLAU=\"\$(clau)\"
LLARGA=\"\$(clau 24)\"
echo \"\${#CLAU} \${#LLARGA} \$CLAU\"" 2>&1)"
ESTAT=$?
check "Les contrasenyes es generen sense matar l'script" "$ESTAT" "$SORTIDA"

if [ "$ESTAT" = "0" ]; then
    set -- $SORTIDA
    [ "${1:-0}" -ge 24 ] && [ "${2:-0}" -ge 40 ]
    check "I tenen la llargada que toca" "$?" "${1:-?} i ${2:-?} caràcters"
    [[ "${3:-}" =~ ^[a-zA-Z0-9]+$ ]]
    check "Sense caràcters que despistin la línia d'ordres" "$?" "${3:-}"

    UNA="$(bash -euo pipefail -c "$FUNCIONS
clau")"
    ALTRA="$(bash -euo pipefail -c "$FUNCIONS
clau")"
    [ "$UNA" != "$ALTRA" ]
    check "I dues seguides no són iguals" "$?"
fi

# L'assaig ha d'arribar al final sense tocar res.
TEMP="$(mktemp -d)"
bash "$SCRIPT" --assaig --sense-paquets --sense-certificat \
    --dominis crosescolar.test,crosescolar.example --arrel "$TEMP/cros" > "$TEMP/assaig.log" 2>&1
check "L'assaig acaba bé" "$?" "$(tail -3 "$TEMP/assaig.log" 2>/dev/null)"

grep -q "8/8" "$TEMP/assaig.log" 2>/dev/null
check "I arriba a l'últim pas" "$?"

[ ! -d "$TEMP/cros" ]
check "Sense haver tocat res" "$?"

# Un domini mal escrit s'ha de notar abans de començar.
bash "$SCRIPT" --assaig --sense-paquets --dominis 'aixo no val' >/dev/null 2>&1
[ "$?" != "0" ]
check "Un domini que no ho és atura l'script" "$?"

rm -rf "$TEMP"

echo
echo "== Resultat =="
echo "  $CORRECTES proves correctes, $ERRORS errors"
echo
[ "$ERRORS" = "0" ]
