# Development

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/development.md) | **English** | [Español](../es/development.md) | [中文](../zh/development.md) | [Français](../fr/development.md) | [Deutsch](../de/development.md) |


The Makefile is the main interface for local development. PHP, Composer, and Symfony Console run inside the PHP container as user `app`; npm runs in an ephemeral Node container.

The current target list is always available through `make help`.

## Initial setup

| Command | Purpose |
|---|---|
| `make help` | Show built-in Makefile help |
| `make init` | Create `.env.docker` and writable local directories |
| `make check-env` | Verify that `.env.docker` exists |

## Docker Compose

| Command | Purpose | Note |
|---|---|---|
| `make config` | Validate the resulting Compose configuration | Starts nothing |
| `make build` | Build the PHP image | |
| `make up` | Start `php`, `nginx`, and `postgres` | |
| `make ps` | Show container state | |
| `make restart <service>` | Restart a service | `php`, `nginx`, `postgres` |
| `make log <service>` | Show a service log | `php`, `nginx`, `postgres` |
| `make log-all` | Show all logs | |
| `make in <service>` | Open a service shell | `php`, `nginx`, `postgres`, `node` |
| `make down` | Stop the environment | PostgreSQL volume is preserved |

The PHP-container shell opens as user `app`, so normal commands should not create `root`-owned files in the working tree.

## Symfony, Composer, and npm

| Command | Purpose | Note |
|---|---|---|
| `make console CMD=about` | Run Symfony Console | Any command is passed through `CMD` |
| `make composer CMD='validate --strict'` | Run Composer | Inside the PHP container |
| `make composer-install` | Run `composer install` | Uses `composer.lock` |
| `make npm CMD='npm --version'` | Run an arbitrary npm command | In an ephemeral Node container |
| `make npm-install` | Run `npm ci` | Uses `package-lock.json` |
| `make assets-build` | Build optimized frontend assets | Webpack Encore |
| `make watch` | Watch frontend assets for changes | Long-running command |

PHP, Composer, Node.js, and Symfony Console are not used directly on the host.

For manual Messenger queue processing:

| Command | Purpose |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Start the `async` transport queue worker |

Docker Compose currently has no permanent Messenger worker. See the [configuration guide](configuration.md) for mail and queue details.

## Quality checks

| Command | Purpose | Modifies files |
|---|---|---|
| `make check` | Strict Composer validation, locked dependency audit, PHP lint, PHP CS Fixer, PHPStan and ESLint | no |
| `make composer-validate` | Validate Composer manifest and lockfile | no |
| `make composer-audit` | Audit locked dependencies | no |
| `make php-lint` | Check PHP syntax | no |
| `make eslint-check` | Check JS/Vue with ESLint | no |
| `make php-cs-fixer-check` | Check first-party PHP formatting | no |
| `make phpstan-check` | Analyse first-party PHP at level 8 | no |
| `make eslint-fix` | Fix ESLint issues | yes |
| `make php-cs-fixer` | Fix PHP formatting | yes |

PHP lint, PHP CS Fixer and PHPStan cover `src`, `tests`, `migrations`, `tools/demo`, `fixtures/demo`, `config/bundles.php`, `config/preload.php`, `public/index.php`, `bin/console` and `.php-cs-fixer.dist.php`. Generated files and dependencies are excluded. Checks may create local caches.

`make check` does not run PHPUnit. Tests use separate targets.

## Tests

| Command | What it checks | Note |
|---|---|---|
| `make test-groups` | Show PHPUnit groups | |
| `make test-list` | Show the test list | |
| `make test-unit` | Isolated application logic | `unit` group |
| `make test-integration` | Doctrine and service interaction | `integration` group |
| `make test-functional` | HTTP, controllers, API, and access rules | `functional` group |
| `make test-functional-panther` | Browser scenarios | `functional-panther` group |
| `make test-all-core CONFIRM=testdb` | Frontend assets + unit + integration + functional | Recreates the test SQLite database |
| `make test-all CONFIRM=testdb` | Full suite including Panther and both PostgreSQL suites | Recreates the test SQLite database |
| `make test-reset-password-postgresql CONFIRM=testdb` | Concurrent password reset operations | Disposable PostgreSQL in a separate service |
| `make test-oauth-postgresql CONFIRM=testdb` | Concurrent OAuth identity mutations | Disposable PostgreSQL in a separate service |

`CONFIRM=testdb` is intentional: aggregate scenarios delete and recreate `var/db_for_test.db`.

Ordinary integration, functional and Panther tests use only the local `var/db_for_test.db`. Before running these groups separately, prepare the database with `make test-db-reset CONFIRM=testdb`. Use the guarded Make targets: arbitrary test database connections are not allowed. Run PostgreSQL concurrency tests only through the two dedicated targets above, in disposable services. Never use the working database or an external connection for tests.

Panther uses Chrome for Testing and Chromedriver from the PHP image. Selenium Server, GeckoDriver, Java, and a locally installed browser are not needed for the current tests.

## Code coverage

| Command | Result | Note |
|---|---|---|
| `make coverage CONFIRM=testdb` | Terminal statistics | `src` + `tools/demo`, without Panther |
| `make coverage-html CONFIRM=testdb` | Terminal + HTML + Clover | `var/coverage/html`, `var/coverage/clover.xml` |

Both commands use the same PHP/PHPUnit scope and recreate the test database beforehand. Panther is not included in the coverage report.

## Database and demo data

| Command | Purpose | Risk |
|---|---|---|
| `make migrate` | Apply Doctrine migrations | normal operation |
| `make demo-init` | Initialize demo catalog, accounts, and orders | replaces existing orders |
| `make test-db-reset CONFIRM=testdb` | Recreate `var/db_for_test.db` | deletes the test SQLite database |
| `make postgres-reinit CONFIRM=postgres18` | Recreate the local PostgreSQL volume | deletes local PostgreSQL data |
| `make cache-prod-clear` | Delete generated prod cache | only `var/cache/prod` inside the PHP container |

`make demo-init` is intended for a reproducible `dev`/`test` environment. Do not run it if the local database contains orders you need to keep.

## CI

Workflow [`CI`](../../../.github/workflows/basic.yml) runs for pushes and pull requests targeting `master`.

It:

1. downloads the pinned Chrome for Testing archive from the official source and verifies its SHA-256 and ZIP integrity;
2. creates `.env.docker`;
3. validates Compose, then builds and starts the Docker environment;
4. installs dependencies and builds frontend assets;
5. runs `make check` (Composer validate/audit, PHP lint, PHP CS Fixer, PHPStan, ESLint);
6. runs unit, integration, functional and Panther tests with a separate SQLite database;
7. runs both PostgreSQL concurrency suites (password reset and OAuth);
8. stops containers.

Coverage reports remain local diagnostics. The workflow can also be started manually (`workflow_dispatch`).

## Logs and diagnostics

| Command | What it shows |
|---|---|
| `make ps` | Container state |
| `make log php` | PHP log |
| `make log nginx` | Nginx log |
| `make log postgres` | PostgreSQL log |
| `make log-all` | All project logs |
| `make console CMD=about` | Symfony application state |

## All Make commands

| Target | Purpose |
|---|---|
| `help` | built-in help |
| `init` | create `.env.docker` and local directories |
| `check-env` | validate `.env.docker` |
| `config` | validate Docker Compose |
| `build` | build the PHP image |
| `up` | start primary services |
| `down` | stop the environment |
| `restart <service>` | restart a service |
| `ps` | container state |
| `log <service>` | selected service log |
| `log-all` | logs for all services |
| `in <service>` | selected service shell |
| `cache-prod-clear` | delete prod cache |
| `console CMD='...'` | Symfony Console |
| `composer CMD='...'` | Composer inside the PHP container |
| `composer-install` | install Composer dependencies |
| `npm CMD='...'` | npm in an ephemeral Node container |
| `npm-install` | install npm dependencies |
| `assets-build` | optimized frontend asset build |
| `watch` | watch frontend assets |
| `migrate` | Doctrine migrations |
| `demo-init` | demo data |
| `postgres-reinit CONFIRM=postgres18` | fully recreate the local PostgreSQL volume |
| `check` | Strict Composer validation, locked dependency audit, PHP lint, PHP CS Fixer, PHPStan and ESLint |
| `composer-validate` | Validate Composer manifest and lockfile |
| `composer-audit` | Audit locked dependencies |
| `php-lint` | Check PHP syntax |
| `eslint-fix` | fix ESLint |
| `eslint-check` | check ESLint |
| `php-cs-fixer` | fix PHP formatting |
| `php-cs-fixer-check` | check PHP formatting |
| `phpstan-check` | PHPStan static analysis |
| `test-all-core CONFIRM=testdb` | Core suite without Panther or PostgreSQL concurrency |
| `coverage CONFIRM=testdb` | terminal coverage |
| `coverage-html CONFIRM=testdb` | coverage + HTML/Clover |
| `test-all CONFIRM=testdb` | Full suite including Panther and both PostgreSQL suites |
| `test-reset-password-postgresql CONFIRM=testdb` | Concurrent password reset operations |
| `test-oauth-postgresql CONFIRM=testdb` | Concurrent OAuth identity mutations |
| `test-groups` | PHPUnit groups |
| `test-list` | PHPUnit test list |
| `test-unit` | unit tests |
| `test-db-reset CONFIRM=testdb` | recreate the test SQLite database |
| `test-integration` | integration tests |
| `test-functional` | functional tests |
| `test-functional-panther` | Panther browser tests |

For first startup and ways to obtain Chrome for Testing, see the [getting-started guide](getting-started.md). `.env*` and local-secret rules are described in the [configuration guide](configuration.md).
