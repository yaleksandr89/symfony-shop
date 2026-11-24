# Desarrollo

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/development.md) | [English](../en/development.md) | **Español** | [中文](../zh/development.md) | [Français](../fr/development.md) | [Deutsch](../de/development.md) |


El Makefile es la interfaz principal para el desarrollo local. PHP, Composer y Symfony Console se ejecutan dentro del contenedor PHP como usuario `app`; npm se ejecuta en un contenedor Node efímero.

La lista actual de objetivos siempre se puede consultar con `make help`.

## Configuración inicial

| Comando | Qué hace |
|---|---|
| `make help` | Muestra la ayuda integrada del Makefile |
| `make init` | Crea `.env.docker` y directorios locales escribibles |
| `make check-env` | Comprueba que exista `.env.docker` |

## Docker Compose

| Comando | Qué hace | Nota |
|---|---|---|
| `make config` | Valida la configuración final de Compose | No inicia nada |
| `make build` | Construye la imagen PHP | |
| `make up` | Inicia `php`, `nginx` y `postgres` | |
| `make ps` | Muestra el estado de los contenedores | |
| `make restart <service>` | Reinicia un servicio | `php`, `nginx`, `postgres` |
| `make log <service>` | Muestra el log de un servicio | `php`, `nginx`, `postgres` |
| `make log-all` | Muestra todos los logs | |
| `make in <service>` | Abre una shell del servicio | `php`, `nginx`, `postgres`, `node` |
| `make down` | Detiene el entorno | Conserva el volumen PostgreSQL |

La shell del contenedor PHP se abre como `app`, por lo que los comandos normales no deberían crear archivos propiedad de `root` en la copia de trabajo.

## Symfony, Composer y npm

| Comando | Qué hace | Nota |
|---|---|---|
| `make console CMD=about` | Ejecuta Symfony Console | Cualquier comando se pasa mediante `CMD` |
| `make composer CMD='validate --strict'` | Ejecuta Composer | Dentro del contenedor PHP |
| `make composer-install` | Ejecuta `composer install` | Usa `composer.lock` |
| `make npm CMD='npm --version'` | Ejecuta un comando npm arbitrario | En un contenedor Node efímero |
| `make npm-install` | Ejecuta `npm ci` | Usa `package-lock.json` |
| `make assets-build` | Compila recursos optimizados del frontend | Webpack Encore |
| `make watch` | Observa cambios en recursos del frontend | Comando de larga duración |

PHP, Composer, Node.js y Symfony Console no se usan directamente en el host.

Para procesar manualmente la cola Messenger:

| Comando | Qué hace |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Inicia el worker de la cola `async` |

Docker Compose no tiene actualmente un worker permanente de Messenger. Consulta la [guía de configuración](configuration.md) para correo y colas.

## Comprobaciones de calidad

| Comando | Qué hace | Modifica archivos |
|---|---|---|
| `make check` | Validación estricta de Composer, auditoría del lock, PHP lint, PHP CS Fixer, PHPStan y ESLint | no |
| `make composer-validate` | Valida el manifiesto y el lock de Composer | no |
| `make composer-audit` | Audita las dependencias del lock | no |
| `make php-lint` | Comprueba la sintaxis PHP | no |
| `make eslint-check` | Comprueba JS/Vue con ESLint | no |
| `make php-cs-fixer-check` | Comprueba el formato del PHP propio | no |
| `make phpstan-check` | Analiza el PHP propio en el nivel 8 | no |
| `make eslint-fix` | Corrige problemas ESLint | sí |
| `make php-cs-fixer` | Corrige formato PHP | sí |

PHP lint, PHP CS Fixer y PHPStan abarcan `src`, `tests`, `migrations`, `tools/demo`, `fixtures/demo`, `config/bundles.php`, `config/preload.php`, `public/index.php`, `bin/console` y `.php-cs-fixer.dist.php`. Se excluyen los archivos generados y las dependencias. Las comprobaciones pueden crear cachés locales.

`make check` no ejecuta PHPUnit. Las pruebas tienen objetivos separados.

## Pruebas

| Comando | Qué comprueba | Nota |
|---|---|---|
| `make test-groups` | Muestra grupos PHPUnit | |
| `make test-list` | Muestra la lista de pruebas | |
| `make test-unit` | Lógica de aplicación aislada | grupo `unit` |
| `make test-integration` | Doctrine e interacción de servicios | grupo `integration` |
| `make test-functional` | HTTP, controladores, API y reglas de acceso | grupo `functional` |
| `make test-functional-panther` | Escenarios de navegador | grupo `functional-panther` |
| `make test-all-core CONFIRM=testdb` | Recursos frontend + unit + integration + functional | Recrea la base SQLite de pruebas |
| `make test-all CONFIRM=testdb` | Suite completa con Panther y ambas suites PostgreSQL | Recrea la base SQLite de pruebas |
| `make test-reset-password-postgresql CONFIRM=testdb` | Operaciones concurrentes de restablecimiento de contraseña | PostgreSQL desechable en un servicio independiente |
| `make test-oauth-postgresql CONFIRM=testdb` | Cambios concurrentes de identidades OAuth | PostgreSQL desechable en un servicio independiente |

`CONFIRM=testdb` es intencionado: los escenarios agregados eliminan y recrean `var/db_for_test.db`.

Las pruebas ordinarias de integración, funcionales y Panther solo utilizan `var/db_for_test.db` local. Antes de ejecutar estos grupos por separado, prepara la base con `make test-db-reset CONFIRM=testdb`. Usa los objetivos Make protegidos: no se permiten conexiones arbitrarias para las pruebas. Ejecuta las pruebas de concurrencia PostgreSQL únicamente mediante los dos objetivos específicos anteriores, en servicios desechables. Nunca uses la base de trabajo ni una conexión externa para las pruebas.

Panther usa Chrome for Testing y Chromedriver de la imagen PHP. Selenium Server, GeckoDriver, Java y un navegador local no son necesarios para las pruebas actuales.

## Cobertura de código

| Comando | Resultado | Nota |
|---|---|---|
| `make coverage CONFIRM=testdb` | Estadísticas en terminal | `src` + `tools/demo`, sin Panther |
| `make coverage-html CONFIRM=testdb` | Terminal + HTML + Clover | `var/coverage/html`, `var/coverage/clover.xml` |

Ambos comandos utilizan el mismo alcance PHP/PHPUnit y recrean la base de pruebas antes. Panther no forma parte del informe.

## Base de datos y datos demo

| Comando | Qué hace | Riesgo |
|---|---|---|
| `make migrate` | Aplica migraciones Doctrine | operación normal |
| `make demo-init` | Inicializa catálogo, cuentas y pedidos demo | sustituye pedidos existentes |
| `make test-db-reset CONFIRM=testdb` | Recrea `var/db_for_test.db` | elimina la base SQLite de pruebas |
| `make postgres-reinit CONFIRM=postgres18` | Recrea el volumen PostgreSQL local | elimina datos PostgreSQL locales |
| `make cache-prod-clear` | Elimina caché prod generado | solo `var/cache/prod` dentro del contenedor PHP |

`make demo-init` está pensado para un entorno `dev`/`test` reproducible. No lo ejecutes si la base local contiene pedidos que necesitas conservar.

## CI

El workflow [`CI`](../../../.github/workflows/basic.yml) se ejecuta para pushes y Pull Requests hacia `master`.

Realiza:

1. descarga el archivo fijado de Chrome for Testing desde la fuente oficial y comprueba su SHA-256 y la integridad ZIP;
2. crea `.env.docker`;
3. valida Compose, construye e inicia el entorno Docker;
4. instala dependencias y compila recursos frontend;
5. ejecuta `make check` (Composer validate/audit, PHP lint, PHP CS Fixer, PHPStan, ESLint);
6. ejecuta pruebas unitarias, de integración, funcionales y Panther con una base SQLite independiente;
7. ejecuta ambas suites de concurrencia PostgreSQL (restablecimiento de contraseña y OAuth);
8. detiene los contenedores.

Los informes de cobertura siguen siendo diagnósticos locales. El workflow también puede iniciarse manualmente (`workflow_dispatch`).

## Logs y diagnóstico

| Comando | Qué muestra |
|---|---|
| `make ps` | Estado de contenedores |
| `make log php` | Log de PHP |
| `make log nginx` | Log de Nginx |
| `make log postgres` | Log de PostgreSQL |
| `make log-all` | Todos los logs |
| `make console CMD=about` | Estado de la aplicación Symfony |

## Todos los comandos Make

| Objetivo | Propósito |
|---|---|
| `help` | ayuda integrada |
| `init` | crear `.env.docker` y directorios locales |
| `check-env` | comprobar `.env.docker` |
| `config` | validar Docker Compose |
| `build` | construir imagen PHP |
| `up` | iniciar servicios principales |
| `down` | detener el entorno |
| `restart <service>` | reiniciar un servicio |
| `ps` | estado de contenedores |
| `log <service>` | log del servicio elegido |
| `log-all` | logs de todos los servicios |
| `in <service>` | shell del servicio elegido |
| `cache-prod-clear` | eliminar caché prod |
| `console CMD='...'` | Symfony Console |
| `composer CMD='...'` | Composer en el contenedor PHP |
| `composer-install` | instalar dependencias Composer |
| `npm CMD='...'` | npm en contenedor Node efímero |
| `npm-install` | instalar dependencias npm |
| `assets-build` | build optimizado del frontend |
| `watch` | observar recursos frontend |
| `migrate` | migraciones Doctrine |
| `demo-init` | datos demo |
| `postgres-reinit CONFIRM=postgres18` | recrear por completo el volumen PostgreSQL local |
| `check` | Validación estricta de Composer, auditoría del lock, PHP lint, PHP CS Fixer, PHPStan y ESLint |
| `composer-validate` | Valida el manifiesto y el lock de Composer |
| `composer-audit` | Audita las dependencias del lock |
| `php-lint` | Comprueba la sintaxis PHP |
| `eslint-fix` | corregir ESLint |
| `eslint-check` | comprobar ESLint |
| `php-cs-fixer` | corregir formato PHP |
| `php-cs-fixer-check` | comprobar formato PHP |
| `phpstan-check` | análisis estático PHPStan |
| `test-all-core CONFIRM=testdb` | Suite principal sin Panther ni concurrencia PostgreSQL |
| `coverage CONFIRM=testdb` | cobertura en terminal |
| `coverage-html CONFIRM=testdb` | cobertura + HTML/Clover |
| `test-all CONFIRM=testdb` | Suite completa con Panther y ambas suites PostgreSQL |
| `test-reset-password-postgresql CONFIRM=testdb` | Operaciones concurrentes de restablecimiento de contraseña |
| `test-oauth-postgresql CONFIRM=testdb` | Cambios concurrentes de identidades OAuth |
| `test-groups` | grupos PHPUnit |
| `test-list` | lista de pruebas PHPUnit |
| `test-unit` | pruebas unitarias |
| `test-db-reset CONFIRM=testdb` | recrear la base SQLite de pruebas |
| `test-integration` | pruebas de integración |
| `test-functional` | pruebas funcionales |
| `test-functional-panther` | pruebas de navegador Panther |

Para el primer arranque y las formas de obtener Chrome for Testing, consulta la [guía de puesta en marcha](getting-started.md). Las reglas de `.env*` y secretos locales están en la [guía de configuración](configuration.md).
