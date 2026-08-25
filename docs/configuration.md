# Конфигурация

В проекте отдельно хранятся общие настройки Symfony, параметры Docker, локальные секреты и тестовые переопределения. Важная особенность: значения, переданные Docker Compose в PHP-контейнер, имеют более высокий приоритет, чем значения из файлов Symfony Dotenv.

## Файлы окружения

| Файл | Назначение | Git |
|---|---|---|
| `.env` | Общие безопасные настройки Symfony и локальные значения по умолчанию | отслеживается |
| `.env.docker` | Параметры Docker Compose и локального PostgreSQL | игнорируется |
| `.env.local` | Секреты и настройки конкретного разработчика | игнорируется |
| `.env.test` | Настройки автоматических тестов | отслеживается |

## Приоритет переменных

От более высокого приоритета к более низкому:

1. переменные окружения процесса, включая значения из `.env.docker`, переданные Docker Compose;
2. `.env.<окружение>.local`;
3. `.env.<окружение>`;
4. `.env.local`;
5. `.env`.

Название `.env.docker` само по себе не даёт файлу специального приоритета. Он появляется потому, что Docker Compose передаёт эти значения в PHP-контейнер как настоящие переменные окружения процесса.

Практическое следствие:

```text
.env.docker
PANTHER_WEB_SERVER_PORT=9080

.env.local
PANTHER_WEB_SERVER_PORT=9999

→ внутри PHP-контейнера используется 9080
```

учётные данные OAuth из `.env.local`, напротив, будут использованы, если Docker не передал переменные с теми же именами.

После изменения `.env.docker` пересоздайте контейнеры через `make down` и `make up`. После изменения `.env` или `.env.local` это обычно не требуется.

## `.env`

Здесь находятся общие параметры приложения: `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`, `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, адрес приложения, CORS и выключатели OAuth.

Значения в `.env` относятся к локальному проекту и не предназначены для боевого окружения.

## `.env.docker`

`make init` создаёт этот файл из `.env.docker.example` и подставляет UID/GID пользователя хоста.

Основные параметры:

| Переменная | Назначение | Значение по умолчанию |
|---|---|---|
| `HOST_UID`, `HOST_GID` | Пользователь файлов, создаваемых контейнерами | заполняются `make init` |
| `APP_PORT` | HTTP-порт Nginx на хосте | `8080` |
| `POSTGRES_DB` | Локальная база PostgreSQL | `s_shop` |
| `POSTGRES_USER` | Локальный пользователь PostgreSQL | `s_shop` |
| `POSTGRES_PASSWORD` | Локальный пароль PostgreSQL | демонстрационное значение |
| `PANTHER_WEB_SERVER_HOST` | Хост встроенного веб-сервера Panther | `php` |
| `PANTHER_WEB_SERVER_PORT` | Порт встроенного веб-сервера Panther | `9080` |

Compose использует `.env.docker` как `env_file` PHP-контейнера, поэтому эти значения становятся переменными окружения процесса.

## `.env.local`

Используйте `.env.local` для учётных данных OAuth, реального `MAILER_DSN`, локального `ADMIN_EMAIL` и других секретов конкретной машины.

Не добавляйте этот файл в Git и не публикуйте его содержимое. В окружении `test` Symfony `.env.local` не загружает.

## `.env.test`

Тестовое окружение использует отдельную SQLite-базу `var/db_for_test.db`, настройки Panther, нейтральные транспорты Mailer/Messenger и выключенные OAuth-провайдеры.

## Почта и Messenger

По умолчанию используется:

```dotenv
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default
```

`MAILER_DSN=null://null` означает, что локальная среда не отправляет письма во внешний SMTP-сервис. Письма, созданные синхронно во время HTTP-запроса, можно посмотреть в панели Mailer Symfony Profiler.

Для реального SMTP транспорта задайте собственный `MAILER_DSN` в `.env.local`, например:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger уже маршрутизирует регистрацию и восстановление пароля в транспорт `async`, но постоянный обработчик очереди в Docker Compose не запускается. Сообщение попадает в Doctrine-очередь и остаётся там, пока обработчик очереди не будет запущен вручную:

| Команда | Что делает |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Запускает обработчик транспорта `async` в PHP-контейнере |

Это особенно важно при проверке регистрации и восстановления пароля: без обработчика очереди соответствующие асинхронные сообщения не будут обработаны. В будущем планируется отдельный локальный почтовый сервис с веб-интерфейсом и постоянный обработчик очереди Messenger.

## PostgreSQL

Docker Compose использует PostgreSQL 18.4. PHP-контейнер подключается к базе по имени сервиса `postgres`; `localhost` внутри PHP-контейнера указывает уже не на PostgreSQL.

На хост PostgreSQL опубликован только через `127.0.0.1:5433`.

`DATABASE_URL` собирается из `POSTGRES_*` и используется Doctrine. Полное пересоздание локального тома PostgreSQL выполняется отдельной деструктивной командой `make postgres-reinit CONFIRM=postgres18`; подробнее она описана в [руководстве по разработке](development.md#база-данных-и-демонстрационные-данные).

## OAuth

Все OAuth-провайдеры по умолчанию выключены. Включение и учётные данные — независимые настройки: для работы провайдера нужны и `*_ENABLED=1`, и заполненные Client ID и Client Secret.

| Провайдер | Выключатель |
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

Остальные имена учётных данных, маршруты и правила работы собраны в [руководстве по OAuth](oauth.md). Реальные ключи, токены доступа, коды авторизации и внешние ID в документацию и Git не добавляются.

## Panther

PHP-образ содержит Chrome for Testing и Chromedriver. Браузер на хосте и Java для тестов не нужны.

В Docker используются `PANTHER_WEB_SERVER_HOST=php` и `PANTHER_WEB_SERVER_PORT=9080`, а `.env.test` добавляет тестовые настройки приложения и каталог скриншотов ошибок.

Способы получить Chrome archive описаны в [руководстве по запуску](getting-started.md#git-lfs-и-chrome-for-testing), а браузерные тесты — в [руководстве по разработке](development.md#тесты).
