# Разработка

Makefile — основной интерфейс локальной разработки. PHP, Composer и Symfony Console запускаются внутри PHP-контейнера от пользователя `app`, npm — в одноразовом Node-контейнере.

Текущий список целей всегда можно посмотреть через `make help`.

## Первичная настройка

| Команда | Что делает |
|---|---|
| `make help` | Показывает встроенную справку по Makefile |
| `make init` | Создаёт `.env.docker` и локальные каталоги с правом записи |
| `make check-env` | Проверяет наличие `.env.docker` |

## Docker Compose

| Команда | Что делает | Примечание |
|---|---|---|
| `make config` | Проверяет итоговую конфигурацию Compose | Ничего не запускает |
| `make build` | Собирает PHP-образ | |
| `make up` | Запускает `php`, `nginx` и `postgres` | |
| `make ps` | Показывает состояние контейнеров | |
| `make restart <service>` | Перезапускает сервис | `php`, `nginx`, `postgres` |
| `make log <service>` | Показывает журнал сервиса | `php`, `nginx`, `postgres` |
| `make log-all` | Показывает все журналы | |
| `make in <service>` | Открывает оболочку сервиса | `php`, `nginx`, `postgres`, `node` |
| `make down` | Останавливает окружение | Том PostgreSQL сохраняется |

Оболочка PHP-контейнера открывается от `app`, поэтому штатные команды не должны создавать в рабочей копии файлы, принадлежащие `root`.

## Symfony, Composer и npm

| Команда | Что делает | Примечание |
|---|---|---|
| `make console CMD=about` | Запускает Symfony Console | Любая команда передаётся через `CMD` |
| `make composer CMD='validate --strict'` | Запускает Composer | Внутри PHP-контейнера |
| `make composer-install` | Выполняет `composer install` | Использует `composer.lock` |
| `make npm CMD='npm --version'` | Запускает произвольную npm-команду | В одноразовом Node-контейнере |
| `make npm-install` | Выполняет `npm ci` | Использует `package-lock.json` |
| `make assets-build` | Собирает оптимизированные ресурсы фронтенда | Webpack Encore |
| `make watch` | Запускает наблюдение за изменениями ресурсов фронтенда | Долгоживущая команда |

PHP, Composer, Node.js и Symfony Console на хосте не используются.

Для ручной обработки очереди Messenger:

| Команда | Что делает |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Запускает обработчик очереди транспорта `async` |

Постоянного обработчика очереди Messenger в Docker Compose пока нет. Подробнее о почте и очереди см. в [руководстве по конфигурации](configuration.md#почта-и-messenger).

## Проверки качества

| Команда | Что делает | Изменяет файлы |
|---|---|---|
| `make check` | ESLint + проверка PHP-CS-Fixer + PHPStan | нет |
| `make eslint-check` | Проверяет JS/Vue через ESLint | нет |
| `make php-cs-fixer-check` | Проверяет форматирование `src/` и `tools/demo/` | нет |
| `make phpstan-check` | Запускает PHPStan для `src` и `tools/demo` | нет |
| `make eslint-fix` | Исправляет ошибки ESLint | да |
| `make php-cs-fixer` | Исправляет форматирование PHP | да |

`make check` не запускает PHPUnit. Тесты выполняются отдельными целями.

## Тесты

| Команда | Что проверяет | Примечание |
|---|---|---|
| `make test-groups` | Показывает группы PHPUnit | |
| `make test-list` | Показывает список тестов | |
| `make test-unit` | Изолированную прикладную логику | группа `unit` |
| `make test-integration` | Doctrine и совместную работу сервисов | группа `integration` |
| `make test-functional` | HTTP, контроллеры, API и правила доступа | группа `functional` |
| `make test-functional-panther` | Браузерные сценарии | группа `functional-panther` |
| `make test-all-core CONFIRM=testdb` | Ресурсы фронтенда + unit + integration + functional | Пересоздаёт тестовую SQLite-базу |
| `make test-all CONFIRM=testdb` | Полный набор, включая Panther | Пересоздаёт тестовую SQLite-базу |

`CONFIRM=testdb` нужен специально: агрегированные сценарии удаляют и заново создают `var/db_for_test.db`.

Panther использует Chrome for Testing и Chromedriver из PHP-образа. Selenium Server, GeckoDriver, Java и локальный браузер для текущих тестов не нужны.

## Покрытие кода

| Команда | Результат | Примечание |
|---|---|---|
| `make coverage CONFIRM=testdb` | Статистика в терминале | `src` + `tools/demo`, без Panther |
| `make coverage-html CONFIRM=testdb` | Терминал + HTML + Clover | `var/coverage/html`, `var/coverage/clover.xml` |

Обе команды используют одну область PHP/PHPUnit-кода и предварительно пересоздают тестовую базу. Panther в отчёт покрытия не входит.

## База данных и демонстрационные данные

| Команда | Что делает | Риск |
|---|---|---|
| `make migrate` | Применяет Doctrine migrations | штатная операция |
| `make demo-init` | Инициализирует демонстрационные каталог, аккаунты и заказы | заменяет существующие заказы |
| `make test-db-reset CONFIRM=testdb` | Пересоздаёт `var/db_for_test.db` | удаляет тестовую SQLite-базу |
| `make postgres-reinit CONFIRM=postgres18` | Пересоздаёт локальный том PostgreSQL | удаляет локальные PostgreSQL-данные |
| `make cache-prod-clear` | Удаляет сгенерированный prod-кеш | только `var/cache/prod` внутри PHP-контейнера |

`make demo-init` предназначен для воспроизводимой `dev`/`test` среды. Если локальная база содержит нужные заказы, эту команду запускать нельзя.

## CI

Workflow [`CI`](../.github/workflows/basic.yml) запускается для push и pull request в `master`.

Он:

1. загружает Git LFS и проверяет Chrome archive;
2. создаёт `.env.docker`;
3. проверяет Compose, собирает и запускает Docker-окружение;
4. устанавливает зависимости и собирает ресурсы фронтенда;
5. запускает ESLint;
6. выполняет unit-, integration-, functional- и Panther-тесты;
7. запускает PHPStan;
8. останавливает контейнеры.

CI не запускает проверку PHP-CS-Fixer и сбор отчёта покрытия, поэтому эти проверки при необходимости выполняются локально.

## Журналы и диагностика

| Команда | Что показывает |
|---|---|
| `make ps` | Состояние контейнеров |
| `make log php` | Журнал PHP |
| `make log nginx` | Журнал Nginx |
| `make log postgres` | Журнал PostgreSQL |
| `make log-all` | Все журналы проекта |
| `make console CMD=about` | Состояние Symfony-приложения |

## Все команды Make

| Цель | Назначение |
|---|---|
| `help` | встроенная справка |
| `init` | создание `.env.docker` и локальных каталогов |
| `check-env` | проверка `.env.docker` |
| `config` | проверка Docker Compose |
| `build` | сборка PHP-образа |
| `up` | запуск основных сервисов |
| `down` | остановка окружения |
| `restart <service>` | перезапуск сервиса |
| `ps` | состояние контейнеров |
| `log <service>` | журнал выбранного сервиса |
| `log-all` | журналы всех сервисов |
| `in <service>` | оболочка выбранного сервиса |
| `cache-prod-clear` | удаление prod-кеша |
| `console CMD='...'` | Symfony Console |
| `composer CMD='...'` | Composer внутри PHP-контейнера |
| `composer-install` | установка Composer-зависимостей |
| `npm CMD='...'` | npm в одноразовом Node-контейнере |
| `npm-install` | установка npm-зависимостей |
| `assets-build` | оптимизированная сборка ресурсов фронтенда |
| `watch` | наблюдение за изменениями ресурсов фронтенда |
| `migrate` | Doctrine migrations |
| `demo-init` | демонстрационные данные |
| `postgres-reinit CONFIRM=postgres18` | полное пересоздание локального тома PostgreSQL |
| `check` | ESLint + PHP-CS-Fixer check + PHPStan |
| `eslint-fix` | исправление ESLint |
| `eslint-check` | проверка ESLint |
| `php-cs-fixer` | исправление PHP-форматирования |
| `php-cs-fixer-check` | проверка PHP-форматирования |
| `phpstan-check` | статический анализ PHPStan |
| `test-all-core CONFIRM=testdb` | основной набор тестов без Panther |
| `coverage CONFIRM=testdb` | покрытие в терминале |
| `coverage-html CONFIRM=testdb` | покрытие + HTML/Clover |
| `test-all CONFIRM=testdb` | полный набор тестов с Panther |
| `test-groups` | список групп PHPUnit |
| `test-list` | список тестов PHPUnit |
| `test-unit` | unit-тесты |
| `test-db-reset CONFIRM=testdb` | пересоздание тестовой SQLite-базы |
| `test-integration` | integration-тесты |
| `test-functional` | functional-тесты |
| `test-functional-panther` | браузерные тесты Panther |

Для первого запуска и способов получить Chrome for Testing см. [руководство по запуску](getting-started.md). Правила `.env*` и локальных секретов собраны в [руководстве по конфигурации](configuration.md).
