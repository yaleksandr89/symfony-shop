# Symfony Shop

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fsymfony--shop-blue.svg?style=flat-square)](https://github.com/yaleksandr89/symfony-shop)
[![CI](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml/badge.svg)](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000.svg?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18.4-4169E1.svg?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED.svg?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](../../LICENSE.md)

<p align="center">
  <img
    src="../img/symfony-shop-readme-cover.png"
    alt="Symfony Shop — tienda en línea con Symfony, Docker y PostgreSQL"
    width="100%"
  >
</p>

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../README.md) | [English](README_en.md) | **Seleccionado** | [中文](README_zh.md) | [Français](README_fr.md) | [Deutsch](README_de.md) |

Symfony Shop es una tienda en línea educativa construida con Symfony. El proyecto incluye catálogo de productos, carrito y checkout, cuenta de usuario, área de administración, API e inicio de sesión mediante OAuth. La mayoría de las páginas se renderizan con Twig y Vue 2 se utiliza para algunos elementos interactivos de la interfaz.

El entorno de desarrollo local compatible se basa en Docker Compose. PHP, Composer, Node.js, PostgreSQL y Chrome for Testing se ejecutan dentro de contenedores o se instalan en la imagen Docker, y las operaciones principales están reunidas en un único Makefile. Ejecutar el proyecto con PHP, Composer y PostgreSQL instalados directamente en el host no es un flujo compatible y no se comprueba en CI.

## Funcionalidad

- catálogo de categorías y productos con imágenes, novedades y descuentos;
- carrito con comprobación de disponibilidad y checkout;
- registro, inicio de sesión, verificación de email y recuperación de contraseña;
- cuenta de usuario;
- OAuth mediante Google, Yandex, VKontakte, GitHub, Facebook y LinkedIn;
- flujos separados para inicio de sesión OAuth, vinculación y desvinculación de cuentas externas;
- administración de usuarios, categorías, productos y pedidos;
- API basada en API Platform;
- pruebas unitarias, de integración, funcionales y de navegador;
- CI en GitHub Actions sobre el mismo entorno Docker.

## Inicio rápido

En el host se necesitan Git, Make y Docker con soporte para Compose. Git LFS es la opción recomendada para un clon normal del repositorio; el archivo grande del navegador también puede obtenerse sin Git LFS.

> [!NOTE]
> Make es una herramienta de línea de comandos habitual en sistemas tipo Unix. En Linux y macOS el proyecto puede ejecutarse directamente desde la terminal. En Windows se recomienda WSL2 junto con Docker Desktop.

| Comando | Qué hace | Nota |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Clona el repositorio | |
| `cd symfony-shop` | Entra en el directorio del proyecto | |
| `git lfs install` | Activa Git LFS | Solo para el flujo con Git LFS |
| `git lfs pull` | Descarga Chrome for Testing | Ejecutar antes de `make build` |
| `make init` | Crea `.env.docker` y directorios locales | No sobrescribe un `.env.docker` existente |
| `make build` | Construye la imagen PHP | Incluye Chrome y Chromedriver para Panther |
| `make up` | Inicia PHP-FPM, Nginx y PostgreSQL | |
| `make composer-install` | Instala dependencias PHP desde `composer.lock` | Composer no es necesario en el host |
| `make npm-install` | Instala dependencias desde `package-lock.json` | Node.js no es necesario en el host |
| `make assets-build` | Compila los recursos del frontend | |
| `make migrate` | Aplica las migraciones de Doctrine | |
| `make demo-init` | Crea datos de demostración | Solo en entornos locales `dev`/`test` |

Tras el arranque, la aplicación está disponible por defecto en [http://localhost:8080](http://localhost:8080).

> [!IMPORTANT]
> El proyecto fija Chrome for Testing `150.0.7871.46`. La forma recomendada de obtener el archivo es `git lfs pull`. A partir de `v3.0.0`, el ZIP del proyecto puede descargarse desde [Releases](https://github.com/yaleksandr89/symfony-shop/releases) con Chrome for Testing ya incluido, por lo que Git LFS no es necesario en ese caso. La versión fijada también puede descargarse directamente desde la fuente oficial. Los enlaces exactos, el nombre del archivo y el SHA-256 están en la [guía de puesta en marcha](es/getting-started.md).

> [!IMPORTANT]
> Los valores de `.env.docker` se pasan al contenedor PHP como variables de entorno del proceso. Si una misma clave existe allí y en `.env.local`, tiene prioridad el valor de `.env.docker`. El esquema completo se explica en la [guía de configuración](es/configuration.md).

> [!WARNING]
> `make demo-init` vuelve a crear los pedidos de demostración. No lo ejecutes sobre una base local que contenga datos que necesites conservar.

El primer arranque detallado, las tres formas de obtener Chrome for Testing y la gestión de contenedores están descritos en la [guía de puesta en marcha](es/getting-started.md).

## Correo y cola de mensajes

Por defecto, `MAILER_DSN=null://null`, así que la aplicación no envía correo a través de un servicio SMTP externo. Los mensajes enviados de forma síncrona durante una petición HTTP pueden verse en el panel Mailer de Symfony Profiler.

El registro y la recuperación de contraseña utilizan el transporte Messenger `async`. El enrutamiento a la cola ya está configurado, pero Docker Compose no inicia actualmente un worker permanente, por lo que esos mensajes se procesan solo después de ejecutar:

```text
make console CMD='messenger:consume async -vv'
```

La configuración del transporte, correo y secretos locales se describe en la [guía de configuración](es/configuration.md).

## OAuth

El inicio de sesión mediante OAuth y la vinculación de una cuenta externa a un usuario existente son operaciones distintas. Que el email del proveedor coincida no permite vincular automáticamente una identidad externa con una cuenta local existente.

Para vincular una cuenta, el usuario primero inicia sesión de forma normal, confirma su contraseña actual e inicia explícitamente el flujo OAuth desde su cuenta. La desvinculación también está protegida por la contraseña actual y un token CSRF.

Los proveedores compatibles, variables de entorno, rutas y reglas de seguridad están documentados en la [guía de OAuth](es/oauth.md). Las reglas generales de configuración local y secretos están en la [guía de configuración](es/configuration.md).

## Estructura del proyecto

```text
Navegador
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
  ↓
Servicios de aplicación / Doctrine
  ↓
PostgreSQL
```

El código principal se agrupa en las áreas `Account`, `Catalog` y `Commerce`. La administración, OAuth y SEO están implementados como bundles internos de Symfony. Vue 2 se utiliza para componentes interactivos concretos, no como una SPA independiente.

El mapa de directorios, el enrutamiento, API Platform, Doctrine y los límites del frontend se describen en la [guía de arquitectura](es/architecture.md).

## Comprobaciones

| Comando | Qué hace | Nota |
|---|---|---|
| `make check` | Ejecuta ESLint, la comprobación de PHP-CS-Fixer y PHPStan | No incluye pruebas |
| `make test-unit` | Ejecuta pruebas unitarias | |
| `make test-integration` | Ejecuta pruebas de integración | |
| `make test-functional` | Ejecuta pruebas funcionales | |
| `make test-functional-panther` | Ejecuta pruebas de navegador con Panther | Chrome ya está incluido en la imagen PHP |
| `make test-all CONFIRM=testdb` | Ejecuta todo el conjunto de pruebas | Vuelve a crear la base de datos de pruebas |
| `make coverage CONFIRM=testdb` | Muestra la cobertura PHP/PHPUnit en la terminal | Panther no forma parte del informe |
| `make coverage-html CONFIRM=testdb` | Genera informes HTML y Clover | `var/coverage/html`, `var/coverage/clover.xml` |

La lista completa de comandos Make, el flujo de la base de datos de pruebas y la composición de CI están en la [guía de desarrollo](es/development.md).

## En planes

1. **Entorno local de correo.** Añadir un servicio de correo con interfaz web y un worker permanente de Messenger para que los mensajes del transporte `async` se procesen automáticamente.
2. **Inertia.js y Vue 3.** Pasar la interacción entre servidor y cliente a Inertia.js y Vue 3. También quiero revisar la localización durante ese trabajo: según el alcance de los cambios, quizá sea posible prescindir del prefijo obligatorio `/{_locale}` en las URL. Lo decidiré al diseñar el nuevo frontend.
3. **Administración.** Después de la migración del frontend, ampliar de forma importante las posibilidades de gestión de la tienda desde el área administrativa.

## Contacto

- errores reproducibles — [GitHub Issues](https://github.com/yaleksandr89/symfony-shop/issues);
- preguntas e ideas — [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions).

## Historia del proyecto

### 2026 — preparación de v3.0.0

- Docker Compose se convirtió en el entorno principal de desarrollo. Se añadieron un Makefile único, bootstrap reproducible, PostgreSQL en Docker, datos demo, Xdebug y APCu.
- CI se trasladó a GitHub Actions y utiliza el mismo flujo basado en Docker que el desarrollo local.
- El stack backend se actualizó progresivamente a PHP 8.5, Symfony 8.1, API Platform 4.3, Doctrine ORM 3 / DBAL 4, PHPUnit 13 y PHPStan 2.
- Se revisaron de forma importante la seguridad y los límites de negocio del carrito, checkout, API, registro, recuperación de contraseña y OAuth.
- OAuth se amplió con Facebook y LinkedIn; inicio de sesión, registro, vinculación y desvinculación se separaron y quedaron protegidos por comprobaciones específicas.
- Se eliminaron Selenium, GeckoDriver, herramientas Java y Deployer. Las pruebas de navegador pasaron a Panther y Chrome for Testing; el archivo de Chrome se almacena mediante Git LFS.
- La arquitectura se reorganizó alrededor de `Account`, `Catalog` y `Commerce`, además de `AdminBundle`, `OAuthBundle` y `SeoBundle`; se centralizaron las rutas y el flujo común de callback OAuth.
- Se reconstruyó el entorno de pruebas, con quality gates basados en Docker y comandos de cobertura.
- Se reescribió por completo la documentación con guías separadas de instalación, configuración, desarrollo, OAuth y arquitectura.
- La licencia se unificó como MIT; se añadieron GitHub Issues/Discussions, plantillas de Pull Request, guía de contribución y política de seguridad.

### 2024 — v2.3.0

- Symfony se actualizó a 6.4.9.
- PHPUnit pasó de 9 a 11 y DAMA Doctrine Test Bundle a la versión 8; se refactorizaron las pruebas existentes.
- Continuó la migración de anotaciones a atributos PHP y la limpieza de avisos de PHPStan.
- Se actualizaron Selenium, ChromeDriver y GeckoDriver.
- Se añadieron ejemplos de Nginx y Supervisor, instrucciones de Deployer y traducciones del README.

### 2023 — v2.1.1 / v2.2.0

- Symfony se actualizó a 6.3.1, se renovaron dependencias y se eliminaron avisos deprecados del código propio.
- Se realizó otra fase de refactorización y limpieza con PHPStan.
- Se actualizó la configuración de Deployer.
- CircleCI se eliminó después de dejar de prestar servicio a usuarios en Rusia.

### 2022 — v1.2.0 / v2.0.0 / v2.1.0

- Se estableció la funcionalidad principal de la tienda.
- Se añadió autenticación OAuth mediante Google, Yandex, VKontakte y GitHub.
- Symfony se actualizó progresivamente de 5.4 a 6.0.
- Se añadió la vinculación y desvinculación de cuentas OAuth externas desde la cuenta de usuario.
- Se añadió protección contra la reutilización de una misma identidad externa por varios usuarios locales.

### 2021 — inicio del proyecto

- Se creó la primera versión de Symfony Shop sobre Symfony 5.3 y PostgreSQL.

---

<p align="center">
  Si el proyecto te ha resultado útil, dale una estrella en GitHub: así será más fácil que otros desarrolladores lo encuentren. 🤘
</p>
