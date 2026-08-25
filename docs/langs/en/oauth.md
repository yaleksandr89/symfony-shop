# OAuth

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../oauth.md) | **English** | [Español](../es/oauth.md) | [中文](../zh/oauth.md) | [Français](../fr/oauth.md) | [Deutsch](../de/oauth.md) |


Symfony Shop uses OAuth for login and registration through external services and for explicitly linking an external account to an existing local user. These flows are separate: a matching email address alone is not considered proof of ownership of a local account.

Terminology used in this document:

- **provider** — an external login service such as Google or GitHub;
- **external ID** — the user's account identifier at the provider;
- **callback** — the user's return to the application after authorization at the provider;
- **state** — a random token binding the beginning of an OAuth flow to the callback.

## Supported providers

| Provider | Application name | `User` field |
|---|---|---|
| Google | `google` | `google_id` |
| Yandex | `yandex` | `yandex_id` |
| VKontakte | `vkontakte` | `vkontakte_id` |
| GitHub EN | `github_en` | `github_id` |
| GitHub RU | `github_rus` | `github_id` |
| Facebook | `facebook` | `facebook_id` |
| LinkedIn | `linkedin` | `linkedin_id` |

GitHub EN and GitHub RU use separate OAuth clients but the same external identifier, `github_id`. One GitHub account cannot be linked to two local users through different clients.

Mail.ru is intentionally unsupported: no OAuth client or routes exist for it, and `OAUTH_MAILRU_ENABLED` must remain `0`.

## Provider configuration

All implemented providers are disabled by default.

| Provider | Switch | Client ID | Client secret |
|---|---|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` | `OAUTH_GOOGLE_ID` | `OAUTH_GOOGLE_SECRET` |
| Yandex | `OAUTH_YANDEX_ENABLED` | `OAUTH_YANDEX_CLIENT_ID` | `OAUTH_YANDEX_CLIENT_SECRET` |
| VKontakte | `OAUTH_VK_ENABLED` | `OAUTH_VK_CLIENT_ID` | `OAUTH_VK_CLIENT_SECRET` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` | `OAUTH_GITHUB_EN_CLIENT_ID` | `OAUTH_GITHUB_EN_CLIENT_SECRET` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` | `OAUTH_GITHUB_RUS_CLIENT_ID` | `OAUTH_GITHUB_RUS_CLIENT_SECRET` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` | `OAUTH_FACEBOOK_CLIENT_ID` | `OAUTH_FACEBOOK_CLIENT_SECRET` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` | `OAUTH_LINKEDIN_CLIENT_ID` | `OAUTH_LINKEDIN_CLIENT_SECRET` |

Example for `.env.local`:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

The switch is enforced server-side and does not merely control button visibility. With `*_ENABLED=0`, new login, registration, and linking flows are blocked before contacting the provider.

Real credentials must not be committed. `.env.local` and Docker variable precedence are described in the [configuration guide](configuration.md).

## Normal login and registration

The main cases are easier to compare together:

| Situation | Result | What does not happen |
|---|---|---|
| The external ID is already linked | The same local account is logged in | the local email is not replaced with provider data and the link is not rewritten |
| The external ID is new, but the same email already exists locally | Login is rejected with a neutral error | no automatic linking, login to the found account, user creation, or registration email |
| Both external ID and email are new | A new unverified local user is created and OAuth login succeeds | the provider does not automatically verify the local email and no random password is sent to the user |
| The provider returns no email | Login is rejected with a neutral error | no user is created and no data is changed |

If the external ID is already linked to a deleted local user, login is also rejected.

For a new user, the application stores the email and external ID, keeps `isVerified=false`, generates a random internal password, and stores only its hash. After persisting the user, the normal email-verification flow starts. The user can set a known local password through password reset.

The registration email is processed through Messenger `async`. Docker Compose currently has no permanent worker, so local verification of this scenario requires running `make console CMD='messenger:consume async -vv'` separately. See the [mail and Messenger section](configuration.md).

OAuth token-exchange or profile-loading errors are converted into a safe application error without exposing the provider response to the user.

## Explicit linking to an existing account

Linking is started by an already authenticated local user.

| Step | What happens |
|---|---|
| `GET` link page | A confirmation form is shown; no data changes |
| `POST` form | Current password and CSRF token are verified |
| Redirect to provider | A one-time linking intent is created in the current session |
| Provider callback | User, provider, OAuth `state`, and intent lifetime are verified |
| Success | Only the selected provider's external ID is written |

The intent remains in the session for at most 600 seconds and is bound to the specific user and provider. The original OAuth `state` is not stored in it; only its SHA-256 hash is kept. The intent is single-use, so replaying the provider callback is rejected.

Linking does not look users up by email and does not change the current login session. If the external ID is already owned by another user, no link is created. The final protection against concurrent writes is the database unique constraint.

## Unlinking

Unlinking is also performed from an authenticated user account.

| Step | What happens |
|---|---|
| `GET` unlink page | A form is shown; the external ID is unchanged |
| `POST` form | Current password and CSRF token are verified |
| Success | Only the selected OAuth field is cleared |

The `User` field is selected server-side from an allowed provider name. The client does not send a setter method name or an arbitrary field name.

If a provider is disabled after linking, the user can still remove the existing link. The switch blocks new OAuth flows but does not prevent safe unlinking.

## Routes

Normal OAuth routes live under `/{_locale}`, where `ru` and `en` are supported.

| Provider | Start OAuth flow | Callback |
|---|---|---|
| Google | `/{_locale}/connect/google` | `/{_locale}/connect/google/check` |
| Yandex | `/{_locale}/connect/yandex` | `/{_locale}/connect/yandex/check` |
| VKontakte | `/{_locale}/connect/vkontakte` | `/{_locale}/connect/vkontakte/check` |
| GitHub EN | `/{_locale}/connect/github-en` | `/{_locale}/connect/github-en/check` |
| GitHub RU | `/{_locale}/connect/github-ru` | `/{_locale}/connect/github-ru/check` |
| Facebook | `/{_locale}/connect/facebook` | `/{_locale}/connect/facebook/check` |
| LinkedIn | `/{_locale}/connect/linkedin` | `/{_locale}/connect/linkedin/check` |

These routes are used by the browser GET flow, but the current YAML configuration does not define separate HTTP-method restrictions for them at the Symfony Router level.

User-account operations have explicit methods:

| Operation | Route | Methods |
|---|---|---|
| Link | `/{_locale}/profile/oauth/{provider}/link` | `GET`, `POST` |
| Unlink | `/{_locale}/profile/oauth/{provider}/unlink` | `GET`, `POST` |

For `{provider}`, valid values are `google`, `yandex`, `vkontakte`, `github_en`, `github_rus`, `facebook`, and `linkedin`.

## External ID uniqueness

The fields `google_id`, `yandex_id`, `vkontakte_id`, `github_id`, `facebook_id`, and `linkedin_id` are protected by unique constraints in Doctrine and the database. One external account cannot belong to two local users at the same time.
