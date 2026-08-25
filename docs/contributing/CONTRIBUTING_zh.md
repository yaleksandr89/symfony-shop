# 为 Symfony Shop 做贡献

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../.github/CONTRIBUTING.md) | [English](CONTRIBUTING_en.md) | [Español](CONTRIBUTING_es.md) | **中文** | [Français](CONTRIBUTING_fr.md) | [Deutsch](CONTRIBUTING_de.md) |

感谢你关注 Symfony Shop。这是一个基于 Symfony 的教学型电商项目，使用 Docker 环境、PostgreSQL、API Platform、OAuth，并包含少量 Vue 交互组件。

## 开始之前

请先检查已有的 Discussions、Issues 和 Pull Requests，并尽量让每次改动只解决一个明确的问题。问题和想法优先在 [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions) 讨论；可复现错误和具体改进提交到 Issues；安全问题应按照[安全策略](../security/SECURITY_zh.md)私下报告，不要公开利用细节。

## 项目边界

- 受支持的本地环境使用 Docker Compose 和 Makefile。
- 正常开发流程不会直接在宿主机运行 PHP、Composer、PostgreSQL、Node.js 或浏览器环境。
- 改动不得悄然削弱访问控制、OAuth 流程、购物车/订单完整性或其他现有用户契约。
- 不要加入与任务无关的大范围重构或依赖升级。
- Vue 2 前端架构会保留到单独迁移到 Inertia.js 和 Vue 3 的阶段。

应用架构见 [`docs/architecture.md`](../architecture.md)，开发命令见 [`docs/development.md`](../development.md)。

## 分支

从最新的 `master` 创建主题分支。名称应简要描述改动，例如：

```text
fix/cart-quantity
docs/oauth
refactor/catalog-query
```

所有进入 `master` 的改动都通过 Pull Request。

## Commit

项目使用 Conventional Commits，并要求描述使用俄语：

```text
fix: исправить проверку количества товара
docs: уточнить настройку OAuth
refactor: упростить выборку каталога
```

每个 commit 应包含一个逻辑上完整的改动集合。

## 本地检查

运行命令前先阅读当前 Makefile。主要检查：

| 命令 | 作用 |
|---|---|
| `make check` | ESLint + PHP-CS-Fixer 检查 + PHPStan |
| `make test-unit` | 单元测试 |
| `make test-integration` | 集成测试 |
| `make test-functional` | 功能测试 |
| `make test-functional-panther` | Panther 浏览器测试 |
| `make test-all CONFIRM=testdb` | 完整测试集，包括 Panther |

只运行与改动相关的检查。如果改动触及共享应用边界，或在大型改动的最终验证前，再运行完整测试集。

## Pull Request

Pull Request 描述中请说明：

- 修改了什么以及原因；
- 如何验证；
- 是否需要手动步骤；
- 是否影响配置、数据、OAuth、访问权限或其他重要契约；
- 如果公开使用方式发生变化，文档是否已经更新。

## 检查清单

- 不包含 secret、真实 OAuth 凭据、access token、cookie、session 标识或本地 `.env*` 内容。
- diff 中没有无关改动。
- `git diff --check` 通过。
- 已运行相关检查。
- 新测试保护具体应用行为，而不是为了增加数量。
- 公开契约、配置或启动流程变化时，文档已同步更新。
