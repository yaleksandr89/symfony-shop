# Запуск проекта

Основной сценарий разработки полностью работает через Docker Compose. На хосте не нужны PHP, Composer, Node.js, PostgreSQL, Java или браузерное окружение для Panther.

## Требования

Для первого запуска понадобятся:

- Git;
- Git LFS;
- Make;
- Docker с поддержкой Compose.

## Первый запуск

| Команда | Что делает | Примечание |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Клонирует репозиторий | |
| `cd symfony-shop` | Переходит в каталог проекта | |
| `git lfs install` | Подключает Git LFS | Обычно выполняется один раз для пользователя |
| `git lfs pull` | Загружает Chrome for Testing | Выполните до `make build` |
| `make init` | Создаёт `.env.docker` и локальные каталоги | Использует `.env.docker.example` и UID/GID пользователя хоста |
| `make build` | Собирает PHP-образ | |
| `make up` | Запускает `php`, `nginx` и `postgres` | |
| `make composer-install` | Устанавливает PHP-зависимости | Использует `composer.lock` |
| `make npm-install` | Устанавливает frontend-зависимости | Использует `package-lock.json` |
| `make assets-build` | Собирает frontend assets | |
| `make migrate` | Применяет Doctrine migrations | |
| `make demo-init` | Инициализирует демонстрационные данные | Только для локальных `dev`/`test` данных |

При стандартной конфигурации приложение доступно по адресу [http://localhost:8080](http://localhost:8080). Порт можно изменить через `APP_PORT` в `.env.docker`.

> [!WARNING]
> `make demo-init` пересоздаёт демонстрационные заказы. Используйте эту команду только для базы, данные которой можно заменить.

## Git LFS и Chrome for Testing

Panther использует Chrome for Testing, который входит в PHP-образ. Архив браузера хранится через Git LFS, а Chromedriver: обычным Git-файлом.

| Артефакт | Путь | Хранение |
|---|---|---|
| Chrome for Testing | `bin/chrome-linux64-150.0.7871.46.zip` | Git LFS |
| Chromedriver | `bin/drivers/chromedriver` | обычный Git |

Для текущего Chrome archive подтверждены:

| Проверка | Ожидаемое значение |
|---|---|
| Размер | `186933179` байт |
| SHA-256 | `ad115a7498a17f53f6ed0914458326c6516addc756224db14c32184a9b1ab078` |

Проверить рабочую копию можно так:

| Команда | Что проверяет |
|---|---|
| `git lfs ls-files` | Chrome archive зарегистрирован в Git LFS |
| `wc -c bin/chrome-linux64-150.0.7871.46.zip` | Размер файла |
| `sha256sum bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 |
| `unzip -tq bin/chrome-linux64-150.0.7871.46.zip` | Целостность ZIP |

Если файл имеет размер около сотни байт и начинается со строки `version https://git-lfs.github.com/spec/v1`, в рабочей копии остался LFS pointer. Выполните `git lfs pull` и повторите проверку.

Ручная загрузка Chrome пока не описывается как поддерживаемый fallback: для неё отдельно должны быть подтверждены официальный URL именно этого зафиксированного артефакта и полный smoke-сценарий. Рекомендуемый путь проекта: Git LFS.

## Локальная конфигурация

`make init` создаёт `.env.docker` из `.env.docker.example`, подставляет текущие `HOST_UID` и `HOST_GID` и создаёт каталоги `var/cache`, `var/log` и `public/uploads`.

Если `.env.docker` уже существует, команда его не перезаписывает. Локальные секреты приложения и OAuth credentials храните не в `.env.docker`, а в `.env.local`. Подробно слои окружения описаны в [руководстве по конфигурации](configuration.md).

## Управление Docker

| Команда | Что делает | Примечание |
|---|---|---|
| `make ps` | Показывает контейнеры проекта | |
| `make restart php` | Перезапускает PHP | Также доступны `nginx` и `postgres` |
| `make log php` | Показывает журнал PHP в реальном времени | Также доступны `nginx` и `postgres` |
| `make log-all` | Показывает журналы всех сервисов | |
| `make in php` | Открывает shell PHP-контейнера | Пользователь `app` |
| `make in node` | Открывает одноразовый Node-контейнер | Профиль `tools` |
| `make down` | Останавливает окружение | PostgreSQL volume сохраняется |

После изменения `.env.docker` контейнеры нужно пересоздать через `make down` и `make up`. Изменения `.env` и `.env.local` обычно не требуют пересоздания контейнеров.

## Первичная диагностика

| Команда | Что проверяет |
|---|---|
| `make check-env` | Наличие `.env.docker` |
| `make config` | Итоговую конфигурацию Docker Compose |
| `make ps` | Состояние контейнеров |
| `make console CMD=about` | Запуск Symfony Console внутри PHP-контейнера |

Если Docker build падает на распаковке Chrome archive, сначала проверьте Git LFS и ZIP командами из раздела выше.

Ежедневные команды разработки, тесты, coverage и CI описаны в [руководстве по разработке](development.md).
