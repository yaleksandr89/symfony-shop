# Arquitectura

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/architecture.md) | [English](../en/architecture.md) | **Español** | [中文](../zh/architecture.md) | [Français](../fr/architecture.md) | [Deutsch](../de/architecture.md) |


Symfony Shop es una única aplicación Symfony con páginas renderizadas en servidor, área de administración y API. El código se agrupa por áreas de aplicación y las rutas se centralizan en archivos YAML, de modo que se pueda seguir el recorrido desde una URL hasta un controlador o recurso API sin arrancar la aplicación.

## Esquema general

```text
Navegador
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
          ↓
Servicios de aplicación / handlers
          ↓
Doctrine ORM
          ↓
PostgreSQL
```

Vue 2 se monta sobre páginas Twig concretas donde hace falta interacción: carrito, indicador del carrito y editor de pedidos. La arquitectura actual no tiene una SPA independiente ni Vue Router.

## Áreas de aplicación

| Área | Contenido |
|---|---|
| [`src/Account`](../../../src/Account) | registro, acceso local, perfil, verificación de email, recuperación de contraseña, mensajes y flujos de correo |
| [`src/Catalog`](../../../src/Catalog) | categorías, productos, imágenes, lectura de catálogo y consultas Doctrine/API relacionadas |
| [`src/Commerce`](../../../src/Commerce) | carrito, líneas de carrito, checkout, pedidos, controles de acceso y notificaciones |
| [`src/Money`](../../../src/Money) | objetos de valor monetarios y cálculos utilizados en los flujos comerciales |

Las entidades Doctrine permanecen en [`src/Entity`](../../../src/Entity), mientras que los servicios de aplicación viven en el área que posee el caso de uso correspondiente.

## Bundles Symfony internos

El proyecto contiene tres bundles Symfony internos. Forman parte de la misma aplicación y no son paquetes Composer independientes.

| Bundle | Propósito |
|---|---|
| [`src/AdminBundle`](../../../src/AdminBundle) | controladores, formularios, plantillas y operaciones API de administración |
| [`src/OAuthBundle`](../../../src/OAuthBundle) | clientes OAuth, autenticadores, vinculación/desvinculación y mapeo de proveedores |
| [`src/SeoBundle`](../../../src/SeoBundle) | `robots.txt` y sitemap |

Los enlaces apuntan directamente a los directorios de cada módulo para poder inspeccionar su estructura sin navegación adicional.

## Enrutamiento

Las rutas de la aplicación están en [`config/routes.yaml`](../../../config/routes.yaml) y [`config/routes/app/`](../../../config/routes/app/).

Las áreas localizadas `account`, `catalog`, `commerce`, `admin` y `oauth` utilizan el prefijo `/{_locale}` con locales `ru|en`. Las rutas SEO no llevan prefijo de idioma.

API Platform se registra por separado mediante [`config/routes/api_platform.yaml`](../../../config/routes/api_platform.yaml) con el prefijo `/api`.

Recorrido práctico de una petición:

```text
URL
→ config/routes*.yaml
→ controlador o recurso API
→ servicio de aplicación / handler API
→ repositorio Doctrine / Doctrine ORM
```

## Doctrine y datos

Las entidades Doctrine están en [`src/Entity`](../../../src/Entity) y las migraciones en [`migrations`](../../../migrations).

Entidades principales:

- `User`;
- `Category`, `Product`, `ProductImage`;
- `Cart`, `CartProduct`;
- `Order`, `OrderProduct`;
- `ResetPasswordRequest`.

Los repositorios y servicios de aplicación no se concentran en una carpeta común: viven cerca del área que los utiliza.

Los datos demo reproducibles están en [`tools/demo`](../../../tools/demo) y solo se cargan en `dev` y `test`.

## API Platform

API Platform se utiliza para la API de la aplicación, no para publicar automáticamente todas las entidades Doctrine.

La API cubre catálogo, carrito y pedidos. El acceso y las modificaciones se restringen además mediante controles de permisos, extensiones de consulta, objetos de entrada y handlers de API Platform. El checkout utiliza un objeto de entrada y handler dedicados, mientras que las operaciones administrativas sobre líneas de pedido se amplían con configuración de `AdminBundle`.

Al investigar el comportamiento de la API, revisa no solo los atributos de la entidad, sino también handlers de API Platform, extensiones de consulta y reglas de acceso.

## Twig, Vue y Webpack Encore

La mayoría de las páginas se renderizan con Twig. Las plantillas comunes están en [`templates`](../../../templates), y las plantillas de bundles internos dentro de sus módulos.

Webpack Encore compila recursos desde [`assets`](../../../assets) a `public/build`. Vue 2 se utiliza de forma puntual como capa interactiva sobre páginas renderizadas en servidor.

La arquitectura cliente actual se mantiene hasta la migración separada a Inertia.js y Vue 3.

## Configuración e inyección de dependencias

[`config/services.yaml`](../../../config/services.yaml) habilita la inyección automática de dependencias (`autowiring`) para el código de la aplicación y contiene configuración explícita para servicios que requieren parámetros especiales o mapas de proveedores.

Los ajustes de Doctrine, Security, Messenger, Mailer, Twig y API Platform están en [`config/packages`](../../../config/packages).

## Pruebas

| Directorio / grupo | Propósito |
|---|---|
| [`tests/Unit`](../../../tests/Unit) | reglas y servicios aislados |
| [`tests/Integration`](../../../tests/Integration) | Doctrine e interacción de varios servicios |
| [`tests/Functional`](../../../tests/Functional) | HTTP, controladores, API y reglas de acceso |
| `functional-panther` | escenarios de navegador mediante Panther |
| [`tests/TestUtils`](../../../tests/TestUtils) | utilidades comunes de pruebas y sustitutos de clientes OAuth externos |

La cobertura PHP/PHPUnit se calcula sobre `src` y `tools/demo`; Panther queda fuera del informe. Los comandos están en la [guía de desarrollo](development.md).

## Docker

Docker Compose inicia tres servicios permanentes:

| Servicio | Función |
|---|---|
| `php` | PHP-FPM, Composer, Symfony Console y entorno Panther |
| `nginx` | entrada HTTP y archivos estáticos |
| `postgres` | PostgreSQL con volumen de datos persistente |

`node` pertenece al perfil `tools` y se usa para comandos npm puntuales y builds del frontend. Docker Compose no tiene actualmente un worker permanente de Messenger.

El primer arranque se explica en la [guía de puesta en marcha](getting-started.md), y las capas `.env*` en la [guía de configuración](configuration.md).
