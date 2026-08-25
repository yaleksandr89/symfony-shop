# Projektstart

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../getting-started.md) | [English](../en/getting-started.md) | [Español](../es/getting-started.md) | [中文](../zh/getting-started.md) | [Français](../fr/getting-started.md) | **Deutsch** |


Der unterstützte lokale Entwicklungsablauf basiert auf Docker Compose. PHP, Composer, Node.js, PostgreSQL und die für Panther benötigte Browser-Umgebung müssen nicht auf dem Host installiert werden.

Ein Betrieb mit direkt im Betriebssystem installierten PHP, Composer, PostgreSQL und Node.js wird vom Projekt nicht als offizieller Ablauf unterstützt: Makefile, CI, Testbefehle und Browser-Umgebung sind auf Docker ausgelegt. Eine manuelle Installation ist technisch möglich, gehört aber nicht zum verifizierten Projektvertrag und wird deshalb hier nicht dokumentiert.

## Voraussetzungen

Für die normale Arbeit werden benötigt:

- Git;
- Make;
- Docker mit Compose-Unterstützung;
- Git LFS, empfohlen beim Klonen mit Git; das Chrome-for-Testing-Archiv kann auch auf anderem Weg bezogen werden.

> [!NOTE]
> Make ist ein übliches Kommandozeilenwerkzeug auf Unix-ähnlichen Systemen. Unter Linux und macOS kann das Projekt direkt im Terminal gestartet werden. Unter Windows wird WSL2 zusammen mit Docker Desktop empfohlen.

## Erster Start mit Git LFS

| Befehl | Zweck | Hinweis |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Repository klonen | |
| `cd symfony-shop` | In das Projektverzeichnis wechseln | |
| `git lfs install` | Git LFS aktivieren | Normalerweise einmal pro Benutzer |
| `git lfs pull` | Chrome for Testing herunterladen | Vor `make build` ausführen |
| `make init` | `.env.docker` und lokale Verzeichnisse anlegen | Verwendet `.env.docker.example` und UID/GID des Host-Benutzers |
| `make build` | PHP-Image bauen | |
| `make up` | `php`, `nginx` und `postgres` starten | |
| `make composer-install` | PHP-Abhängigkeiten installieren | Verwendet `composer.lock` |
| `make npm-install` | Frontend-Abhängigkeiten installieren | Verwendet `package-lock.json` |
| `make assets-build` | Frontend-Assets bauen | |
| `make migrate` | Doctrine-Migrationen anwenden | |
| `make demo-init` | Demo-Daten initialisieren | Nur lokale `dev`/`test`-Daten |

Mit der Standardkonfiguration ist die Anwendung unter [http://localhost:8080](http://localhost:8080) erreichbar. Der Port kann über `APP_PORT` in `.env.docker` geändert werden.

> [!WARNING]
> `make demo-init` erstellt Demo-Bestellungen neu. Verwende den Befehl nur mit einer Datenbank, deren Daten ersetzt werden dürfen.

## Git LFS und Chrome for Testing

Panther verwendet Chrome for Testing, das während `make build` in das PHP-Image installiert wird. Das Browser-Archiv liegt in Git LFS, Chromedriver dagegen als normale Git-Datei.

| Artefakt | Pfad | Speicherung |
|---|---|---|
| Chrome for Testing | `bin/chrome-linux64-150.0.7871.46.zip` | Git LFS |
| Chromedriver | `bin/drivers/chromedriver` | normales Git |

Das Dockerfile erwartet exakt Chrome for Testing `150.0.7871.46`. Ersetze diese Version nicht durch die aktuelle stabile Chrome-Version, ohne gleichzeitig die Docker-/Panther-Konfiguration anzupassen und zu prüfen.

Für das festgelegte Archiv wurden bestätigt:

| Prüfung | Erwarteter Wert |
|---|---|
| Größe | `186933179` Bytes |
| SHA-256 | `ad115a7498a17f53f6ed0914458326c6516addc756224db14c32184a9b1ab078` |

Das Archiv kann auf drei Arten bezogen werden.

### Variante 1 — Git LFS

Empfohlener Weg für ein normales `git clone`:

```text
git lfs install
git lfs pull
```

Offizieller Client und Installationshinweise: [git-lfs.com](https://git-lfs.com/).

### Variante 2 — Symfony-Shop-Release-Archiv

Ab Version `v3.0.0` kann das Projekt-ZIP von der Seite [Releases](https://github.com/yaleksandr89/symfony-shop/releases) heruntergeladen werden. Chrome for Testing ist bereits enthalten, sodass Git LFS für diesen Weg nicht installiert werden muss.

Verwende das Archiv der tatsächlich benötigten Projektversion: ältere Releases können eine andere Chrome-Version und abweichende Konfiguration enthalten.

### Variante 3 — offizielles Chrome for Testing

Version `150.0.7871.46` ist im offiziellen Chrome-for-Testing-Katalog veröffentlicht:

- [Metadaten für Version `150.0.7871.46`](https://googlechromelabs.github.io/chrome-for-testing/150.0.7871.46.json);
- [offizielles Chrome-for-Testing-Archiv für Linux x64](https://storage.googleapis.com/chrome-for-testing-public/150.0.7871.46/linux64/chrome-linux64.zip).

Speichere die heruntergeladene Datei unter:

```text
bin/chrome-linux64-150.0.7871.46.zip
```

Nach einem manuellen Download immer Größe, SHA-256 und ZIP-Integrität anhand der Tabelle oben prüfen.

## Chrome-Archiv prüfen

| Befehl | Prüfung |
|---|---|
| `git lfs ls-files` | Archiv ist bei Nutzung des LFS-Ablaufs in Git LFS registriert |
| `wc -c < bin/chrome-linux64-150.0.7871.46.zip` | Dateigröße |
| `sha256sum bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 unter Linux/WSL |
| `shasum -a 256 bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 unter macOS |
| `unzip -tq bin/chrome-linux64-150.0.7871.46.zip` | ZIP-Integrität |

Ist die Datei nur ungefähr hundert Bytes groß und beginnt mit `version https://git-lfs.github.com/spec/v1`, liegt in der Arbeitskopie noch ein Git-LFS-Pointer. Führe `git lfs pull` aus oder ersetze den Pointer durch das echte Archiv aus einer der beiden Alternativen oben.

Nach jedem manuellen Austausch muss das Archiv denselben erwarteten SHA-256 liefern. Bei abweichender Prüfsumme weder bauen noch die Datei committen.

## Lokale Konfiguration

`make init` erstellt `.env.docker` aus `.env.docker.example`, setzt die aktuellen `HOST_UID` und `HOST_GID` ein und legt `var/cache`, `var/log` und `public/uploads` an.

Existiert `.env.docker` bereits, wird sie nicht überschrieben. Lokale Anwendungs-Secrets und OAuth-Zugangsdaten gehören in `.env.local`, nicht in `.env.docker`.

> [!IMPORTANT]
> Werte aus `.env.docker` werden dem PHP-Container als Prozess-Umgebungsvariablen übergeben und haben Vorrang vor gleichnamigen Werten aus `.env.local`. Das ist besonders für Panther, Datenbankeinstellungen und versehentlich doppelt definierte Schlüssel wichtig.

Umgebungsebenen und Prioritäten sind im [Konfigurationsleitfaden](configuration.md) beschrieben.

## Docker verwalten

| Befehl | Zweck | Hinweis |
|---|---|---|
| `make ps` | Projektcontainer anzeigen | |
| `make restart php` | PHP neu starten | Auch `nginx` und `postgres` möglich |
| `make log php` | PHP-Log verfolgen | Auch `nginx` und `postgres` möglich |
| `make log-all` | Alle Logs anzeigen | |
| `make in php` | Bash im PHP-Container als Benutzer `app` öffnen | |
| `make down` | Umgebung stoppen | PostgreSQL-Volume bleibt erhalten |

Die vollständige Liste der Make-Ziele einschließlich Tests, Checks, Coverage und destruktiver Befehle steht im [Entwicklungsleitfaden](development.md).

## Wenn der erste Start fehlschlägt

| Symptom | Prüfen |
|---|---|
| `make build` scheitert beim Entpacken von Chrome | Größe, SHA-256 und `unzip -tq` des Chrome-Archivs |
| Chrome-Datei enthält `git-lfs.github.com/spec/v1` | ob `git lfs pull` ausgeführt wurde; bei Release/manuellem Download Pointer durch echtes ZIP ersetzen |
| `.env.docker` fehlt | `make init` ausführen |
| Container starten nicht | `make config`, danach `make ps` und `make log-all` |
| Anwendung ist nicht auf `8080` erreichbar | `APP_PORT` in `.env.docker` und `make ps` prüfen |
| Änderung in `.env.local` wirkt nicht | prüfen, ob derselbe Schlüssel in `.env.docker` gesetzt ist |

Regeln zu Mail, Messenger, OAuth, Testumgebung und weiteren `.env*` stehen im [Konfigurationsleitfaden](configuration.md).
