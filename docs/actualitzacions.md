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

## «L'origen d'actualitzacions encara no té cap versió publicada»

És l'estat normal fins que es publica la primera versió: l'API de GitHub respon amb un
404 a `releases/latest` quan el dipòsit no té cap *release*. El panell ho detecta,
consulta la llista de versions i ho mostra com un avís informatiu, no com un error.

Per deixar-ho a punt, trieu una de les dues opcions:

**A. Amb GitHub (recomanat si ja hi teniu el codi)**

El dipòsit porta un flux de treball que valida el codi, executa totes les proves,
genera el paquet i publica la versió amb el ZIP i el manifest adjunts. Es pot llançar
de dues maneres:

- **Des del web de GitHub:** pestanya *Actions* → *Publicar versió* → **Run workflow**.
  El camp «Versió» és opcional: si es deixa buit s'agafa la d'`app/version.php`.
- **Amb una etiqueta:** `git tag v1.1.1 && git push origin v1.1.1`.

Quan acabi, al panell: **Sistema → Actualitzacions → Comprovar ara**.

El resum SHA-256 del paquet s'inclou a les notes de la versió i el panell el fa servir
per comprovar que el fitxer descarregat no s'ha manipulat.

**B. Sense GitHub (allotjant els fitxers al mateix servidor)**

1. `php tools/build-release.php --version=1.1.0 --url=https://cros.afalagranada.cat/actualitzacions`
2. Pugeu `dist/manifest.json` i `dist/cros-escolar-1.1.0.zip` a
   `/actualitzacions/` del web (o a qualsevol adreça pública).
3. Poseu `https://cros.afalagranada.cat/actualitzacions/manifest.json` a
   **Configuració → Actualitzacions → URL del manifest**.

Mentrestant, sempre podeu instal·lar un paquet a mà des de
**Sistema → Actualitzacions → Instal·lar un paquet manualment**.

### Dipòsits privats

Si el dipòsit de GitHub és privat, cal un token d'accés amb permís de lectura
(«Contents: read») a **Configuració → Actualitzacions → Token d'accés**. El panell
el fa servir tant per llegir la llista de versions com per descarregar el paquet
(en aquest cas fa servir l'adreça de l'API, perquè l'enllaç de descàrrega directa
no accepta tokens).

## Missatges d'error habituals

| Missatge | Què vol dir | Solució |
|---|---|---|
| L'origen encara no té cap versió publicada | L'origen respon bé però no hi ha cap *release* | Publiqueu-ne una (vegeu més amunt) |
| GitHub respon que no ha trobat res (404) | El dipòsit no existeix o és privat sense token | Reviseu l'URL o afegiu un token |
| Ha denegat l'accés (401/403) | Token incorrecte o caducat | Torneu a generar el token |
| S'ha superat el límit de consultes | Massa consultes anònimes a l'API de GitHub | Espereu una estona o poseu un token |
| No s'ha trobat el manifest (404) | L'URL del `manifest.json` no existeix | Comproveu que el fitxer és accessible pel navegador |
| La resposta no és un JSON vàlid | L'URL retorna una pàgina HTML | Comproveu que apunteu al fitxer i no a una pàgina d'error |

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
