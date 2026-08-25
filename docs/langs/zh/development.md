# 开发

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/development.md) | [English](../ru/development.md) | [Español](../ru/development.md) | **中文** | [Français](../ru/development.md) | [Deutsch](../ru/development.md) |


Makefile 是本地开发的主要入口。PHP、Composer 和 Symfony Console 在 PHP 容器中以用户 `app` 运行；npm 在一次性的 Node 容器中运行。

当前 target 列表可随时通过 `make help` 查看。

## 初始配置

| 命令 | 作用 |
|---|---|
| `make help` | 显示 Makefile 内置帮助 |
| `make init` | 创建 `.env.docker` 和可写本地目录 |
| `make check-env` | 检查 `.env.docker` 是否存在 |

## Docker Compose

| 命令 | 作用 | 说明 |
|---|---|---|
| `make config` | 验证最终 Compose 配置 | 不启动任何服务 |
| `make build` | 构建 PHP 镜像 | |
| `make up` | 启动 `php`、`nginx` 和 `postgres` | |
| `make ps` | 显示容器状态 | |
| `make restart <service>` | 重启服务 | `php`、`nginx`、`postgres` |
| `make log <service>` | 显示服务日志 | `php`、`nginx`、`postgres` |
| `make log-all` | 显示所有日志 | |
| `make in <service>` | 打开服务 shell | `php`、`nginx`、`postgres`、`node` |
| `make down` | 停止环境 | PostgreSQL volume 会保留 |

PHP 容器 shell 以 `app` 用户打开，因此正常命令不应在工作副本中创建 `root` 所有的文件。

## Symfony、Composer 与 npm

| 命令 | 作用 | 说明 |
|---|---|---|
| `make console CMD=about` | 运行 Symfony Console | 任意命令通过 `CMD` 传入 |
| `make composer CMD='validate --strict'` | 运行 Composer | 在 PHP 容器中 |
| `make composer-install` | 执行 `composer install` | 使用 `composer.lock` |
| `make npm CMD='npm --version'` | 执行任意 npm 命令 | 在一次性 Node 容器中 |
| `make npm-install` | 执行 `npm ci` | 使用 `package-lock.json` |
| `make assets-build` | 构建优化后的前端资源 | Webpack Encore |
| `make watch` | 监听前端资源变化 | 长时间运行命令 |

宿主机不直接使用 PHP、Composer、Node.js 或 Symfony Console。

手动处理 Messenger 队列：

| 命令 | 作用 |
|---|---|
| `make console CMD='messenger:consume async -vv'` | 启动 `async` transport 队列 worker |

Docker Compose 当前没有常驻 Messenger worker。邮件与队列详情见[配置指南](configuration.md)。

## 质量检查

| 命令 | 作用 | 修改文件 |
|---|---|---|
| `make check` | ESLint + PHP-CS-Fixer 检查 + PHPStan | 否 |
| `make eslint-check` | 使用 ESLint 检查 JS/Vue | 否 |
| `make php-cs-fixer-check` | 检查 `src/` 和 `tools/demo/` 格式 | 否 |
| `make phpstan-check` | 对 `src` 和 `tools/demo` 运行 PHPStan | 否 |
| `make eslint-fix` | 修复 ESLint 问题 | 是 |
| `make php-cs-fixer` | 修复 PHP 格式 | 是 |

`make check` 不运行 PHPUnit。测试使用独立 target。

## 测试

| 命令 | 检查内容 | 说明 |
|---|---|---|
| `make test-groups` | 显示 PHPUnit groups | |
| `make test-list` | 显示测试列表 | |
| `make test-unit` | 隔离的应用逻辑 | `unit` group |
| `make test-integration` | Doctrine 与服务协作 | `integration` group |
| `make test-functional` | HTTP、controller、API 和访问规则 | `functional` group |
| `make test-functional-panther` | 浏览器场景 | `functional-panther` group |
| `make test-all-core CONFIRM=testdb` | 前端资源 + unit + integration + functional | 重建测试 SQLite 数据库 |
| `make test-all CONFIRM=testdb` | 完整测试集，包括 Panther | 重建测试 SQLite 数据库 |

`CONFIRM=testdb` 是有意要求的：聚合测试流程会删除并重新创建 `var/db_for_test.db`。

Panther 使用 PHP 镜像中的 Chrome for Testing 和 Chromedriver。当前测试不需要 Selenium Server、GeckoDriver、Java，也不需要本地安装浏览器。

## 代码覆盖率

| 命令 | 结果 | 说明 |
|---|---|---|
| `make coverage CONFIRM=testdb` | 终端统计 | `src` + `tools/demo`，不含 Panther |
| `make coverage-html CONFIRM=testdb` | 终端 + HTML + Clover | `var/coverage/html`、`var/coverage/clover.xml` |

两个命令使用相同的 PHP/PHPUnit 范围，并会预先重建测试数据库。Panther 不进入覆盖率报告。

## 数据库与演示数据

| 命令 | 作用 | 风险 |
|---|---|---|
| `make migrate` | 应用 Doctrine migrations | 正常操作 |
| `make demo-init` | 初始化演示目录、账户和订单 | 替换已有订单 |
| `make test-db-reset CONFIRM=testdb` | 重建 `var/db_for_test.db` | 删除测试 SQLite 数据库 |
| `make postgres-reinit CONFIRM=postgres18` | 重建本地 PostgreSQL volume | 删除本地 PostgreSQL 数据 |
| `make cache-prod-clear` | 删除生成的 prod cache | 只删除 PHP 容器内的 `var/cache/prod` |

`make demo-init` 面向可复现的 `dev`/`test` 环境。如果本地数据库中有需要保留的订单，不要运行它。

## CI

Workflow [`CI`](../../../.github/workflows/basic.yml) 会在 push 和面向 `master` 的 Pull Request 中运行。

它会：

1. 下载 Git LFS 对象并检查 Chrome 归档；
2. 创建 `.env.docker`；
3. 验证 Compose，构建并启动 Docker 环境；
4. 安装依赖并构建前端资源；
5. 运行 ESLint；
6. 运行 unit、integration、functional 和 Panther 测试；
7. 运行 PHPStan；
8. 停止容器。

CI 不运行 PHP-CS-Fixer 检查，也不生成覆盖率报告；需要时这些检查在本地执行。

## 日志与诊断

| 命令 | 显示内容 |
|---|---|
| `make ps` | 容器状态 |
| `make log php` | PHP 日志 |
| `make log nginx` | Nginx 日志 |
| `make log postgres` | PostgreSQL 日志 |
| `make log-all` | 所有项目日志 |
| `make console CMD=about` | Symfony 应用状态 |

## 所有 Make 命令

| Target | 作用 |
|---|---|
| `help` | 内置帮助 |
| `init` | 创建 `.env.docker` 和本地目录 |
| `check-env` | 检查 `.env.docker` |
| `config` | 验证 Docker Compose |
| `build` | 构建 PHP 镜像 |
| `up` | 启动主要服务 |
| `down` | 停止环境 |
| `restart <service>` | 重启服务 |
| `ps` | 容器状态 |
| `log <service>` | 指定服务日志 |
| `log-all` | 所有服务日志 |
| `in <service>` | 指定服务 shell |
| `cache-prod-clear` | 删除 prod cache |
| `console CMD='...'` | Symfony Console |
| `composer CMD='...'` | PHP 容器中的 Composer |
| `composer-install` | 安装 Composer 依赖 |
| `npm CMD='...'` | 一次性 Node 容器中的 npm |
| `npm-install` | 安装 npm 依赖 |
| `assets-build` | 优化的前端 build |
| `watch` | 监听前端资源 |
| `migrate` | Doctrine migrations |
| `demo-init` | 演示数据 |
| `postgres-reinit CONFIRM=postgres18` | 完全重建本地 PostgreSQL volume |
| `check` | ESLint + PHP-CS-Fixer check + PHPStan |
| `eslint-fix` | 修复 ESLint |
| `eslint-check` | 检查 ESLint |
| `php-cs-fixer` | 修复 PHP 格式 |
| `php-cs-fixer-check` | 检查 PHP 格式 |
| `phpstan-check` | PHPStan 静态分析 |
| `test-all-core CONFIRM=testdb` | 不含 Panther 的主要测试集 |
| `coverage CONFIRM=testdb` | 终端覆盖率 |
| `coverage-html CONFIRM=testdb` | 覆盖率 + HTML/Clover |
| `test-all CONFIRM=testdb` | 含 Panther 的完整测试集 |
| `test-groups` | PHPUnit groups |
| `test-list` | PHPUnit 测试列表 |
| `test-unit` | unit 测试 |
| `test-db-reset CONFIRM=testdb` | 重建测试 SQLite 数据库 |
| `test-integration` | integration 测试 |
| `test-functional` | functional 测试 |
| `test-functional-panther` | Panther 浏览器测试 |

首次启动和获取 Chrome for Testing 的方式见[项目启动指南](getting-started.md)。`.env*` 与本地 secret 规则见[配置指南](configuration.md)。
