# Online Shop with Symfony

> [Go to the website](https://s-shop.alexanderyurchenko.ru/ "Go to the website")

## Choose Language

| Русский  | English                              | Español                              | 中文                              | Français                              | Deutsch                              |
|----------|--------------------------------------|--------------------------------------|---------------------------------|---------------------------------------|--------------------------------------|
| [Русский](../../README.md) | **Selected** | [Español](./README_es.md) | [中文](./README_zh.md) | [Français](./README_fr.md) | [Deutsch](./README_de.md) |

## Technologies Used

* Nginx 1.26.1
* PHP 8.3.9
* Composer 2.7.7
* PostgreSQL 16.3
* npm 10.8.2

## About the Project

This project implements an online shop using **Symfony v6.4.9**. Some functionality is built with **Vue 2.6** for the shopping cart and admin panel.

### Main Features

* Language switching
* Console commands:
   * `php bin/console app:add-user` - create user
   * `php bin/console app:update-slug-product` - update product slug

### Frontend

* Visitor registration;
* User account;
* Password recovery;
* Order checkout with email notifications;
* Authentication and/or registration using: Yandex, Google, GitHub, or VKontakte.

### Admin Area

* Management of orders and users;
* Creation of categories;
* Creation of products;
* Creation of orders.

## Installation Guide

1. Clone the repository: `git clone git@github.com:yaleksandr89/symfony-shop.git`.
2. Rename `.env-example` to `.env`:
   * Configure `ADMIN_EMAIL` / `MAILER_DSN` otherwise password recovery won't work properly, and user registration process won't complete.
   * Configure `OAUTH_GOOGLE_ID` / `OAUTH_GOOGLE_SECRET` - otherwise Google authentication won't work.
   * Configure `OAUTH_YANDEX_CLIENT_ID` / `OAUTH_YANDEX_CLIENT_SECRET` - otherwise Yandex authentication won't work.
   * Configure `OAUTH_VK_CLIENT_ID` / `OAUTH_VK_CLIENT_SECRET` - otherwise VKontakte authentication won't work.
   * Configure `OAUTH_GITHUB_EN_CLIENT_ID` / `OAUTH_GITHUB_SECRET` - otherwise GitHub authentication won't work (locale: en).
   * Configure `OAUTH_GITHUB_RUS_CLIENT_ID` / `OAUTH_GITHUB_RUS_CLIENT_SECRET` - otherwise GitHub authentication won't work (locale: ru).
   * Configure `SITE_BASE_HOST` / `SITE_BASE_SCHEME` - otherwise incorrect links will be generated during registration, password recovery, and links in emails.
   * Configure `APP_TIMEZONE` - specifies the timezone the project will use. Default is `APP_TIMEZONE=Europe/Moscow`, if you want to use the timezone specified in `php.ini`, leave this variable empty.
3. Execute: `composer i && npm i && npm run build`.
4. Create the database: `php bin/console doctrine:database:create` or `symfony doctrine:database:create` (if symfony cli is installed).
   * The project uses `uuid_generate_v4` (used database PostgreSQL), so before migration, connect to the database and execute:
      * Connect to the created database (`\c NAME OF CREATED DATABASE`).
      * `CREATE EXTENSION "uuid-ossp";`.
      * To check, you can execute `SELECT uuid_generate_v4();` - if a UUID is generated in response, you can proceed with migrations.
5. Execute migrations: `php bin/console doctrine:migrations:migrate` or `symfony doctrine:migrations:migrate` (if symfony cli is installed).
6. Execute: `php bin/console assets:install` or `symfony console assets:install` (if symfony cli is installed).
7. At this point, the front-end site will be operational, but to access the admin panel, you need to create a user. You can do this using the created command:
   * `php bin/console app:add-user` or `symfony console app:add-user` (if symfony cli is installed).
   * Specify the email address.
   * Specify the password (it will not be displayed during input).
   * Specify the role, for admin you can specify `ROLE_SUPER_ADMIN` (available roles: `ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_USER`).

## Messenger Configuration

To send certain emails (password recovery, account confirmation), [Symfony Messenger](https://symfony.com/doc/current/components/messenger.html "Symfony Messenger") is used, so you need to run the command in the terminal `symfony console messenger:consume async -vv`. Manual command execution is advisable during the testing phase, once everything is verified, it is recommended to:

* add the command to `cron`
* configure `supervisor`

Example configuration that needs to be placed in `/etc/supervisor/conf.d/messenger-worker.conf`:

```
;/etc/supervisor/conf.d/messenger-worker.conf
[program
]
command=php /path/to/your/app/bin/console messenger
async --time-limit=3600
user=ubuntu
numprocs=2
startsecs=0
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```


* `command=` - after `php` specify the path to the console and after a space specify the command to add
* `user=` - specify the current user
* `numprocs=` - number of processes to be created

The other options can remain unchanged. [Example configuration](https://symfony.com/doc/6.4/messenger.html#supervisor-configuration) from the official website.

### Testing

The project is covered with various types of tests (grouped into `#[Group(name: '{name}')]`):

* Unit tests
* Integration tests
* Functional tests
* Functional Panther tests
* Functional Selenium tests

Groups of tests 1 - 3 should run without any problems `php ./vendor/bin/phpunit --testdox --group unit --group integration --group functional`. For the last two groups, problems may occur due to the absence of installed [chromedriver](../../drivers/chromedriver) - Chrome engine or [geckodriver](../../drivers/geckodriver) - Firefox engine.

![chromedriver-not-found](../img/chromedriver-not-found.png)

![selenium-server-not-work](../img/selenium-server-not-work.png)

These bugs are easy to fix by downloading the driver: https://chromedriver.chromium.org/downloads (choose depending on the Chrome version). You can try using the drivers I placed in the project directory **drivers/**, but if the driver version and the installed browser version differ, errors may occur.
How to install the driver globally in the system (Linux): https://bangladroid.wordpress.com/2016/08/10/how-to-install-chrome-driver-in-linux-mint-selenium-webdriver/

After this, before starting testing, you need to start selenium with the command:

* `java -jar bin/selenium-server-4.22.0.jar standalone`
* `java -jar bin/selenium-server-standalone-3.141.59.jar` (does not require specifying the standalone parameter, but the version is older)

Requires Java, which can be installed on Ubuntu with the command: `sudo apt install openjdk-21-jdk`, the version may vary - I always install the latest version.

![install-openjdk-21-jdk](../img/install-openjdk-21-jdk.png)

## Updates

* 08.07.2023 - Removed `.circleci` configuration. No longer works in Russia: https://support.circleci.com/hc/en-us/articles/360043679453-CircleCI-Terms-of-Service-Violation-Sanctioned-Country
* 08.07.2023 - Symfony updated to the latest version, `6.3.1`
* 17.07.2024 - Symfony updated to version `6.4.9`
* 17.07.2024 - Unit tests updated to version 11, also refactored the tests
* Added configuration for [nginx](../conf/nginx/s-shop.conf) and [supervisor](../conf/supervisor/messenger-worker.conf), as well as various translations for README.md
