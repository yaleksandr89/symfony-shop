# Desarrollo

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/development.md) | [English](../ru/development.md) | **Español** | [中文](../ru/development.md) | [Français](../ru/development.md) | [Deutsch](../ru/development.md) |


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
| `make check` | ESLint + comprobación PHP-CS-Fixer + PHPStan | no |
| `make eslint-check` | Comprueba JS/Vue con ESLint | no |
| `make php-cs-fixer-check` | Comprueba formato en `src/` y `tools/demo/` | no |
| `make phpstan-check` | Ejecuta PHPStan en `src` y `tools/demo` | no |
| `make eslint-fix` | Corrige problemas ESLint | sí |
| `make php-cs-fixer` | Corrige formato PHP | sí |

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
| `make test-all CONFIRM=testdb` | Suite completa con Panther | Recrea la base SQLite de pruebas |

`CONFIRM=testdb` es intencionado: los escenarios agregados eliminan y recrean `var/db_for_test.db`.

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

1. descarga objetos Git LFS y comprueba el archivo Chrome;
2. crea `.env.docker`;
3. valida Compose, construye e inicia el entorno Docker;
4. instala dependencias y compila recursos frontend;
5. ejecuta ESLint;
6. ejecuta pruebas unitarias, de integración, funcionales y Panther;
7. ejecuta PHPStan;
8. detiene los contenedores.

La CI no ejecuta la comprobación PHP-CS-Fixer ni genera informes de cobertura; esas comprobaciones se ejecutan localmente cuando hacen falta.

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
| `check` | ESLint + PHP-CS-Fixer check + PHPStan |
| `eslint-fix` | corregir ESLint |
| `eslint-check` | comprobar ESLint |
| `php-cs-fixer` | corregir formato PHP |
| `php-cs-fixer-check` | comprobar formato PHP |
| `phpstan-check` | análisis estático PHPStan |
| `test-all-core CONFIRM=testdb` | suite principal sin Panther |
| `coverage CONFIRM=testdb` | cobertura en terminal |
| `coverage-html CONFIRM=testdb` | cobertura + HTML/Clover |
| `test-all CONFIRM=testdb` | suite completa con Panther |
| `test-groups` | grupos PHPUnit |
| `test-list` | lista de pruebas PHPUnit |
| `test-unit` | pruebas unitarias |
| `test-db-reset CONFIRM=testdb` | recrear la base SQLite de pruebas |
| `test-integration` | pruebas de integración |
| `test-functional` | pruebas funcionales |
| `test-functional-panther` | pruebas de navegador Panther |

Para el primer arranque y las formas de obtener Chrome for Testing, consulta la [guía de puesta en marcha](getting-started.md). Las reglas de `.env*` y secretos locales están en la [guía de configuración](configuration.md).
