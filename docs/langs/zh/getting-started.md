# 项目启动

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/getting-started.md) | [English](../en/getting-started.md) | [Español](../es/getting-started.md) | **中文** | [Français](../fr/getting-started.md) | [Deutsch](../de/getting-started.md) |


受支持的本地开发流程基于 Docker Compose。宿主机无需直接安装 PHP、Composer、Node.js、PostgreSQL，也无需安装 Panther 所使用的浏览器环境。

项目不支持把 PHP、Composer、PostgreSQL 和 Node.js 直接安装到操作系统后作为标准运行方式：Makefile、CI、测试命令以及浏览器环境都围绕 Docker 设计。手动搭建这样的环境在技术上可行，但它不是经过验证的项目契约，因此这里不提供该方式的说明。

## 要求

正常开发需要：

- Git；
- Make；
- 支持 Compose 的 Docker；
- Git LFS，使用 Git 克隆时推荐安装；Chrome for Testing 归档也可以通过其他方式获取。

> [!NOTE]
> Make 是 Unix 类系统中常见的命令行工具。在 Linux 和 macOS 上可以直接通过终端运行项目。在 Windows 上推荐使用 WSL2 配合 Docker Desktop。

## 使用 Git LFS 首次启动

| 命令 | 作用 | 说明 |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | 克隆仓库 | |
| `cd symfony-shop` | 进入项目目录 | |
| `git lfs install` | 启用 Git LFS | 通常每个用户只需执行一次 |
| `git lfs pull` | 下载 Chrome for Testing | 在 `make build` 之前执行 |
| `make init` | 创建 `.env.docker` 和本地目录 | 使用 `.env.docker.example` 以及宿主机用户的 UID/GID |
| `make build` | 构建 PHP 镜像 | |
| `make up` | 启动 `php`、`nginx` 和 `postgres` | |
| `make composer-install` | 安装 PHP 依赖 | 使用 `composer.lock` |
| `make npm-install` | 安装前端依赖 | 使用 `package-lock.json` |
| `make assets-build` | 构建前端资源 | |
| `make migrate` | 应用 Doctrine migrations | |
| `make demo-init` | 初始化演示数据 | 仅用于本地 `dev`/`test` 数据 |

使用默认配置时，应用可通过 [http://localhost:8080](http://localhost:8080) 访问。端口可通过 `.env.docker` 中的 `APP_PORT` 修改。

> [!WARNING]
> `make demo-init` 会重新创建演示订单。只应在允许替换数据的数据库中使用该命令。

## Git LFS 与 Chrome for Testing

Panther 使用 Chrome for Testing，它会在 `make build` 时安装到 PHP 镜像中。浏览器归档通过 Git LFS 存储，而 Chromedriver 是普通 Git 文件。

| 资源 | 路径 | 存储方式 |
|---|---|---|
| Chrome for Testing | `bin/chrome-linux64-150.0.7871.46.zip` | Git LFS |
| Chromedriver | `bin/drivers/chromedriver` | 普通 Git |

Dockerfile 明确要求 Chrome for Testing `150.0.7871.46`。不要直接替换成当前稳定版 Chrome，除非同时修改并验证 Docker/Panther 配置。

已确认固定归档的以下值：

| 检查项 | 期望值 |
|---|---|
| 大小 | `186933179` 字节 |
| SHA-256 | `ad115a7498a17f53f6ed0914458326c6516addc756224db14c32184a9b1ab078` |

可以通过三种方式获得该归档。

### 方式 1 — Git LFS

这是普通 `git clone` 推荐的方式：

```text
git lfs install
git lfs pull
```

官方客户端及安装说明：[git-lfs.com](https://git-lfs.com/)。

### 方式 2 — Symfony Shop Release 归档

从 `v3.0.0` 开始，可以在 [Releases](https://github.com/yaleksandr89/symfony-shop/releases) 页面下载项目 ZIP。归档中已经包含 Chrome for Testing，因此这种方式无需单独安装 Git LFS。

请使用与所需项目版本完全对应的归档：旧版本可能包含不同的 Chrome 版本和不同配置。

### 方式 3 — 官方 Chrome for Testing

版本 `150.0.7871.46` 已发布在官方 Chrome for Testing 目录：

- [版本 `150.0.7871.46` 元数据](https://googlechromelabs.github.io/chrome-for-testing/150.0.7871.46.json)；
- [Linux x64 官方 Chrome for Testing 归档](https://storage.googleapis.com/chrome-for-testing-public/150.0.7871.46/linux64/chrome-linux64.zip)。

下载后将文件保存为：

```text
bin/chrome-linux64-150.0.7871.46.zip
```

手动下载后，请务必根据上表检查文件大小、SHA-256 和 ZIP 完整性。

## 检查 Chrome 归档

| 命令 | 检查内容 |
|---|---|
| `git lfs ls-files` | 使用 LFS 流程时，确认归档已注册到 Git LFS |
| `wc -c < bin/chrome-linux64-150.0.7871.46.zip` | 文件大小 |
| `sha256sum bin/chrome-linux64-150.0.7871.46.zip` | Linux/WSL 下的 SHA-256 |
| `shasum -a 256 bin/chrome-linux64-150.0.7871.46.zip` | macOS 下的 SHA-256 |
| `unzip -tq bin/chrome-linux64-150.0.7871.46.zip` | ZIP 完整性 |

如果文件只有大约一百字节，并以 `version https://git-lfs.github.com/spec/v1` 开头，说明工作副本中仍然是 Git LFS pointer。执行 `git lfs pull`，或用上面两种替代方式获得的真实归档替换 pointer。

任何手动替换后，归档都必须得到相同的预期 SHA-256。如果校验和不同，不要执行构建，也不要提交该文件。

## 本地配置

`make init` 会根据 `.env.docker.example` 创建 `.env.docker`，写入当前 `HOST_UID` 和 `HOST_GID`，并创建 `var/cache`、`var/log` 和 `public/uploads`。

如果 `.env.docker` 已存在，该命令不会覆盖它。应用本地 secret 和 OAuth 凭据应放在 `.env.local`，不要放在 `.env.docker`。

> [!IMPORTANT]
> `.env.docker` 中的值会作为进程环境变量传入 PHP 容器，并优先于 `.env.local` 中同名的值。这对 Panther、数据库配置以及意外在两个文件中重复的键尤其重要。

环境层级及其优先级详见[配置指南](configuration.md)。

## Docker 管理

| 命令 | 作用 | 说明 |
|---|---|---|
| `make ps` | 显示项目容器 | |
| `make restart php` | 重启 PHP | 也支持 `nginx` 和 `postgres` |
| `make log php` | 实时查看 PHP 日志 | 也支持 `nginx` 和 `postgres` |
| `make log-all` | 显示所有服务日志 | |
| `make in php` | 以用户 `app` 身份进入 PHP 容器 Bash | |
| `make down` | 停止环境 | PostgreSQL volume 会保留 |

包括测试、检查、覆盖率和破坏性命令在内的完整 Make target 列表见[开发指南](development.md)。

## 首次启动失败时

| 现象 | 检查内容 |
|---|---|
| `make build` 在解压 Chrome 时失败 | Chrome 归档大小、SHA-256 和 `unzip -tq` |
| Chrome 文件包含 `git-lfs.github.com/spec/v1` | 是否执行了 `git lfs pull`；使用 Release 或手动下载时，需要用真实 Chrome ZIP 替换 pointer |
| 缺少 `.env.docker` | 执行 `make init` |
| 容器无法启动 | `make config`，然后 `make ps` 和 `make log-all` |
| 应用无法通过 `8080` 访问 | 检查 `.env.docker` 中的 `APP_PORT` 和 `make ps` |
| 修改 `.env.local` 没有效果 | 检查同名键是否在 `.env.docker` 中定义 |

邮件、Messenger、OAuth、测试环境以及其他 `.env*` 规则见[配置指南](configuration.md)。
