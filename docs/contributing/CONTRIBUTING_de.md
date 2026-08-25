# Zu Symfony Shop beitragen

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../.github/CONTRIBUTING.md) | [English](CONTRIBUTING_en.md) | [Español](CONTRIBUTING_es.md) | [中文](CONTRIBUTING_zh.md) | [Français](CONTRIBUTING_fr.md) | **Deutsch** |

Danke für dein Interesse an Symfony Shop. Es ist ein Lernprojekt für einen Symfony-Onlineshop mit Docker-Umgebung, PostgreSQL, API Platform, OAuth und ausgewählten interaktiven Vue-Komponenten.

## Vor dem Start

Prüfe vorhandene Discussions, Issues und Pull Requests und beschränke jede Änderung auf eine klar verständliche Aufgabe. Fragen und Ideen gehören zuerst in [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions), reproduzierbare Fehler und konkrete Verbesserungen in Issues, Sicherheitsprobleme gemäß der [Security Policy](../security/SECURITY_de.md), ohne Exploit-Details öffentlich zu machen.

## Projektgrenzen

- Die unterstützte lokale Umgebung verwendet Docker Compose und das Makefile.
- PHP, Composer, PostgreSQL, Node.js und die Browser-Umgebung werden im normalen Entwicklungsablauf nicht direkt auf dem Host ausgeführt.
- Änderungen dürfen Zugriffsregeln, OAuth-Abläufe, Warenkorb-/Bestellintegrität oder andere bestehende Verträge nicht unbemerkt abschwächen.
- Keine breiten Refactorings oder Dependency-Upgrades ohne Bezug zur Aufgabe.
- Die Vue-2-Frontend-Architektur bleibt bis zur separaten Migration auf Inertia.js und Vue 3 bestehen.

Die Architektur ist in [`docs/architecture.md`](../architecture.md) beschrieben, Entwicklungsbefehle in [`docs/development.md`](../development.md).

## Branches

Erstelle einen thematischen Branch vom aktuellen `master`. Der Name soll die Änderung kurz beschreiben, zum Beispiel:

```text
fix/cart-quantity
docs/oauth
refactor/catalog-query
```

Änderungen gelangen per Pull Request nach `master`.

## Commits

Das Projekt verwendet Conventional Commits mit russischer Beschreibung:

```text
fix: исправить проверку количества товара
docs: уточнить настройку OAuth
refactor: упростить выборку каталога
```

Ein Commit soll eine logisch zusammengehörige Gruppe von Änderungen enthalten.

## Lokale Prüfungen

Lies vor dem Ausführen von Befehlen das aktuelle Makefile. Wichtigste Prüfungen:

| Befehl | Zweck |
|---|---|
| `make check` | ESLint + PHP-CS-Fixer-Prüfung + PHPStan |
| `make test-unit` | Unit-Tests |
| `make test-integration` | Integrationstests |
| `make test-functional` | Funktionstests |
| `make test-functional-panther` | Panther-Browser-Tests |
| `make test-all CONFIRM=testdb` | vollständige Testsuite einschließlich Panther |

Führe die zur Änderung passenden Prüfungen aus. Die vollständige Suite ist sinnvoll, wenn gemeinsame Anwendungsgrenzen betroffen sind oder vor der finalen Prüfung einer größeren Änderung.

## Pull Request

Beschreibe im Pull Request:

- was geändert wurde und warum;
- wie die Änderung geprüft wurde;
- ob manuelle Schritte nötig sind;
- ob Konfiguration, Daten, OAuth, Zugriffsrechte oder ein anderer wichtiger Vertrag betroffen sind;
- ob die Dokumentation aktualisiert wurde, wenn sich die öffentliche Nutzung geändert hat.

## Checkliste

- Keine Secrets, echten OAuth-Zugangsdaten, Access Tokens, Cookies, Session-IDs oder Inhalte lokaler `.env*`.
- Keine fremden Änderungen im Diff.
- `git diff --check` ist erfolgreich.
- Relevante Prüfungen wurden ausgeführt.
- Neue Tests schützen konkretes Verhalten und werden nicht nur für mehr Testmenge ergänzt.
- Dokumentation wird aktualisiert, wenn sich ein öffentlicher Vertrag, Konfiguration oder Startablauf ändert.
