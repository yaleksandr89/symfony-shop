# 配置

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/configuration.md) | [English](../ru/configuration.md) | [Español](../ru/configuration.md) | **中文** | [Français](../ru/configuration.md) | [Deutsch](../ru/configuration.md) |


项目分别保存 Symfony 通用设置、Docker 参数、本地 secret 和测试覆盖配置。一个重要细节是：Docker Compose 传入 PHP 容器的值，比 Symfony Dotenv 文件加载的值优先级更高。

## 环境文件

| 文件 | 用途 | Git |
|---|---|---|
| `.env` | 通用安全的 Symfony 设置和本地默认值 | 跟踪 |
| `.env.docker` | Docker Compose 与本地 PostgreSQL 参数 | 忽略 |
| `.env.local` | 开发者机器专用 secret 和设置 | 忽略 |
| `.env.test` | 自动化测试设置 | 跟踪 |

## 变量优先级

从高到低：

1. 进程环境变量，包括 Docker Compose 从 `.env.docker` 传入的值；
2. `.env.<环境>.local`；
3. `.env.<环境>`；
4. `.env.local`；
5. `.env`。

文件名 `.env.docker` 本身并不会赋予特殊优先级。优先级来自 Docker Compose 把这些值作为进程环境变量传入 PHP 容器。

实际示例：

```text
.env.docker
PANTHER_WEB_SERVER_PORT=9080

.env.local
PANTHER_WEB_SERVER_PORT=9999

→ PHP 容器中使用 9080
```

相反，如果 Docker 没有传入同名变量，`.env.local` 中的 OAuth 凭据会被使用。

修改 `.env.docker` 后，请通过 `make down` 和 `make up` 重新创建容器。修改 `.env` 或 `.env.local` 通常不需要这么做。

## `.env`

这里包含应用通用参数：`APP_ENV`、`APP_DEBUG`、`APP_SECRET`、`APP_TIMEZONE`、`DATABASE_URL`、`MAILER_DSN`、`MESSENGER_TRANSPORT_DSN`、应用地址、CORS 以及 OAuth 开关。

`.env` 中的值属于本地项目默认值，不用于 production。

## `.env.docker`

`make init` 会根据 `.env.docker.example` 创建此文件，并写入宿主机用户的 UID/GID。

主要参数：

| 变量 | 用途 | 默认值 |
|---|---|---|
| `HOST_UID`, `HOST_GID` | 容器创建文件的所有者 | 由 `make init` 填写 |
| `APP_PORT` | 宿主机上的 Nginx HTTP 端口 | `8080` |
| `POSTGRES_DB` | 本地 PostgreSQL 数据库 | `s_shop` |
| `POSTGRES_USER` | 本地 PostgreSQL 用户 | `s_shop` |
| `POSTGRES_PASSWORD` | 本地 PostgreSQL 密码 | 演示值 |
| `PANTHER_WEB_SERVER_HOST` | Panther 内置 Web 服务器 host | `php` |
| `PANTHER_WEB_SERVER_PORT` | Panther 内置 Web 服务器端口 | `9080` |

Compose 把 `.env.docker` 作为 PHP 容器的 `env_file`，因此这些值会成为进程环境变量。

## `.env.local`

请在 `.env.local` 中保存 OAuth 凭据、真实的 `MAILER_DSN`、本地 `ADMIN_EMAIL` 以及其他当前机器专用 secret。

不要把此文件加入 Git，也不要公开其内容。在 `test` 环境中，Symfony 不会加载 `.env.local`。

## `.env.test`

测试环境使用独立 SQLite 数据库 `var/db_for_test.db`、Panther 设置、中性的 Mailer/Messenger transport，并关闭 OAuth provider。

## 邮件与 Messenger

默认值：

```dotenv
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default
```

`MAILER_DSN=null://null` 表示本地环境不会通过外部 SMTP 服务发送邮件。在 HTTP 请求中同步创建的邮件可以在 Symfony Profiler 的 Mailer 面板中查看。

如果需要真实 SMTP transport，请在 `.env.local` 中配置自己的 `MAILER_DSN`，例如：

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger 已经把注册和密码重置消息路由到 `async` transport，但 Docker Compose 当前不会启动常驻 worker。消息会留在 Doctrine 队列中，直到手动启动 worker：

| 命令 | 作用 |
|---|---|
| `make console CMD='messenger:consume async -vv'` | 在 PHP 容器中启动 `async` transport worker |

测试注册和密码重置时尤其要注意：没有 worker 时，相应异步消息不会被处理。后续计划加入带 Web 界面的本地邮件服务以及常驻 Messenger worker。

## PostgreSQL

Docker Compose 使用 PostgreSQL 18.4。PHP 容器通过服务名 `postgres` 连接数据库；PHP 容器中的 `localhost` 并不指向 PostgreSQL。

PostgreSQL 只通过 `127.0.0.1:5433` 暴露给宿主机。

`DATABASE_URL` 根据 `POSTGRES_*` 组合并由 Doctrine 使用。完全重建本地 PostgreSQL volume 使用破坏性命令 `make postgres-reinit CONFIRM=postgres18`；详见[开发指南](development.md)。

## OAuth

所有 OAuth provider 默认关闭。启用 provider 与填写凭据是两个独立设置：既需要 `*_ENABLED=1`，也需要有效的 Client ID 和 Client Secret。

| Provider | 开关 |
|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` |
| Yandex | `OAUTH_YANDEX_ENABLED` |
| VKontakte | `OAUTH_VK_ENABLED` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` |
| Mail.ru | `OAUTH_MAILRU_ENABLED`：必须保持 `0` |

本地 Google 配置示例：

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

其他凭据名称、路由和行为规则见 [OAuth 指南](oauth.md)。真实 key、access token、authorization code 和外部 ID 不应加入文档或 Git。

## Panther

PHP 镜像已经包含 Chrome for Testing 和 Chromedriver。测试不需要宿主机浏览器，也不需要 Java。

Docker 使用 `PANTHER_WEB_SERVER_HOST=php` 和 `PANTHER_WEB_SERVER_PORT=9080`，`.env.test` 还会添加测试专用设置和错误截图目录。

获取 Chrome 归档的方法见[项目启动指南](getting-started.md)，浏览器测试见[开发指南](development.md)。
