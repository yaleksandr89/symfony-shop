# Вклад в Symfony Shop

## Выберите язык

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| **Русский** | [English](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/en/CONTRIBUTING.md) | [Español](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/es/CONTRIBUTING.md) | [中文](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/zh/CONTRIBUTING.md) | [Français](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/fr/CONTRIBUTING.md) | [Deutsch](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/de/CONTRIBUTING.md) |

Спасибо за интерес к Symfony Shop. Это учебный интернет-магазин на Symfony с Docker-окружением, PostgreSQL, API Platform, OAuth и отдельными интерактивными компонентами на Vue.

## Перед началом

Проверьте существующие Discussions, Issues и Pull Requests и постарайтесь ограничить изменение одной понятной задачей. Вопросы и идеи лучше сначала обсуждать в [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions), воспроизводимые ошибки и конкретные улучшения оформлять через Issues, а проблемы безопасности сообщать по [политике безопасности](https://github.com/yaleksandr89/symfony-shop/security/policy) без публикации деталей эксплуатации.

## Основные границы проекта

- Поддерживаемая локальная среда работает через Docker Compose и Makefile.
- PHP, Composer, PostgreSQL, Node.js и браузерное окружение проекта не запускаются на хосте как часть штатного процесса разработки.
- Изменения не должны незаметно ослаблять правила доступа, OAuth-сценарии, целостность корзины/заказов или другие существующие пользовательские контракты.
- Не добавляйте широкие рефакторинги и обновления зависимостей, не связанные с задачей.
- Архитектура фронтенда на Vue 3 сохраняется до отдельной миграции на Inertia.js.

Архитектура приложения описана в [`docs/langs/ru/architecture.md`](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/ru/architecture.md), а команды разработки — в [`docs/langs/ru/development.md`](https://github.com/yaleksandr89/symfony-shop/blob/master/docs/langs/ru/development.md).

## Ветки

Создавайте тематическую ветку от актуального `master`. Имя должно кратко отражать изменение, например:

```text
fix/cart-quantity
docs/oauth
refactor/catalog-query
```

Изменения в `master` попадают через Pull Request.

## Коммиты

В проекте используются Conventional Commits с русским описанием:

```text
fix: исправить проверку количества товара
docs: уточнить настройку OAuth
refactor: упростить выборку каталога
```

Коммит должен содержать одну логически цельную группу изменений.

## Локальные проверки

Перед запуском команд прочитайте актуальный Makefile. Основные проверки:

| Команда | Назначение |
|---|---|
| `make check` | Строгая валидация Composer, аудит lock-файла, PHP lint, PHP CS Fixer, PHPStan и ESLint |
| `make test-unit` | unit-тесты |
| `make test-integration` | integration-тесты |
| `make test-functional` | functional-тесты |
| `make test-functional-panther` | браузерные тесты Panther |
| `make test-all CONFIRM=testdb` | Полный набор, включая Panther и оба PostgreSQL-контура |
| `make test-reset-password-postgresql CONFIRM=testdb` | Конкурентные операции восстановления пароля |
| `make test-oauth-postgresql CONFIRM=testdb` | Конкурентные изменения OAuth-идентичностей |

Обычные integration-, functional- и Panther-тесты работают только с локальной `var/db_for_test.db`. Перед отдельным запуском этих групп подготовьте базу через `make test-db-reset CONFIRM=testdb`. Используйте защищённые Make-команды: произвольные подключения тестового контура не допускаются. PostgreSQL-тесты конкурентности запускаются только через две отдельные цели выше, в одноразовых сервисах. Никогда не используйте рабочую базу или внешнее подключение для тестов.

Запускайте только проверки, относящиеся к изменению, а полный набор — когда затронуты общие границы приложения или перед финальной проверкой крупного изменения.

## Pull Request

В описании Pull Request укажите:

- что изменено и зачем;
- как проверялось изменение;
- нужны ли ручные шаги;
- затрагивает ли изменение конфигурацию, данные, OAuth, права доступа или другой важный контракт;
- обновлена ли документация, если изменился публичный способ использования проекта.

## Чек-лист

- Нет секретов, реальных учётных данных OAuth, токенов доступа, cookies, идентификаторов сессий или содержимого локальных `.env*`.
- В diff нет посторонних изменений.
- `git diff --check` проходит.
- Выполнены относящиеся к изменению проверки.
- Новые тесты защищают конкретное поведение приложения, а не добавлены ради количества.
- Документация обновлена, если изменился публичный контракт, настройка или способ запуска.
