# Configuración

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/configuration.md) | [English](../ru/configuration.md) | **Español** | [中文](../ru/configuration.md) | [Français](../ru/configuration.md) | [Deutsch](../ru/configuration.md) |


El proyecto mantiene por separado los ajustes comunes de Symfony, los parámetros de Docker, los secretos locales y las redefiniciones para pruebas. Un detalle importante: los valores que Docker Compose pasa al contenedor PHP tienen más prioridad que los valores cargados desde archivos de Symfony Dotenv.

## Archivos de entorno

| Archivo | Propósito | Git |
|---|---|---|
| `.env` | Ajustes Symfony seguros y valores locales por defecto | versionado |
| `.env.docker` | Parámetros de Docker Compose y PostgreSQL local | ignorado |
| `.env.local` | Secretos y ajustes específicos del desarrollador | ignorado |
| `.env.test` | Ajustes de pruebas automáticas | versionado |

## Prioridad de variables

De mayor a menor prioridad:

1. variables de entorno del proceso, incluidos los valores de `.env.docker` pasados por Docker Compose;
2. `.env.<entorno>.local`;
3. `.env.<entorno>`;
4. `.env.local`;
5. `.env`.

El nombre `.env.docker` no da por sí mismo una prioridad especial. La prioridad aparece porque Docker Compose pasa esos valores al contenedor PHP como variables reales de entorno del proceso.

Ejemplo práctico:

```text
.env.docker
PANTHER_WEB_SERVER_PORT=9080

.env.local
PANTHER_WEB_SERVER_PORT=9999

→ dentro del contenedor PHP se usa 9080
```

Las credenciales OAuth de `.env.local`, en cambio, se utilizan si Docker no ha pasado variables con los mismos nombres.

Tras modificar `.env.docker`, recrea los contenedores con `make down` y `make up`. Los cambios en `.env` o `.env.local` normalmente no lo requieren.

## `.env`

Contiene parámetros comunes de la aplicación: `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`, `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, dirección de la aplicación, CORS e interruptores de OAuth.

Los valores de `.env` son valores locales del proyecto y no están pensados para producción.

## `.env.docker`

`make init` crea este archivo desde `.env.docker.example` e inserta el UID/GID del usuario del host.

Parámetros principales:

| Variable | Propósito | Valor por defecto |
|---|---|---|
| `HOST_UID`, `HOST_GID` | Propietario de los archivos creados por contenedores | se rellenan con `make init` |
| `APP_PORT` | Puerto HTTP de Nginx en el host | `8080` |
| `POSTGRES_DB` | Base PostgreSQL local | `s_shop` |
| `POSTGRES_USER` | Usuario PostgreSQL local | `s_shop` |
| `POSTGRES_PASSWORD` | Contraseña PostgreSQL local | valor de demostración |
| `PANTHER_WEB_SERVER_HOST` | Host del servidor integrado de Panther | `php` |
| `PANTHER_WEB_SERVER_PORT` | Puerto del servidor integrado de Panther | `9080` |

Compose usa `.env.docker` como `env_file` del contenedor PHP, por lo que estos valores se convierten en variables de entorno del proceso.

## `.env.local`

Utiliza `.env.local` para credenciales OAuth, un `MAILER_DSN` real, un `ADMIN_EMAIL` local y otros secretos específicos de la máquina.

No añadas este archivo a Git ni publiques su contenido. En el entorno `test`, Symfony no carga `.env.local`.

## `.env.test`

El entorno de pruebas usa una base SQLite independiente en `var/db_for_test.db`, ajustes de Panther, transportes neutros de Mailer/Messenger y proveedores OAuth desactivados.

## Correo y Messenger

Por defecto:

```dotenv
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default
```

`MAILER_DSN=null://null` significa que el entorno local no envía correo mediante un servicio SMTP externo. Los mensajes creados de forma síncrona durante una petición HTTP pueden verse en el panel Mailer de Symfony Profiler.

Para un transporte SMTP real, define tu propio `MAILER_DSN` en `.env.local`, por ejemplo:

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger ya enruta el registro y la recuperación de contraseña al transporte `async`, pero Docker Compose no inicia un worker permanente. El mensaje queda en la cola Doctrine hasta que el worker se inicia manualmente:

| Comando | Qué hace |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Inicia el worker del transporte `async` dentro del contenedor PHP |

Esto es especialmente importante al probar registro y recuperación de contraseña: sin el worker, los mensajes asíncronos correspondientes no se procesarán. Más adelante se prevé añadir un servicio local de correo con interfaz web y un worker permanente de Messenger.

## PostgreSQL

Docker Compose usa PostgreSQL 18.4. El contenedor PHP se conecta a la base por el nombre de servicio `postgres`; `localhost` dentro del contenedor PHP no apunta a PostgreSQL.

PostgreSQL se publica al host solo en `127.0.0.1:5433`.

`DATABASE_URL` se compone a partir de `POSTGRES_*` y lo utiliza Doctrine. El recreado completo del volumen PostgreSQL local se hace con el comando destructivo `make postgres-reinit CONFIRM=postgres18`; consulta la [guía de desarrollo](development.md).

## OAuth

Todos los proveedores OAuth están desactivados por defecto. Activar un proveedor y proporcionar credenciales son ajustes separados: se necesitan tanto `*_ENABLED=1` como Client ID y Client Secret válidos.

| Proveedor | Interruptor |
|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` |
| Yandex | `OAUTH_YANDEX_ENABLED` |
| VKontakte | `OAUTH_VK_ENABLED` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` |
| Mail.ru | `OAUTH_MAILRU_ENABLED`: debe permanecer en `0` |

Ejemplo local para Google:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

Los demás nombres de credenciales, rutas y reglas de funcionamiento están en la [guía de OAuth](oauth.md). No añadas a documentación ni Git claves reales, tokens de acceso, códigos de autorización ni ID externos.

## Panther

La imagen PHP contiene Chrome for Testing y Chromedriver. No se necesita navegador en el host ni Java para las pruebas.

Docker utiliza `PANTHER_WEB_SERVER_HOST=php` y `PANTHER_WEB_SERVER_PORT=9080`; `.env.test` añade la configuración específica de pruebas y el directorio para capturas de errores.

Las formas de obtener el archivo Chrome están en la [guía de puesta en marcha](getting-started.md), y las pruebas de navegador en la [guía de desarrollo](development.md).
