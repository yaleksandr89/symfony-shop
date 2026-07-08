NODE_MODULES = ./node_modules
VENDOR = ./vendor

COMPOSE = docker compose -p symfony-shop --env-file .env.docker
CMD ?=

.PHONY: help init check-env config build up down restart ps logs shell console composer composer-install npm npm-install assets-build watch migrate demo-init postgres-reinit del-log del-cache deploy check refactoring eslint eslint-check php-cs-fixer php-cs-fixer-check phpstan phpstan-check run-test test-groups test-list test-unit

help:
	@printf '%s\n' 'Docker local development:'
	@printf '%s\n' '  make init              Create .env.docker and writable local directories'
	@printf '%s\n' '  make config            Validate and print Docker Compose config'
	@printf '%s\n' '  make build             Build PHP development image'
	@printf '%s\n' '  make up                Start php, nginx and postgres'
	@printf '%s\n' '  make down              Stop containers'
	@printf '%s\n' '  make logs [SERVICE=x]  Follow logs for all services or one service'
	@printf '%s\n' '  make shell [SERVICE=x] Open a shell in a running service'
	@printf '%s\n' '  make console CMD=about Run Symfony console in php as app'
	@printf '%s\n' '  make composer CMD=...  Run Composer in php as app'
	@printf '%s\n' '  make npm CMD=...       Run npm command in one-off Node container'
	@printf '%s\n' '  make test-groups       List available PHPUnit groups'
	@printf '%s\n' '  make test-list         List available PHPUnit tests'
	@printf '%s\n' '  make test-unit         Run PHPUnit unit group without result cache'
	@printf '%s\n' '  make phpstan-check     Run PHPStan read-only analysis'
	@printf '%s\n' '  make php-cs-fixer-check Check PHP-CS-Fixer rules without writing files'
	@printf '%s\n' '  make eslint-check      Run ESLint without --fix'
	@printf '%s\n' '  make demo-init         Initialize reproducible dev/test demo data'
	@printf '%s\n' '  make postgres-reinit CONFIRM=postgres18  destructive: stop stack and remove local PostgreSQL volume'
	@printf '%s\n' ''
	@printf '%s\n' 'First run:'
	@printf '%s\n' '  make init'
	@printf '%s\n' '  make build'
	@printf '%s\n' '  make up'
	@printf '%s\n' '  make composer-install'
	@printf '%s\n' '  make npm-install'
	@printf '%s\n' '  make assets-build'
	@printf '%s\n' '  make migrate'
	@printf '%s\n' '  make demo-init'

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
	$(COMPOSE) down

restart: check-env
	$(COMPOSE) restart $(SERVICE)

ps: check-env
	$(COMPOSE) ps

logs: check-env
	$(COMPOSE) logs -f $(SERVICE)

shell: check-env
	$(COMPOSE) exec $(if $(SERVICE),$(SERVICE),php) sh

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
	$(COMPOSE) down
	@if docker volume inspect symfony-shop_postgres-data >/dev/null 2>&1; then \
		docker volume rm symfony-shop_postgres-data; \
	else \
		printf '%s\n' 'PostgreSQL volume symfony-shop_postgres-data does not exist; nothing to remove'; \
	fi

##
## UTILS
## ----------
del-log:
	rm -rf ./var/log

del-cache:
	rm -rf ./var/cache

deploy:
	php deployer7 deploy

##
## REFACTORING
## -----------

check:
	make refactoring --keep-going

refactoring: eslint php-cs-fixer

eslint:
	${NODE_MODULES}/.bin/eslint assets/js/ --ext .js,.vue --fix

eslint-check:
	$(MAKE) npm CMD='./node_modules/.bin/eslint assets/js/ --ext .js,.vue'

php-cs-fixer:
	${VENDOR}/bin/php-cs-fixer fix src/  --verbose

php-cs-fixer-check:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/php-cs-fixer fix src/ --dry-run --diff --using-cache=no --verbose

phpstan:
	${VENDOR}/bin/phpstan analyse src --level 4

phpstan-check:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/phpstan analyse src --level 4

##
## TESTING
## -----------

run-test:
	sh ./bin/run-tests.sh

test-groups:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/phpunit --list-groups

test-list:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/phpunit --list-tests

test-unit:
	$(COMPOSE) exec --user app php php /var/www/html/vendor/bin/phpunit --group unit --do-not-cache-result --no-coverage
