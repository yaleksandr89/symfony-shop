# Puesta en marcha

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/getting-started.md) | [English](../ru/getting-started.md) | **Español** | [中文](../ru/getting-started.md) | [Français](../ru/getting-started.md) | [Deutsch](../ru/getting-started.md) |


El flujo compatible de desarrollo local utiliza Docker Compose. No es necesario instalar PHP, Composer, Node.js, PostgreSQL ni el entorno de navegador que usa Panther directamente en el host.

El proyecto no admite como flujo oficial una instalación con PHP, Composer, PostgreSQL y Node.js instalados directamente en el sistema operativo: el Makefile, la CI, los comandos de pruebas y el entorno de navegador están diseñados para Docker. Técnicamente se puede montar una instalación manual, pero no forma parte del contrato verificado del proyecto y por eso no se documenta aquí.

## Requisitos

Para trabajar normalmente se necesita:

- Git;
- Make;
- Docker con soporte para Compose;
- Git LFS, recomendado al clonar con Git; el archivo de Chrome for Testing también puede obtenerse por otros medios.

> [!NOTE]
> Make es una herramienta de línea de comandos habitual en sistemas tipo Unix. En Linux y macOS se puede ejecutar el proyecto directamente desde la terminal. En Windows se recomienda WSL2 junto con Docker Desktop.

## Primer arranque con Git LFS

| Comando | Qué hace | Nota |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Clona el repositorio | |
| `cd symfony-shop` | Entra en el directorio del proyecto | |
| `git lfs install` | Activa Git LFS | Normalmente se ejecuta una sola vez por usuario |
| `git lfs pull` | Descarga Chrome for Testing | Ejecutar antes de `make build` |
| `make init` | Crea `.env.docker` y directorios locales | Usa `.env.docker.example` y el UID/GID del usuario del host |
| `make build` | Construye la imagen PHP | |
| `make up` | Inicia `php`, `nginx` y `postgres` | |
| `make composer-install` | Instala dependencias PHP | Usa `composer.lock` |
| `make npm-install` | Instala dependencias del frontend | Usa `package-lock.json` |
| `make assets-build` | Compila los recursos del frontend | |
| `make migrate` | Aplica las migraciones Doctrine | |
| `make demo-init` | Inicializa datos de demostración | Solo datos locales `dev`/`test` |

Con la configuración estándar, la aplicación queda disponible en [http://localhost:8080](http://localhost:8080). El puerto se puede cambiar mediante `APP_PORT` en `.env.docker`.

> [!WARNING]
> `make demo-init` vuelve a crear los pedidos de demostración. Utiliza este comando solo con una base de datos cuyos datos puedan reemplazarse.

## Git LFS y Chrome for Testing

Panther utiliza Chrome for Testing, que se instala en la imagen PHP durante `make build`. El archivo del navegador se almacena mediante Git LFS, mientras que Chromedriver es un archivo Git normal.

| Artefacto | Ruta | Almacenamiento |
|---|---|---|
| Chrome for Testing | `bin/chrome-linux64-150.0.7871.46.zip` | Git LFS |
| Chromedriver | `bin/drivers/chromedriver` | Git normal |

El Dockerfile espera exactamente Chrome for Testing `150.0.7871.46`. No lo sustituyas por la versión estable actual de Chrome sin modificar y comprobar al mismo tiempo la configuración Docker/Panther.

Para el archivo fijado se han verificado:

| Comprobación | Valor esperado |
|---|---|
| Tamaño | `186933179` bytes |
| SHA-256 | `ad115a7498a17f53f6ed0914458326c6516addc756224db14c32184a9b1ab078` |

Hay tres formas de obtener el archivo.

### Opción 1 — Git LFS

Es la opción recomendada para un `git clone` normal:

```text
git lfs install
git lfs pull
```

Cliente oficial e instrucciones de instalación: [git-lfs.com](https://git-lfs.com/).

### Opción 2 — archivo de una versión de Symfony Shop

A partir de `v3.0.0`, el ZIP del proyecto puede descargarse desde [Releases](https://github.com/yaleksandr89/symfony-shop/releases). Chrome for Testing ya está incluido, por lo que Git LFS no es necesario en este flujo.

Utiliza el archivo de la versión exacta del proyecto que necesites: las versiones anteriores pueden contener otra versión de Chrome y una configuración diferente.

### Opción 3 — Chrome for Testing oficial

La versión `150.0.7871.46` está publicada en el catálogo oficial de Chrome for Testing:

- [metadatos de la versión `150.0.7871.46`](https://googlechromelabs.github.io/chrome-for-testing/150.0.7871.46.json);
- [archivo oficial de Chrome for Testing para Linux x64](https://storage.googleapis.com/chrome-for-testing-public/150.0.7871.46/linux64/chrome-linux64.zip).

Guarda el archivo descargado con el nombre:

```text
bin/chrome-linux64-150.0.7871.46.zip
```

Después de una descarga manual, verifica siempre el tamaño, SHA-256 y la integridad ZIP con la tabla anterior.

## Comprobar el archivo de Chrome

| Comando | Qué comprueba |
|---|---|
| `git lfs ls-files` | El archivo está registrado en Git LFS si se usa ese flujo |
| `wc -c < bin/chrome-linux64-150.0.7871.46.zip` | Tamaño |
| `sha256sum bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 en Linux/WSL |
| `shasum -a 256 bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 en macOS |
| `unzip -tq bin/chrome-linux64-150.0.7871.46.zip` | Integridad ZIP |

Si el archivo ocupa solo alrededor de cien bytes y empieza por `version https://git-lfs.github.com/spec/v1`, la copia de trabajo aún contiene un puntero de Git LFS. Ejecuta `git lfs pull` o sustituye el puntero por el archivo real obtenido mediante una de las dos alternativas anteriores.

Tras cualquier sustitución manual, el archivo debe producir el mismo SHA-256 esperado. Si la suma no coincide, no ejecutes la construcción ni hagas commit de ese archivo.

## Configuración local

`make init` crea `.env.docker` a partir de `.env.docker.example`, sustituye `HOST_UID` y `HOST_GID` por los valores actuales y crea `var/cache`, `var/log` y `public/uploads`.

Si `.env.docker` ya existe, no se sobrescribe. Guarda los secretos locales de la aplicación y las credenciales OAuth en `.env.local`, no en `.env.docker`.

> [!IMPORTANT]
> Los valores de `.env.docker` se pasan al contenedor PHP como variables de entorno del proceso y tienen prioridad sobre los valores con el mismo nombre en `.env.local`. Esto importa especialmente para Panther, la base de datos y cualquier clave duplicada accidentalmente en ambos archivos.

Las capas de entorno y su prioridad se explican en la [guía de configuración](configuration.md).

## Gestión de Docker

| Comando | Qué hace | Nota |
|---|---|---|
| `make ps` | Muestra los contenedores del proyecto | |
| `make restart php` | Reinicia PHP | También admite `nginx` y `postgres` |
| `make log php` | Sigue el log de PHP | También admite `nginx` y `postgres` |
| `make log-all` | Muestra todos los logs | |
| `make in php` | Abre Bash en el contenedor PHP como usuario `app` | |
| `make down` | Detiene el entorno | Conserva el volumen PostgreSQL |

La lista completa de objetivos Make, incluidas pruebas, comprobaciones, cobertura y comandos destructivos, está en la [guía de desarrollo](development.md).

## Si el primer arranque falla

| Síntoma | Qué comprobar |
|---|---|
| `make build` falla al extraer Chrome | tamaño, SHA-256 y `unzip -tq` del archivo Chrome |
| El archivo Chrome contiene `git-lfs.github.com/spec/v1` | si se ejecutó `git lfs pull`; al usar una release o descarga manual hay que sustituir el puntero por el ZIP real |
| Falta `.env.docker` | ejecutar `make init` |
| Los contenedores no arrancan | `make config`, después `make ps` y `make log-all` |
| La aplicación no abre en `8080` | comprobar `APP_PORT` en `.env.docker` y `make ps` |
| Un cambio en `.env.local` no tiene efecto | comprobar si la misma clave existe en `.env.docker` |

Las reglas de correo, Messenger, OAuth, entorno de pruebas y demás `.env*` están en la [guía de configuración](configuration.md).
