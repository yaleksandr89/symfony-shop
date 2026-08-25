# Architecture

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/architecture.md) | **English** | [Español](../es/architecture.md) | [中文](../zh/architecture.md) | [Français](../fr/architecture.md) | [Deutsch](../de/architecture.md) |


Symfony Shop is a single Symfony application with server-rendered pages, an administration area, and an API. Code is grouped by application area, while routes are centralized in YAML files so the path from a URL to a controller or API resource can be found without starting the application.

## Overview

```text
Browser
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
          ↓
Application services / handlers
          ↓
Doctrine ORM
          ↓
PostgreSQL
```

Vue 2 is mounted on selected Twig pages where interactivity is needed: the cart, cart indicator, and order editor. There is no standalone SPA and no Vue Router in the current architecture.

## Application areas

| Area | Contents |
|---|---|
| [`src/Account`](../../../src/Account) | registration, local login, profile, email verification, password reset, messages, and mail flows |
| [`src/Catalog`](../../../src/Catalog) | categories, products, images, catalog reads, and related Doctrine/API queries |
| [`src/Commerce`](../../../src/Commerce) | cart, cart items, checkout, orders, access checks, and notifications |
| [`src/Money`](../../../src/Money) | monetary value objects and calculations used by commerce flows |

Doctrine entities remain in [`src/Entity`](../../../src/Entity), while application services live in the area that owns the corresponding use case.

## Internal Symfony bundles

The project contains three internal Symfony bundles. They remain part of the same application and are not separate Composer packages.

| Bundle | Purpose |
|---|---|
| [`src/AdminBundle`](../../../src/AdminBundle) | administration controllers, forms, templates, and API operations |
| [`src/OAuthBundle`](../../../src/OAuthBundle) | OAuth clients, authenticators, linking/unlinking, and provider mapping |
| [`src/SeoBundle`](../../../src/SeoBundle) | `robots.txt` and sitemap |

The links point directly to module directories so their structure can be inspected without additional repository navigation.

## Routing

Application routes are defined in [`config/routes.yaml`](../../../config/routes.yaml) and [`config/routes/app/`](../../../config/routes/app/).

Localized `account`, `catalog`, `commerce`, `admin`, and `oauth` areas use the `/{_locale}` prefix with `ru|en`. SEO routes remain outside the language prefix.

API Platform is registered separately through [`config/routes/api_platform.yaml`](../../../config/routes/api_platform.yaml) with the `/api` prefix.

A practical request-tracing path:

```text
URL
→ config/routes*.yaml
→ controller or API resource
→ application service / API handler
→ Doctrine repository / Doctrine ORM
```

## Doctrine and data

Doctrine entities are located in [`src/Entity`](../../../src/Entity), and migrations in [`migrations`](../../../migrations).

Main entities:

- `User`;
- `Category`, `Product`, `ProductImage`;
- `Cart`, `CartProduct`;
- `Order`, `OrderProduct`;
- `ResetPasswordRequest`.

Repositories and application services are not collected in one common directory: they live near the application area that uses them.

Reproducible demo data lives in [`tools/demo`](../../../tools/demo) and is loaded only in `dev` and `test`.

## API Platform

API Platform is used for the application API rather than automatically exposing every Doctrine entity.

The API covers the catalog, cart, and orders. Data access and mutations are additionally restricted through access checks, query extensions, input objects, and API Platform handlers. Checkout uses a dedicated input object and handler, while administrative order-item operations are extended through `AdminBundle` configuration.

When tracing API behavior, inspect not only entity attributes but also the corresponding API Platform handlers, query extensions, and access rules.

## Twig, Vue, and Webpack Encore

Most pages are rendered with Twig. Shared templates live in [`templates`](../../../templates), while internal bundle templates live inside their respective modules.

Webpack Encore builds assets from [`assets`](../../../assets) into `public/build`. Vue 2 is used selectively as an interactive layer over server-rendered pages.

The current client architecture remains in place until the separate migration to Inertia.js and Vue 3.

## Configuration and dependency injection

[`config/services.yaml`](../../../config/services.yaml) enables automatic dependency injection (`autowiring`) for application code and contains explicit service configuration where special parameters or provider maps are required.

Doctrine, Security, Messenger, Mailer, Twig, and API Platform settings live under [`config/packages`](../../../config/packages).

## Tests

| Directory / group | Purpose |
|---|---|
| [`tests/Unit`](../../../tests/Unit) | isolated application rules and services |
| [`tests/Integration`](../../../tests/Integration) | Doctrine and interaction among several services |
| [`tests/Functional`](../../../tests/Functional) | HTTP, controllers, API, and access rules |
| `functional-panther` | browser scenarios through Panther |
| [`tests/TestUtils`](../../../tests/TestUtils) | shared test helpers and replacements for external OAuth clients |

PHP/PHPUnit coverage is calculated for `src` and `tools/demo`; Panther is excluded from the report. Commands are collected in the [development guide](development.md).

## Docker

Docker Compose starts three persistent services:

| Service | Role |
|---|---|
| `php` | PHP-FPM, Composer, Symfony Console, and Panther environment |
| `nginx` | HTTP entry point and static files |
| `postgres` | PostgreSQL with a persistent data volume |

`node` is part of the `tools` profile and is used for one-off npm commands and frontend builds. Docker Compose currently has no permanent Messenger worker.

First startup is described in the [getting-started guide](getting-started.md), while `.env*` layers are covered in the [configuration guide](configuration.md).
