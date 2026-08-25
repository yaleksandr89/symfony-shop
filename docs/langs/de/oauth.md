# OAuth

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/oauth.md) | [English](../en/oauth.md) | [Español](../es/oauth.md) | [中文](../zh/oauth.md) | [Français](../fr/oauth.md) | **Deutsch** |


Symfony Shop verwendet OAuth für Anmeldung und Registrierung über externe Dienste sowie für das ausdrückliche Verknüpfen eines solchen Kontos mit einem bestehenden lokalen Benutzer. Diese Abläufe sind getrennt: Eine übereinstimmende E-Mail-Adresse allein gilt nicht als Nachweis für den Besitz des lokalen Kontos.

Begriffe in diesem Dokument:

- **Provider** — externer Anmeldedienst wie Google oder GitHub;
- **externe ID** — Konto-ID des Benutzers beim Provider;
- **Callback** — Rückkehr des Benutzers zur Anwendung nach der Autorisierung beim Provider;
- **state** — zufälliges Token, das Beginn und Rückkehr eines OAuth-Ablaufs verbindet.

## Unterstützte Provider

| Provider | Name in der Anwendung | `User`-Feld |
|---|---|---|
| Google | `google` | `google_id` |
| Yandex | `yandex` | `yandex_id` |
| VKontakte | `vkontakte` | `vkontakte_id` |
| GitHub EN | `github_en` | `github_id` |
| GitHub RU | `github_rus` | `github_id` |
| Facebook | `facebook` | `facebook_id` |
| LinkedIn | `linkedin` | `linkedin_id` |

GitHub EN und GitHub RU verwenden verschiedene OAuth-Clients, aber dieselbe externe ID `github_id`. Ein GitHub-Konto kann nicht über unterschiedliche Clients mit zwei lokalen Benutzern verbunden werden.

Mail.ru wird bewusst nicht unterstützt: Dafür gibt es weder OAuth-Client noch Routen, und `OAUTH_MAILRU_ENABLED` muss `0` bleiben.

## Provider konfigurieren

Alle implementierten Provider sind standardmäßig deaktiviert.

| Provider | Schalter | Client ID | Client secret |
|---|---|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` | `OAUTH_GOOGLE_ID` | `OAUTH_GOOGLE_SECRET` |
| Yandex | `OAUTH_YANDEX_ENABLED` | `OAUTH_YANDEX_CLIENT_ID` | `OAUTH_YANDEX_CLIENT_SECRET` |
| VKontakte | `OAUTH_VK_ENABLED` | `OAUTH_VK_CLIENT_ID` | `OAUTH_VK_CLIENT_SECRET` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` | `OAUTH_GITHUB_EN_CLIENT_ID` | `OAUTH_GITHUB_EN_CLIENT_SECRET` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` | `OAUTH_GITHUB_RUS_CLIENT_ID` | `OAUTH_GITHUB_RUS_CLIENT_SECRET` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` | `OAUTH_FACEBOOK_CLIENT_ID` | `OAUTH_FACEBOOK_CLIENT_SECRET` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` | `OAUTH_LINKEDIN_CLIENT_ID` | `OAUTH_LINKEDIN_CLIENT_SECRET` |

Beispiel für `.env.local`:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

Der Schalter wird serverseitig ausgewertet und steuert nicht nur die Sichtbarkeit eines Buttons. Bei `*_ENABLED=0` werden neue Anmelde-, Registrierungs- und Verknüpfungsabläufe blockiert, bevor der Provider kontaktiert wird.

Echte Zugangsdaten gehören nicht in Git. Prioritäten zwischen `.env.local` und Docker-Variablen stehen im [Konfigurationsleitfaden](configuration.md).

## Normale Anmeldung und Registrierung

Die wichtigsten Fälle im Vergleich:

| Situation | Ergebnis | Was nicht passiert |
|---|---|---|
| Externe ID ist bereits verknüpft | Anmeldung am selben lokalen Konto | lokale E-Mail wird nicht durch Providerdaten ersetzt, Verknüpfung wird nicht überschrieben |
| Externe ID ist neu, aber dieselbe E-Mail existiert lokal | Anmeldung wird mit neutralem Fehler abgelehnt | keine automatische Verknüpfung, keine Anmeldung am gefundenen Konto, keine Benutzererstellung, keine Registrierungs-Mail |
| Externe ID und E-Mail sind neu | Neuer unverifizierter lokaler Benutzer wird erstellt und OAuth-Anmeldung gelingt | Provider bestätigt die lokale E-Mail nicht automatisch, kein zufälliges Passwort wird verschickt |
| Provider liefert keine E-Mail | Anmeldung wird neutral abgelehnt | kein Benutzer wird erstellt und keine Daten werden verändert |

Ist die externe ID bereits mit einem gelöschten lokalen Benutzer verknüpft, wird die Anmeldung ebenfalls abgelehnt.

Für einen neuen Benutzer speichert die Anwendung E-Mail und externe ID, lässt `isVerified=false`, erzeugt ein zufälliges internes Passwort und speichert nur dessen Hash. Nach dem Speichern startet der normale E-Mail-Bestätigungsablauf. Ein bekanntes lokales Passwort kann über die Passwort-Zurücksetzung gesetzt werden.

Die Registrierungs-Mail wird über Messenger `async` verarbeitet. Docker Compose hat derzeit keinen permanenten Worker; für lokale Prüfung dieses Ablaufs muss separat `make console CMD='messenger:consume async -vv'` laufen. Siehe [Mail und Messenger](configuration.md).

Fehler beim OAuth-Token-Austausch oder Laden des Profils werden in einen sicheren Anwendungsfehler umgewandelt, ohne die Provider-Antwort dem Benutzer anzuzeigen.

## Explizites Verknüpfen mit einem bestehenden Konto

Das Verknüpfen startet ein bereits authentifizierter lokaler Benutzer.

| Schritt | Ablauf |
|---|---|
| `GET` der Verknüpfungsseite | Bestätigungsformular wird angezeigt; Daten bleiben unverändert |
| `POST` des Formulars | Aktuelles Passwort und CSRF-Token werden geprüft |
| Weiterleitung zum Provider | Ein einmaliger Verknüpfungs-Intent wird in der aktuellen Session erzeugt |
| Provider-Callback | Benutzer, Provider, OAuth-`state` und Lebensdauer des Intents werden geprüft |
| Erfolg | Nur die externe ID des gewählten Providers wird geschrieben |

Der Intent bleibt höchstens 600 Sekunden in der Session und ist an einen konkreten Benutzer und Provider gebunden. Der ursprüngliche OAuth-`state` wird darin nicht gespeichert; gespeichert wird nur sein SHA-256-Hash. Der Intent ist einmalig, daher wird ein wiederholter Callback abgelehnt.

Beim Verknüpfen wird nicht nach Benutzern anhand der E-Mail gesucht und die aktuelle Anmeldesession wird nicht geändert. Gehört die externe ID bereits einem anderen Benutzer, wird keine Verknüpfung erstellt. Die letzte Schutzgrenze gegen parallele Schreibvorgänge ist die Unique Constraint der Datenbank.

## Trennen

Auch das Trennen erfolgt aus einem authentifizierten Benutzerkonto.

| Schritt | Ablauf |
|---|---|
| `GET` der Trennseite | Formular wird angezeigt; externe ID bleibt unverändert |
| `POST` des Formulars | Aktuelles Passwort und CSRF-Token werden geprüft |
| Erfolg | Nur das ausgewählte OAuth-Feld wird geleert |

Das `User`-Feld wird serverseitig aus einem erlaubten Providernamen ausgewählt. Der Client übermittelt weder einen Setter-Methodennamen noch einen beliebigen Feldnamen.

Wird ein Provider nach dem Verknüpfen deaktiviert, kann der Benutzer die bestehende Verbindung weiterhin entfernen. Der Schalter blockiert neue OAuth-Abläufe, aber kein sicheres Trennen.

## Routen

Normale OAuth-Routen liegen unter `/{_locale}`, unterstützt werden `ru` und `en`.

| Provider | OAuth-Ablauf starten | Callback |
|---|---|---|
| Google | `/{_locale}/connect/google` | `/{_locale}/connect/google/check` |
| Yandex | `/{_locale}/connect/yandex` | `/{_locale}/connect/yandex/check` |
| VKontakte | `/{_locale}/connect/vkontakte` | `/{_locale}/connect/vkontakte/check` |
| GitHub EN | `/{_locale}/connect/github-en` | `/{_locale}/connect/github-en/check` |
| GitHub RU | `/{_locale}/connect/github-ru` | `/{_locale}/connect/github-ru/check` |
| Facebook | `/{_locale}/connect/facebook` | `/{_locale}/connect/facebook/check` |
| LinkedIn | `/{_locale}/connect/linkedin` | `/{_locale}/connect/linkedin/check` |

Diese Routen werden im Browser-GET-Ablauf verwendet; die aktuelle YAML-Konfiguration definiert auf Symfony-Router-Ebene keine separaten HTTP-Methodenbeschränkungen dafür.

Benutzerkonto-Operationen besitzen explizite Methoden:

| Operation | Route | Methoden |
|---|---|---|
| Verknüpfen | `/{_locale}/profile/oauth/{provider}/link` | `GET`, `POST` |
| Trennen | `/{_locale}/profile/oauth/{provider}/unlink` | `GET`, `POST` |

Für `{provider}` sind `google`, `yandex`, `vkontakte`, `github_en`, `github_rus`, `facebook` und `linkedin` erlaubt.

## Eindeutigkeit externer IDs

Die Felder `google_id`, `yandex_id`, `vkontakte_id`, `github_id`, `facebook_id` und `linkedin_id` sind durch Unique Constraints in Doctrine und der Datenbank geschützt. Ein externes Konto kann nicht gleichzeitig zwei lokalen Benutzern gehören.
