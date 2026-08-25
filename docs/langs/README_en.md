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
    alt="Symfony Shop — e-commerce application built with Symfony, Docker, and PostgreSQL"
    width="100%"
  >
</p>

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../README.md) | **Selected** | [Español](README_es.md) | [中文](README_zh.md) | [Français](README_fr.md) | [Deutsch](README_de.md) |

Symfony Shop is an educational e-commerce application built with Symfony. It includes a product catalog, cart and checkout, user account, administration area, API, and OAuth login. Most pages are rendered with Twig, while Vue 2 is used for selected interactive interface elements.

The supported local development environment is based on Docker Compose. PHP, Composer, Node.js, PostgreSQL, and Chrome for Testing run inside containers or are installed into the Docker image, while the main workflows are exposed through a single Makefile. Running the project with PHP, Composer, and PostgreSQL installed directly on the host is not a supported workflow and is not covered by CI.

## Features

- categories and products with images, new-product markers, and discounts;
- cart availability checks and order checkout;
- registration, login, email verification, and password reset;
- user account;
- OAuth through Google, Yandex, VKontakte, GitHub, Facebook, and LinkedIn;
- separate OAuth login, account-linking, and unlinking flows;
- administration of users, categories, products, and orders;
- API powered by API Platform;
- unit, integration, functional, and browser tests;
- GitHub Actions CI using the same Docker-based environment.

## Quick start

The host needs Git, Make, and Docker with Compose support. Git LFS is recommended for a normal repository clone; the large browser archive can also be obtained without it.

> [!NOTE]
> Make is a standard command-line tool on Unix-like systems. On Linux and macOS the project can be run directly from the terminal. On Windows, the recommended setup is WSL2 together with Docker Desktop.

| Command | Purpose | Note |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Clone the repository | |
| `cd symfony-shop` | Enter the project directory | |
| `git lfs install` | Enable Git LFS | Required only for the Git LFS workflow |
| `git lfs pull` | Download Chrome for Testing | Run before `make build` |
| `make init` | Create `.env.docker` and local directories | Does not overwrite an existing `.env.docker` |
| `make build` | Build the PHP image | The image includes Chrome and Chromedriver for Panther |
| `make up` | Start PHP-FPM, Nginx, and PostgreSQL | |
| `make composer-install` | Install PHP dependencies from `composer.lock` | Composer is not required on the host |
| `make npm-install` | Install dependencies from `package-lock.json` | Node.js is not required on the host |
| `make assets-build` | Build frontend assets | |
| `make migrate` | Apply Doctrine migrations | |
| `make demo-init` | Create demo data | Local `dev`/`test` environments only |

After startup, the application is available at [http://localhost:8080](http://localhost:8080) by default.

> [!IMPORTANT]
> The project pins Chrome for Testing `150.0.7871.46`. The recommended way to obtain the archive is `git lfs pull`. Starting with `v3.0.0`, the project ZIP can be downloaded from [Releases](https://github.com/yaleksandr89/symfony-shop/releases) with Chrome for Testing already included, so Git LFS is not required for that workflow. The pinned Chrome for Testing version can also be downloaded directly from the official source. Exact links, the filename, and SHA-256 are listed in the [setup guide](../getting-started.md#git-lfs-и-chrome-for-testing).

> [!IMPORTANT]
> Values from `.env.docker` are passed to the PHP container as process environment variables. If the same key is defined both there and in `.env.local`, the `.env.docker` value takes precedence. The complete precedence model is described in the [configuration guide](../configuration.md#приоритет-переменных).

> [!WARNING]
> `make demo-init` recreates demo orders. Do not run it against a local database that contains data you need to keep.

The complete first-run procedure, all three ways to obtain Chrome for Testing, and container-management commands are covered in the [setup guide](../getting-started.md).

## Mail and message queue

By default, `MAILER_DSN=null://null`, so the application does not send mail through an external SMTP service. Messages sent synchronously during an HTTP request can be inspected in the Mailer panel of Symfony Profiler.

Registration and password reset use the Messenger `async` transport. Routing to the queue is already configured, but Docker Compose does not currently start a permanent worker, so those messages are processed only after running:

```text
make console CMD='messenger:consume async -vv'
```

Transport, mail, and local-secret configuration is described in the [configuration guide](../configuration.md#почта-и-messenger).

## OAuth

OAuth login and linking an external account to an existing user are separate operations. A matching provider email is not sufficient to automatically link an external identity to an existing local account.

To link an account, the user first signs in normally, confirms the current password, and explicitly starts the OAuth flow from the account page. Unlinking is also protected by the current password and a CSRF token.

Supported providers, environment variables, routes, and security rules are documented in the [OAuth guide](../oauth.md). General rules for local configuration and secrets are covered in the [configuration guide](../configuration.md).

## Project structure

```text
Browser
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
  ↓
Application services / Doctrine
  ↓
PostgreSQL
```

The main code is grouped into `Account`, `Catalog`, and `Commerce` areas. Administration, OAuth, and SEO are implemented as internal Symfony bundles. Vue 2 is used for selected interactive components rather than as a standalone SPA.

The directory map, routing, API Platform, Doctrine, and frontend boundaries are described in the [architecture guide](../architecture.md).

## Checks

| Command | Purpose | Note |
|---|---|---|
| `make check` | Run ESLint, PHP-CS-Fixer check, and PHPStan | Tests are not included |
| `make test-unit` | Run unit tests | |
| `make test-integration` | Run integration tests | |
| `make test-functional` | Run functional tests | |
| `make test-functional-panther` | Run browser tests with Panther | Chrome is already included in the PHP image |
| `make test-all CONFIRM=testdb` | Run the full test suite | Recreates the test database |
| `make coverage CONFIRM=testdb` | Show PHP/PHPUnit coverage in the terminal | Panther is not included |
| `make coverage-html CONFIRM=testdb` | Generate HTML and Clover reports | `var/coverage/html`, `var/coverage/clover.xml` |

The full Make command list, test database workflow, and CI composition are documented in the [development guide](../development.md).

## Planned work

1. **Local mail environment.** Add a dedicated mail service with a web interface and a persistent Messenger worker so messages on the `async` transport are processed automatically.
2. **Inertia.js and Vue 3.** Move server/client interaction to Inertia.js and Vue 3. I also want to revisit localization during that work: depending on the scope of the migration, it may become possible to drop the mandatory `/{_locale}` URL prefix. I will decide that as part of the new frontend design.
3. **Administration.** After the frontend migration, significantly expand store-management capabilities in the administration interface.

## Feedback

- reproducible bugs — [GitHub Issues](https://github.com/yaleksandr89/symfony-shop/issues);
- questions and ideas — [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions).

## Project history

### 2026 — preparing v3.0.0

- Docker Compose became the primary development environment. A single Makefile, reproducible bootstrap, PostgreSQL in Docker, demo data, Xdebug, and APCu were added.
- CI moved to GitHub Actions and now uses the same Docker-based workflow as local development.
- The backend stack was progressively upgraded to PHP 8.5, Symfony 8.1, API Platform 4.3, Doctrine ORM 3 / DBAL 4, PHPUnit 13, and PHPStan 2.
- Security and business boundaries around the cart, checkout, API, registration, password reset, and OAuth were substantially revised.
- OAuth support was expanded with Facebook and LinkedIn; login, registration, linking, and unlinking are now separate flows protected by dedicated checks.
- Selenium, GeckoDriver, Java tooling, and Deployer were removed. Browser tests moved to Panther and Chrome for Testing; the Chrome archive is stored through Git LFS.
- The application architecture was reorganized around `Account`, `Catalog`, and `Commerce`, plus `AdminBundle`, `OAuthBundle`, and `SeoBundle`; routing and the shared OAuth callback flow were centralized.
- The test setup was rebuilt with Docker-backed quality gates and coverage commands.
- Project documentation was fully rewritten with dedicated setup, configuration, development, OAuth, and architecture guides.
- The project license was standardized as MIT; GitHub Issues/Discussions, Pull Request templates, a contribution guide, and a security policy were added.

### 2024 — v2.3.0

- Symfony was upgraded to 6.4.9.
- PHPUnit moved from 9 to 11 and DAMA Doctrine Test Bundle to version 8; the existing tests were refactored.
- The migration from annotations to PHP attributes continued together with PHPStan cleanup.
- Selenium, ChromeDriver, and GeckoDriver were updated.
- Nginx and Supervisor examples, Deployer instructions, and README translations were added.

### 2023 — v2.1.1 / v2.2.0

- Symfony was upgraded to 6.3.1, dependencies were refreshed, and first-party deprecation notices were removed.
- Another refactoring and PHPStan cleanup pass was completed.
- Deployer configuration was updated.
- CircleCI was removed after the service stopped operating for users in Russia.

### 2022 — v1.2.0 / v2.0.0 / v2.1.0

- The core e-commerce functionality was established.
- OAuth authentication through Google, Yandex, VKontakte, and GitHub was added.
- Symfony was progressively upgraded from 5.4 to 6.0.
- External OAuth accounts could be linked and unlinked from the user account.
- Protection against reusing the same external identity across multiple local users was added.

### 2021 — project start

- The first Symfony Shop version was created on Symfony 5.3 with PostgreSQL.

---

<p align="center">
  If you found the project useful, give it a star on GitHub — it helps other developers discover it. 🤘
</p>
