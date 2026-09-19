# Cros Escolar La Granada

Web oficial del **Cros Escolar de La Granada** (Alt Penedès): informació de la cursa,
recorreguts amb mapes de Wikiloc, categories i premis, inscripcions i venda en línia
dels tiquets del punt de recàrrega amb Stripe.

- **Domini:** https://cros.afalagranada.cat
- **Idioma:** català
- **Tecnologia:** PHP 8 + MySQL/MariaDB, **sense cap dependència externa** (ni Composer ni npm)
- **Allotjament:** servidor VPS compartit amb CloudPanel (nginx + PHP-FPM)

---

## Què inclou

### Web pública
| Pàgina | Adreça | Contingut |
|---|---|---|
| Portada | `/` | Banner configurable, compte enrere, blocs destacats, recorreguts, programa, galeria, preguntes freqüents, ubicació i patrocinadors |
| Recorreguts | `/recorreguts` | Fitxa de cada circuit amb mapa de Wikiloc i descàrrega del GPX |
| Categories i premis | `/categories-i-premis` | Taula de categories amb horaris, distàncies i premis |
| Punt de recàrrega | `/punt-de-recarrega` | El servei de bar de la cursa: informació i preus (opcionalment, venda en línia amb Stripe). L'adreça antiga `/esmorzar` hi redirigeix |
| Resultats | `/resultats` | Classificació per categories, amb descàrrega en PDF |
| Els meus tiquets | `/els-meus-tiquets` | Consulta dels tiquets comprats amb codi QR i versió imprimible |
| Inscripció | `/inscripcio` | Formulari d'inscripció a les curses |
| Les meves inscripcions | `/les-meves-inscripcions` | Les famílies hi entren amb un codi d'un sol ús que reben per correu i poden revisar i modificar les dades dels seus participants |
| Contacte, avís legal i privacitat | `/contacte`, `/avis-legal`, `/privacitat` | |

### Panell d'administració (`/admin`)
- **Tauler** amb recaptació, tiquets venuts, inscripcions i compte enrere.
- **Continguts**: banner i textos de totes les pàgines, blocs destacats, recorreguts
  (Wikiloc i GPX), categories, premis, programa, preguntes freqüents, galeria i documents.
- **Patrocinadors**: logotips agrupats (institucionals, principals, col·laboradors).
- **Punt de recàrrega**: tipus de tiquet amb preu i existències, comandes, venda manual a taquilla,
  devolucions per Stripe i **validació de tiquets amb lector de QR**.
- **Inscripcions**: llistat, filtres, edició i exportació a CSV, amb **número de dorsal**
  automàtic i descàrrega dels dorsals en PDF.
- **Dorsals**: maqueta en PDF pujada des del panell i col·locació del número, el nom i la
  categoria; les famílies el reben per correu.
- **Resultats**: registre de les arribades a meta per dorsal, classificació per categories,
  publicació al web i exportació en PDF (per categoria o per ordre d'arribada) i CSV.
- **Configuració**: credencials de Stripe (xifrades), correu SMTP, colors, SEO i textos legals.
- **Sistema**: usuaris i rols, registre d'activitat, correus enviats, còpies de seguretat
  i **actualitzacions automàtiques (OTA)**.
- **Web en preparació**: amaga el web al públic amb un avís de «Aviat publicarem el web»
  mentre l'organització hi treballa; qui té la sessió iniciada continua veient-lo sencer.

---

## Instal·lació ràpida

1. Pugeu tot el contingut d'aquesta carpeta a l'arrel del lloc a CloudPanel
   (`/home/<usuari>/htdocs/cros.afalagranada.cat`).
2. Creeu una base de dades MySQL des de CloudPanel.
3. Obriu `https://cros.afalagranada.cat/install.php` i seguiu l'assistent.
4. **Esborreu `install.php`** quan acabi.

Instruccions detallades (nginx, permisos, Stripe, correu): [`docs/instalacio.md`](docs/instalacio.md).

---

## Documentació

| Document | Contingut |
|---|---|
| [`docs/instalacio.md`](docs/instalacio.md) | Instal·lació pas a pas a CloudPanel, nginx i permisos |
| [`docs/stripe.md`](docs/stripe.md) | Configuració de Stripe i del webhook |
| [`docs/actualitzacions.md`](docs/actualitzacions.md) | Sistema OTA, paquets i còpies de seguretat |
| [`docs/manual.md`](docs/manual.md) | Manual d'ús del panell per a l'AFA |
| [`docs/nginx.conf`](docs/nginx.conf) | Blocs de configuració per a nginx |

---

## Estructura del projecte

```
├── index.php              Controlador frontal (totes les peticions)
├── install.php            Assistent d'instal·lació (esborreu-lo després)
├── app/
│   ├── bootstrap.php      Arrencada: constants, autocàrrega, sessió, errors
│   ├── routes.php         Taula de rutes
│   ├── settings_schema.php Definició dels textos i opcions configurables
│   ├── resources.php      Definició dels continguts gestionables (CRUD genèric)
│   ├── version.php        Versió instal·lada (la fa servir l'actualitzador OTA)
│   ├── config.php         Generat per l'instal·lador (no es puja al repositori)
│   ├── core/              Nucli: Db, Router, View, Auth, Mailer, Stripe, Qr, Pdf, Updater…
│   ├── controllers/       Controladors públics i del panell (`admin/`)
│   ├── models/            Comandes, tiquets, inscripcions i continguts
│   ├── migrations/        Migracions SQL numerades
│   └── views/             Plantilles (públiques, panell i correus)
├── assets/                CSS i JavaScript (sense compilació)
├── uploads/               Imatges i documents pujats des del panell
├── storage/               Registres, còpies de seguretat i fitxers temporals
├── tests/                 Proves funcionals (SQLite) i de l'instal·lador (MySQL)
└── tools/build-release.php Generador de paquets d'actualització
```

---

## Desenvolupament i proves

Les proves funcionals aixequen l'aplicació sencera sobre SQLite, sense necessitat de MySQL:

```bash
CROS_TEST_FRESH=1 php tests/env.php             # crea la base de dades de proves
php -S 127.0.0.1:8123 -t . tests/server.php &   # servidor local
php tests/functional.php                        # web i panell
php tests/race.php                              # dorsals i resultats
php tests/account.php                           # «Les meves inscripcions»
php tests/migrations.php                        # migracions de continguts
php tests/pdf.php                               # motor de PDF i maquetes
```

Prova de l'assistent d'instal·lació contra un MySQL real (s'omet si no es configura):

```bash
CROS_DB_NAME=cros CROS_DB_USER=cros CROS_DB_PASS=secret php tests/installer.php
```

Accés de proves: `admin@example.test` / `provaprova`.

Generar un paquet d'actualització:

```bash
php tools/build-release.php --version=1.1.0 --url=https://cros.afalagranada.cat/actualitzacions
```

---

## Requisits del servidor

- PHP 8.0 o superior amb `pdo_mysql`, `mbstring`, `openssl` i `json`
- Recomanats: `gd` (redimensionat d'imatges), `zip` (actualitzacions) i `curl` (Stripe)
- MySQL 5.7+ o MariaDB 10.3+
- Permisos d'escriptura a `app/`, `uploads/` i `storage/`

---

## Llicència i crèdits

Desenvolupat per a l'AFA de l'Escola La Granada. Tots els textos i imatges pujats
són propietat de l'entitat organitzadora.
