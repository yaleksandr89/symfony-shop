COMPOSE = docker compose -p symfony-shop --env-file .env.docker
CMD ?=
PHP_APP = $(COMPOSE) exec -T --user app
SQLITE_TEST_PHP = $(PHP_APP) -e APP_ENV=test -e SYMFONY_SHOP_TEST_DB_GUARD=sqlite -e TEST_DATABASE_URL -e TEST_TOKEN
CORE_COVERAGE = --group unit --group integration --group functional --exclude-group functional-panther --exclude-group reset-password-postgresql --exclude-group oauth-postgresql --do-not-record-test-run-history
RESET_PASSWORD_COMPOSE = $(COMPOSE) -f docker-compose.yml -f docker-compose.reset-password-test.yml
OAUTH_COMPOSE = $(COMPOSE) -f docker-compose.yml -f docker-compose.oauth-test.yml

INTERACTIVE_TARGETS = restart log in
IN_SERVICES = php nginx postgres node
RESTART_SERVICES = php nginx postgres
LOG_SERVICES = php nginx postgres

ifneq ($(filter $(INTERACTIVE_TARGETS),$(MAKECMDGOALS)),)
ifeq ($(filter $(INTERACTIVE_TARGETS),$(firstword $(MAKECMDGOALS))),)
$(error Service target must be the first goal)
endif
SERVICE := $(word 2,$(MAKECMDGOALS))
ifeq ($(strip $(SERVICE)),)
$(error Usage: make $(firstword $(MAKECMDGOALS)) <service>)
endif
ifneq ($(strip $(word 3,$(MAKECMDGOALS))),)
$(error Expected exactly one service argument)
endif
ifeq ($(firstword $(MAKECMDGOALS)),in)
ifeq ($(filter $(SERVICE),$(IN_SERVICES)),)
$(error Unknown service '$(SERVICE)'. Allowed: $(IN_SERVICES))
endif
endif
ifeq ($(firstword $(MAKECMDGOALS)),restart)
ifeq ($(filter $(SERVICE),$(RESTART_SERVICES)),)
$(error Unknown service '$(SERVICE)'. Allowed: $(RESTART_SERVICES))
endif
endif
ifeq ($(firstword $(MAKECMDGOALS)),log)
ifeq ($(filter $(SERVICE),$(LOG_SERVICES)),)
$(error Unknown service '$(SERVICE)'. Allowed: $(LOG_SERVICES))
endif
endif
.PHONY: $(SERVICE)
$(SERVICE): ;
endif

.PHONY: help init check-env config build up down restart ps log log-all in cache-prod-clear console composer composer-install npm npm-install assets-build watch migrate demo-init postgres-reinit check eslint-fix eslint-check php-cs-fixer php-cs-fixer-check phpstan-check test-all-core coverage coverage-html test-all test-groups test-list test-unit test-db-reset test-integration test-functional test-functional-panther
.PHONY: composer-validate composer-audit php-lint test-reset-password-postgresql test-oauth-postgresql

help:
	@printf '%s\n' 'Bootstrap / Первичная настройка:'
	@printf '%s\n' '  make init                              Create .env.docker and writable local directories / Создать .env.docker и локальные каталоги с правом записи'
	@printf '%s\n' '  make check-env                         Verify .env.docker exists / Проверить наличие .env.docker'
	@printf '%s\n' ''
	@printf '%s\n' 'Docker lifecycle / Управление Docker:'
	@printf '%s\n' '  make config                            Validate and print Docker Compose config / Проверить и вывести конфигурацию Docker Compose'
	@printf '%s\n' '  make build                             Build PHP development image / Собрать dev-образ PHP'
	@printf '%s\n' '  make up                                Start php, nginx and postgres / Запустить php, nginx и postgres'
	@printf '%s\n' '  make down                              Stop project containers and remove orphans / Остановить контейнеры проекта и удалить orphan-контейнеры'
	@printf '%s\n' '  make restart <php|nginx|postgres>      Restart one running service / Перезапустить один запущенный сервис'
	@printf '%s\n' '  make ps                                Show project containers / Показать контейнеры проекта'
	@printf '%s\n' ''
	@printf '%s\n' 'Interactive / diagnostics / Интерактивная работа и диагностика:'
	@printf '%s\n' '  make in <php|nginx|postgres|node>      Open a non-root service shell / Открыть shell сервиса без root'
	@printf '%s\n' '  make log <php|nginx|postgres>          Follow logs for one service / Смотреть логи одного сервиса в реальном времени'
	@printf '%s\n' '  make log-all                           Follow project logs / Смотреть все логи проекта в реальном времени'
	@printf '%s\n' ''
	@printf '%s\n' 'PHP / Symfony / Composer:'
	@printf '%s\n' '  make console CMD=about                 Run Symfony console in php as app / Запустить Symfony Console в PHP-контейнере от app'
	@printf '%s\n' '  make composer CMD="..."                  Run Composer in php as app / Запустить Composer в PHP-контейнере от app'
	@printf '%s\n' '  make composer-install                  Install locked Composer dependencies / Установить Composer-зависимости из lock-файла'
	@printf '%s\n' '  make migrate                           Run Doctrine migrations / Применить миграции Doctrine'
	@printf '%s\n' '  make demo-init                         Initialize reproducible dev/test demo data / Инициализировать воспроизводимые demo-данные для dev/test'
	@printf '%s\n' ''
	@printf '%s\n' 'Frontend / Фронтенд:'
	@printf '%s\n' '  make npm CMD="..."                       Run npm command in one-off Node container / Запустить npm-команду в одноразовом Node-контейнере'
	@printf '%s\n' '  make npm-install                       Install locked npm dependencies / Установить npm-зависимости из lock-файла'
	@printf '%s\n' '  make assets-build                      Build frontend assets / Собрать frontend assets'
	@printf '%s\n' '  make watch                             Run frontend watcher / Запустить watcher frontend assets'
	@printf '%s\n' ''
	@printf '%s\n' 'Quality / Качество:'
	@printf '%s\n' '  make check                             Run Composer, PHP and ESLint checks (may write caches) / Проверить Composer, PHP и ESLint (возможна запись кешей)'
	@printf '%s\n' '  make composer-validate                 Validate composer.json and its lock strictly / Строго проверить composer.json и lock-файл'
	@printf '%s\n' '  make composer-audit                    Audit locked PHP dependencies / Проверить зафиксированные PHP-зависимости на уязвимости'
	@printf '%s\n' '  make php-lint                          Lint first-party PHP, including bin/console / Проверить синтаксис PHP-кода проекта, включая bin/console'
	@printf '%s\n' '  make eslint-check                      Check first-party JS and Vue in assets / Проверить JS и Vue проекта в assets'
	@printf '%s\n' '  make eslint-fix                        Fix ESLint issues through Node container / Исправить ESLint через Node-контейнер'
	@printf '%s\n' '  make php-cs-fixer                      Fix first-party PHP formatting as app / Исправить форматирование PHP-кода проекта от app'
	@printf '%s\n' '  make php-cs-fixer-check                Check first-party PHP style (may write cache) / Проверить стиль PHP-кода проекта (возможна запись кеша)'
	@printf '%s\n' '  make phpstan-check                     Run PHPStan level 8 (may write cache) / Запустить PHPStan уровня 8 (возможна запись кеша)'
	@printf '%s\n' ''
	@printf '%s\n' 'Tests / Тесты:'
	@printf '%s\n' '  make test-groups                       List available PHPUnit groups / Показать доступные группы PHPUnit'
	@printf '%s\n' '  make test-list                         List available PHPUnit tests / Показать доступные тесты PHPUnit'
	@printf '%s\n' '  make test-unit                         Run PHPUnit unit group / Запустить unit-группу PHPUnit'
	@printf '%s\n' '  make test-integration                  Run PHPUnit integration group / Запустить integration-группу PHPUnit'
	@printf '%s\n' '  make test-functional                   Run PHPUnit functional group / Запустить functional-группу PHPUnit'
	@printf '%s\n' '  make test-reset-password-postgresql CONFIRM=testdb  Run reset concurrency tests in disposable PostgreSQL / Проверить конкурентный сброс в одноразовой PostgreSQL БД'
	@printf '%s\n' '  make test-oauth-postgresql CONFIRM=testdb  Run OAuth identity races in disposable PostgreSQL / Проверить конкурентные изменения OAuth в одноразовой PostgreSQL БД'
	@printf '%s\n' '  make test-functional-panther           Run PHPUnit functional-panther group / Запустить browser-группу PHPUnit через Panther'
	@printf '%s\n' '  make test-all-core CONFIRM=testdb       Build assets and run the core test baseline / Собрать assets и запустить основной набор тестов'
	@printf '%s\n' '  make coverage CONFIRM=testdb            Run core PHP/PHPUnit coverage after SQLite reset, excluding browser and PostgreSQL suites / Получить покрытие основных тестов после пересоздания SQLite, без браузерных и PostgreSQL-тестов'
	@printf '%s\n' '  make coverage-html CONFIRM=testdb       Run the same core coverage with HTML/Clover reports / Запустить то же core-покрытие с HTML/Clover-отчётами'
	@printf '%s\n' '  make test-all CONFIRM=testdb            Run core, Panther and both PostgreSQL suites / Запустить основные тесты, Panther и оба набора PostgreSQL-тестов'
	@printf '%s\n' ''
	@printf '%s\n' 'Destructive maintenance / Деструктивные операции:'
	@printf '%s\n' '  make cache-prod-clear                   Remove generated prod cache in php as app / Удалить сгенерированный prod-кеш в PHP-контейнере от app'
	@printf '%s\n' '  make test-db-reset CONFIRM=testdb       Reset APP_ENV=test SQLite DB var/db_for_test.db / Пересоздать тестовую SQLite БД var/db_for_test.db'
	@printf '%s\n' '  make postgres-reinit CONFIRM=postgres18 Stop stack and remove local PostgreSQL volume / Остановить stack и удалить локальный volume PostgreSQL'
	@printf '%s\n' ''

init:
	@if [ ! -f .env.docker ]; then \
		cp .env.docker.example .env.docker; \
		sed -i "s/^HOST_UID=.*/HOST_UID=$$(id -u)/" .env.docker; \
		sed -i "s/^HOST_GID=.*/HOST_GID=$$(id -g)/" .env.docker; \
		printf '%s\n' 'Created .env.docker from .env.docker.example'; \
	else \
		printf '%s\n' '.env.docker already exists; leaving it unchanged'; \
	fi
	@mkdir -p var/cache var/log public/uploads

check-env:
	@if [ ! -f .env.docker ]; then \
		printf '%s\n' 'Missing .env.docker. Run: make init'; \
		exit 1; \
	fi

config: check-env
	$(COMPOSE) --profile tools config

build: check-env
	$(COMPOSE) build

up: check-env
	$(COMPOSE) up -d php nginx postgres

down: check-env
	$(COMPOSE) --profile tools down --remove-orphans

restart: check-env
	$(COMPOSE) restart $(SERVICE)

ps: check-env
	$(COMPOSE) ps

log: check-env
	$(COMPOSE) logs -f $(SERVICE)

log-all: check-env
	$(COMPOSE) logs -f

in: check-env
	$(if $(filter php,$(SERVICE)),$(COMPOSE) exec --user app php bash,$(if $(filter nginx,$(SERVICE)),$(COMPOSE) exec --user nginx nginx sh,$(if $(filter postgres,$(SERVICE)),$(COMPOSE) exec --user postgres postgres sh,$(COMPOSE) run --rm --no-deps node sh)))

cache-prod-clear: check-env
	$(COMPOSE) exec --user app php rm -rf /var/www/html/var/cache/prod

console: check-env
	$(COMPOSE) exec --user app php php bin/console $(CMD)

composer: check-env
	$(COMPOSE) exec --user app php composer $(CMD)

composer-install: check-env
	$(MAKE) composer CMD='install'

npm: check-env
	$(COMPOSE) run --rm --no-deps node $(CMD)

npm-install: check-env
	$(MAKE) npm CMD='npm ci'

assets-build: check-env
	$(MAKE) npm CMD='npm run build'

watch: check-env
	$(MAKE) npm CMD='npm run watch'

migrate: check-env
	$(COMPOSE) exec --user app php php bin/console doctrine:migrations:migrate --no-interaction

demo-init: check-env
	$(MAKE) console CMD='app:demo:init'

postgres-reinit: check-env
	@if [ "$(CONFIRM)" != "postgres18" ]; then \
		printf '%s\n' 'Refusing to reinitialize PostgreSQL volume. Re-run with: make postgres-reinit CONFIRM=postgres18'; \
		exit 1; \
	fi
	$(MAKE) down
	@if docker volume inspect symfony-shop_postgres-data >/dev/null 2>&1; then \
		docker volume rm symfony-shop_postgres-data; \
	else \
		printf '%s\n' 'PostgreSQL volume symfony-shop_postgres-data does not exist; nothing to remove'; \
	fi

check:
	$(PHP_APP) php composer check
	$(MAKE) eslint-check

composer-validate:
	$(PHP_APP) php composer validate --strict --no-check-publish

composer-audit:
	$(PHP_APP) php composer audit --locked

php-lint:
	$(PHP_APP) php composer lint

eslint-fix:
	$(MAKE) npm CMD='./node_modules/.bin/eslint assets/ --ext .js,.vue --fix'

eslint-check:
	$(MAKE) npm CMD='./node_modules/.bin/eslint assets/ --ext .js,.vue'

php-cs-fixer:
	$(PHP_APP) php composer cs:fix

php-cs-fixer-check:
	$(PHP_APP) php composer cs:check

phpstan-check:
	$(PHP_APP) php composer analyse

test-all-core:
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing to run the full test baseline. Re-run with: make test-all-core CONFIRM=testdb'; \
		exit 1; \
	fi
	$(MAKE) assets-build
	$(MAKE) test-unit
	$(MAKE) test-db-reset CONFIRM="$(CONFIRM)"
	$(MAKE) test-integration
	$(MAKE) test-functional

coverage:
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing to run core PHP/PHPUnit coverage. Re-run with: make coverage CONFIRM=testdb'; \
		exit 1; \
	fi
	$(MAKE) assets-build
	$(MAKE) test-db-reset CONFIRM="$(CONFIRM)"
	$(SQLITE_TEST_PHP) -e XDEBUG_MODE=coverage php php vendor/bin/phpunit $(CORE_COVERAGE) --coverage-text

coverage-html:
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing to run core PHP/PHPUnit coverage with reports. Re-run with: make coverage-html CONFIRM=testdb'; \
		exit 1; \
	fi
	$(MAKE) assets-build
	$(MAKE) test-db-reset CONFIRM="$(CONFIRM)"
	$(PHP_APP) php sh -ec 'test ! -L var/coverage && rm -rf var/coverage/html var/coverage/clover.xml && mkdir -p var/coverage'
	$(SQLITE_TEST_PHP) -e XDEBUG_MODE=coverage php php vendor/bin/phpunit $(CORE_COVERAGE) --coverage-text --coverage-html var/coverage/html --coverage-clover var/coverage/clover.xml

test-all:
	$(MAKE) test-all-core CONFIRM="$(CONFIRM)"
	$(MAKE) test-functional-panther
	$(MAKE) test-reset-password-postgresql CONFIRM="$(CONFIRM)"
	$(MAKE) test-oauth-postgresql CONFIRM="$(CONFIRM)"

test-groups:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --list-groups

test-list:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --list-tests

test-unit:
	$(PHP_APP) -e APP_ENV=test php composer test:unit

test-db-reset:
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing to reset APP_ENV=test SQLite DB var/db_for_test.db. Re-run with: make test-db-reset CONFIRM=testdb'; \
		exit 1; \
	fi
	$(SQLITE_TEST_PHP) php sh -ec 'php tests/TestUtils/Database/verify.php && rm -f var/db_for_test.db'
	$(SQLITE_TEST_PHP) php php bin/console doctrine:schema:update --force
	$(SQLITE_TEST_PHP) php php bin/console hautelook:fixtures:load --no-interaction

test-integration:
	$(SQLITE_TEST_PHP) php composer test:integration

test-functional:
	$(SQLITE_TEST_PHP) php composer test:functional

test-reset-password-postgresql: check-env
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing disposable reset-password DB tests. Re-run with: make test-reset-password-postgresql CONFIRM=testdb'; \
		exit 1; \
	fi
	@{ set -eu; \
	flock -n 9 || { printf '%s\n' 'Reset-password PostgreSQL tests are already running'; exit 1; }; \
	existing="$$( $(RESET_PASSWORD_COMPOSE) ps -a -q reset-password-postgres )"; \
	if [ -n "$$existing" ]; then \
		printf '%s\n' 'Refusing to reuse an existing reset-password test container'; exit 1; \
	fi; \
	trap '$(RESET_PASSWORD_COMPOSE) rm --stop --force reset-password-postgres' EXIT; \
	trap 'exit 130' INT; trap 'exit 143' TERM; \
	$(RESET_PASSWORD_COMPOSE) up -d --no-deps --wait --wait-timeout 60 reset-password-postgres; \
	$(RESET_PASSWORD_COMPOSE) exec -T --user app -e APP_ENV=test -e APP_DEBUG=1 -e TEST_TOKEN= -e SYMFONY_SHOP_TEST_DB_GUARD=reset-password-postgresql \
		-e TEST_DATABASE_URL='postgresql://reset_password_test:disposable_reset_password_test@reset-password-postgres:5432/reset_password_concurrency?serverVersion=18.4' \
		php php /var/www/html/vendor/bin/phpunit -c phpunit.reset-password-postgresql.xml.dist --do-not-record-test-run-history --no-coverage; \
	} 9>/tmp/symfony-shop-reset-password-postgresql.lock

test-oauth-postgresql: check-env
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing disposable OAuth DB tests. Re-run with: make test-oauth-postgresql CONFIRM=testdb'; \
		exit 1; \
	fi
	@{ set -eu; \
	flock -n 9 || { printf '%s\n' 'OAuth PostgreSQL tests are already running'; exit 1; }; \
	existing="$$( $(OAUTH_COMPOSE) ps -a -q oauth-postgres )"; \
	if [ -n "$$existing" ]; then \
		printf '%s\n' 'Refusing to reuse an existing OAuth test container'; exit 1; \
	fi; \
	trap '$(OAUTH_COMPOSE) rm --stop --force oauth-postgres' EXIT; \
	trap 'exit 130' INT; trap 'exit 143' TERM; \
	$(OAUTH_COMPOSE) up -d --no-deps --wait --wait-timeout 60 oauth-postgres; \
	$(OAUTH_COMPOSE) exec -T --user app -e APP_ENV=test -e APP_DEBUG=1 -e TEST_TOKEN= -e SYMFONY_SHOP_TEST_DB_GUARD=oauth-postgresql \
		-e TEST_DATABASE_URL='postgresql://oauth_identity_test:disposable_oauth_identity_test@oauth-postgres:5432/oauth_identity_concurrency?serverVersion=18.4' \
		php php /var/www/html/vendor/bin/phpunit -c phpunit.oauth-postgresql.xml.dist --do-not-record-test-run-history --no-coverage; \
	} 9>/tmp/symfony-shop-oauth-postgresql.lock

test-functional-panther:
	$(SQLITE_TEST_PHP) php php tests/TestUtils/Database/verify.php
	$(SQLITE_TEST_PHP) php php vendor/bin/phpunit --group functional-panther --do-not-record-test-run-history --no-coverage
