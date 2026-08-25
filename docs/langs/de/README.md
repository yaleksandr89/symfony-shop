# Symfony Shop

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fsymfony--shop-blue.svg?style=flat-square)](https://github.com/yaleksandr89/symfony-shop)
[![CI](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml/badge.svg)](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000.svg?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18.4-4169E1.svg?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED.svg?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](../../../LICENSE.md)

<p align="center">
  <img
    src="../../img/symfony-shop-readme-cover.png"
    alt="Symfony Shop — Online-Shop mit Symfony, Docker und PostgreSQL"
    width="100%"
  >
</p>

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../README.md) | [English](../en/README.md) | [Español](../es/README.md) | [中文](../zh/README.md) | [Français](../fr/README.md) | **Ausgewählt** |

Symfony Shop ist ein Lernprojekt für einen Online-Shop auf Symfony. Das Projekt umfasst Produktkatalog, Warenkorb und Checkout, Benutzerkonto, Administrationsbereich, API und OAuth-Anmeldung. Die meisten Seiten werden mit Twig gerendert; Vue 2 wird für einzelne interaktive Oberflächenelemente eingesetzt.

Die unterstützte lokale Entwicklungsumgebung basiert auf Docker Compose. PHP, Composer, Node.js, PostgreSQL und Chrome for Testing laufen in Containern oder werden in das Docker-Image installiert; die wichtigsten Abläufe sind in einem einzigen Makefile zusammengefasst. Ein Betrieb mit direkt auf dem Host installierten PHP, Composer und PostgreSQL wird nicht unterstützt und nicht durch CI geprüft.

## Funktionen

- Kategorien und Produkte mit Bildern, Neuheiten und Rabatten;
- Warenkorb mit Verfügbarkeitsprüfung und Checkout;
- Registrierung, Anmeldung, E-Mail-Bestätigung und Passwort-Zurücksetzung;
- Benutzerkonto;
- OAuth über Google, Yandex, VKontakte, GitHub, Facebook und LinkedIn;
- getrennte Abläufe für OAuth-Anmeldung, Verknüpfen und Trennen externer Konten;
- Verwaltung von Benutzern, Kategorien, Produkten und Bestellungen;
- API auf Basis von API Platform;
- Unit-, Integrations-, Funktions- und Browser-Tests;
- GitHub-Actions-CI auf derselben Docker-Umgebung.

## Schnellstart

Auf dem Host werden Git, Make und Docker mit Compose-Unterstützung benötigt. Git LFS wird für einen normalen Repository-Klon empfohlen; das große Browser-Archiv kann auch ohne Git LFS bezogen werden.

> [!NOTE]
> Make ist ein übliches Kommandozeilenwerkzeug auf Unix-ähnlichen Systemen. Unter Linux und macOS kann das Projekt direkt im Terminal gestartet werden. Unter Windows wird WSL2 zusammen mit Docker Desktop empfohlen.

| Befehl | Zweck | Hinweis |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Repository klonen | |
| `cd symfony-shop` | In das Projektverzeichnis wechseln | |
| `git lfs install` | Git LFS aktivieren | Nur für den Git-LFS-Ablauf |
| `git lfs pull` | Chrome for Testing herunterladen | Vor `make build` ausführen |
| `make init` | `.env.docker` und lokale Verzeichnisse anlegen | Überschreibt keine vorhandene `.env.docker` |
| `make build` | PHP-Image bauen | Enthält Chrome und Chromedriver für Panther |
| `make up` | PHP-FPM, Nginx und PostgreSQL starten | |
| `make composer-install` | PHP-Abhängigkeiten aus `composer.lock` installieren | Composer wird auf dem Host nicht benötigt |
| `make npm-install` | Abhängigkeiten aus `package-lock.json` installieren | Node.js wird auf dem Host nicht benötigt |
| `make assets-build` | Frontend-Assets bauen | |
| `make migrate` | Doctrine-Migrationen anwenden | |
| `make demo-init` | Demo-Daten erstellen | Nur in lokalen `dev`/`test`-Umgebungen |

Nach dem Start ist die Anwendung standardmäßig unter [http://localhost:8080](http://localhost:8080) erreichbar.

> [!IMPORTANT]
> Das Projekt verwendet fest Chrome for Testing `150.0.7871.46`. Empfohlen wird `git lfs pull`. Ab `v3.0.0` kann das Projekt-ZIP von [Releases](https://github.com/yaleksandr89/symfony-shop/releases) heruntergeladen werden; Chrome for Testing ist darin bereits enthalten, sodass Git LFS für diesen Weg nicht benötigt wird. Die festgelegte Version kann außerdem direkt aus der offiziellen Quelle geladen werden. Exakte Links, Dateiname und SHA-256 stehen im [Setup-Leitfaden](getting-started.md).

> [!IMPORTANT]
> Werte aus `.env.docker` werden dem PHP-Container als Prozess-Umgebungsvariablen übergeben. Ist derselbe Schlüssel sowohl dort als auch in `.env.local` definiert, hat der Wert aus `.env.docker` Vorrang. Das vollständige Schema steht im [Konfigurationsleitfaden](configuration.md).

> [!WARNING]
> `make demo-init` erstellt Demo-Bestellungen neu. Führe den Befehl nicht gegen eine lokale Datenbank aus, die Daten enthält, die du behalten möchtest.

Der vollständige Erststart, alle drei Wege zu Chrome for Testing und die Containerverwaltung sind im [Setup-Leitfaden](getting-started.md) beschrieben.

## E-Mail und Nachrichtenwarteschlange

Standardmäßig gilt `MAILER_DSN=null://null`; die Anwendung sendet daher keine Nachrichten über einen externen SMTP-Dienst. Synchron während einer HTTP-Anfrage versendete Nachrichten können im Mailer-Bereich des Symfony Profilers angesehen werden.

Registrierung und Passwort-Zurücksetzung verwenden den Messenger-Transport `async`. Das Routing in die Warteschlange ist bereits eingerichtet, Docker Compose startet derzeit jedoch keinen permanenten Worker. Solche Nachrichten werden daher erst nach folgendem Befehl verarbeitet:

```text
make console CMD='messenger:consume async -vv'
```

Transport, Mail und lokale Secrets sind im [Konfigurationsleitfaden](configuration.md) beschrieben.

## OAuth

OAuth-Anmeldung und das Verknüpfen eines externen Kontos mit einem bestehenden Benutzer sind getrennte Vorgänge. Eine übereinstimmende E-Mail-Adresse beim Provider reicht nicht aus, um eine externe Identität automatisch mit einem bestehenden lokalen Konto zu verbinden.

Zum Verknüpfen meldet sich der Benutzer zunächst normal an, bestätigt das aktuelle Passwort und startet den OAuth-Ablauf ausdrücklich im Benutzerkonto. Auch das Trennen ist durch das aktuelle Passwort und ein CSRF-Token geschützt.

Unterstützte Provider, Umgebungsvariablen, Routen und Sicherheitsregeln sind im [OAuth-Leitfaden](oauth.md) dokumentiert. Allgemeine Regeln zu lokaler Konfiguration und Secrets stehen im [Konfigurationsleitfaden](configuration.md).

## Projektstruktur

```text
Browser
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
  ↓
Anwendungsdienste / Doctrine
  ↓
PostgreSQL
```

Der Hauptcode ist in die Bereiche `Account`, `Catalog` und `Commerce` gegliedert. Administration, OAuth und SEO sind als interne Symfony-Bundles umgesetzt. Vue 2 wird für einzelne interaktive Komponenten genutzt, nicht als eigenständige SPA.

Verzeichnisstruktur, Routing, API Platform, Doctrine und Frontend-Grenzen werden im [Architekturleitfaden](architecture.md) beschrieben.

## Prüfungen

| Befehl | Zweck | Hinweis |
|---|---|---|
| `make check` | ESLint, PHP-CS-Fixer-Prüfung und PHPStan ausführen | Tests sind nicht enthalten |
| `make test-unit` | Unit-Tests ausführen | |
| `make test-integration` | Integrationstests ausführen | |
| `make test-functional` | Funktionstests ausführen | |
| `make test-functional-panther` | Browser-Tests mit Panther ausführen | Chrome ist bereits im PHP-Image enthalten |
| `make test-all CONFIRM=testdb` | Gesamte Testsuite ausführen | Erstellt die Testdatenbank neu |
| `make coverage CONFIRM=testdb` | PHP/PHPUnit-Coverage im Terminal anzeigen | Panther ist nicht enthalten |
| `make coverage-html CONFIRM=testdb` | HTML- und Clover-Berichte erzeugen | `var/coverage/html`, `var/coverage/clover.xml` |

Die vollständige Make-Befehlsliste, der Umgang mit der Testdatenbank und die CI-Zusammensetzung stehen im [Entwicklungsleitfaden](development.md).

## Geplant

1. **Lokale Mail-Umgebung.** Einen Mail-Dienst mit Weboberfläche und einen permanenten Messenger-Worker hinzufügen, damit Nachrichten des Transports `async` automatisch verarbeitet werden.
2. **Inertia.js und Vue 3.** Die Server-/Client-Interaktion auf Inertia.js und Vue 3 umstellen. Dabei möchte ich auch die Lokalisierung neu bewerten: Je nach Umfang der Änderungen könnte der verpflichtende `/{_locale}`-Präfix in URLs entfallen. Das entscheide ich beim Entwurf des neuen Frontends.
3. **Administration.** Nach der Frontend-Migration die Möglichkeiten zur Shop-Verwaltung im Administrationsbereich deutlich ausbauen.

## Feedback

- reproduzierbare Fehler — [GitHub Issues](https://github.com/yaleksandr89/symfony-shop/issues);
- Fragen und Ideen — [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions).

## Projektgeschichte

### 2026 — Vorbereitung von v3.0.0

- Docker Compose wurde zur primären Entwicklungsumgebung. Hinzu kamen ein einheitliches Makefile, reproduzierbarer Bootstrap, PostgreSQL in Docker, Demo-Daten, Xdebug und APCu.
- CI wurde zu GitHub Actions migriert und verwendet denselben Docker-basierten Ablauf wie die lokale Entwicklung.
- Der Backend-Stack wurde schrittweise auf PHP 8.5, Symfony 8.1, API Platform 4.3, Doctrine ORM 3 / DBAL 4, PHPUnit 13 und PHPStan 2 aktualisiert.
- Sicherheits- und Geschäftsgrenzen rund um Warenkorb, Checkout, API, Registrierung, Passwort-Zurücksetzung und OAuth wurden grundlegend überarbeitet.
- OAuth wurde um Facebook und LinkedIn erweitert; Anmeldung, Registrierung, Verknüpfen und Trennen sind getrennte, eigens geschützte Abläufe.
- Selenium, GeckoDriver, Java-Tooling und Deployer wurden entfernt. Browser-Tests verwenden nun Panther und Chrome for Testing; das Chrome-Archiv liegt in Git LFS.
- Die Architektur wurde um `Account`, `Catalog` und `Commerce` sowie `AdminBundle`, `OAuthBundle` und `SeoBundle` neu strukturiert; Routing und gemeinsamer OAuth-Callback wurden zentralisiert.
- Die Testinfrastruktur wurde mit Docker-basierten Quality Gates und Coverage-Befehlen neu aufgebaut.
- Die Projektdokumentation wurde vollständig mit eigenen Leitfäden für Setup, Konfiguration, Entwicklung, OAuth und Architektur überarbeitet.
- Die Lizenz wurde auf MIT vereinheitlicht; GitHub Issues/Discussions, Pull-Request-Vorlagen, Contribution Guide und Security Policy wurden ergänzt.

### 2024 — v2.3.0

- Symfony wurde auf 6.4.9 aktualisiert.
- PHPUnit wechselte von 9 auf 11 und DAMA Doctrine Test Bundle auf Version 8; bestehende Tests wurden refaktoriert.
- Die Umstellung von Annotationen auf PHP-Attribute und die PHPStan-Bereinigung wurden fortgeführt.
- Selenium, ChromeDriver und GeckoDriver wurden aktualisiert.
- Nginx- und Supervisor-Beispiele, Deployer-Anleitungen und README-Übersetzungen wurden ergänzt.

### 2023 — v2.1.1 / v2.2.0

- Symfony wurde auf 6.3.1 aktualisiert, Abhängigkeiten wurden erneuert und Deprecation-Hinweise im eigenen Code entfernt.
- Eine weitere Refactoring- und PHPStan-Bereinigungsphase wurde abgeschlossen.
- Die Deployer-Konfiguration wurde aktualisiert.
- CircleCI wurde entfernt, nachdem der Dienst seine Arbeit für Nutzer in Russland eingestellt hatte.

### 2022 — v1.2.0 / v2.0.0 / v2.1.0

- Die grundlegende Shop-Funktionalität wurde aufgebaut.
- OAuth-Authentifizierung über Google, Yandex, VKontakte und GitHub wurde hinzugefügt.
- Symfony wurde schrittweise von 5.4 auf 6.0 aktualisiert.
- Externe OAuth-Konten konnten im Benutzerkonto verknüpft und getrennt werden.
- Schutz gegen die Wiederverwendung derselben externen Identität durch mehrere lokale Benutzer wurde ergänzt.

### 2021 — Projektstart

- Die erste Symfony-Shop-Version entstand auf Symfony 5.3 mit PostgreSQL.

---

<p align="center">
  Wenn dir das Projekt geholfen hat, gib ihm einen Stern auf GitHub — so finden es auch andere Entwickler leichter. 🤘
</p>
