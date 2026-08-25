# Разработка и проверки

Makefile — единая точка входа в локальную среду. PHP, Composer и Symfony Console выполняются в PHP-контейнере от пользователя `app`; npm запускается в одноразовом Node-контейнере. Перед работой нужны созданный `make init` файл `.env.docker` и запущенные сервисы.

Полный актуальный список можно получить командой:

```bash
make help
```

## Контейнеры

```bash
make config
make build
make up
make ps
make restart php
make restart nginx
make restart postgres
make down
```

- `make config` проверяет и печатает итоговую конфигурацию Docker Compose;
- `make build` собирает PHP-образ для разработки;
- `make up` запускает `php`, `nginx` и `postgres`;
- `make down` останавливает проект и удаляет orphan-контейнеры, не удаляя PostgreSQL volume.

Shell нужного сервиса открывается как `make in php`, `make in nginx`, `make in postgres` или `make in node`.

## Symfony Console, Composer и npm

```bash
make console CMD=about
make console CMD='debug:router'
make composer CMD='validate --strict'
make composer-install
make npm CMD='npm --version'
make npm-install
```

`make composer-install` выполняет `composer install`, а `make npm-install` — `npm ci`. Они используют lock-файлы и не заменяют операции обновления зависимостей. Не запускайте Composer, npm или Symfony CLI на хосте: их версии и расширения не являются средой проекта.

Для ручной обработки очереди Messenger используется тот же путь:

```bash
make console CMD='messenger:consume async -vv'
```

Compose не содержит постоянно работающего worker.

## Ресурсы фронтенда

```bash
make assets-build
make watch
```

`make assets-build` запускает production-сборку Webpack Encore в одноразовом Node-контейнере. `make watch` оставляет наблюдение за изменениями активным. Текущий фронтенд состоит из Vue 2-компонентов, подключённых как отдельные точки входа Encore, и серверных Twig-страниц.

## Проверки качества

```bash
make check
make eslint-check
make php-cs-fixer-check
make phpstan-check
```

`make check` последовательно объединяет:

1. ESLint без записи файлов;
2. PHP-CS-Fixer в режиме `--dry-run` для `src/` и `tools/demo/`;
3. PHPStan уровня 4 для `src` и `tools/demo`.

Тесты в `make check` не входят. Исправляющие цели существуют отдельно:

```bash
make eslint-fix
make php-cs-fixer
```

Они изменяют файлы, поэтому перед запуском следует проверить рабочее дерево.

## PHPUnit

Список групп и тестов:

```bash
make test-groups
make test-list
```

Отдельные слои:

```bash
make test-unit
make test-integration
make test-functional
make test-functional-panther
```

- `unit` проверяет изолированную прикладную логику;
- `integration` проверяет взаимодействие сервисов и Doctrine;
- `functional` проверяет HTTP, контроллеры, API и контракты безопасности;
- `functional-panther` запускает реальные браузерные сценарии через Panther.

Chrome for Testing и Chromedriver уже находятся в PHP-образе. Локальные Chrome, Java, Selenium Server и дополнительные WebDriver не нужны.

## Агрегированные тесты

```bash
make test-all-core CONFIRM=testdb
make test-all CONFIRM=testdb
```

`make test-all-core`:

1. собирает ресурсы фронтенда;
2. запускает unit-тесты;
3. пересоздаёт SQLite-базу `var/db_for_test.db` и загружает тестовые данные;
4. запускает integration- и functional-тесты.

`make test-all` выполняет тот же основной набор и затем `functional-panther`. Значение `CONFIRM=testdb` обязательно, потому что агрегированный сценарий удаляет и создаёт заново тестовую базу.

## Coverage

```bash
make coverage CONFIRM=testdb
make coverage-html CONFIRM=testdb
```

Обе цели используют основной PHPUnit-набор без группы `functional-panther`, предварительно собирают ресурсы фронтенда и пересоздают тестовую БД. Источники coverage — PHP-код в `src` и `tools/demo`; браузерные Panther-тесты в PHP/PHPUnit coverage не входят.

- `make coverage` печатает только терминальную статистику;
- `make coverage-html` печатает ту же сводку и создаёт `var/coverage/html` и `var/coverage/clover.xml`.

Coverage служит инженерной диагностикой: он помогает находить непроверенные ветви, но не является KPI или публичным доказательством качества. Публичный badge и интеграция Codecov в проекте не используются.

## База данных и demo-данные

```bash
make migrate
make demo-init
make test-db-reset CONFIRM=testdb
make postgres-reinit CONFIRM=postgres18
```

- `make migrate` применяет Doctrine migrations к текущей базе;
- `make demo-init` работает только в `dev` и `test`, обновляет демонстрационный каталог и полностью пересоздаёт заказы;
- `make test-db-reset` удаляет тестовую SQLite-базу, создаёт схему и загружает тестовые данные;
- `make postgres-reinit` останавливает контейнеры и удаляет локальный volume PostgreSQL проекта.

Последние две операции защищены разными точными подтверждениями. `make postgres-reinit` уничтожает локальные данные PostgreSQL; используйте его только когда это действительно требуется.

## Журналы и диагностика

```bash
make log php
make log nginx
make log postgres
make log-all
make ps
make console CMD=about
```

Цели `log` и `log-all` непрерывно следят за журналами. Для изменения настройки среды из `.env.docker` пересоздайте контейнеры; обычное изменение `.env` или `.env.local` обычно подхватывается Symfony без этого.

## GitHub Actions

Workflow `Docker Baseline CI` запускается для push и pull request в `master`. Он:

- загружает Git LFS и проверяет Chrome ZIP;
- создаёт `.env.docker`, проверяет конфигурацию Compose, собирает и запускает контейнеры;
- устанавливает зафиксированные PHP- и npm-зависимости и собирает ресурсы фронтенда;
- выполняет ESLint, unit-, integration-, functional- и Panther-тесты;
- выполняет PHPStan;
- останавливает контейнеры на завершающем шаге.

CI не запускает PHP-CS-Fixer check и coverage, поэтому не является точным эквивалентом `make check` или всей локальной цепочки. Локально выбирайте проверки по характеру изменения.
