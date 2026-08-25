# OAuth

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../oauth.md) | [English](../en/oauth.md) | [Español](../es/oauth.md) | **中文** | [Français](../fr/oauth.md) | [Deutsch](../de/oauth.md) |


Symfony Shop 使用 OAuth 通过外部服务完成登录和注册，也支持把外部账户明确绑定到已有本地用户。这些流程彼此独立：仅仅 email 相同，并不能证明用户拥有已有的本地账户。

本文中的术语：

- **provider** — 外部登录服务，例如 Google 或 GitHub；
- **外部 ID** — 用户在 provider 处的账户标识；
- **callback** — 用户在 provider 完成授权后返回应用；
- **state** — 随机 token，用来把 OAuth 流程开始阶段与 callback 关联起来。

## 支持的 Provider

| Provider | 应用中的名称 | `User` 字段 |
|---|---|---|
| Google | `google` | `google_id` |
| Yandex | `yandex` | `yandex_id` |
| VKontakte | `vkontakte` | `vkontakte_id` |
| GitHub EN | `github_en` | `github_id` |
| GitHub RU | `github_rus` | `github_id` |
| Facebook | `facebook` | `facebook_id` |
| LinkedIn | `linkedin` | `linkedin_id` |

GitHub EN 和 GitHub RU 使用不同的 OAuth client，但共用同一个外部标识 `github_id`。同一个 GitHub 账户不能通过不同 client 绑定到两个本地用户。

项目明确不支持 Mail.ru：没有对应 OAuth client 和路由，并且 `OAUTH_MAILRU_ENABLED` 必须保持 `0`。

## Provider 配置

所有已经实现的 provider 默认关闭。

| Provider | 开关 | Client ID | Client secret |
|---|---|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` | `OAUTH_GOOGLE_ID` | `OAUTH_GOOGLE_SECRET` |
| Yandex | `OAUTH_YANDEX_ENABLED` | `OAUTH_YANDEX_CLIENT_ID` | `OAUTH_YANDEX_CLIENT_SECRET` |
| VKontakte | `OAUTH_VK_ENABLED` | `OAUTH_VK_CLIENT_ID` | `OAUTH_VK_CLIENT_SECRET` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` | `OAUTH_GITHUB_EN_CLIENT_ID` | `OAUTH_GITHUB_EN_CLIENT_SECRET` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` | `OAUTH_GITHUB_RUS_CLIENT_ID` | `OAUTH_GITHUB_RUS_CLIENT_SECRET` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` | `OAUTH_FACEBOOK_CLIENT_ID` | `OAUTH_FACEBOOK_CLIENT_SECRET` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` | `OAUTH_LINKEDIN_CLIENT_ID` | `OAUTH_LINKEDIN_CLIENT_SECRET` |

`.env.local` 示例：

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

开关在服务器端生效，并不只是控制按钮是否显示。设置 `*_ENABLED=0` 时，新的登录、注册和绑定流程会在联系 provider 之前被阻止。

真实凭据不能加入 Git。`.env.local` 与 Docker 变量的优先级见[配置指南](configuration.md)。

## 普通登录与注册

主要情况可以放在一起比较：

| 情况 | 结果 | 不会发生什么 |
|---|---|---|
| 外部 ID 已绑定 | 登录同一个本地账户 | 本地 email 不会被 provider 数据替换，绑定关系不会被重写 |
| 外部 ID 是新的，但本地已有相同 email | 使用中性错误拒绝登录 | 不会自动绑定、不会登录找到的账户、不会创建用户，也不会发送注册邮件 |
| 外部 ID 和 email 都是新的 | 创建新的未验证本地用户并完成 OAuth 登录 | provider 不会自动验证本地 email，也不会把随机密码发送给用户 |
| Provider 未返回 email | 使用中性错误拒绝登录 | 不创建用户，也不修改数据 |

如果外部 ID 已经绑定到一个被删除的本地用户，也会拒绝登录。

对于新用户，应用保存 email 和外部 ID，保持 `isVerified=false`，生成随机内部密码，并只保存其 hash。用户写入后会启动正常的 email 验证流程。用户可以通过密码重置设置一个自己知道的本地密码。

注册邮件通过 Messenger `async` 处理。当前 Docker Compose 没有常驻 worker，因此在本地验证该流程时，需要另外运行 `make console CMD='messenger:consume async -vv'`。详见[邮件与 Messenger](configuration.md)。

OAuth token 交换或 profile 获取错误会转成安全的应用错误，不会把 provider 响应直接显示给用户。

## 明确绑定到已有账户

绑定流程由已经认证的本地用户发起。

| 步骤 | 发生的事情 |
|---|---|
| 绑定页面 `GET` | 显示确认表单，不修改数据 |
| 表单 `POST` | 验证当前密码和 CSRF token |
| 跳转到 provider | 在当前 session 中创建一次性的绑定 intent |
| Provider callback | 验证用户、provider、OAuth `state` 和 intent 有效期 |
| 成功 | 只写入所选 provider 的外部 ID |

Intent 在 session 中最多保留 600 秒，并绑定到具体用户和 provider。原始 OAuth `state` 不会直接保存，只保存其 SHA-256 hash。Intent 只能使用一次，因此重复 callback 会被拒绝。

绑定流程不会根据 email 查找用户，也不会改变当前登录 session。如果外部 ID 已属于另一个用户，则不会创建绑定。防止并发写入的最后一道边界是数据库 unique constraint。

## 解绑

解绑同样在已认证的用户账户中进行。

| 步骤 | 发生的事情 |
|---|---|
| 解绑页面 `GET` | 显示表单，外部 ID 不变 |
| 表单 `POST` | 验证当前密码和 CSRF token |
| 成功 | 只清空所选 OAuth 字段 |

`User` 字段由服务器根据允许的 provider 名称选择。客户端不会传入 setter 方法名或任意字段名。

如果 provider 在绑定后被关闭，用户仍然可以删除已有绑定。开关阻止新的 OAuth 流程，但不会阻止安全解绑。

## 路由

普通 OAuth 路由位于 `/{_locale}` 下，目前支持 `ru` 和 `en`。

| Provider | 开始 OAuth 流程 | Callback |
|---|---|---|
| Google | `/{_locale}/connect/google` | `/{_locale}/connect/google/check` |
| Yandex | `/{_locale}/connect/yandex` | `/{_locale}/connect/yandex/check` |
| VKontakte | `/{_locale}/connect/vkontakte` | `/{_locale}/connect/vkontakte/check` |
| GitHub EN | `/{_locale}/connect/github-en` | `/{_locale}/connect/github-en/check` |
| GitHub RU | `/{_locale}/connect/github-ru` | `/{_locale}/connect/github-ru/check` |
| Facebook | `/{_locale}/connect/facebook` | `/{_locale}/connect/facebook/check` |
| LinkedIn | `/{_locale}/connect/linkedin` | `/{_locale}/connect/linkedin/check` |

浏览器 GET 流程使用这些路由，但当前 YAML 配置没有在 Symfony Router 层为它们单独限制 HTTP method。

用户账户操作具有明确 method：

| 操作 | 路由 | Methods |
|---|---|---|
| 绑定 | `/{_locale}/profile/oauth/{provider}/link` | `GET`, `POST` |
| 解绑 | `/{_locale}/profile/oauth/{provider}/unlink` | `GET`, `POST` |

`{provider}` 可取 `google`、`yandex`、`vkontakte`、`github_en`、`github_rus`、`facebook` 和 `linkedin`。

## 外部 ID 唯一性

字段 `google_id`、`yandex_id`、`vkontakte_id`、`github_id`、`facebook_id` 和 `linkedin_id` 在 Doctrine 与数据库层都受 unique constraint 保护。同一个外部账户不能同时属于两个本地用户。
