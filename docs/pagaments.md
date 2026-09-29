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
| `/pagaments` (al panell de superadministració) | El llistat i la gestió |

---

## Els imports

Els preus s'escriuen **amb l'impost inclòs**, que és com els diu tothom qui ven un tiquet
d'esmorzar. Al document, l'impost se'n desglossa cap enrere:

```
base = round(total / (1 + tipus/100))
impost = total − base
```

Tot es desa en **cèntims**, sempre. No hi ha cap decimal enlloc que es pugui arrodonir sol.

## La numeració

Cada sèrie i any tenen el seu comptador en una taula (`billing_counters`,
`platform_invoice_counters`). No es calcula amb un `MAX()`: dues vendes alhora podrien
acabar amb el mateix número, i un forat a la numeració és el primer que mira qui revisa una
comptabilitat.

Un document emès **no canvia mai més**. Les dades fiscals de qui l'emet es congelen a dins
el dia que s'emet: si després es modifiquen, el que ja s'havia lliurat es queda com estava.
