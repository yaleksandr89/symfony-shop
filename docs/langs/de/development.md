# Entwicklung

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/development.md) | [English](../ru/development.md) | [Español](../ru/development.md) | [中文](../ru/development.md) | [Français](../ru/development.md) | **Deutsch** |


Das Makefile ist die zentrale Schnittstelle für lokale Entwicklung. PHP, Composer und Symfony Console laufen im PHP-Container als Benutzer `app`; npm läuft in einem kurzlebigen Node-Container.

Die aktuelle Zielliste ist jederzeit mit `make help` verfügbar.

## Erstkonfiguration

| Befehl | Zweck |
|---|---|
| `make help` | Eingebaute Makefile-Hilfe anzeigen |
| `make init` | `.env.docker` und beschreibbare lokale Verzeichnisse erstellen |
| `make check-env` | Prüfen, ob `.env.docker` existiert |

## Docker Compose

| Befehl | Zweck | Hinweis |
|---|---|---|
| `make config` | Endgültige Compose-Konfiguration prüfen | Startet nichts |
| `make build` | PHP-Image bauen | |
| `make up` | `php`, `nginx` und `postgres` starten | |
| `make ps` | Containerstatus anzeigen | |
| `make restart <service>` | Service neu starten | `php`, `nginx`, `postgres` |
| `make log <service>` | Service-Log anzeigen | `php`, `nginx`, `postgres` |
| `make log-all` | Alle Logs anzeigen | |
| `make in <service>` | Shell eines Services öffnen | `php`, `nginx`, `postgres`, `node` |
| `make down` | Umgebung stoppen | PostgreSQL-Volume bleibt erhalten |

Die Shell des PHP-Containers wird als `app` geöffnet, damit normale Befehle keine `root`-eigenen Dateien in der Arbeitskopie erzeugen.

## Symfony, Composer und npm

| Befehl | Zweck | Hinweis |
|---|---|---|
| `make console CMD=about` | Symfony Console ausführen | Beliebiger Befehl über `CMD` |
| `make composer CMD='validate --strict'` | Composer ausführen | Im PHP-Container |
| `make composer-install` | `composer install` ausführen | Verwendet `composer.lock` |
| `make npm CMD='npm --version'` | Beliebigen npm-Befehl ausführen | In kurzlebigem Node-Container |
| `make npm-install` | `npm ci` ausführen | Verwendet `package-lock.json` |
| `make assets-build` | Optimierte Frontend-Assets bauen | Webpack Encore |
| `make watch` | Frontend-Assets auf Änderungen überwachen | Lang laufender Befehl |

PHP, Composer, Node.js und Symfony Console werden nicht direkt auf dem Host verwendet.

Für manuelle Messenger-Queue-Verarbeitung:

| Befehl | Zweck |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Worker für die `async`-Queue starten |

Docker Compose hat derzeit keinen permanenten Messenger-Worker. Details zu Mail und Queue im [Konfigurationsleitfaden](configuration.md).

## Qualitätsprüfungen

| Befehl | Zweck | Ändert Dateien |
|---|---|---|
| `make check` | ESLint + PHP-CS-Fixer-Prüfung + PHPStan | nein |
| `make eslint-check` | JS/Vue mit ESLint prüfen | nein |
| `make php-cs-fixer-check` | Formatierung in `src/` und `tools/demo/` prüfen | nein |
| `make phpstan-check` | PHPStan für `src` und `tools/demo` ausführen | nein |
| `make eslint-fix` | ESLint-Probleme beheben | ja |
| `make php-cs-fixer` | PHP-Formatierung korrigieren | ja |

`make check` führt PHPUnit nicht aus. Tests haben eigene Ziele.

## Tests

| Befehl | Prüfung | Hinweis |
|---|---|---|
| `make test-groups` | PHPUnit-Gruppen anzeigen | |
| `make test-list` | Testliste anzeigen | |
| `make test-unit` | Isolierte Anwendungslogik | Gruppe `unit` |
| `make test-integration` | Doctrine und Service-Zusammenspiel | Gruppe `integration` |
| `make test-functional` | HTTP, Controller, API und Zugriffsregeln | Gruppe `functional` |
| `make test-functional-panther` | Browser-Szenarien | Gruppe `functional-panther` |
| `make test-all-core CONFIRM=testdb` | Frontend-Assets + unit + integration + functional | Erstellt Test-SQLite-Datenbank neu |
| `make test-all CONFIRM=testdb` | Vollständige Suite inklusive Panther | Erstellt Test-SQLite-Datenbank neu |

`CONFIRM=testdb` ist absichtlich erforderlich: Aggregierte Abläufe löschen und erstellen `var/db_for_test.db` neu.

Panther verwendet Chrome for Testing und Chromedriver aus dem PHP-Image. Selenium Server, GeckoDriver, Java und ein lokal installierter Browser werden für die aktuellen Tests nicht benötigt.

## Code Coverage

| Befehl | Ergebnis | Hinweis |
|---|---|---|
| `make coverage CONFIRM=testdb` | Statistik im Terminal | `src` + `tools/demo`, ohne Panther |
| `make coverage-html CONFIRM=testdb` | Terminal + HTML + Clover | `var/coverage/html`, `var/coverage/clover.xml` |

Beide Befehle verwenden denselben PHP/PHPUnit-Bereich und erstellen vorher die Testdatenbank neu. Panther ist im Coverage-Bericht nicht enthalten.

## Datenbank und Demo-Daten

| Befehl | Zweck | Risiko |
|---|---|---|
| `make migrate` | Doctrine-Migrationen anwenden | normaler Vorgang |
| `make demo-init` | Demo-Katalog, Konten und Bestellungen initialisieren | ersetzt vorhandene Bestellungen |
| `make test-db-reset CONFIRM=testdb` | `var/db_for_test.db` neu erstellen | löscht Test-SQLite-Datenbank |
| `make postgres-reinit CONFIRM=postgres18` | Lokales PostgreSQL-Volume neu erstellen | löscht lokale PostgreSQL-Daten |
| `make cache-prod-clear` | Generierten prod-Cache löschen | nur `var/cache/prod` im PHP-Container |

`make demo-init` ist für eine reproduzierbare `dev`/`test`-Umgebung gedacht. Nicht ausführen, wenn die lokale Datenbank Bestellungen enthält, die erhalten bleiben müssen.

## CI

Workflow [`CI`](../../../.github/workflows/basic.yml) läuft bei Pushes und Pull Requests nach `master`.

Er:

1. lädt Git-LFS-Objekte und prüft das Chrome-Archiv;
2. erstellt `.env.docker`;
3. prüft Compose, baut und startet die Docker-Umgebung;
4. installiert Abhängigkeiten und baut Frontend-Assets;
5. führt ESLint aus;
6. führt Unit-, Integrations-, Functional- und Panther-Tests aus;
7. führt PHPStan aus;
8. stoppt die Container.

CI führt weder die PHP-CS-Fixer-Prüfung noch einen Coverage-Bericht aus; diese Checks werden bei Bedarf lokal ausgeführt.

## Logs und Diagnose

| Befehl | Anzeige |
|---|---|
| `make ps` | Containerstatus |
| `make log php` | PHP-Log |
| `make log nginx` | Nginx-Log |
| `make log postgres` | PostgreSQL-Log |
| `make log-all` | Alle Projektlogs |
| `make console CMD=about` | Zustand der Symfony-Anwendung |

## Alle Make-Befehle

| Ziel | Zweck |
|---|---|
| `help` | eingebaute Hilfe |
| `init` | `.env.docker` und lokale Verzeichnisse erstellen |
| `check-env` | `.env.docker` prüfen |
| `config` | Docker Compose prüfen |
| `build` | PHP-Image bauen |
| `up` | Hauptservices starten |
| `down` | Umgebung stoppen |
| `restart <service>` | Service neu starten |
| `ps` | Containerstatus |
| `log <service>` | Log des gewählten Services |
| `log-all` | Logs aller Services |
| `in <service>` | Shell des gewählten Services |
| `cache-prod-clear` | prod-Cache löschen |
| `console CMD='...'` | Symfony Console |
| `composer CMD='...'` | Composer im PHP-Container |
| `composer-install` | Composer-Abhängigkeiten installieren |
| `npm CMD='...'` | npm im kurzlebigen Node-Container |
| `npm-install` | npm-Abhängigkeiten installieren |
| `assets-build` | optimierter Frontend-Build |
| `watch` | Frontend-Assets überwachen |
| `migrate` | Doctrine-Migrationen |
| `demo-init` | Demo-Daten |
| `postgres-reinit CONFIRM=postgres18` | lokales PostgreSQL-Volume vollständig neu erstellen |
| `check` | ESLint + PHP-CS-Fixer check + PHPStan |
| `eslint-fix` | ESLint korrigieren |
| `eslint-check` | ESLint prüfen |
| `php-cs-fixer` | PHP-Formatierung korrigieren |
| `php-cs-fixer-check` | PHP-Formatierung prüfen |
| `phpstan-check` | PHPStan-Analyse |
| `test-all-core CONFIRM=testdb` | Haupttests ohne Panther |
| `coverage CONFIRM=testdb` | Coverage im Terminal |
| `coverage-html CONFIRM=testdb` | Coverage + HTML/Clover |
| `test-all CONFIRM=testdb` | vollständige Tests mit Panther |
| `test-groups` | PHPUnit-Gruppen |
| `test-list` | PHPUnit-Testliste |
| `test-unit` | Unit-Tests |
| `test-db-reset CONFIRM=testdb` | Test-SQLite-Datenbank neu erstellen |
| `test-integration` | Integrationstests |
| `test-functional` | Functional-Tests |
| `test-functional-panther` | Panther-Browser-Tests |

Für den ersten Start und Wege zu Chrome for Testing siehe [Projektstart](getting-started.md). Regeln für `.env*` und lokale Secrets stehen im [Konfigurationsleitfaden](configuration.md).
