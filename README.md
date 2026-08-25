# Symfony Shop

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fsymfony--shop-blue.svg?style=flat-square)](https://github.com/yaleksandr89/symfony-shop)
[![CI](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml/badge.svg)](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000.svg?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18.4-4169E1.svg?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED.svg?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Symfony Shop: учебный интернет-магазин на Symfony. В проекте есть каталог товаров, корзина и оформление заказов, личный кабинет, административная часть, API и OAuth-авторизация. Основные страницы рендерятся Twig, а отдельные интерактивные части интерфейса работают на Vue 2.

Проект запускается в Docker Compose: PHP, Composer, Node.js, PostgreSQL и браузерное окружение для Panther не нужно устанавливать на хост. Для разработки и проверок используется единый Makefile.

## Возможности

- каталог категорий и товаров с изображениями, признаками новинок и скидок;
- корзина с проверкой доступности товаров и оформление заказа;
- регистрация, вход, подтверждение email и восстановление пароля;
- личный кабинет пользователя;
- OAuth через Google, Yandex, VKontakte, GitHub, Facebook и LinkedIn;
- отдельные сценарии входа через OAuth, привязки и отвязки внешнего аккаунта;
- административное управление пользователями, категориями, товарами и заказами;
- API на базе API Platform;
- автоматические unit-, integration-, functional- и browser-тесты;
- Docker-backed CI в GitHub Actions.

## Быстрый старт

На хосте нужны Git, Git LFS, Make и Docker с поддержкой Compose.

| Команда | Что делает | Примечание |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Клонирует репозиторий | |
| `cd symfony-shop` | Переходит в каталог проекта | |
| `git lfs install` | Подключает Git LFS для текущего пользователя | Обычно выполняется один раз |
| `git lfs pull` | Загружает Chrome for Testing из LFS | Нужен до сборки PHP-образа |
| `make init` | Создаёт `.env.docker` и локальные каталоги | Не перезаписывает существующий `.env.docker` |
| `make build` | Собирает PHP-образ | В образ входят Chrome и Chromedriver для Panther |
| `make up` | Запускает PHP-FPM, Nginx и PostgreSQL | |
| `make composer-install` | Устанавливает PHP-зависимости из `composer.lock` | Composer на хосте не нужен |
| `make npm-install` | Устанавливает npm-зависимости из `package-lock.json` | Node.js на хосте не нужен |
| `make assets-build` | Собирает frontend assets | |
| `make migrate` | Применяет Doctrine migrations | |
| `make demo-init` | Создаёт демонстрационные данные | Только для локальной `dev`/`test` среды |

После запуска приложение по умолчанию доступно по адресу [http://localhost:8080](http://localhost:8080).

> [!IMPORTANT]
> Проект использует Git LFS для `bin/chrome-linux64-150.0.7871.46.zip`. Если вместо ZIP в рабочей копии остался LFS pointer, `make build` завершится ошибкой. Проверка артефакта и диагностика описаны в [руководстве по запуску](docs/getting-started.md).

> [!WARNING]
> `make demo-init` пересоздаёт демонстрационные заказы. Не запускайте его в локальной базе, где есть нужные вам данные.

Подробный первый запуск, работа с Git LFS и управление контейнерами собраны в [руководстве по запуску](docs/getting-started.md).

## OAuth

OAuth-вход и привязка внешнего аккаунта к существующему пользователю: разные операции. Совпадение email у провайдера не даёт права автоматически связать OAuth-аккаунт с уже существующей локальной учётной записью.

Для привязки пользователь сначала входит обычным способом, затем подтверждает текущий пароль и явно начинает OAuth-сценарий из личного кабинета. Отвязка также защищена паролем и CSRF-токеном.

Поддерживаемые провайдеры, переменные окружения, маршруты и правила безопасности подробно описаны в [руководстве по OAuth](docs/oauth.md). Общие правила хранения локальных настроек и секретов находятся в [руководстве по конфигурации](docs/configuration.md).

## Как устроен проект

```text
Browser
  ↓
Nginx
  ↓
Symfony routes / controllers
  ├─ Twig → HTML
  └─ API Platform → JSON API
  ↓
Application services / Doctrine
  ↓
PostgreSQL
```

Основной код сгруппирован по областям `Account`, `Catalog` и `Commerce`. Административная часть, OAuth и SEO оформлены внутренними Symfony bundle. Vue 2 используется для отдельных интерактивных компонентов, а не как отдельное SPA.

Карта каталогов, маршрутизация, API Platform, Doctrine и frontend-границы разобраны в [описании архитектуры](docs/architecture.md).

## Проверки

| Команда | Что делает | Примечание |
|---|---|---|
| `make check` | Запускает ESLint, PHP-CS-Fixer check и PHPStan | Тесты сюда не входят |
| `make test-unit` | Запускает unit-тесты | |
| `make test-integration` | Запускает integration-тесты | |
| `make test-functional` | Запускает functional-тесты | |
| `make test-functional-panther` | Запускает browser-тесты через Panther | Chrome уже находится в PHP-образе |
| `make test-all CONFIRM=testdb` | Запускает полный тестовый набор | Пересоздаёт тестовую БД |
| `make coverage CONFIRM=testdb` | Показывает PHP/PHPUnit coverage в терминале | Panther не входит в coverage |
| `make coverage-html CONFIRM=testdb` | Создаёт HTML и Clover отчёты | `var/coverage/html`, `var/coverage/clover.xml` |

Coverage используется как инженерная диагностика, а не как публичный показатель качества. Полный набор Make-команд, устройство тестовой базы и различия между локальными проверками и CI приведены в [руководстве по разработке](docs/development.md).

## Coming Soon

Следующий крупный шаг: переход frontend-взаимодействия на Inertia.js и Vue 3. После этого планируется существенное расширение административной части.

## Лицензия

Проект распространяется по лицензии [MIT](LICENSE.md).

---

<p align="center">
  Если проект оказался полезен, поставьте звезду на GitHub: так его будет проще найти другим разработчикам. 🤘
</p>
