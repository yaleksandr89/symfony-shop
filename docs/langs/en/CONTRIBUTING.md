# Contributing to Symfony Shop

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../.github/CONTRIBUTING.md) | **English** | [Español](../es/CONTRIBUTING.md) | [中文](../zh/CONTRIBUTING.md) | [Français](../fr/CONTRIBUTING.md) | [Deutsch](../de/CONTRIBUTING.md) |

Thank you for your interest in Symfony Shop. It is an educational Symfony e-commerce project with a Docker-based environment, PostgreSQL, API Platform, OAuth, and selected interactive components built with Vue.

## Before you start

Check existing Discussions, Issues, and Pull Requests, and keep each change focused on one clear task. Questions and ideas are best discussed first in [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions); reproducible bugs and concrete improvements belong in Issues; security problems should be reported according to the [security policy](SECURITY.md) without publishing exploitation details.

## Project boundaries

- The supported local environment uses Docker Compose and the Makefile.
- PHP, Composer, PostgreSQL, Node.js, and the browser environment are not run directly on the host as part of the normal development workflow.
- Changes must not silently weaken access rules, OAuth flows, cart/order integrity, or other existing user-facing contracts.
- Do not include broad refactoring or dependency upgrades unrelated to the task.
- The Vue 2 frontend architecture remains in place until the separate migration to Inertia.js and Vue 3.

Application architecture is documented in [`architecture.md`](architecture.md), and development commands are listed in [`development.md`](development.md).

## Branches

Create a focused branch from the current `master`. The name should briefly describe the change, for example:

```text
fix/cart-quantity
docs/oauth
refactor/catalog-query
```

Changes reach `master` through a Pull Request.

## Commits

The project uses Conventional Commits with descriptions written in Russian:

```text
fix: исправить проверку количества товара
docs: уточнить настройку OAuth
refactor: упростить выборку каталога
```

A commit should contain one logically coherent group of changes.

## Local checks

Read the current Makefile before running commands. Main checks:

| Command | Purpose |
|---|---|
| `make check` | ESLint + PHP-CS-Fixer check + PHPStan |
| `make test-unit` | unit tests |
| `make test-integration` | integration tests |
| `make test-functional` | functional tests |
| `make test-functional-panther` | Panther browser tests |
| `make test-all CONFIRM=testdb` | full test suite, including Panther |

Run checks relevant to your change. Use the full suite when shared application boundaries are affected or before the final verification of a larger change.

## Pull Request

In the Pull Request description, include:

- what changed and why;
- how the change was verified;
- whether manual steps are required;
- whether configuration, data, OAuth, access control, or another important contract is affected;
- whether documentation was updated when the public usage flow changed.

## Checklist

- No secrets, real OAuth credentials, access tokens, cookies, session identifiers, or local `.env*` contents.
- No unrelated changes in the diff.
- `git diff --check` passes.
- Relevant checks have been run.
- New tests protect specific application behavior rather than being added for quantity.
- Documentation is updated when a public contract, configuration, or startup flow changes.
