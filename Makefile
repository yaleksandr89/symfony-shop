COMPOSE = docker compose -p symfony-shop --env-file .env.docker
CMD ?=

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

.PHONY: help init check-env config build up down restart ps log log-all in cache-prod-clear console composer composer-install npm npm-install assets-build watch migrate demo-init postgres-reinit check eslint-fix eslint-check php-cs-fixer php-cs-fixer-check phpstan-check test-all-core test-coverage-core test-all test-groups test-list test-unit test-db-reset test-integration test-functional test-functional-panther

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
	@printf '%s\n' '  make check                             Run all read-only quality checks / Запустить все проверки качества без изменения файлов'
	@printf '%s\n' '  make eslint-check                      Run ESLint without writing files / Проверить ESLint без изменения файлов'
	@printf '%s\n' '  make eslint-fix                        Fix ESLint issues through Node container / Исправить ESLint через Node-контейнер'
	@printf '%s\n' '  make php-cs-fixer                      Fix src/ + tools/demo/ formatting in php as app / Исправить форматирование src/ + tools/demo/ в PHP-контейнере от app'
	@printf '%s\n' '  make php-cs-fixer-check                Check PHP-CS-Fixer rules without writing files / Проверить PHP-CS-Fixer без изменения файлов'
	@printf '%s\n' '  make phpstan-check                     Run PHPStan read-only analysis / Запустить PHPStan без изменения файлов'
	@printf '%s\n' ''
	@printf '%s\n' 'Tests / Тесты:'
	@printf '%s\n' '  make test-groups                       List available PHPUnit groups / Показать доступные группы PHPUnit'
	@printf '%s\n' '  make test-list                         List available PHPUnit tests / Показать доступные тесты PHPUnit'
	@printf '%s\n' '  make test-unit                         Run PHPUnit unit group / Запустить unit-группу PHPUnit'
	@printf '%s\n' '  make test-integration                  Run PHPUnit integration group / Запустить integration-группу PHPUnit'
	@printf '%s\n' '  make test-functional                   Run PHPUnit functional group / Запустить functional-группу PHPUnit'
	@printf '%s\n' '  make test-functional-panther           Run PHPUnit functional-panther group / Запустить browser-группу PHPUnit через Panther'
	@printf '%s\n' '  make test-all-core CONFIRM=testdb       Build assets and run the core test baseline / Собрать assets и запустить основной набор тестов'
	@printf '%s\n' '  make test-coverage-core CONFIRM=testdb  Run core PHP/PHPUnit coverage after test DB reset, excluding Panther / Запустить core-покрытие PHP/PHPUnit после пересоздания тестовой БД, без Panther'
	@printf '%s\n' '  make test-all CONFIRM=testdb            Run the full baseline, including Panther / Запустить полный набор тестов, включая Panther'
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

check: eslint-check php-cs-fixer-check phpstan-check

eslint-fix:
	$(MAKE) npm CMD='./node_modules/.bin/eslint assets/js/ --ext .js,.vue --fix'

eslint-check:
	$(MAKE) npm CMD='./node_modules/.bin/eslint assets/js/ --ext .js,.vue'

php-cs-fixer:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/php-cs-fixer fix --config=/var/www/html/.php-cs-fixer.dist.php src/ tools/demo/ --verbose

php-cs-fixer-check:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/php-cs-fixer fix --config=/var/www/html/.php-cs-fixer.dist.php src/ tools/demo/ --dry-run --diff --using-cache=no --verbose

phpstan-check:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/phpstan analyse src tools/demo --level 4

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

test-coverage-core:
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing to run core PHP/PHPUnit coverage. Re-run with: make test-coverage-core CONFIRM=testdb'; \
		exit 1; \
	fi
	$(COMPOSE) exec --user app php rm -rf var/coverage
	$(COMPOSE) exec --user app php mkdir -p var/coverage
	$(MAKE) assets-build
	$(MAKE) test-db-reset CONFIRM="$(CONFIRM)"
	$(COMPOSE) exec --user app -e APP_ENV=test -e XDEBUG_MODE=coverage php php /var/www/html/vendor/bin/phpunit --exclude-group functional-panther --do-not-record-test-run-history --coverage-text --coverage-html var/coverage/html --coverage-clover var/coverage/clover.xml

test-all:
	$(MAKE) test-all-core CONFIRM="$(CONFIRM)"
	$(MAKE) test-functional-panther

test-groups:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --list-groups

test-list:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --list-tests

test-unit:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --group unit --do-not-record-test-run-history --no-coverage

test-db-reset:
	@if [ "$(CONFIRM)" != "testdb" ]; then \
		printf '%s\n' 'Refusing to reset APP_ENV=test SQLite DB var/db_for_test.db. Re-run with: make test-db-reset CONFIRM=testdb'; \
		exit 1; \
	fi
	$(COMPOSE) exec --user app -e APP_ENV=test php rm -f var/db_for_test.db
	$(COMPOSE) exec --user app -e APP_ENV=test php php bin/console doctrine:schema:update --force
	$(COMPOSE) exec --user app -e APP_ENV=test php php bin/console hautelook:fixtures:load --no-interaction

test-integration:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --group integration --do-not-record-test-run-history --no-coverage

test-functional:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --group functional --do-not-record-test-run-history --no-coverage

test-functional-panther:
	$(COMPOSE) exec --user app -e APP_ENV=test php php /var/www/html/vendor/bin/phpunit --group functional-panther --do-not-record-test-run-history --no-coverage
