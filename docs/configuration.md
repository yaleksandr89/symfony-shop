# Конфигурация

Проект разделяет настройки приложения, Docker-инфраструктуры, локальные секреты и тестовые переопределения. Это позволяет хранить безопасные значения в Git и не смешивать их с параметрами конкретного разработчика.

## Слои конфигурации

### `.env`

Отслеживаемый Git файл с общими безопасными настройками Symfony:

- `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`;
- адрес приложения, административный email и правило CORS;
- `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`;
- выключатели и пустые учётные данные OAuth.

Значения предназначены для локальной разработки и не являются конфигурацией production-среды.

### `.env.docker`

Игнорируемый локальный файл для Docker Compose. `make init` создаёт его из `.env.docker.example` и подставляет UID/GID пользователя хоста. Здесь находятся:

- `HOST_UID` и `HOST_GID` для прав на смонтированные файлы;
- `APP_PORT`;
- имя, пользователь и пароль локального PostgreSQL;
- адрес встроенного web server Panther.

Compose использует этот файл и для подстановки в `docker-compose.yml`, и как `env_file` PHP-контейнера. Поэтому переданные значения становятся настоящими переменными окружения процесса.

### `.env.local`

Игнорируемый Symfony-файл для секретов и локальных переопределений, которых нет среди переменных контейнера. Здесь следует хранить учётные данные OAuth, реальный `MAILER_DSN`, локальный `ADMIN_EMAIL` и другие параметры конкретной рабочей машины.

Не добавляйте `.env.local` в Git и не публикуйте его содержимое. В окружении `test` Symfony не загружает `.env.local`.

### `.env.test`

Отслеживаемые тестовые переопределения: SQLite-база `var/db_for_test.db`, настройки Panther, нейтральные транспорты Mailer и Messenger и выключенные OAuth-провайдеры. Эти значения не предназначены для разработки или production-среды.

## Приоритет значений

От более высокого приоритета к более низкому:

1. настоящие переменные окружения контейнера, в том числе переданные из `.env.docker`;
2. `.env.<окружение>.local`, например `.env.dev.local`;
3. `.env.<окружение>`, например `.env.test`;
4. `.env.local`;
5. `.env`.

Название `.env.docker` не является частью механизма Symfony Dotenv. Приоритет появляется потому, что Docker Compose передаёт его значения процессу PHP. Например, `PANTHER_WEB_SERVER_PORT` из `.env.local` не заменит одноимённую переменную из `.env.docker`. Напротив, учётные данные OAuth из `.env.local` сработают, если такие переменные не переданы контейнеру.

После изменения `.env.docker` пересоздайте контейнеры:

```bash
make down
make up
```

После изменения `.env` или `.env.local` это обычно не требуется.

## Docker и локальная среда

Основные локальные параметры:

| Переменная | Назначение | Значение в примере |
| --- | --- | --- |
| `HOST_UID`, `HOST_GID` | пользователь файлов и процессов инструментальных контейнеров | заполняются `make init` |
| `APP_PORT` | HTTP-порт Nginx на хосте | `8080` |
| `POSTGRES_DB` | локальная база приложения | `s_shop` |
| `POSTGRES_USER` | локальный пользователь PostgreSQL | `s_shop` |
| `POSTGRES_PASSWORD` | локальный пароль PostgreSQL | демонстрационное значение |

PHP-FPM внутри образа работает от пользователя `app`. PostgreSQL опубликован только на `127.0.0.1:5433`, а приложение обращается к нему по имени сервиса `postgres`.

## Приложение и база данных

`DATABASE_URL` собирается из `POSTGRES_*` и указывает на PostgreSQL 18 внутри сети Compose. Для локального Docker-сценария не заменяйте имя узла `postgres` на `localhost`: из PHP-контейнера это разные адреса.

`SITE_BASE_SCHEME` и `SITE_BASE_HOST` задают базовый адрес, используемый приложением. Стандартная комбинация — `http` и `localhost:${APP_PORT}`. `APP_TIMEZONE` по умолчанию равен `Europe/Moscow`, основные локали приложения — `ru` и `en`, русская используется по умолчанию.

`ADMIN_EMAIL` и `CORS_ALLOW_ORIGIN` также относятся к приложению. Для локальных значений используйте адреса домена `.test` и не помещайте реальные персональные данные в отслеживаемые файлы.

## Почта и Messenger

По умолчанию `MAILER_DSN=null://null`, поэтому письма не доставляются во внешнюю почтовую систему. Это особенно важно для регистрации, подтверждения email, восстановления пароля и OAuth-регистрации: создание данных может завершиться успешно, но документация не обещает доставку письма при нейтральном транспорте.

Локальный пример с заполнителями:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger использует `MESSENGER_TRANSPORT_DSN=doctrine://default`. Событие регистрации и команда восстановления пароля направляются в транспорт `async`. В Compose нет постоянно работающего worker; для ручной обработки используйте консоль через Docker:

```bash
make console CMD='messenger:consume async -vv'
```

## OAuth

Каждый реализованный провайдер имеет отдельный выключатель. Значение `0` блокирует маршруты на сервере, а не только скрывает кнопку:

```dotenv
OAUTH_GOOGLE_ENABLED=0
OAUTH_YANDEX_ENABLED=0
OAUTH_VK_ENABLED=0
OAUTH_GITHUB_EN_ENABLED=0
OAUTH_GITHUB_RUS_ENABLED=0
OAUTH_FACEBOOK_ENABLED=0
OAUTH_LINKEDIN_ENABLED=0
OAUTH_MAILRU_ENABLED=0
```

Все выключатели по умолчанию равны `0`. Mail.ru намеренно не реализован и должен оставаться выключенным.

Учётные данные храните только локально, используя заполнители вместо реальных значений:

```dotenv
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
OAUTH_YANDEX_CLIENT_ID=YOUR_YANDEX_CLIENT_ID
OAUTH_YANDEX_CLIENT_SECRET=YOUR_YANDEX_CLIENT_SECRET
OAUTH_VK_CLIENT_ID=YOUR_VK_CLIENT_ID
OAUTH_VK_CLIENT_SECRET=YOUR_VK_CLIENT_SECRET
OAUTH_GITHUB_EN_CLIENT_ID=YOUR_GITHUB_EN_CLIENT_ID
OAUTH_GITHUB_EN_CLIENT_SECRET=YOUR_GITHUB_EN_CLIENT_SECRET
OAUTH_GITHUB_RUS_CLIENT_ID=YOUR_GITHUB_RU_CLIENT_ID
OAUTH_GITHUB_RUS_CLIENT_SECRET=YOUR_GITHUB_RU_CLIENT_SECRET
OAUTH_FACEBOOK_CLIENT_ID=YOUR_FACEBOOK_CLIENT_ID
OAUTH_FACEBOOK_CLIENT_SECRET=YOUR_FACEBOOK_CLIENT_SECRET
OAUTH_LINKEDIN_CLIENT_ID=YOUR_LINKEDIN_CLIENT_ID
OAUTH_LINKEDIN_CLIENT_SECRET=YOUR_LINKEDIN_CLIENT_SECRET
```

Для включения провайдера нужны и значение `1` у выключателя, и оба значения учётных данных. Включённый, но не настроенный провайдер возвращает контролируемую ошибку сервера без раскрытия секретов. Подробности — в [руководстве по OAuth](oauth.md).

## Panther и тестовое окружение

`.env.docker` передаёт:

```dotenv
PANTHER_WEB_SERVER_HOST=php
PANTHER_WEB_SERVER_PORT=9080
```

PHP-образ задаёт пути к Chrome и Chromedriver, поэтому браузерные тесты не зависят от браузера или Java на хосте. `.env.test` добавляет `PANTHER_APP_ENV=panther` и каталог для снимков ошибок. Не переносите эти значения в production-среду.

Тесты используют отдельную SQLite-базу и операции с ней защищены явным `CONFIRM=testdb`. Команды и их границы описаны в [руководстве по разработке](development.md).
