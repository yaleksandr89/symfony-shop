# Konfiguration

## Sprache wählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/configuration.md) | [English](../en/configuration.md) | [Español](../es/configuration.md) | [中文](../zh/configuration.md) | [Français](../fr/configuration.md) | **Deutsch** |


Das Projekt trennt gemeinsame Symfony-Einstellungen, Docker-Parameter, lokale Secrets und Test-Overrides. Wichtig: Werte, die Docker Compose in den PHP-Container übergibt, haben eine höhere Priorität als Werte aus Symfony-Dotenv-Dateien.

## Umgebungsdateien

| Datei | Zweck | Git |
|---|---|---|
| `.env` | Gemeinsame sichere Symfony-Einstellungen und lokale Defaults | versioniert |
| `.env.docker` | Docker-Compose- und lokale PostgreSQL-Parameter | ignoriert |
| `.env.local` | Entwicklerbezogene Secrets und Einstellungen | ignoriert |
| `.env.test` | Einstellungen automatisierter Tests | versioniert |

## Variablenpriorität

Von höchster zu niedrigster Priorität:

1. Prozess-Umgebungsvariablen, einschließlich der von Docker Compose aus `.env.docker` übergebenen Werte;
2. `.env.<umgebung>.local`;
3. `.env.<umgebung>`;
4. `.env.local`;
5. `.env`.

Der Dateiname `.env.docker` allein erzeugt keine Sonderpriorität. Die Priorität entsteht dadurch, dass Docker Compose diese Werte als Prozess-Umgebungsvariablen in den PHP-Container übergibt.

Praktisches Beispiel:

```text
.env.docker
PANTHER_WEB_SERVER_PORT=9080

.env.local
PANTHER_WEB_SERVER_PORT=9999

→ im PHP-Container wird 9080 verwendet
```

OAuth-Zugangsdaten aus `.env.local` werden dagegen verwendet, wenn Docker keine gleichnamigen Variablen übergeben hat.

Nach Änderungen an `.env.docker` die Container mit `make down` und `make up` neu erstellen. Änderungen an `.env` oder `.env.local` erfordern das normalerweise nicht.

## `.env`

Hier stehen gemeinsame Anwendungsparameter: `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`, `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, Anwendungsadresse, CORS und OAuth-Schalter.

Die Werte in `.env` sind lokale Projektwerte und nicht für Production gedacht.

## `.env.docker`

`make init` erstellt diese Datei aus `.env.docker.example` und setzt UID/GID des Host-Benutzers ein.

Wichtige Parameter:

| Variable | Zweck | Standard |
|---|---|---|
| `HOST_UID`, `HOST_GID` | Besitzer von Dateien, die Container erzeugen | durch `make init` gesetzt |
| `APP_PORT` | Nginx-HTTP-Port auf dem Host | `8080` |
| `POSTGRES_DB` | Lokale PostgreSQL-Datenbank | `s_shop` |
| `POSTGRES_USER` | Lokaler PostgreSQL-Benutzer | `s_shop` |
| `POSTGRES_PASSWORD` | Lokales PostgreSQL-Passwort | Demo-Wert |
| `PANTHER_WEB_SERVER_HOST` | Host des eingebauten Panther-Webservers | `php` |
| `PANTHER_WEB_SERVER_PORT` | Port des eingebauten Panther-Webservers | `9080` |

Compose verwendet `.env.docker` als `env_file` des PHP-Containers; die Werte werden dadurch Prozess-Umgebungsvariablen.

## `.env.local`

Nutze `.env.local` für OAuth-Zugangsdaten, einen echten `MAILER_DSN`, ein lokales `ADMIN_EMAIL` und andere maschinenspezifische Secrets.

Diese Datei nicht zu Git hinzufügen und ihren Inhalt nicht veröffentlichen. In der Umgebung `test` lädt Symfony `.env.local` nicht.

## `.env.test`

Die Testumgebung verwendet eine separate SQLite-Datenbank `var/db_for_test.db`, Panther-Einstellungen, neutrale Mailer-/Messenger-Transporte und deaktivierte OAuth-Provider.

## Mail und Messenger

Standardwerte:

```dotenv
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default
```

`MAILER_DSN=null://null` bedeutet, dass die lokale Umgebung keine Mails über einen externen SMTP-Dienst verschickt. Synchron während einer HTTP-Anfrage erzeugte Nachrichten können im Mailer-Bereich des Symfony Profilers angesehen werden.

Für echten SMTP-Transport einen eigenen `MAILER_DSN` in `.env.local` setzen, zum Beispiel:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger routet Registrierung und Passwort-Zurücksetzung bereits zum Transport `async`, Docker Compose startet aber keinen permanenten Worker. Die Nachricht bleibt in der Doctrine-Queue, bis der Worker manuell gestartet wird:

| Befehl | Zweck |
|---|---|
| `make console CMD='messenger:consume async -vv'` | `async`-Transport-Worker im PHP-Container starten |

Das ist besonders beim Testen von Registrierung und Passwort-Zurücksetzung wichtig: Ohne Worker werden die entsprechenden asynchronen Nachrichten nicht verarbeitet. Später ist eine lokale Mail-Umgebung mit Weboberfläche und permanentem Messenger-Worker geplant.

## PostgreSQL

Docker Compose verwendet PostgreSQL 18.4. Der PHP-Container verbindet sich über den Servicenamen `postgres`; `localhost` im PHP-Container zeigt nicht auf PostgreSQL.

PostgreSQL ist für den Host nur über `127.0.0.1:5433` veröffentlicht.

`DATABASE_URL` wird aus `POSTGRES_*` zusammengesetzt und von Doctrine verwendet. Das lokale PostgreSQL-Volume kann mit dem destruktiven Befehl `make postgres-reinit CONFIRM=postgres18` vollständig neu erstellt werden; Details im [Entwicklungsleitfaden](development.md).

## OAuth

Alle OAuth-Provider sind standardmäßig deaktiviert. Aktivierung und Zugangsdaten sind getrennte Einstellungen: Es werden sowohl `*_ENABLED=1` als auch gültige Client-ID-/Client-Secret-Werte benötigt.

| Provider | Schalter |
|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` |
| Yandex | `OAUTH_YANDEX_ENABLED` |
| VKontakte | `OAUTH_VK_ENABLED` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` |
| Mail.ru | `OAUTH_MAILRU_ENABLED`: muss `0` bleiben |

Lokales Google-Beispiel:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

Weitere Namen von Zugangsdaten, Routen und Regeln stehen im [OAuth-Leitfaden](oauth.md). Echte Schlüssel, Access Tokens, Autorisierungscodes oder externe IDs gehören weder in die Dokumentation noch in Git.

## Panther

Das PHP-Image enthält Chrome for Testing und Chromedriver. Für Tests werden weder Browser auf dem Host noch Java benötigt.

Docker verwendet `PANTHER_WEB_SERVER_HOST=php` und `PANTHER_WEB_SERVER_PORT=9080`; `.env.test` ergänzt testspezifische Einstellungen und das Verzeichnis für Fehler-Screenshots.

Wege zum Chrome-Archiv stehen im [Leitfaden zum Projektstart](getting-started.md), Browser-Tests im [Entwicklungsleitfaden](development.md).
