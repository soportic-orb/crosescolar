# Els dos sistemes de cobrament

Al Cros Escolar hi ha **dos** sistemes de cobrament que no es toquen mai. Tenir-ho clar
estalvia molts maldecaps, perquè és el que més fàcilment es barreja.

|  | **La plataforma cobra al client** | **El client cobra als participants** |
|---|---|---|
| Què | L'activació del web (pagament únic) | Inscripcions i tiquets del punt de recàrrega |
| Qui paga | L'AFA, l'escola o el club | Les famílies |
| Passarel·la | El Stripe de la plataforma | La que el client triï: Stripe, PayPal o Redsys |
| On viu | Base de dades de la plataforma | Base de dades del client |
| Taules | `platform_payments`, `platform_invoices` | `payments`, `payment_items`, `billing_documents` |
| Codi | `Cros\Platform\Charge`, `Cros\Platform\Invoice`, `Cros\Platform\Plan` | `Cros\Models\Payment`, `Cros\Models\Billing`, `Cros\Payments\*` |
| Dades fiscals | Les de qui manté la plataforma | Les de l'entitat organitzadora |
| Numeració | `A-2026-0001` | `R-2026-0001` (rebuts) i `F-2026-0001` (factures) |
| Panell | Superadministració → Pagaments | Panell del cros → Cobraments |

**A un rebut d'un cros no hi surt mai cap dada de la plataforma**, i a una factura de la
plataforma no hi surt mai cap dada d'un participant. Cada costat llegeix la seva
configuració i la seva base de dades, i hi ha proves automàtiques
(`tests/cobraments.php` i `tests/console.php`) que ho comproven llegint el text dels PDF.

---

## Les passarel·les del client, per dins

Una passarel·la és un mòdul: una classe que estén `Cros\Payments\Gateway` i s'apunta a
`Cros\Payments\Gateways::MODULES`. Només se'n pot activar una alhora.

Totes fan el mateix vist des de fora —endur-se qui paga a un lloc segur i tornar-lo amb un
sí o un no—, però per dins no s'assemblen gens. Per això `begin()` no torna una adreça
sinó **què cal fer**:

```php
['mode' => 'redirect', 'url' => 'https://…', 'fields' => [], 'ref' => 'cs_test_…']
['mode' => 'form',     'url' => 'https://sis.redsys.es/…', 'fields' => [...], 'ref' => '0001ABCD1234']
```

| | Stripe | PayPal | Redsys |
|---|---|---|---|
| Com hi va | Redirecció a Checkout | Redirecció a l'enllaç d'aprovació | Formulari signat que s'autoenvia |
| Quan es cobra | A la pàgina de Stripe | A la **captura**, en tornar | Al TPV |
| Qui confirma | El webhook signat | La captura + la consulta de la comanda | La **notificació en línia** del TPV |
| Devolucions | Sí, des del panell | Sí, des del panell | No: des del portal del banc |

**El que mana sempre és l'avís del servidor**, no el retorn del navegador: si algú tanca
la finestra just després de pagar, aquell és l'únic que arriba. Per això
`Checkout::finish()` es pot cridar dues vegades sense fer cap mal.

### La signatura de Redsys

No hi ha cap llibreria. La clau de la signatura surt de xifrar el número de comanda amb la
clau del comerç en 3DES (vector d'inicialització a zero, farciment a múltiple de 8), i la
signatura és un HMAC-SHA256 dels paràmetres amb aquella clau. Ho fa l'`openssl` que ja
porta el PHP.

El número de comanda que vol el TPV té dotze caràcters i els quatre primers han de ser
xifres: es fa amb l'identificador del cobrament i el seu codi.

---

## Adreces

### Al web d'un cros

| Adreça | Què fa |
|---|---|
| `/pagament/{token}` | L'estat d'un cobrament. El testimoni és el que dona accés |
| `/pagament/{token}/anar` | Comença el pagament amb la passarel·la activa |
| `/pagament/{token}/tornada` | Torna el navegador |
| `/pagament/{token}/anullat` | Qui s'ha fet enrere |
| `/pagament/{token}/document` | El rebut o la factura en PDF |
| `/pagament/avis/{passarel·la}` | L'avís del servidor de la passarel·la |
| `/stripe/webhook` | El webhook de Stripe (es manté per als enllaços ja configurats) |

### A la plataforma

| Adreça | Què fa |
|---|---|
| `https://<domini>/pagament/avis` | L'avís de Stripe sobre els pagaments d'activació |
| `/admin/activacio` (al panell d'un cros) | La pantalla d'activació del client |
| `/admin/activacio/publicar` | El pagament amb la targeta, sense sortir del panell |
| `/admin/activacio/tornada` | On torna el navegador quan s'ha pagat |
| `/pagaments` (al panell de superadministració) | El llistat i la gestió |

### Pagar sense sortir del panell

Qui vol publicar el seu web no ha de marxar enlloc. La barra de dalt de tot del panell
avisa que el web encara no és públic i el botó **«Publicar web»** porta a
`/admin/activacio/publicar`, on hi ha el preu desglossat i el formulari de la targeta.

El formulari el dibuixa **Stripe.js** (`js.stripe.com/v3`) dins d'una finestreta seva, a
partir d'un **PaymentIntent** que crea `Cros\Platform\Charge::intent()`. Ni el número de
la targeta ni el CVC no passen mai pel nostre servidor: nosaltres només en sabem el
`client_secret`, que no serveix per cobrar res més que aquell import.

Quan el pagament acaba, Stripe torna el navegador a `/admin/activacio/tornada` i
`Charge::confirm()` mira com ha quedat el PaymentIntent. Si va bé, s'activa la instància i
s'emet la factura, exactament igual que si s'hagués pagat a la pàgina de Stripe: les dues
vies acaben al mateix `markPaid()`, de manera que no hi ha cap camí que activi un web
sense factura.

Un web que ja s'hagi cobrat no es torna a cobrar: `Charge::forActivation()` reaprofita el
cobrament pendent que hi hagi i `intent()` reaprofita el PaymentIntent mentre no s'hagi
gastat i l'import no hagi canviat.

---

## Els imports

Els preus s'escriuen **amb l'impost inclòs**, que és com els diu tothom qui ven un tiquet
d'esmorzar. Al document, l'impost se'n desglossa cap enrere:

```
base = round(total / (1 + tipus/100))
impost = total − base
```

Tot es desa en **cèntims**, sempre. No hi ha cap decimal enlloc que es pugui arrodonir sol.

### El pagament d'activació va al revés

El preu del plà **no** porta l'impost inclòs: és la **base imposable**, i els dos impostos
es calculen a sobre, cadascun activable a part:

```
IVA   = base × tipus/100      s'hi suma
IRPF  = base × tipus/100      s'hi resta
total = base + IVA − IRPF
```

L'IVA se suma perquè és el que es repercuteix a qui paga. L'IRPF es resta perquè és una
**retenció**: aquells diners el client no ens els paga a nosaltres sinó a Hisenda en nom
nostre. Per això el que es cobra amb targeta és més petit del que diu la factura.

### La retenció depèn de qui paga

Qui reté és qui paga, i **només retenen les persones jurídiques** (entitats, clubs, AFA,
empreses) i els professionals. A un **particular** no se li reté mai: encara que la
retenció estigui activada al panell, la seva factura és base + IVA i el total que paga és
més gran.

Cadascú diu quina mena de client és **en donar-se d'alta**, i es desa a `clients.kind`
(`company` o `person`). El superadministrador ho pot corregir a la fitxa del web. Els
clients que ja hi eren abans d'això neixen com a entitat, que és el que se'ls estava
aplicant.

Qui decideix és `Client::withholds()`, i `Plan::amountsFor($client)` en treu els números.
Al crear el cobrament, la decisió **es congela**: `platform_payments.payer_kind`,
`irpf_rate` i `irpf_cents` guarden el que valia aquell dia, de manera que corregir la fitxa
d'un client no toca cap factura ja emesa.

## La numeració

Cada sèrie i any tenen el seu comptador en una taula (`billing_counters`,
`platform_invoice_counters`). No es calcula amb un `MAX()`: dues vendes alhora podrien
acabar amb el mateix número, i un forat a la numeració és el primer que mira qui revisa una
comptabilitat.

Un document emès **no canvia mai més**. Les dades fiscals de qui l'emet es congelen a dins
el dia que s'emet: si després es modifiquen, el que ja s'havia lliurat es queda com estava.
