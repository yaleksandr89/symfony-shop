# Symfony Shop

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fsymfony--shop-blue.svg?style=flat-square)](https://github.com/yaleksandr89/symfony-shop)
[![CI](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml/badge.svg)](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000.svg?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18.4-4169E1.svg?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED.svg?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Symfony Shop — учебный интернет-магазин на Symfony. В проекте реализованы каталог товаров, корзина и оформление заказов, личный кабинет, административная часть, API и вход через OAuth. Основные страницы формируются Twig, а Vue 2 используется для отдельных интерактивных элементов интерфейса.

Поддерживаемая среда локальной разработки построена на Docker Compose. PHP, Composer, Node.js, PostgreSQL и Chrome for Testing запускаются внутри контейнеров или устанавливаются в Docker-образ, а основные операции собраны в Makefile. Отдельный сценарий запуска с PHP, Composer и PostgreSQL, установленными непосредственно на хосте, проектом не поддерживается и в CI не проверяется.

## Возможности

- каталог категорий и товаров с изображениями, новинками и скидками;
- корзина с проверкой доступности товаров и оформление заказа;
- регистрация, вход, подтверждение email и восстановление пароля;
- личный кабинет пользователя;
- OAuth через Google, Yandex, VKontakte, GitHub, Facebook и LinkedIn;
- отдельные сценарии входа через OAuth, привязки и отвязки внешнего аккаунта;
- административное управление пользователями, категориями, товарами и заказами;
- API на базе API Platform;
- unit-, integration-, functional- и браузерные тесты;
- CI в GitHub Actions на том же Docker-окружении.

## Быстрый старт

На хосте нужны Git, Make и Docker с поддержкой Compose. Git LFS рекомендуется для обычного клонирования репозитория; получить большой браузерный архив можно и без него — варианты описаны ниже.

> [!NOTE]
> Make — обычная консольная утилита для Unix-подобных систем. На Linux и macOS проект можно запускать напрямую из терминала. На Windows рекомендуемый вариант — WSL2 вместе с Docker Desktop.

| Команда | Что делает | Примечание |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Клонирует репозиторий | |
| `cd symfony-shop` | Переходит в каталог проекта | |
| `git lfs install` | Подключает Git LFS | Нужен только при сценарии через Git LFS |
| `git lfs pull` | Загружает Chrome for Testing | Выполните до `make build` |
| `make init` | Создаёт `.env.docker` и локальные каталоги | Не перезаписывает существующий `.env.docker` |
| `make build` | Собирает PHP-образ | В образ входят Chrome и Chromedriver для Panther |
| `make up` | Запускает PHP-FPM, Nginx и PostgreSQL | |
| `make composer-install` | Устанавливает PHP-зависимости из `composer.lock` | Composer на хосте не нужен |
| `make npm-install` | Устанавливает зависимости из `package-lock.json` | Node.js на хосте не нужен |
| `make assets-build` | Собирает ресурсы фронтенда | |
| `make migrate` | Применяет Doctrine migrations | |
| `make demo-init` | Создаёт демонстрационные данные | Только для локальной `dev`/`test` среды |

После запуска приложение по умолчанию доступно по адресу [http://localhost:8080](http://localhost:8080).

> [!IMPORTANT]
> Проект использует зафиксированный Chrome for Testing `150.0.7871.46`. Рекомендуемый способ получить архив — `git lfs pull`. Начиная с версии `v3.0.0`, ZIP-архив проекта можно скачать со страницы [Releases](https://github.com/yaleksandr89/symfony-shop/releases). В архив уже включён Chrome for Testing, поэтому Git LFS для такого способа не нужен. Также нужную версию Chrome for Testing можно скачать напрямую из официального источника. Точные ссылки, имя файла и SHA-256 приведены в [руководстве по запуску](docs/getting-started.md#git-lfs-и-chrome-for-testing).

> [!IMPORTANT]
> Значения из `.env.docker` передаются в PHP-контейнер как переменные окружения процесса. Если один и тот же ключ задан и там, и в `.env.local`, значение из `.env.docker` имеет приоритет. Подробная схема описана в [руководстве по конфигурации](docs/configuration.md#приоритет-переменных).

> [!WARNING]
> `make demo-init` пересоздаёт демонстрационные заказы. Не запускайте его в локальной базе, где есть нужные вам данные.

Подробный первый запуск, три способа получить Chrome for Testing и управление контейнерами собраны в [руководстве по запуску](docs/getting-started.md).

## Почта и очередь сообщений

По умолчанию `MAILER_DSN=null://null`, поэтому приложение не отправляет письма во внешний SMTP-сервис. Письма, отправленные синхронно во время HTTP-запроса, можно посмотреть в панели Mailer Symfony Profiler.

Регистрация и восстановление пароля используют транспорт Messenger `async`. Маршрутизация в очередь уже настроена, но постоянный обработчик очереди в Docker Compose сейчас не запускается, поэтому такие сообщения обрабатываются только после ручного запуска:

```text
make console CMD='messenger:consume async -vv'
```

Настройка транспорта, почты и локальных секретов описана в [руководстве по конфигурации](docs/configuration.md#почта-и-messenger).

## OAuth

Вход через OAuth и привязка внешнего аккаунта к существующему пользователю — разные операции. Совпадение email у провайдера не даёт права автоматически связать внешний аккаунт с уже существующей локальной учётной записью.

Для привязки пользователь сначала входит обычным способом, затем подтверждает текущий пароль и явно начинает OAuth-сценарий из личного кабинета. Отвязка также защищена текущим паролем и CSRF-токеном.

Поддерживаемые провайдеры, переменные окружения, маршруты и правила безопасности подробно описаны в [руководстве по OAuth](docs/oauth.md). Общие правила хранения локальных настроек и секретов находятся в [руководстве по конфигурации](docs/configuration.md).

## Как устроен проект

```text
Браузер
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
  ↓
Прикладные сервисы / Doctrine
  ↓
PostgreSQL
```

Основной код сгруппирован по областям `Account`, `Catalog` и `Commerce`. Административная часть, OAuth и SEO оформлены как внутренние Symfony-бандлы. Vue 2 используется для отдельных интерактивных компонентов, а не как самостоятельное SPA.

Карта каталогов, маршрутизация, API Platform, Doctrine и границы фронтенда разобраны в [описании архитектуры](docs/architecture.md).

## Проверки

| Команда | Что делает | Примечание |
|---|---|---|
| `make check` | Запускает ESLint, проверку PHP-CS-Fixer и PHPStan | Тесты сюда не входят |
| `make test-unit` | Запускает unit-тесты | |
| `make test-integration` | Запускает integration-тесты | |
| `make test-functional` | Запускает functional-тесты | |
| `make test-functional-panther` | Запускает браузерные тесты через Panther | Chrome уже находится в PHP-образе |
| `make test-all CONFIRM=testdb` | Запускает полный набор тестов | Пересоздаёт тестовую БД |
| `make coverage CONFIRM=testdb` | Показывает покрытие PHP/PHPUnit в терминале | Panther не входит в отчёт |
| `make coverage-html CONFIRM=testdb` | Создаёт HTML- и Clover-отчёты | `var/coverage/html`, `var/coverage/clover.xml` |

Полный список Make-команд, устройство тестовой базы и состав CI приведены в [руководстве по разработке](docs/development.md).

## В планах

1. **Локальная почтовая среда.** Добавить отдельный почтовый сервис с веб-интерфейсом для просмотра писем и постоянный обработчик очереди Messenger, чтобы сообщения транспорта `async` обрабатывались автоматически.
2. **Inertia.js и Vue 3.** Перевести взаимодействие серверной и клиентской частей на Inertia.js и Vue 3. Заодно хочу пересмотреть локализацию: в зависимости от объёма изменений, возможно, получится отказаться от обязательного префикса `/{_locale}` в URL. Это решу уже при проектировании нового фронтенда.
3. **Административная часть.** После миграции фронтенда существенно расширить возможности управления магазином из административного интерфейса.

## Обратная связь

- воспроизводимые ошибки — [GitHub Issues](https://github.com/yaleksandr89/symfony-shop/issues);
- вопросы и идеи — [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions).

---

<p align="center">
  Если проект оказался полезен, поставьте звезду на GitHub — так его будет проще найти другим разработчикам. 🤘
</p>
