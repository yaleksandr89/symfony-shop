# Architektur

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/architecture.md) | [English](../ru/architecture.md) | [Español](../ru/architecture.md) | [中文](../ru/architecture.md) | [Français](../ru/architecture.md) | **Deutsch** |


Symfony Shop ist eine einzelne Symfony-Anwendung mit servergerenderten Seiten, Administrationsbereich und API. Der Code ist nach Anwendungsbereichen gruppiert, während Routen zentral in YAML-Dateien liegen. Dadurch lässt sich der Weg von einer URL zu Controller oder API-Ressource auch ohne Start der Anwendung nachvollziehen.

## Übersicht

```text
Browser
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
          ↓
Anwendungsdienste / Handler
          ↓
Doctrine ORM
          ↓
PostgreSQL
```

Vue 2 wird auf ausgewählten Twig-Seiten eingebunden, wenn Interaktivität benötigt wird: Warenkorb, Warenkorb-Indikator und Bestelleditor. Die aktuelle Architektur besitzt weder eine eigenständige SPA noch Vue Router.

## Anwendungsbereiche

| Bereich | Inhalt |
|---|---|
| [`src/Account`](../../../src/Account) | Registrierung, lokale Anmeldung, Profil, E-Mail-Bestätigung, Passwort-Zurücksetzung, Nachrichten und Mail-Abläufe |
| [`src/Catalog`](../../../src/Catalog) | Kategorien, Produkte, Bilder, Katalog-Lesezugriffe und zugehörige Doctrine-/API-Abfragen |
| [`src/Commerce`](../../../src/Commerce) | Warenkorb, Warenkorbpositionen, Checkout, Bestellungen, Zugriffsprüfungen und Benachrichtigungen |
| [`src/Money`](../../../src/Money) | monetäre Value Objects und Berechnungen für Commerce-Abläufe |

Doctrine-Entities bleiben in [`src/Entity`](../../../src/Entity), Anwendungsdienste liegen im Bereich, der den jeweiligen Use Case besitzt.

## Interne Symfony-Bundles

Das Projekt enthält drei interne Symfony-Bundles. Sie bleiben Teil derselben Anwendung und sind keine separaten Composer-Pakete.

| Bundle | Zweck |
|---|---|
| [`src/AdminBundle`](../../../src/AdminBundle) | Administrations-Controller, Formulare, Templates und API-Operationen |
| [`src/OAuthBundle`](../../../src/OAuthBundle) | OAuth-Clients, Authenticators, Verknüpfen/Trennen und Provider-Mapping |
| [`src/SeoBundle`](../../../src/SeoBundle) | `robots.txt` und Sitemap |

Die Links führen direkt zu den Modulverzeichnissen, damit deren Struktur ohne zusätzliche Repository-Navigation sichtbar ist.

## Routing

Anwendungsrouten liegen in [`config/routes.yaml`](../../../config/routes.yaml) und [`config/routes/app/`](../../../config/routes/app/).

Die lokalisierten Bereiche `account`, `catalog`, `commerce`, `admin` und `oauth` verwenden den Präfix `/{_locale}` mit `ru|en`. SEO-Routen bleiben ohne Sprachpräfix.

API Platform ist separat über [`config/routes/api_platform.yaml`](../../../config/routes/api_platform.yaml) mit dem Präfix `/api` registriert.

Praktischer Pfad zum Nachverfolgen einer Anfrage:

```text
URL
→ config/routes*.yaml
→ Controller oder API-Ressource
→ Anwendungsdienst / API-Handler
→ Doctrine-Repository / Doctrine ORM
```

## Doctrine und Daten

Doctrine-Entities liegen in [`src/Entity`](../../../src/Entity), Migrationen in [`migrations`](../../../migrations).

Wichtige Entities:

- `User`;
- `Category`, `Product`, `ProductImage`;
- `Cart`, `CartProduct`;
- `Order`, `OrderProduct`;
- `ResetPasswordRequest`.

Repositories und Anwendungsdienste sind nicht in einem gemeinsamen Ordner gesammelt, sondern liegen nahe bei dem Anwendungsbereich, der sie nutzt.

Reproduzierbare Demo-Daten befinden sich in [`tools/demo`](../../../tools/demo) und werden nur in `dev` und `test` geladen.

## API Platform

API Platform dient der Anwendungs-API und veröffentlicht nicht automatisch alle Doctrine-Entities.

Die API umfasst Katalog, Warenkorb und Bestellungen. Zugriff und Änderungen werden zusätzlich durch Zugriffsprüfungen, Query Extensions, Input Objects und API-Platform-Handler begrenzt. Für Checkout gibt es ein eigenes Input Object und einen eigenen Handler; administrative Operationen an Bestellpositionen werden durch `AdminBundle`-Konfiguration ergänzt.

Beim Nachvollziehen von API-Verhalten nicht nur Entity-Attribute prüfen, sondern auch zugehörige API-Platform-Handler, Query Extensions und Zugriffsregeln.

## Twig, Vue und Webpack Encore

Die meisten Seiten werden mit Twig gerendert. Gemeinsame Templates liegen in [`templates`](../../../templates), Templates interner Bundles in den jeweiligen Modulen.

Webpack Encore baut Assets aus [`assets`](../../../assets) nach `public/build`. Vue 2 wird punktuell als interaktive Schicht auf servergerenderten Seiten eingesetzt.

Die aktuelle Client-Architektur bleibt bis zur separaten Migration auf Inertia.js und Vue 3 bestehen.

## Konfiguration und Dependency Injection

[`config/services.yaml`](../../../config/services.yaml) aktiviert automatische Dependency Injection (`autowiring`) für Anwendungscode und enthält explizite Service-Konfiguration für spezielle Parameter oder Provider-Mappings.

Doctrine-, Security-, Messenger-, Mailer-, Twig- und API-Platform-Einstellungen liegen unter [`config/packages`](../../../config/packages).

## Tests

| Verzeichnis / Gruppe | Zweck |
|---|---|
| [`tests/Unit`](../../../tests/Unit) | isolierte Anwendungsregeln und Services |
| [`tests/Integration`](../../../tests/Integration) | Doctrine und Zusammenspiel mehrerer Services |
| [`tests/Functional`](../../../tests/Functional) | HTTP, Controller, API und Zugriffsregeln |
| `functional-panther` | Browser-Szenarien über Panther |
| [`tests/TestUtils`](../../../tests/TestUtils) | gemeinsame Testhilfen und Ersatzimplementierungen externer OAuth-Clients |

PHP/PHPUnit-Coverage wird für `src` und `tools/demo` berechnet; Panther ist nicht enthalten. Befehle stehen im [Entwicklungsleitfaden](development.md).

## Docker

Docker Compose startet drei permanente Services:

| Service | Rolle |
|---|---|
| `php` | PHP-FPM, Composer, Symfony Console und Panther-Umgebung |
| `nginx` | HTTP-Einstieg und statische Dateien |
| `postgres` | PostgreSQL mit persistentem Datenvolume |

`node` gehört zum Profil `tools` und wird für einmalige npm-Befehle und Frontend-Builds verwendet. Docker Compose besitzt derzeit keinen permanenten Messenger-Worker.

Der erste Start ist in [Projektstart](getting-started.md) beschrieben, die `.env*`-Ebenen im [Konfigurationsleitfaden](configuration.md).
