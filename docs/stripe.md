# Configuració de Stripe

El web cobra els tiquets de l'esmorzar amb **Stripe Checkout**: el pagament es fa a
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
