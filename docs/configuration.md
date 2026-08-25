# Конфигурация

В проекте отдельно хранятся общие настройки Symfony, параметры Docker, локальные секреты и тестовые переопределения. Это важно не только для порядка: значения, переданные Docker Compose в контейнер, имеют более высокий приоритет, чем файлы Symfony Dotenv.

## Файлы окружения

| Файл | Назначение | Git |
|---|---|---|
| `.env` | Общие безопасные настройки Symfony и локальные значения по умолчанию | отслеживается |
| `.env.docker` | Параметры Docker Compose и локального PostgreSQL | игнорируется |
| `.env.local` | Секреты и настройки конкретного разработчика | игнорируется |
| `.env.test` | Настройки автоматических тестов | отслеживается |

### `.env`

Здесь находятся общие параметры приложения: `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`, `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, адрес приложения, CORS и выключатели OAuth.

Значения в `.env` относятся к локальному проекту и не являются production-конфигурацией.

### `.env.docker`

`make init` создаёт этот файл из `.env.docker.example` и подставляет UID/GID пользователя хоста.

Основные параметры:

| Переменная | Назначение | Значение по умолчанию |
|---|---|---|
| `HOST_UID`, `HOST_GID` | Пользователь файлов, создаваемых контейнерами | заполняются `make init` |
| `APP_PORT` | HTTP-порт Nginx на хосте | `8080` |
| `POSTGRES_DB` | Локальная база PostgreSQL | `s_shop` |
| `POSTGRES_USER` | Локальный пользователь PostgreSQL | `s_shop` |
| `POSTGRES_PASSWORD` | Локальный пароль PostgreSQL | демонстрационное значение |
| `PANTHER_WEB_SERVER_HOST` | Хост встроенного web server Panther | `php` |
| `PANTHER_WEB_SERVER_PORT` | Порт встроенного web server Panther | `9080` |

Compose использует `.env.docker` как `env_file` PHP-контейнера. Поэтому эти значения становятся настоящими переменными окружения процесса.

### `.env.local`

Используйте `.env.local` для OAuth credentials, реального `MAILER_DSN`, локального `ADMIN_EMAIL` и других секретов конкретной машины.

Не добавляйте этот файл в Git и не публикуйте его содержимое. В окружении `test` Symfony `.env.local` не загружает.

### `.env.test`

Тестовое окружение использует отдельную SQLite-базу `var/db_for_test.db`, настройки Panther, нейтральные Mailer/Messenger transports и выключенные OAuth providers.

## Приоритет переменных

От более высокого приоритета к более низкому:

1. переменные окружения процесса, включая значения из `.env.docker`;
2. `.env.<окружение>.local`;
3. `.env.<окружение>`;
4. `.env.local`;
5. `.env`.

Название `.env.docker` само по себе не даёт файлу специального приоритета. Приоритет появляется потому, что Docker Compose передаёт его значения в PHP-контейнер как настоящие environment variables.

Например, `PANTHER_WEB_SERVER_PORT` из `.env.local` не перекроет одноимённое значение из `.env.docker`. OAuth credentials из `.env.local`, напротив, будут использованы, если Docker не передал переменные с теми же именами.

После изменения `.env.docker` пересоздайте контейнеры через `make down` и `make up`. После изменения `.env` или `.env.local` это обычно не требуется.

## PostgreSQL

Docker Compose использует PostgreSQL 18.4. PHP-контейнер подключается к базе по имени сервиса `postgres`; `localhost` внутри PHP-контейнера указывает уже не на PostgreSQL.

На хост PostgreSQL опубликован только через `127.0.0.1:5433`.

`DATABASE_URL` собирается из `POSTGRES_*` и используется Doctrine. Полное пересоздание локального PostgreSQL volume выполняется отдельной деструктивной командой `make postgres-reinit CONFIRM=postgres18`; подробнее она описана в [руководстве по разработке](development.md#база-данных-и-demo-данные).

## Почта и Messenger

По умолчанию используется `MAILER_DSN=null://null`, поэтому локальная среда не отправляет письма наружу.

Для реального SMTP транспорта задайте собственный `MAILER_DSN` в `.env.local`, например:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger настроен на Doctrine transport `async`. В Compose нет постоянно работающего worker. Для ручной обработки очереди используйте:

| Команда | Что делает |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Запускает worker внутри PHP-контейнера |

## OAuth

Все OAuth providers по умолчанию выключены. Включение и credentials: разные настройки: provider должен иметь и `*_ENABLED=1`, и заполненные Client ID/secret.

| Provider | Выключатель |
|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` |
| Yandex | `OAUTH_YANDEX_ENABLED` |
| VKontakte | `OAUTH_VK_ENABLED` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` |
| Mail.ru | `OAUTH_MAILRU_ENABLED`: должен оставаться `0` |

Пример локальной настройки Google:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

Остальные имена credentials, маршруты и правила работы собраны в [руководстве по OAuth](oauth.md). Реальные ключи, access tokens, authorization codes и external IDs в документацию и Git не добавляются.

## Panther

PHP-образ содержит Chrome for Testing и Chromedriver. Браузер на хосте и Java для тестов не нужны.

В Docker используются `PANTHER_WEB_SERVER_HOST=php` и `PANTHER_WEB_SERVER_PORT=9080`, а `.env.test` добавляет тестовые настройки приложения и каталог скриншотов ошибок.

Порядок получения Chrome archive через Git LFS описан в [руководстве по запуску](getting-started.md#git-lfs-и-chrome-for-testing), а browser-тесты: в [руководстве по разработке](development.md#тесты).
