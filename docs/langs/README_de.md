# Online-Shop mit Symfony

> [Zur Website gehen](https://s-shop.alexanderyurchenko.ru/ "Zur Website gehen")

## Sprache wählen

| Русский  | English                              | Español                              | 中文                              | Français                              | Deutsch                              |
|----------|--------------------------------------|--------------------------------------|---------------------------------|---------------------------------------|--------------------------------------|
| [Русский](../../README.md) | [English](./README_en.md) | [Español](./README_es.md) | [中文](./README_zh.md) | [Français](./README_fr.md) | **Ausgewählt** |

## Verwendete Technologien

* Nginx 1.26.1
* PHP 8.3.9
* Composer 2.7.7
* PostgreSQL 16.3
* npm 10.8.2

## Über das Projekt

Dieses Projekt implementiert einen Online-Shop mit **Symfony v6.4.9**. Einige Funktionen werden mit **Vue 2.6** für den Warenkorb und das Admin-Panel realisiert.

### Hauptfunktionen

* Sprachwechsel
* Konsolenbefehle:
    * `php bin/console app:add-user` - Benutzer erstellen
    * `php bin/console app:update-slug-product` - Produkt-Slug aktualisieren

### Frontend

* Besucherregistrierung;
* Benutzerkonto;
* Passwort-Wiederherstellung;
* Bestellabwicklung mit E-Mail-Benachrichtigungen;
* Möglichkeit zur Authentifizierung und/oder Registrierung über: Yandex, Google, GitHub oder VKontakte.

### Admin-Bereich

* Verwaltung von Bestellungen und Benutzern;
* Erstellung von Kategorien;
* Erstellung von Produkten;
* Erstellung von Bestellungen.

## Installation des Projekts

1. Repository klonen: `git clone git@github.com:yaleksandr89/symfony-shop.git`.
2. Benennen Sie `.env-example` in `.env` um:
    * Konfigurieren Sie `ADMIN_EMAIL` / `MAILER_DSN`, sonst funktionieren die Passwort-Wiederherstellung und die Benutzerregistrierung nicht richtig.
    * Konfigurieren Sie `OAUTH_GOOGLE_ID` / `OAUTH_GOOGLE_SECRET` - sonst funktioniert die Authentifizierung über Google nicht.
    * Konfigurieren Sie `OAUTH_YANDEX_CLIENT_ID` / `OAUTH_YANDEX_CLIENT_SECRET` - sonst funktioniert die Authentifizierung über Yandex nicht.
    * Konfigurieren Sie `OAUTH_VK_CLIENT_ID` / `OAUTH_VK_CLIENT_SECRET` - sonst funktioniert die Authentifizierung über VKontakte nicht.
    * Konfigurieren Sie `OAUTH_GITHUB_EN_CLIENT_ID` / `OAUTH_GITHUB_SECRET` - sonst funktioniert die Authentifizierung über GitHub nicht (Lokalisierung: en).
    * Konfigurieren Sie `OAUTH_GITHUB_RUS_CLIENT_ID` / `OAUTH_GITHUB_RUS_CLIENT_SECRET` - sonst funktioniert die Authentifizierung über GitHub nicht (Lokalisierung: ru).
    * Konfigurieren Sie `SITE_BASE_HOST` / `SITE_BASE_SCHEME` - sonst werden falsche Links bei der Registrierung, der Passwort-Wiederherstellung und in E-Mails generiert.
    * Konfigurieren Sie `APP_TIMEZONE` - gibt die Zeitzone an, die das Projekt verwenden soll. Standardmäßig `APP_TIMEZONE=Europe/Moscow`, wenn Sie die in `php.ini` angegebene Zeitzone verwenden möchten, lassen Sie diese Variable leer.
3. Führen Sie aus: `composer i && npm i && npm run build`.
4. Erstellen Sie die Datenbank: `php bin/console doctrine:database:create` oder `symfony doctrine:database:create` (wenn symfony cli installiert ist).
    * Im Projekt wird `uuid_generate_v4` (verwendete Datenbank PostgreSQL) verwendet, daher müssen Sie sich vor der Migration mit der Datenbank verbinden und ausführen:
        * Verbinden Sie sich mit der ausgewählten Datenbank (`\c NAME DER ERSTELLTEN DATENBANK`).
        * `CREATE EXTENSION "uuid-ossp";`.
        * Zur Überprüfung können Sie `SELECT uuid_generate_v4();` ausführen - wenn eine UUID generiert wird, können Sie mit den Migrationen fortfahren.
5. Führen Sie die Migrationen aus: `php bin/console doctrine:migrations:migrate` oder `symfony doctrine:migrations:migrate` (wenn symfony cli installiert ist).
6. Führen Sie aus: `php bin/console assets:install` oder `symfony console assets:install` (wenn symfony cli installiert ist).
7. Zu diesem Zeitpunkt sollte die Frontend-Seite der Website funktionieren, aber um auf das Admin-Panel zuzugreifen, müssen Sie einen Benutzer erstellen. Dies können Sie über den erstellten Befehl tun:
    * `php bin/console app:add-user` oder `symfony console app:add-user` (wenn symfony cli installiert ist).
    * Geben Sie die E-Mail-Adresse an.
    * Geben Sie das Passwort an (es wird bei der Eingabe nicht angezeigt).
    * Geben Sie die Rolle an, für einen Admin können Sie `ROLE_SUPER_ADMIN` angeben (verfügbare Rollen: `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_USER`).

## Konfiguration von Messenger

Um bestimmte E-Mails (Passwort-Wiederherstellung, Kontobestätigung) zu senden, wird [Symfony Messenger](https://symfony.com/doc/current/components/messenger.html "Symfony Messenger") verwendet, daher müssen Sie den Befehl im Terminal ausführen `symfony console messenger:consume async -vv`. Das manuelle Ausführen des Befehls ist während der Testphase sinnvoll, wenn alles überprüft ist, wird empfohlen:

* den Befehl in `cron` einfügen
* `supervisor` konfigurieren

Beispielkonfiguration, die in `/etc/supervisor/conf.d/messenger-worker.conf` platziert werden muss:

```
;/etc/supervisor/conf.d/messenger-worker.conf
[program:messenger-consume]
command=php /path/to/your/app/bin/console messenger:consume async --time-limit=3600
user=ubuntu
numprocs=2
startsecs=0
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```


* `command=` - nach `php` den Pfad zur Konsole und nach einem Leerzeichen den hinzuzufügenden Befehl angeben
* `user=` - den aktuellen Benutzer angeben
* `numprocs=` - Anzahl der zu erstellenden Prozesse

Die anderen Optionen können unverändert bleiben. [Beispielkonfiguration](https://symfony.com/doc/6.4/messenger.html#supervisor-configuration) von der offiziellen Website.

### Tests

Das Projekt ist mit verschiedenen Arten von Tests abgedeckt (aufgeteilt in Gruppen `#[Group(name: '{name}')]`):

* Unit-Tests
* Integrationstests
* Funktionstests
* Funktionale Panther-Tests
* Funktionale Selenium-Tests

Die Testgruppen 1 - 3 sollten problemlos ausgeführt werden `php ./vendor/bin/phpunit --testdox --group unit --group integration --group functional`. Bei den letzten beiden Gruppen können Probleme aufgrund fehlender [chromedriver](../../drivers/chromedriver) - Chrome-Engine oder [geckodriver](../../drivers/geckodriver) - Firefox-Engine auftreten.

![chromedriver-not-found](../img/chromedriver-not-found.png)

![selenium-server-not-work](../img/selenium-server-not-work.png)

Diese Fehler lassen sich leicht beheben, indem Sie den Treiber herunterladen: https://chromedriver.chromium.org/downloads (abhängig von der Chrome-Version wählen). Sie können versuchen, die Treiber zu verwenden, die ich im Projektverzeichnis **drivers/** abgelegt habe, aber wenn sich die Treiberversion und die installierte Browserversion unterscheiden, können Fehler auftreten.
Wie man den Treiber global im System (Linux) installiert: https://bangladroid.wordpress.com/2016/08/10/how-to-install-chrome-driver-in-linux-mint-selenium-webdriver/

Anschließend müssen Sie vor dem Testen selenium mit dem Befehl starten:

* `java -jar bin/selenium-server-4.22.0.jar standalone`
* `java -jar bin/selenium-server-standalone-3.141.59.jar` (erfordert keinen standalone-Parameter, aber die Version ist älter)

Erfordert Java, das unter Ubuntu mit dem Befehl installiert werden kann: `sudo apt install openjdk-21-jdk`, die Version kann variieren - ich installiere immer die neueste Version.

![install-openjdk-21-jdk](../img/install-openjdk-21-jdk.png)

## Aktualisierungen

* 08.07.2023 - `.circleci` Konfiguration entfernt. Funktioniert nicht mehr in Russland: https://support.circleci.com/hc/en-us/articles/360043679453-CircleCI-Terms-of-Service-Violation-Sanctioned-Country
* 08.07.2023 - Symfony auf die neueste Version aktualisiert, `6.3.1`
* 17.07.2024 - Symfony auf Version `6.4.9` aktualisiert
* 17.07.2024 - Unit-Tests auf Version 11 aktualisiert, ebenfalls Refaktorisierung der Tests
* Hinzufügung der Konfiguration für [nginx](../conf/nginx/s-shop.conf) und [supervisor](../conf/supervisor/messenger-worker.conf), sowie verschiedene Übersetzungen für README.md
