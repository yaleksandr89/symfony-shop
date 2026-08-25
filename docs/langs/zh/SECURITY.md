# 安全

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../.github/SECURITY.md) | [English](../en/SECURITY.md) | [Español](../es/SECURITY.md) | **中文** | [Français](../fr/SECURITY.md) | [Deutsch](../de/SECURITY.md) |

请负责任地报告潜在漏洞。Symfony Shop 是公开的教学项目，但认证、OAuth、购物车、结账、API、用户输入处理和配置相关问题都会按正常的应用安全问题处理。

## 应私下报告的问题

- 绕过认证或授权；
- 登录其他用户账户，或把外部 OAuth 身份绑定到错误的本地用户；
- 绕过修改状态操作的 CSRF 防护；
- SQL/DQL 注入；
- 存储型或反射型 XSS；
- 读取或修改其他用户的购物车、订单或管理数据；
- 绕过 API 限制或通过 API 泄露数据；
- 泄露 `.env`、OAuth 凭据、access token、cookie、session 标识、内部异常或其他敏感信息；
- 绕过服务端 OAuth provider 开关；
- 对项目有实质影响的可利用依赖漏洞；
- CI、源代码或依赖供应链被攻破。

## 可以公开提交到 Issues 的问题

- 不影响安全性的可复现界面错误；
- 不涉及访问其他用户数据的目录、购物车或管理后台错误；
- Docker/bootstrap 或兼容性问题；
- 文档错误；
- 改进建议。

如果不确定问题是否属于安全问题，请优先使用私下渠道。

## 如何报告

- 如果仓库 Security 页面提供私有漏洞报告表单，请优先使用。
- 不要在 Issues、Pull Requests 或日志中公开 exploit 代码、真实 secret、OAuth 凭据、access token、cookie、session 标识或本地 `.env*` 内容。
- 如果没有私有表单，请创建一个不包含利用细节的最小公开 Issue，并请求私下沟通渠道。
- 在修复可用之前，不要公开技术利用细节。

## 建议提供的信息

尽可能提供：

- commit SHA 或分支；
- 受影响的应用区域；
- 影响；
- 最小复现步骤；
- 必要时提供已清理的请求、响应或日志片段；
- 如果与复现有关，提供 PHP/Symfony/PostgreSQL 版本。

只使用合成数据。不要附加真实密码、token、外部 ID、cookie、session 标识或本地 `.env*` 内容。

## 后续处理

- 项目由一名作者维护，不提供保证的 SLA。
- 报告会被检查，并在需要时准备修复和回归验证。
- 不承诺 bug bounty 计划。
- 最好等修复可用后再公开披露细节。
