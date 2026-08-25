# Sicherheit

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../.github/SECURITY.md) | [English](../en/SECURITY.md) | [Español](../es/SECURITY.md) | [中文](../zh/SECURITY.md) | [Français](../fr/SECURITY.md) | **Deutsch** |

Bitte melde mögliche Sicherheitslücken verantwortungsvoll. Symfony Shop ist ein öffentliches Lernprojekt, Probleme in Authentifizierung, OAuth, Warenkorb, Checkout, API, Verarbeitung von Benutzereingaben und Konfiguration werden jedoch wie normale Anwendungssicherheitsprobleme behandelt.

## Was privat gemeldet werden sollte

- Umgehung von Authentifizierung oder Autorisierung;
- Möglichkeit, sich in ein fremdes Konto einzuloggen oder eine externe OAuth-Identität mit dem falschen lokalen Benutzer zu verknüpfen;
- Umgehung des CSRF-Schutzes bei zustandsändernden Operationen;
- SQL-/DQL-Injection;
- gespeichertes oder reflektiertes XSS;
- Lesen oder Ändern fremder Warenkörbe, Bestellungen oder Administrationsdaten;
- Umgehung von API-Beschränkungen oder Datenoffenlegung über die API;
- Offenlegung von `.env`, OAuth-Zugangsdaten, Access Tokens, Cookies, Session-IDs, internen Exceptions oder anderen sensiblen Informationen;
- Umgehung eines serverseitigen Schalters für OAuth-Provider;
- ausnutzbare Schwachstelle einer Abhängigkeit mit wesentlichem Einfluss auf das Projekt;
- Kompromittierung von CI, Quellcode oder Dependency Supply Chain.

## Was in Issues veröffentlicht werden kann

- reproduzierbarer UI-Fehler ohne Sicherheitsauswirkung;
- Fehler in Katalog, Warenkorb oder Administration ohne Zugriff auf fremde Daten;
- Docker-/Bootstrap- oder Kompatibilitätsproblem;
- Dokumentationsfehler;
- Verbesserungsvorschlag.

Wenn unklar ist, ob ein Problem sicherheitsrelevant ist, nutze zunächst einen privaten Kanal.

## Meldung

- Wenn im Security-Bereich des Repositorys ein privates Formular für Schwachstellen verfügbar ist, verwende es zuerst.
- Veröffentliche keinen Exploit-Code, echte Secrets, OAuth-Zugangsdaten, Access Tokens, Cookies, Session-IDs oder Inhalte lokaler `.env*` in Issues, Pull Requests oder Logs.
- Falls keine private Meldung möglich ist, erstelle ein minimales öffentliches Issue ohne Exploit-Details und bitte um einen privaten Kommunikationskanal.
- Technische Exploit-Details sollten nicht veröffentlicht werden, bevor ein Fix verfügbar ist.

## Was enthalten sein sollte

Wenn möglich, gib Folgendes an:

- Commit-SHA oder Branch;
- betroffenen Anwendungsbereich;
- Auswirkung;
- minimale Schritte zur Reproduktion;
- bereinigten Ausschnitt aus Request, Response oder Log, falls relevant;
- PHP-/Symfony-/PostgreSQL-Versionen, falls sie für die Reproduktion wichtig sind.

Verwende ausschließlich synthetische Daten. Keine echten Passwörter, Tokens, externen IDs, Cookies, Session-IDs oder Inhalte lokaler `.env*` anhängen.

## Was danach passiert

- Das Projekt wird von einem Autor gepflegt; es gibt keine garantierte SLA.
- Die Meldung wird geprüft und bei Bedarf mit einem Fix und einem Regressionstest beantwortet.
- Es wird kein Bug-Bounty-Programm zugesichert.
- Öffentliche Details sollten möglichst erst nach Verfügbarkeit eines Fixes veröffentlicht werden.
