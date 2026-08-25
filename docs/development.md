# Разработка

Makefile: основной интерфейс локальной разработки. PHP, Composer и Symfony Console запускаются внутри PHP-контейнера от пользователя `app`, npm: в одноразовом Node-контейнере.

Текущий список команд всегда можно посмотреть через `make help`.

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
| `make in <service>` | Открывает shell сервиса | `php`, `nginx`, `postgres`, `node` |
| `make down` | Останавливает окружение | PostgreSQL volume сохраняется |

PHP-shell открывается от `app`, поэтому штатные команды не должны создавать в рабочей копии файлы, принадлежащие `root`.

## Symfony, Composer и npm

| Команда | Что делает | Примечание |
|---|---|---|
| `make console CMD=about` | Запускает Symfony Console | Любая команда передаётся через `CMD` |
| `make composer CMD='validate --strict'` | Запускает Composer | Внутри PHP-контейнера |
| `make composer-install` | Выполняет `composer install` | Использует `composer.lock` |
| `make npm CMD='npm --version'` | Запускает произвольную npm-команду | В одноразовом Node-контейнере |
| `make npm-install` | Выполняет `npm ci` | Использует `package-lock.json` |
| `make assets-build` | Собирает production assets | Webpack Encore |
| `make watch` | Запускает watcher frontend assets | Долгоживущая команда |

Composer, npm и Symfony CLI на хосте не используются.

Для ручной обработки очереди Messenger:

| Команда | Что делает |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Запускает worker транспорта `async` |

Постоянного Messenger worker в Docker Compose нет.

## Проверки качества

| Команда | Что делает | Изменяет файлы |
|---|---|---|
| `make check` | ESLint + PHP-CS-Fixer check + PHPStan | нет |
| `make eslint-check` | Проверяет JS/Vue через ESLint | нет |
| `make php-cs-fixer-check` | Проверяет форматирование `src/` и `tools/demo/` | нет |
| `make phpstan-check` | Запускает PHPStan для `src` и `tools/demo` | нет |
| `make eslint-fix` | Исправляет ESLint findings | да |
| `make php-cs-fixer` | Исправляет форматирование PHP | да |

`make check` не запускает PHPUnit. Тесты выполняются отдельными целями.

## Тесты

| Команда | Что проверяет | Примечание |
|---|---|---|
| `make test-groups` | Показывает группы PHPUnit | |
| `make test-list` | Показывает список тестов | |
| `make test-unit` | Изолированную прикладную логику | группа `unit` |
| `make test-integration` | Doctrine и совместную работу сервисов | группа `integration` |
| `make test-functional` | HTTP, controllers, API и security | группа `functional` |
| `make test-functional-panther` | Реальные browser-сценарии | группа `functional-panther` |
| `make test-all-core CONFIRM=testdb` | Assets + unit + integration + functional | Пересоздаёт тестовую SQLite-базу |
| `make test-all CONFIRM=testdb` | Полный набор, включая Panther | Пересоздаёт тестовую SQLite-базу |

`CONFIRM=testdb` нужен специально: агрегированные сценарии удаляют и заново создают `var/db_for_test.db`.

Panther использует Chrome for Testing и Chromedriver из PHP-образа. Selenium Server, GeckoDriver, Java и локальный браузер для текущего тестового контракта не нужны.

## Coverage

| Команда | Результат | Примечание |
|---|---|---|
| `make coverage CONFIRM=testdb` | Статистика в терминале | `src` + `tools/demo`, без Panther |
| `make coverage-html CONFIRM=testdb` | Терминал + HTML + Clover | `var/coverage/html`, `var/coverage/clover.xml` |

Обе команды используют один и тот же PHP/PHPUnit scope и предварительно пересоздают тестовую базу. Panther не входит в этот coverage.

Coverage используется как диагностический инструмент для поиска непроверенных участков. Публичного coverage/Codecov badge у проекта нет.

## База данных и demo-данные

| Команда | Что делает | Риск |
|---|---|---|
| `make migrate` | Применяет Doctrine migrations | штатная операция |
| `make demo-init` | Инициализирует demo catalog/accounts/orders | заменяет существующие заказы |
| `make test-db-reset CONFIRM=testdb` | Пересоздаёт `var/db_for_test.db` | удаляет тестовую SQLite-базу |
| `make postgres-reinit CONFIRM=postgres18` | Пересоздаёт локальный PostgreSQL volume | удаляет локальные PostgreSQL данные |

`make demo-init` предназначен для воспроизводимой `dev`/`test` среды. Если локальная база содержит нужные заказы, эту команду запускать нельзя.

## GitHub Actions

Workflow [`Docker Baseline CI`](../.github/workflows/basic.yml) запускается для push и pull request в `master`.

Он:

1. загружает Git LFS и проверяет Chrome archive;
2. создаёт `.env.docker`;
3. проверяет Compose, собирает и запускает Docker-окружение;
4. устанавливает зависимости и собирает frontend assets;
5. запускает ESLint;
6. выполняет unit-, integration-, functional- и Panther-тесты;
7. запускает PHPStan;
8. останавливает контейнеры.

CI не запускает PHP-CS-Fixer check и coverage, поэтому не является полным эквивалентом локального `make check` плюс тестовый набор.

## Журналы и диагностика

| Команда | Что показывает |
|---|---|
| `make ps` | Состояние контейнеров |
| `make log php` | Журнал PHP |
| `make log nginx` | Журнал Nginx |
| `make log postgres` | Журнал PostgreSQL |
| `make log-all` | Все журналы проекта |
| `make console CMD=about` | Состояние Symfony-приложения |

Для первого запуска и Git LFS см. [руководство по запуску](getting-started.md). Правила `.env*` и локальных секретов собраны в [руководстве по конфигурации](configuration.md).
