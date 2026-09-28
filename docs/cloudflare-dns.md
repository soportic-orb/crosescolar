# Passar el DNS a Cloudflare i tenir el comodí renovant-se sol

Un certificat amb comodí (`*.crosescolar.cat`) s'ha de validar pel DNS, i això,
fet a mà, vol dir tornar a posar registres TXT **cada 60-90 dies**. Si el DNS el
porta un proveïdor amb API, el certbot els posa i els treu ell mateix i la
renovació passa a ser automàtica per sempre.

Aquesta guia mou el DNS de `crosescolar.cat` a **Cloudflare** (el pla gratuït en
té prou) i deixa el certificat renovant-se sol. **El domini es queda registrat
on és**: només se li canvien els servidors de noms.

Compteu-hi una hora, la major part esperant la propagació. Els webs **no
s'aturen** en cap moment si feu els passos en aquest ordre.

## 1. Apuntar el que hi ha ara (no us salteu això)

Cloudflare, en importar una zona, fa el que pot, i el que pot no sempre és tot:
els registres de correu i els TXT de verificació són els que més es perden. Si
el domini rep correu i es perden els MX, el correu deixa d'arribar i no se'n
sap res fins que algú es queixa.

Des del servidor o des de casa:

```bash
DOMINI=crosescolar.cat
for T in SOA NS A AAAA MX TXT CNAME SRV CAA; do
  echo "== $T"; dig +noall +answer "$DOMINI" "$T" @1.1.1.1
done
for N in www admin lagranada mail ftp webmail autodiscover _dmarc; do
  echo -n "$N → "; dig +short "$N.$DOMINI" @1.1.1.1 | tr '\n' ' '; echo
done
dig +short "qualsevolcosa.$DOMINI" @1.1.1.1   # diu si hi ha comodí
```

I, sobretot, **entreu al panell de Nominalia i feu una captura de la zona
sencera**. El `dig` només troba el que sabeu preguntar; la captura ho ensenya
tot, inclosos els registres que no sabíeu que hi eren.

Deseu-ho tot en un fitxer. És la llista amb què comprovareu la feina al pas 3.

## 2. Crear la zona a Cloudflare

1. Compte a [cloudflare.com](https://dash.cloudflare.com/sign-up) (gratuït).
2. **Add a site** → `crosescolar.cat` → pla **Free**.
3. Cloudflare llegeix la zona actual i us ensenya els registres que ha trobat.
4. **Compareu-los un per un amb la llista del pas 1** i afegiu-hi a mà els que
   falten. Els que solen faltar: `MX`, els `TXT` de SPF/DKIM/DMARC i els
   comodins.

Els registres que ha de tenir la plataforma, com a mínim:

| Tipus | Nom | Valor | Proxy |
|---|---|---|---|
| A | `@` | la IP del servidor | DNS only |
| A | `admin` | la IP del servidor | DNS only |
| A | `*` | la IP del servidor | DNS only |

El comodí `*` és el que fa que cada cros nou funcioni el mateix dia que el doneu
d'alta, sense tocar el DNS.

> **Deixeu-ho tot en «DNS only» (el núvol gris), no en «Proxied» (taronja).**
> Amb el proxy, el trànsit passa per Cloudflare, la IP que veuen els visitants
> canvia i els registres de l'nginx deixen de dir qui entra; i al pla gratuït
> els comodins no es poden servir per proxy. Amb «DNS only» tot queda
> exactament com ara: Cloudflare només respon preguntes de DNS.

Al final, Cloudflare us dona **dos servidors de noms** (per exemple
`aria.ns.cloudflare.com` i `rick.ns.cloudflare.com`). Apunteu-los.

## 3. Comprovar abans de canviar res

Encara no heu tocat el domini: Nominalia continua manant. Pregunteu
directament als servidors de Cloudflare a veure si contesten el que toca:

```bash
NS=aria.ns.cloudflare.com        # el que us hagi donat Cloudflare
dig +short crosescolar.cat A @$NS
dig +short admin.crosescolar.cat A @$NS
dig +short lagranada.crosescolar.cat A @$NS
dig +short qualsevolcosa.crosescolar.cat A @$NS
dig +short crosescolar.cat MX @$NS
dig +short crosescolar.cat TXT @$NS
```

Tot ha de respondre el mateix que responia al pas 1. **Si alguna cosa no
coincideix, arregleu-la a Cloudflare ara**, que encara no serveix ningú.

## 4. Canviar els servidors de noms a Nominalia

Al panell de Nominalia, a la gestió del domini, canvieu els servidors de noms
pels dos de Cloudflare. Això és l'únic que es toca a Nominalia.

La propagació sol trigar de minuts a poques hores (el màxim teòric és el TTL de
la delegació, sovint 24 h). Mentrestant, uns visitants ho resoldran per
Nominalia i altres per Cloudflare: com que les dues zones diuen el mateix, no
se'n notarà res.

Per saber quan ha passat:

```bash
dig +short crosescolar.cat NS @1.1.1.1
```

Quan surtin els de Cloudflare, ja hi som. Cloudflare també us enviarà un correu
dient que la zona està activa.

## 5. El testimoni de l'API

Al panell de Cloudflare: la vostra icona → **My Profile** → **API Tokens** →
**Create Token** → plantilla **Edit zone DNS** → **Use template**.

- **Permissions**: `Zone` · `DNS` · `Edit` (ja hi ve).
- **Zone Resources**: `Include` · `Specific zone` · `crosescolar.cat`.

Creeu-lo i **copieu-lo**: només es veu un cop. Amb aquests permisos, el
testimoni només pot editar el DNS d'aquesta zona; no pot tocar res més del
compte.

## 6. El certificat, al servidor

Com a root:

```bash
apt install -y python3-certbot-dns-cloudflare

install -m 600 /dev/null /etc/letsencrypt/cloudflare.ini
printf 'dns_cloudflare_api_token = EL-TESTIMONI-QUE-HEU-COPIAT\n' > /etc/letsencrypt/cloudflare.ini
chmod 600 /etc/letsencrypt/cloudflare.ini
```

I ara el certificat. **El `--cert-name` és important**: fa que substitueixi el
que ja teniu en comptes de crear-ne un de nou en una carpeta diferent, i així
l'nginx, que apunta a `/etc/letsencrypt/live/crosescolar.cat/`, continua trobant
el bo.

```bash
certbot certonly \
  --dns-cloudflare --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
  --dns-cloudflare-propagation-seconds 30 \
  --cert-name crosescolar.cat \
  --agree-tos \
  -d crosescolar.cat -d '*.crosescolar.cat'

systemctl reload nginx
```

## 7. Comprovar que la renovació ja no demana ningú

Aquest pas és el que dona sentit a tota la guia:

```bash
certbot renew --dry-run
```

Ha d'acabar sol, sense demanar-vos cap registre TXT. I mireu que el certificat
hagi quedat amb l'autenticador nou:

```bash
certbot certificates | grep -A 5 'crosescolar.cat'
grep authenticator /etc/letsencrypt/renewal/crosescolar.cat.conf
# ha de dir: authenticator = dns-cloudflare
```

A partir d'aquí se n'ocupa el cron de root que ja hi ha posat:

```
17 4 * * *   /var/www/crosescolar/tools/renovar-certificat.sh /var/www/crosescolar
```

I el panell ho vigila pel seu compte: al tauler hi surt quant li queda al
certificat, i la superadministració rep un correu si algun dia això s'espatlla.

Per veure-ho de seguida sense esperar el cron:

```bash
cd /var/www/crosescolar
sudo -u www-data php tools/platform.php certificat
```

## Repàs final

| | Com es comprova |
|---|---|
| Els webs van | `curl -sI https://admin.crosescolar.cat/ \| head -1` |
| El correu del domini arriba | Envieu-vos-en un i mireu que hi torni |
| El comodí funciona | `dig +short elquesigui.crosescolar.cat @1.1.1.1` |
| El certificat és nou | `certbot certificates` |
| La renovació és sola | `certbot renew --dry-run` |
| El panell ho sap | Tauler de `admin.crosescolar.cat` |

## Coses a tenir presents

- **El domini continua a Nominalia.** Si algun dia voleu tornar enrere, es
  canvien els servidors de noms altra vegada i au. Guardeu la captura del pas 1.
- **El testimoni de Cloudflare és una clau.** Viu a
  `/etc/letsencrypt/cloudflare.ini`, només llegible per root, i **ha d'entrar a
  la còpia de seguretat del servidor**. Si un dia se sap, esborreu-lo des del
  panell de Cloudflare i feu-ne un de nou.
- **Res de proxy taronja** mentre la plataforma serveixi cros per subdominis amb
  comodí.
- Si algun dia moveu el servidor de lloc, només heu de canviar la IP dels tres
  registres A de Cloudflare; el certificat no se n'assabenta ni li cal.
