# Configuration

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/configuration.md) | **English** | [Español](../ru/configuration.md) | [中文](../ru/configuration.md) | [Français](../ru/configuration.md) | [Deutsch](../ru/configuration.md) |


The project keeps Symfony defaults, Docker parameters, local secrets, and test overrides in separate layers. An important detail is that values passed into the PHP container by Docker Compose have higher priority than values loaded from Symfony Dotenv files.

## Environment files

| File | Purpose | Git |
|---|---|---|
| `.env` | Shared safe Symfony settings and local defaults | tracked |
| `.env.docker` | Docker Compose and local PostgreSQL parameters | ignored |
| `.env.local` | Developer-specific secrets and settings | ignored |
| `.env.test` | Automated test settings | tracked |

## Variable precedence

From highest to lowest priority:

1. process environment variables, including values from `.env.docker` passed by Docker Compose;
2. `.env.<environment>.local`;
3. `.env.<environment>`;
4. `.env.local`;
5. `.env`.

The filename `.env.docker` does not itself give the file special priority. The priority comes from Docker Compose passing these values into the PHP container as real process environment variables.

Practical example:

```text
.env.docker
PANTHER_WEB_SERVER_PORT=9080

.env.local
PANTHER_WEB_SERVER_PORT=9999

→ inside the PHP container, 9080 is used
```

OAuth credentials from `.env.local`, on the other hand, are used when Docker has not passed variables with the same names.

After changing `.env.docker`, recreate the containers with `make down` and `make up`. Changes to `.env` or `.env.local` usually do not require that.

## `.env`

This file contains shared application parameters such as `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`, `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, the application address, CORS, and OAuth feature switches.

Values in `.env` are local project defaults and are not intended for production.

## `.env.docker`

`make init` creates this file from `.env.docker.example` and inserts the host user's UID/GID.

Main parameters:

| Variable | Purpose | Default |
|---|---|---|
| `HOST_UID`, `HOST_GID` | Ownership for files created by containers | filled by `make init` |
| `APP_PORT` | Nginx HTTP port on the host | `8080` |
| `POSTGRES_DB` | Local PostgreSQL database | `s_shop` |
| `POSTGRES_USER` | Local PostgreSQL user | `s_shop` |
| `POSTGRES_PASSWORD` | Local PostgreSQL password | demo value |
| `PANTHER_WEB_SERVER_HOST` | Panther built-in web server host | `php` |
| `PANTHER_WEB_SERVER_PORT` | Panther built-in web server port | `9080` |

Compose uses `.env.docker` as the PHP container `env_file`, so these values become process environment variables.

## `.env.local`

Use `.env.local` for OAuth credentials, a real `MAILER_DSN`, a local `ADMIN_EMAIL`, and other machine-specific secrets.

Do not add this file to Git or publish its contents. In the `test` environment, Symfony does not load `.env.local`.

## `.env.test`

The test environment uses a separate SQLite database at `var/db_for_test.db`, Panther settings, neutral Mailer/Messenger transports, and disabled OAuth providers.

## Mail and Messenger

The defaults are:

```dotenv
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default
```

`MAILER_DSN=null://null` means the local environment does not send messages through an external SMTP service. Messages created synchronously during an HTTP request can be inspected in the Mailer panel of Symfony Profiler.

For a real SMTP transport, define your own `MAILER_DSN` in `.env.local`, for example:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger already routes registration and password-reset messages to the `async` transport, but Docker Compose does not start a permanent queue worker. A message is stored in the Doctrine queue and remains there until the worker is started manually:

| Command | Purpose |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Start the `async` transport worker inside the PHP container |

This is especially important when testing registration and password reset: without the worker, the corresponding asynchronous messages will not be processed. A dedicated local mail service with a web interface and a permanent Messenger worker is planned for a later stage.

## PostgreSQL

Docker Compose uses PostgreSQL 18.4. The PHP container connects to the database through the `postgres` service name; `localhost` inside the PHP container does not point to PostgreSQL.

PostgreSQL is exposed to the host only through `127.0.0.1:5433`.

`DATABASE_URL` is assembled from the `POSTGRES_*` values and used by Doctrine. A full recreation of the local PostgreSQL volume is performed by the separate destructive command `make postgres-reinit CONFIRM=postgres18`; see the [development guide](development.md) for details.

## OAuth

All OAuth providers are disabled by default. Enabling a provider and providing credentials are separate settings: both `*_ENABLED=1` and valid Client ID / Client Secret values are required.

| Provider | Switch |
|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` |
| Yandex | `OAUTH_YANDEX_ENABLED` |
| VKontakte | `OAUTH_VK_ENABLED` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` |
| Mail.ru | `OAUTH_MAILRU_ENABLED`: must remain `0` |

Example local Google configuration:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

The remaining credential names, routes, and behavioral rules are collected in the [OAuth guide](oauth.md). Real keys, access tokens, authorization codes, and external IDs must not be added to documentation or Git.

## Panther

The PHP image contains Chrome for Testing and Chromedriver. A browser on the host and Java are not required for tests.

Docker uses `PANTHER_WEB_SERVER_HOST=php` and `PANTHER_WEB_SERVER_PORT=9080`, while `.env.test` adds test-specific application settings and the directory for failure screenshots.

Ways to obtain the Chrome archive are described in the [getting-started guide](getting-started.md), and browser tests are covered in the [development guide](development.md).
