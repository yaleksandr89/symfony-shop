# Архитектура

Symfony Shop: одно Symfony-приложение с серверными страницами, административной частью и API. Код сгруппирован по прикладным областям, а маршруты вынесены в централизованные YAML-файлы, чтобы путь от URL к нужному контроллеру или API resource можно было найти без запуска приложения.

## Общая схема

```text
Browser
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
          ↓
Application services / managers / processors
          ↓
Doctrine ORM
          ↓
PostgreSQL
```

Vue 2 подключается поверх отдельных Twig-страниц там, где нужна интерактивность: корзина, индикатор корзины и редактор заказа. Отдельного SPA и Vue Router в текущей архитектуре нет.

## Прикладные области

| Область | Что находится внутри |
|---|---|
| `src/Account` | регистрация, локальный вход, профиль, подтверждение email, reset password, связанные messages и mail |
| `src/Catalog` | категории, товары, изображения, чтение каталога и связанные Doctrine/API queries |
| `src/Commerce` | корзина, позиции корзины, checkout, заказы, voters и уведомления о заказе |
| `src/Money` | денежные value objects и вычисления, используемые Commerce |

Сущности Doctrine остаются в `src/Entity`, а прикладные сервисы и managers находятся в области, которая владеет соответствующим сценарием.

## Внутренние Symfony bundle

В проекте есть три внутренних bundle:

| Bundle | Назначение |
|---|---|
| `src/AdminBundle` | административные controllers, forms, templates и API-операции |
| `src/OAuthBundle` | OAuth clients, authenticators, link/unlink и provider mapping |
| `src/SeoBundle` | `robots.txt` и sitemap |

Это части одного приложения, а не отдельные Composer packages.

## Маршрутизация

First-party маршруты собраны в [`config/routes.yaml`](../config/routes.yaml) и [`config/routes/app/`](../config/routes/app/).

Локализованные области `account`, `catalog`, `commerce`, `admin` и `oauth` работают под префиксом `/{_locale}` с локалями `ru|en`. SEO-маршруты остаются без языкового префикса.

API Platform подключён отдельно через [`config/routes/api_platform.yaml`](../config/routes/api_platform.yaml) с префиксом `/api`.

Практический путь поиска endpoint:

```text
URL
→ config/routes*.yaml
→ controller или API resource
→ application service / processor
→ repository / Doctrine
```

## Doctrine и данные

Doctrine entities находятся в [`src/Entity`](../src/Entity), migrations: в [`migrations`](../migrations).

Основные сущности:

- `User`;
- `Category`, `Product`, `ProductImage`;
- `Cart`, `CartProduct`;
- `Order`, `OrderProduct`;
- `ResetPasswordRequest`.

Репозитории и application services не собраны в одну общую папку: они расположены рядом с прикладной областью, которая ими пользуется.

Воспроизводимые demo-данные находятся в [`tools/demo`](../tools/demo) и подключаются только для `dev` и `test`.

## API Platform

API Platform используется как прикладной API, а не как автоматическая публикация всех Doctrine entities.

В API участвуют каталог, корзина и заказы. Доступ и изменение данных дополнительно ограничиваются voters, query extensions, input objects и processors. Для checkout используется отдельный input/processor contract, а административные операции над позициями заказа дополняются конфигурацией AdminBundle.

При поиске API-поведения смотрите не только Entity attributes, но и соответствующие processors, extensions и security boundaries.

## Twig, Vue и Webpack Encore

Основные страницы рендерятся Twig. Общие templates находятся в [`templates`](../templates), bundle-specific templates: внутри соответствующих bundle.

Webpack Encore собирает frontend из [`assets`](../assets) в `public/build`. Vue 2 используется точечно как интерактивный слой поверх серверных страниц.

Текущая frontend-архитектура остаётся такой до отдельной миграции на Inertia.js и Vue 3.

## Конфигурация и DI

[`config/services.yaml`](../config/services.yaml) включает autowiring для first-party кода и содержит явную конфигурацию сервисов, которым нужны специальные параметры или provider maps.

Настройки Doctrine, Security, Messenger, Mailer, Twig и API Platform находятся в [`config/packages`](../config/packages).

## Тесты

| Каталог / группа | Назначение |
|---|---|
| `tests/Unit` | изолированные first-party правила и сервисы |
| `tests/Integration` | Doctrine и взаимодействие нескольких сервисов |
| `tests/Functional` | HTTP, controllers, API и security contracts |
| `functional-panther` | browser-сценарии через Panther |
| `tests/TestUtils` | общие test helpers и замены внешних OAuth clients |

PHP/PHPUnit coverage считается по `src` и `tools/demo`; Panther в coverage не входит. Команды запуска собраны в [руководстве по разработке](development.md).

## Docker

Docker Compose поднимает три постоянных сервиса:

| Сервис | Роль |
|---|---|
| `php` | PHP-FPM, Composer, Symfony Console и Panther runtime |
| `nginx` | HTTP entrypoint и статические файлы |
| `postgres` | PostgreSQL с постоянным volume |

`node` включён в профиль `tools` и используется для одноразовых npm-команд и сборки assets. Постоянного Messenger worker в Compose нет.

Первый запуск описан в [руководстве по запуску](getting-started.md), а слои `.env*`: в [руководстве по конфигурации](configuration.md).
