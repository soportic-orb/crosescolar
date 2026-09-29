# Configuració de Stripe

El web cobra els tiquets del punt de recàrrega amb **Stripe Checkout**: el pagament es fa a
la pàgina segura de Stripe i el web no desa mai cap dada de la targeta.

## 1. Crear el compte

1. Creeu un compte a [stripe.com](https://dashboard.stripe.com/register) a nom de l'entitat.
2. Completeu les dades fiscals de l'AFA per poder rebre els diners al compte bancari.

## 2. Copiar les claus

Al tauler de Stripe, a **Developers → API keys**:

| Camp del panell | Clau de Stripe |
|---|---|
| Clau pública de proves | `pk_test_…` |
| Clau secreta de proves | `sk_test_…` |
| Clau pública de producció | `pk_live_…` |
| Clau secreta de producció | `sk_live_…` |

Enganxeu-les a **Configuració → Pagaments**. Les claus secretes es desen **xifrades**
a la base de dades amb la clau de l'aplicació.

## 3. Configurar el webhook

El webhook confirma els pagaments encara que la persona tanqui el navegador.

1. A Stripe: **Developers → Webhooks → Add endpoint**.
2. URL: `https://cros.afalagranada.cat/stripe/webhook`
3. Esdeveniments a escoltar:
   - `checkout.session.completed`
   - `checkout.session.expired`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.async_payment_failed`
   - `charge.refunded`
4. Copieu el **Signing secret** (`whsec_…`) al camp corresponent del panell
   (secret de proves o de producció segons el mode).

> El webhook es valida amb signatura HMAC i una tolerància de 5 minuts.
> Les peticions sense signatura vàlida es rebutgen amb un error 400.

## 4. Provar-ho

1. Deixeu el **Mode** en «Proves».
2. A **Configuració → Pagaments**, premeu *Provar la connexió amb Stripe*.
3. Feu una compra al web amb la targeta de prova `4242 4242 4242 4242`,
   qualsevol data futura i qualsevol CVC.
4. Comproveu que:
   - la comanda apareix com a **pagada** a *Comandes*;
   - arriba el correu amb els tiquets i el codi QR;
   - el tiquet es valida correctament a *Validar tiquets*.

## 5. Passar a producció

1. Canvieu **Mode** a «Producció».
2. Assegureu-vos d'haver omplert les claus `live` i el webhook de producció
   (cal crear-ne un de nou al mode *live* de Stripe).
3. Feu una compra real de prova amb una targeta pròpia i, si voleu, feu-ne la
   devolució des de la fitxa de la comanda (*Devolució per Stripe*).

## Comissions i terminis

Stripe cobra una comissió per transacció (consulteu les tarifes vigents per a
targetes europees). Els diners s'ingressen al compte bancari de l'entitat segons
el calendari de pagaments configurat a Stripe.

## Si el pagament en línia no està disponible

Si no hi ha credencials configurades, el botó de pagament es desactiva i la web
convida a comprar els tiquets presencialment. L'organització sempre pot registrar
vendes en efectiu des de **Comandes → Venda manual**.

---

# El Stripe de la plataforma

Tot el que hi ha més amunt és el Stripe **de cada client**, amb què cobra els seus
participants. Això d'aquí és l'altre: el Stripe **de la plataforma**, amb què cobreu
vosaltres l'activació dels webs. Són dos comptes i dos jocs de claus que no es toquen
mai; si els barregeu, els diners d'uns acaben al compte dels altres.

| | El Stripe del client | El Stripe de la plataforma |
|---|---|---|
| Qui cobra | L'entitat organitzadora | Qui manté la plataforma |
| Què | Inscripcions i tiquets | L'activació del web (pagament únic) |
| On es configura | Panell del client → Cobraments | Superadministració → Stripe de la plataforma |
| Webhook | `https://<elseuweb>/stripe/webhook` | `https://<domini-principal>/pagament/avis` |

## 1. El compte

Cal un compte de Stripe **a nom de qui presta el servei**, amb les dades fiscals
completes i un compte bancari verificat. És el mateix compte que després surt a
**Superadministració → Dades fiscals**, que és el que s'imprimeix a les factures.

## 2. Les claus

Al tauler de Stripe, a dalt a la dreta, hi ha el commutador **Test mode**. Les claus de
proves i les de producció són diferents i s'omplen totes dues: així podeu provar-ho sense
moure diners i canviar de mode quan estigui a punt.

A **Developers → API keys**:

| Camp del panell | Clau de Stripe | On es veu |
|---|---|---|
| Clau pública de proves | `pk_test_…` | Es veu sencera |
| Clau secreta de proves | `sk_test_…` | Cal prémer *Reveal* |
| Clau pública de producció | `pk_live_…` | Es veu sencera |
| Clau secreta de producció | `sk_live_…` | Només es veu **un cop**: copieu-la de seguida |

Enganxeu-les a **Superadministració → Configuració → Stripe de la plataforma**. Les
secretes es desen **xifrades** amb la clau de l'aplicació.

> La clau pública no és cap secret: viatja al navegador de qui paga, i ha de ser-hi
> perquè el formulari de la targeta funcioni. La secreta no surt mai del servidor.

## 3. El webhook

Aquí és on més gent s'equivoca, i és la part que més importa.

**Per què cal.** Quan algú paga, el navegador torna a `/admin/activacio/tornada` i allà es
confirma el cobrament. Però si tanca la pestanya, es queda sense cobertura o el mòbil se li
apaga just després de pagar, aquell retorn no passa mai: Stripe ha cobrat i la plataforma
no se n'assabenta. El webhook és un avís que Stripe envia **al servidor**, pel seu compte,
i és l'única xarxa que hi ha per a aquests casos. Sense webhook, tard o d'hora tindreu un
client que ha pagat i que no pot publicar el web.

**L'adreça.** És al domini públic de la plataforma, no al panell:

```
https://esportweb.cat/pagament/avis
```

Ha de ser el **domini principal** (el que teniu a `base_domain`), amb HTTPS i accessible
des de fora. No poseu-hi `admin.`: aquella adreça demana sessió i Stripe no en té cap.

**Els passos**, al tauler de Stripe:

1. **Developers → Webhooks → Add endpoint**.
2. **Endpoint URL**: l'adreça de sobre.
3. **Select events**: aquests sis, ni més ni menys.

| Esdeveniment | Per què |
|---|---|
| `payment_intent.succeeded` | El pagament fet des del panell, que és el camí normal |
| `payment_intent.payment_failed` | La targeta s'ha rebutjat |
| `payment_intent.canceled` | El pagament s'ha anul·lat |
| `checkout.session.completed` | El pagament fet a la pàgina de Stripe |
| `checkout.session.expired` | Aquella sessió ha caducat sense pagar |
| `checkout.session.async_payment_succeeded` | Mètodes que triguen (domiciliacions) |

4. **Add endpoint**, i a la fitxa que surt, **Reveal** del **Signing secret** (`whsec_…`).
5. Enganxeu-lo al camp **Secret del webhook** del panell, el de proves o el de producció
   **segons el mode en què esteu**.

> Els dos primers esdeveniments són els que fan falta des que el pagament es fa **dins del
> panell**. Si només hi teniu els de `checkout.session`, el webhook arriba i es descarta,
> i tornem a tenir el forat que volíem tapar.

**El secret és per endpoint i per mode.** Si creeu el webhook en mode de proves i després
en feu un altre en producció, són dos secrets diferents i van a dos camps diferents. Un
secret enganxat al camp equivocat fa que tots els avisos es rebutgin amb un 400, i al
registre hi sortirà «Avís de Stripe rebutjat».

## 4. Provar-ho abans de cobrar de debò

1. Mode **Proves**, amb les claus `test` i el webhook de test posats.
2. A **Configuració → Pagament d'activació**, activeu el cobrament i poseu-hi un preu.
3. Des del panell d'un client de prova, premeu **Publicar web** i pagueu amb la targeta
   `4242 4242 4242 4242`, qualsevol data futura i qualsevol CVC.
4. Comproveu les quatre coses:
   - a **Superadministració → Pagaments** el cobrament surt **pagat**;
   - se n'ha emès la **factura**, amb el desglossament que toqui;
   - el client ja pot **publicar el web**;
   - a Stripe, **Developers → Webhooks → el vostre endpoint**, els intents surten amb
     **200**. Si hi veieu 400, és el secret; si hi veieu 404 o 502, és l'adreça o l'nginx.

Per provar la xarxa de seguretat de debò: feu un pagament i **tanqueu la pestanya** just
després de prémer el botó, sense esperar que torni. El cobrament ha de quedar pagat
igualment en pocs segons, perquè arriba pel webhook.

## 5. Passar a producció

1. Ompliu les claus `live` si encara no hi són.
2. Creeu **un webhook nou** amb el Test mode desactivat, amb la mateixa adreça i els
   mateixos sis esdeveniments, i enganxeu el seu secret al camp de producció.
3. Canvieu el **Mode** a «Producció».
4. Feu un cobrament real de poc import amb una targeta vostra i torneu-lo des de la fitxa
   del pagament, per comprovar que la devolució també funciona.

## Si alguna cosa no rutlla

| Símptoma | Què mirar |
|---|---|
| A Stripe els intents surten **400** | El secret del webhook no és el d'aquell endpoint, o és al camp de l'altre mode |
| Surten **404** | L'adreça: ha de ser el domini pelat i acabar en `/pagament/avis` |
| Surten **200** però el cobrament es queda pendent | Que l'esdeveniment sigui dels sis de la llista; mireu el registre de la plataforma |
| «La plataforma no té Stripe configurat» | Falten la clau pública o la secreta del mode actiu |
| El formulari de la targeta no surt | La clau **pública** del mode actiu és buida o no és la que toca |
| El client paga i no s'activa | Mireu `storage/logs/` de la plataforma: hi surt què ha dit Stripe |
