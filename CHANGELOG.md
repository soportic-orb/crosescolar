# Registre de canvis

El format segueix [Keep a Changelog](https://keepachangelog.com/ca/1.1.0/)
i el versionatge semàntic.

## [1.0.0] — 2026-09-17

Primera versió del web del Cros Escolar La Granada.

### Afegit
- Portada amb banner configurable, compte enrere, blocs destacats, programa,
  galeria, preguntes freqüents i ubicació.
- Recorreguts amb mapes de Wikiloc (càrrega diferida) i descàrrega de GPX.
- Pàgina de categories i premis amb horaris de sortida.
- Venda de tiquets de l'esmorzar amb Stripe Checkout, webhook signat,
  generació de tiquets amb codi QR i correu de confirmació.
- Consulta pública dels tiquets comprats i versió imprimible.
- Formulari d'inscripció a les curses amb assignació automàtica de categoria.
- Panell d'administració complet: continguts, patrocinadors, comandes,
  inscripcions, validació de tiquets per QR, usuaris i configuració.
- Instal·lador automàtic amb comprovació de requisits i continguts d'exemple.
- Sistema d'actualitzacions OTA amb verificació SHA-256, còpies de seguretat
  i migracions de base de dades.
- Proves funcionals automatitzades (45 comprovacions) sobre SQLite.
