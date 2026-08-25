# 架构

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../architecture.md) | [English](../en/architecture.md) | [Español](../es/architecture.md) | **中文** | [Français](../fr/architecture.md) | [Deutsch](../de/architecture.md) |


Symfony Shop 是一个单体 Symfony 应用，包含服务器渲染页面、管理后台和 API。代码按应用领域组织，路由集中在 YAML 文件中，因此无需启动应用，也能从 URL 找到对应 controller 或 API resource。

## 总体结构

```text
浏览器
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
          ↓
应用服务 / handler
          ↓
Doctrine ORM
          ↓
PostgreSQL
```

需要交互时，Vue 2 会挂载到部分 Twig 页面上，例如购物车、购物车状态指示和订单编辑器。当前架构没有独立 SPA，也没有 Vue Router。

## 应用领域

| 领域 | 内容 |
|---|---|
| [`src/Account`](../../../src/Account) | 注册、本地登录、个人资料、email 验证、密码重置、消息与邮件流程 |
| [`src/Catalog`](../../../src/Catalog) | 分类、商品、图片、目录读取以及相关 Doctrine/API 查询 |
| [`src/Commerce`](../../../src/Commerce) | 购物车、购物车项、checkout、订单、访问检查与通知 |
| [`src/Money`](../../../src/Money) | 商业流程使用的货币 value object 与计算 |

Doctrine entity 仍位于 [`src/Entity`](../../../src/Entity)，应用服务则放在拥有对应用例的领域中。

## 内部 Symfony Bundle

项目包含三个内部 Symfony bundle。它们仍属于同一个应用，不是独立 Composer package。

| Bundle | 用途 |
|---|---|
| [`src/AdminBundle`](../../../src/AdminBundle) | 管理 controller、form、template 和 API 操作 |
| [`src/OAuthBundle`](../../../src/OAuthBundle) | OAuth client、authenticator、绑定/解绑与 provider mapping |
| [`src/SeoBundle`](../../../src/SeoBundle) | `robots.txt` 和 sitemap |

链接直接指向模块目录，无需额外浏览仓库即可查看结构。

## 路由

应用路由位于 [`config/routes.yaml`](../../../config/routes.yaml) 与 [`config/routes/app/`](../../../config/routes/app/)。

本地化领域 `account`、`catalog`、`commerce`、`admin` 和 `oauth` 使用 `/{_locale}` 前缀，支持 `ru|en`。SEO 路由不使用语言前缀。

API Platform 通过 [`config/routes/api_platform.yaml`](../../../config/routes/api_platform.yaml) 单独注册，前缀为 `/api`。

追踪请求的常见路径：

```text
URL
→ config/routes*.yaml
→ controller 或 API resource
→ 应用服务 / API handler
→ Doctrine repository / Doctrine ORM
```

## Doctrine 与数据

Doctrine entity 位于 [`src/Entity`](../../../src/Entity)，migration 位于 [`migrations`](../../../migrations)。

主要 entity：

- `User`；
- `Category`, `Product`, `ProductImage`；
- `Cart`, `CartProduct`；
- `Order`, `OrderProduct`；
- `ResetPasswordRequest`。

Repository 和应用服务没有集中放在一个公共目录，而是靠近使用它们的应用领域。

可复现的演示数据位于 [`tools/demo`](../../../tools/demo)，只在 `dev` 和 `test` 中加载。

## API Platform

API Platform 用于应用 API，而不是自动公开所有 Doctrine entity。

API 包含目录、购物车和订单。数据访问和修改还会通过权限检查、query extension、input object 与 API Platform handler 进一步限制。Checkout 使用专用 input object 和 handler，订单项的管理操作则通过 `AdminBundle` 配置扩展。

排查 API 行为时，不要只看 entity attribute，还要检查对应 API Platform handler、query extension 和访问规则。

## Twig、Vue 与 Webpack Encore

主要页面由 Twig 渲染。公共 template 位于 [`templates`](../../../templates)，内部 bundle 的 template 位于各自模块中。

Webpack Encore 从 [`assets`](../../../assets) 构建资源到 `public/build`。Vue 2 只作为服务器渲染页面上的局部交互层使用。

当前客户端架构会保留到单独迁移至 Inertia.js 和 Vue 3 的阶段。

## 配置与依赖注入

[`config/services.yaml`](../../../config/services.yaml) 为应用代码启用自动依赖注入（`autowiring`），并为需要特殊参数或 provider map 的服务提供显式配置。

Doctrine、Security、Messenger、Mailer、Twig 与 API Platform 的设置位于 [`config/packages`](../../../config/packages)。

## 测试

| 目录 / group | 用途 |
|---|---|
| [`tests/Unit`](../../../tests/Unit) | 隔离的应用规则与服务 |
| [`tests/Integration`](../../../tests/Integration) | Doctrine 与多个服务的协作 |
| [`tests/Functional`](../../../tests/Functional) | HTTP、controller、API 和访问规则 |
| `functional-panther` | 使用 Panther 的浏览器场景 |
| [`tests/TestUtils`](../../../tests/TestUtils) | 公共测试辅助类以及外部 OAuth client 的替代实现 |

PHP/PHPUnit 覆盖率针对 `src` 和 `tools/demo` 计算；Panther 不进入报告。运行命令见[开发指南](development.md)。

## Docker

Docker Compose 启动三个常驻服务：

| 服务 | 作用 |
|---|---|
| `php` | PHP-FPM、Composer、Symfony Console 和 Panther 环境 |
| `nginx` | HTTP 入口与静态文件 |
| `postgres` | 带持久化数据 volume 的 PostgreSQL |

`node` 属于 `tools` profile，用于一次性 npm 命令和前端 build。Docker Compose 当前没有常驻 Messenger worker。

首次启动见[项目启动指南](getting-started.md)，`.env*` 层级见[配置指南](configuration.md)。
