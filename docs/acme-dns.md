# El certificat comodí, renovat tot sol (acme-dns)

Un certificat amb comodí (`*.crosescolar.cat`) només es pot demanar validant pel
DNS: Let's Encrypt us fa posar un registre TXT a `_acme-challenge` per demostrar
que el domini és vostre. Si això es fa a mà, **cada renovació —cada 60-90 dies—
demana una persona al davant**, i el dia que se n'oblidi, tots els webs
comencen a donar avís de seguretat.

L'**acme-dns** resol exactament això. És un servidor de DNS diminut que només
sap fer una cosa: guardar registres TXT de `_acme-challenge`. Es delega una
vegada, i a partir d'aquí el certbot hi escriu ell mateix a cada renovació,
sense tocar mai més el DNS del proveïdor.

Això és el que cal quan el vostre proveïdor de DNS **no té API** (Nominalia,
per exemple). Si en té (Cloudflare, OVH, DigitalOcean, Gandi, Hetzner…), no
llegiu aquest document: instal·leu `python3-certbot-dns-<proveïdor>` i ja està.

> **Alternativa sense muntar res:** hi ha un acme-dns públic
> (`auth.acme-dns.io`) que fa la mateixa feina sense instal·lar cap servei. A
> canvi, qui el controli podria demanar certificats del vostre domini. Si teniu
> servidor propi, val més muntar-lo a casa, que és el que explica aquesta guia.

## Abans de començar

1. **Que el vostre proveïdor us deixi crear registres NS i CNAME** en un
   subdomini. Al panell de Nominalia són a la gestió de la zona DNS. Si només
   us hi deixa posar A, MX i TXT, això no es pot fer: aneu a la darrera secció.
2. Tenir a mà la **IP del servidor** i entrar-hi per SSH com a root.
3. Les ordres d'aquí són per a Ubuntu o Debian, i el domini d'exemple és
   `crosescolar.cat` amb la IP `198.51.100.1`: canvieu-los pels vostres.

## 1. Instal·lar l'acme-dns

```bash
VERSIO=2.0.2
cd /tmp
curl -fsSLO "https://github.com/joohoi/acme-dns/releases/download/v${VERSIO}/acme-dns_${VERSIO}_linux_amd64.tar.gz"
tar xzf "acme-dns_${VERSIO}_linux_amd64.tar.gz"
install -m 755 acme-dns /usr/local/bin/acme-dns
acme-dns --help >/dev/null && echo "instal·lat"
```

Un usuari propi i les seves carpetes, que un servei que mira a internet no ha
de córrer com a root:

```bash
useradd --system --no-create-home --shell /usr/sbin/nologin acme-dns
mkdir -p /etc/acme-dns /var/lib/acme-dns
chown acme-dns:acme-dns /var/lib/acme-dns
```

## 2. Configurar-lo

```bash
nano /etc/acme-dns/config.cfg
```

```toml
[general]
# S'escolta a la IP pública, no a totes: així no es baralla amb el
# systemd-resolved, que ja té el 127.0.0.53.
listen = "198.51.100.1:53"
protocol = "both"
# La zona que governarà l'acme-dns. No cal que existeixi enlloc més.
domain = "acme.crosescolar.cat"
# El nom del servidor de noms. Es deixa FORA de la zona delegada a propòsit:
# així el proveïdor no ha de servir cap «glue record», que és el que fa
# fallar aquesta configuració a mig món.
nsname = "ns-acme.crosescolar.cat"
nsadmin = "hola.crosescolar.cat"
records = [
    "acme.crosescolar.cat. NS ns-acme.crosescolar.cat.",
    "ns-acme.crosescolar.cat. A 198.51.100.1",
]
debug = false

[database]
engine = "sqlite3"
connection = "/var/lib/acme-dns/acme-dns.db"

[api]
# L'API només escolta a la màquina mateixa: qui demana certificats és el
# certbot d'aquest servidor i ningú més ha de poder registrar-s'hi.
ip = "127.0.0.1"
port = 8080
tls = "none"
corsorigins = ["*"]
use_header = false
header_name = "X-Forwarded-For"

[logconfig]
loglevel = "info"
logtype = "stdout"
logformat = "text"
```

```bash
chown root:acme-dns /etc/acme-dns/config.cfg
chmod 640 /etc/acme-dns/config.cfg
```

## 3. Engegar-lo com a servei

```bash
nano /etc/systemd/system/acme-dns.service
```

```ini
[Unit]
Description=acme-dns
After=network.target

[Service]
User=acme-dns
Group=acme-dns
ExecStart=/usr/local/bin/acme-dns -c /etc/acme-dns/config.cfg
# Fa falta per escoltar al port 53 sense ser root.
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
PrivateTmp=true
ReadWritePaths=/var/lib/acme-dns
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now acme-dns
systemctl status acme-dns --no-pager | head -12
```

I obriu-li el port, que un servidor de DNS s'ha de poder preguntar des de fora:

```bash
ufw status | head -3      # si diu «inactive», salteu-vos això
ufw allow 53/udp
ufw allow 53/tcp
```

Comproveu que respon a la mateixa màquina:

```bash
dig @127.0.0.1 -p 53 acme.crosescolar.cat NS +short
```

Si no en surt res, mireu `journalctl -u acme-dns -n 30`: el 99% de les vegades
és que la IP de `listen` no és la de la màquina o que el port 53 ja estava
ocupat.

## 4. Delegar la zona al proveïdor (Nominalia)

Al panell de DNS de `crosescolar.cat`, dos registres nous:

| Tipus | Nom | Valor |
|---|---|---|
| A | `ns-acme` | `198.51.100.1` |
| NS | `acme` | `ns-acme.crosescolar.cat.` |

Al panell, el «nom» sol ser només la part de davant (`ns-acme`, `acme`), que ell
ja hi afegeix el domini. El valor de l'NS acaba amb **punt**.

Espereu que es propagui i comproveu-ho **des de fora**, no des del servidor:

```bash
dig acme.crosescolar.cat NS @1.1.1.1 +short
# ha de dir: ns-acme.crosescolar.cat.
dig test.acme.crosescolar.cat TXT @1.1.1.1
# ha de respondre l'acme-dns (encara que sigui amb «no hi ha res»)
```

Fins que això no funcioni, no seguiu: la resta depèn d'aquest pas.

## 5. El connector del certbot

```bash
curl -fsSLo /etc/letsencrypt/acme-dns-auth.py \
  https://raw.githubusercontent.com/joohoi/acme-dns-certbot-joohoi/master/acme-dns-auth.py
chmod 700 /etc/letsencrypt/acme-dns-auth.py
nano /etc/letsencrypt/acme-dns-auth.py
```

Dues línies a tocar a dalt de tot:

```python
#!/usr/bin/env python3
...
ACMEDNS_URL = "http://127.0.0.1:8080"
```

La primera perquè a Ubuntu modern no hi ha cap ordre que es digui `python`,
només `python3`. La segona per apuntar a l'acme-dns d'aquesta màquina en
comptes del públic.

## 6. Demanar el certificat

```bash
certbot certonly \
  --manual --preferred-challenges dns \
  --manual-auth-hook /etc/letsencrypt/acme-dns-auth.py \
  --debug-challenges --agree-tos \
  -d crosescolar.cat -d '*.crosescolar.cat'
```

La primera vegada el connector registra un compte a l'acme-dns i s'atura
ensenyant-vos una cosa com aquesta:

```
Please add the following CNAME record to your main DNS zone:
_acme-challenge.crosescolar.cat CNAME a1b2c3d4-....acme.crosescolar.cat.
```

Aneu al panell de Nominalia i creeu-lo:

| Tipus | Nom | Valor |
|---|---|---|
| CNAME | `_acme-challenge` | el que us hagi dit, acabat en punt |

Comproveu-ho abans de prémer Enter:

```bash
dig _acme-challenge.crosescolar.cat CNAME @1.1.1.1 +short
```

Quan hi surti, torneu al certbot i premeu Enter. **Aquest CNAME es posa una
vegada i no s'hi torna mai més**: a partir d'ara els TXT els escriu el connector
dins de l'acme-dns.

Si el certificat que teníeu era del mateix domini, el certbot us preguntarà si
el voleu renovar o ampliar: digueu-li que sí, que en farà un de nou amb la
configuració bona.

## 7. Comprovar que la renovació és automàtica

Aquest és el pas que de debò importa:

```bash
certbot renew --dry-run
```

Ha d'acabar sense demanar-vos res. Si us demana posar cap TXT, alguna cosa de
l'apartat 5 o 6 no ha quedat bé i la renovació de veritat també us ho demanaria.

Després, poseu l'nginx al dia si encara no té el 443 (ho explica
[docs/vps.md](vps.md)) i deixeu que se n'ocupi el cron de root, que ja hi és:

```
17 4 * * *   /var/www/crosescolar/tools/renovar-certificat.sh /var/www/crosescolar
```

El panell ho vigila pel seu compte: al tauler hi surt quant li queda al
certificat, i la superadministració rep un correu si un dia això s'espatlla.

## Si Nominalia no us deixa crear registres NS

Aleshores la delegació no es pot fer i us queden dues sortides:

- **Moure el DNS** a un proveïdor que sí que ho permeti (Cloudflare és gratuït
  per a això i, de fet, té connector propi per al certbot, amb la qual cosa ni
  us caldria l'acme-dns). El domini es queda a Nominalia; només se'n canvien
  els servidors de noms.
- **Deixar el comodí** i fer certificats **per nom** amb
  `certbot --nginx -d crosescolar.cat -d admin.crosescolar.cat -d …`, que es
  renoven sols des del primer dia. L'única feina és tornar a executar l'ordre
  quan doneu d'alta un cros nou, i el panell us avisa quan toca, amb l'ordre a
  punt de copiar.

## Què heu deixat en marxa

| | |
|---|---|
| Un servei nou | `acme-dns`, escoltant al port 53, amb usuari propi i l'API només accessible des de la màquina |
| Una delegació | `acme.crosescolar.cat` la governa el vostre servidor |
| Un CNAME | `_acme-challenge.crosescolar.cat`, posat una sola vegada |
| Credencials | `/etc/letsencrypt/acmedns.json`, només llegible per root: **entren a la còpia de seguretat del servidor** |

Val la pena tenir present que heu publicat un servei de DNS a internet. És
petit i només fa una cosa, però convé actualitzar-lo de tant en tant
(`acme-dns --version` i la pàgina de versions del projecte).
