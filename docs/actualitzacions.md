# Actualitzacions automàtiques (OTA)

El web es pot actualitzar sense tornar a pujar fitxers per SFTP: des del panell
comprova si hi ha una versió nova, la descarrega, en verifica la integritat,
fa una còpia de seguretat i substitueix els fitxers.

## Com funciona

```
Manifest remot (JSON)  →  Comprovació de versió  →  Descàrrega del ZIP
      →  Verificació SHA-256  →  Còpia de seguretat  →  Substitució de fitxers
      →  Migracions de base de dades  →  Fi del mode manteniment
```

Durant el procés el web mostra una pàgina de manteniment als visitants; el panell
continua accessible.

**Mai no se sobreescriuen**: `app/config.php`, `uploads/` i `storage/`.

## Configuració

A **Configuració → Actualitzacions**:

| Camp | Descripció |
|---|---|
| URL del manifest | Adreça del fitxer `manifest.json` o de l'API de versions de GitHub |
| Token d'accés | Només per a dipòsits privats (s'envia com a `Authorization: Bearer`) |
| Comprovar automàticament | Consulta el manifest cada 6 hores |
| Còpia de seguretat abans d'actualitzar | Recomanat: deixeu-ho activat |

### Format del manifest propi

```json
{
  "version": "1.1.0",
  "released": "2026-11-02",
  "min_php": "8.0.0",
  "zip_url": "https://cros.afalagranada.cat/actualitzacions/cros-escolar-1.1.0.zip",
  "sha256": "…",
  "notes": "Novetats d'aquesta versió"
}
```

També s'accepta directament la resposta de l'API de GitHub
(`https://api.github.com/repos/<usuari>/<repositori>/releases/latest`).

## Publicar una versió nova

```bash
php tools/build-release.php --version=1.1.0 \
    --url=https://cros.afalagranada.cat/actualitzacions \
    --notes="Nova galeria i millores als recorreguts"
```

Es generen a `dist/`:

- `cros-escolar-1.1.0.zip` — paquet complet (sense `config.php`, `uploads/` ni `storage/`)
- `manifest.json` — amb la versió, l'URL i el resum SHA-256

Pugeu tots dos fitxers a la carpeta pública d'actualitzacions (o adjunteu el ZIP a
una *release* de GitHub) i el panell detectarà la versió nova.

## Instal·lació manual d'un paquet

A **Sistema → Actualitzacions → Instal·lar un paquet manualment** podeu pujar el ZIP
directament. És la via recomanada si el servidor no té sortida a Internet.

## Còpies de seguretat i restauració

Cada actualització crea `storage/backups/backup-<versió>-<data>.zip` amb:

- tots els fitxers de l'aplicació (excepte `uploads/` i `storage/`)
- `database.sql`, un bolcat complet de la base de dades

Per restaurar-la:

```bash
cd /home/cros-afa/htdocs/cros.afalagranada.cat
unzip -o storage/backups/backup-1.0.0-2026-11-02-101500.zip -d .
mysql -u cros -p cros < database.sql
```

## Migracions de base de dades

Els canvis d'esquema es distribueixen com a fitxers numerats a `app/migrations/`
(`0002_…sql`, `0003_…sql`). S'apliquen automàticament en actualitzar i queden
registrats a la taula `migrations`; mai no s'executen dues vegades.

## Requisits

- Extensió `zip` de PHP activada
- Permisos d'escriptura a l'arrel del lloc (l'usuari de PHP-FPM ha de ser el propietari)
- Sortida HTTPS des del servidor (per a la descàrrega automàtica)
