# OAuth

OAuth в Symfony Shop используется для входа и регистрации через внешний сервис, а также для явной привязки такого аккаунта к уже существующему локальному пользователю. Эти сценарии разделены: совпадение email само по себе не считается доказательством владения локальной учётной записью.

Здесь provider: внешний сервис входа, external ID: идентификатор аккаунта пользователя у этого сервиса, callback: возврат пользователя обратно в приложение после авторизации у provider.

## Поддерживаемые providers

| Provider | Имя в приложении | Поле User |
|---|---|---|
| Google | `google` | `google_id` |
| Yandex | `yandex` | `yandex_id` |
| VKontakte | `vkontakte` | `vkontakte_id` |
| GitHub EN | `github_en` | `github_id` |
| GitHub RU | `github_rus` | `github_id` |
| Facebook | `facebook` | `facebook_id` |
| LinkedIn | `linkedin` | `linkedin_id` |

GitHub EN и GitHub RU используют разные OAuth clients, но один внешний идентификатор `github_id`. Один GitHub-аккаунт не может быть связан с двумя локальными пользователями через разные клиенты.

Mail.ru намеренно не поддерживается: OAuth client и маршруты для него отсутствуют, а `OAUTH_MAILRU_ENABLED` должен оставаться равным `0`.

## Настройка provider

По умолчанию все реализованные providers выключены.

| Provider | Выключатель | Client ID | Client secret |
|---|---|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` | `OAUTH_GOOGLE_ID` | `OAUTH_GOOGLE_SECRET` |
| Yandex | `OAUTH_YANDEX_ENABLED` | `OAUTH_YANDEX_CLIENT_ID` | `OAUTH_YANDEX_CLIENT_SECRET` |
| VKontakte | `OAUTH_VK_ENABLED` | `OAUTH_VK_CLIENT_ID` | `OAUTH_VK_CLIENT_SECRET` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` | `OAUTH_GITHUB_EN_CLIENT_ID` | `OAUTH_GITHUB_EN_CLIENT_SECRET` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` | `OAUTH_GITHUB_RUS_CLIENT_ID` | `OAUTH_GITHUB_RUS_CLIENT_SECRET` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` | `OAUTH_FACEBOOK_CLIENT_ID` | `OAUTH_FACEBOOK_CLIENT_SECRET` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` | `OAUTH_LINKEDIN_CLIENT_ID` | `OAUTH_LINKEDIN_CLIENT_SECRET` |

Пример для `.env.local`:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

Выключатель работает на сервере, а не только управляет видимостью кнопки. При `*_ENABLED=0` новые OAuth start/callback сценарии блокируются до обращения к provider.

Реальные credentials не добавляются в Git. Приоритет `.env.local` и переменных Docker подробно описан в [руководстве по конфигурации](configuration.md).

## Обычный вход и регистрация

### External ID уже связан

Если полученный external ID уже принадлежит локальному пользователю, выполняется вход в этот же аккаунт.

При таком входе приложение не заменяет локальный email данными provider и не перепривязывает OAuth ID. Удалённый локальный пользователь к входу не допускается.

### External ID новый, но email уже есть локально

Вход отклоняется без автоматической привязки.

Приложение:

- не записывает external ID в найденного по email пользователя;
- не выполняет вход в этот аккаунт;
- не создаёт нового пользователя;
- не отправляет регистрационное письмо.

Чтобы связать внешний аккаунт с существующей учётной записью, пользователь должен сначала войти обычным способом и выполнить явную привязку из личного кабинета.

### External ID и email новые

Создаётся новый локальный пользователь:

1. сохраняются email и external ID;
2. `isVerified` остаётся `false`;
3. генерируется случайный внутренний пароль и сохраняется только его хеш;
4. пользователь записывается в базу;
5. после получения ID формируется обычная ссылка подтверждения email;
6. выполняется OAuth-вход нового пользователя.

Provider не может автоматически подтвердить локальную учётную запись своим `email_verified`. Случайный локальный пароль пользователю не отправляется; известный пароль можно установить через обычный reset-password flow.

При стандартном `MAILER_DSN=null://null` внешняя доставка письма не выполняется, даже если сам сценарий подтверждения email отработал успешно.

### Provider не вернул email

Если external ID ещё не связан и provider не вернул непустой email, новый пользователь не создаётся, а вход отклоняется нейтрально.

Ошибки обмена OAuth token или получения профиля также нормализуются до безопасной ошибки приложения без вывода upstream response пользователю.

## Явная привязка к существующему аккаунту

Привязку начинает уже аутентифицированный локальный пользователь.

| Шаг | Что происходит |
|---|---|
| `GET` страницы привязки | Показывается форма подтверждения, данные не меняются |
| `POST` формы | Проверяются текущий пароль и CSRF-токен |
| Переход к provider | Создаётся одноразовое намерение в текущей сессии |
| Callback | Проверяются пользователь, provider, OAuth state и срок жизни намерения |
| Успех | Записывается только external ID выбранного provider |

Намерение хранится в сессии не более 600 секунд и связано с конкретным пользователем и provider. Исходный OAuth state в нём не хранится: сохраняется SHA-256 hash. Намерение одноразовое, поэтому повторный callback отклоняется.

Привязка не ищет пользователя по email и не меняет текущую login session. Если external ID уже занят другим пользователем, связь не создаётся. Финальной границей от гонки остаётся уникальное ограничение базы данных.

## Отвязка

Отвязка тоже выполняется из аутентифицированного личного кабинета.

| Шаг | Что происходит |
|---|---|
| `GET` страницы отвязки | Показывается форма, external ID не меняется |
| `POST` формы | Проверяются текущий пароль и CSRF-токен |
| Успех | Обнуляется только выбранное OAuth-поле |

Поле User выбирается на сервере по допустимому имени provider. Клиент не передаёт имя setter или произвольного поля.

Если provider выключили после привязки, пользователь всё равно может удалить уже существующую связь. Выключатель запрещает новые OAuth-сценарии, но не блокирует безопасную отвязку.

## Маршруты

Обычные OAuth-маршруты находятся под `/{_locale}`, где поддерживаются `ru` и `en`.

| Provider | Start | Callback |
|---|---|---|
| Google | `/{_locale}/connect/google` | `/{_locale}/connect/google/check` |
| Yandex | `/{_locale}/connect/yandex` | `/{_locale}/connect/yandex/check` |
| VKontakte | `/{_locale}/connect/vkontakte` | `/{_locale}/connect/vkontakte/check` |
| GitHub EN | `/{_locale}/connect/github-en` | `/{_locale}/connect/github-en/check` |
| GitHub RU | `/{_locale}/connect/github-ru` | `/{_locale}/connect/github-ru/check` |
| Facebook | `/{_locale}/connect/facebook` | `/{_locale}/connect/facebook/check` |
| LinkedIn | `/{_locale}/connect/linkedin` | `/{_locale}/connect/linkedin/check` |

Эти start/callback routes используются браузерным GET-flow, но текущая YAML-конфигурация не задаёт для них отдельный `methods` constraint на уровне router.

Операции личного кабинета имеют явные методы:

| Операция | Маршрут | Методы |
|---|---|---|
| Привязка | `/{_locale}/profile/oauth/{provider}/link` | `GET`, `POST` |
| Отвязка | `/{_locale}/profile/oauth/{provider}/unlink` | `GET`, `POST` |

Для `{provider}` используются `google`, `yandex`, `vkontakte`, `github_en`, `github_rus`, `facebook` и `linkedin`.

## Уникальность external ID

Поля `google_id`, `yandex_id`, `vkontakte_id`, `github_id`, `facebook_id` и `linkedin_id` защищены уникальными ограничениями Doctrine и базы данных. Один внешний аккаунт не может одновременно принадлежать двум локальным пользователям.
