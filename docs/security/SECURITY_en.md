# Security

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../.github/SECURITY.md) | **English** | [Español](SECURITY_es.md) | [中文](SECURITY_zh.md) | [Français](SECURITY_fr.md) | [Deutsch](SECURITY_de.md) |

Please report potential vulnerabilities responsibly. Symfony Shop is a public educational project, but authentication, OAuth, cart, checkout, API, user-input handling, and configuration issues are treated as ordinary application-security problems.

## What should be reported privately

- authentication or authorization bypass;
- the ability to sign in to another user's account or link an external OAuth identity to the wrong local user;
- bypassing CSRF protection on state-changing operations;
- SQL/DQL injection;
- stored or reflected XSS;
- reading or modifying another user's cart, order, or administrative data;
- bypassing API restrictions or disclosing data through the API;
- exposure of `.env`, OAuth credentials, access tokens, cookies, session identifiers, internal exceptions, or other sensitive information;
- bypassing a server-side OAuth provider switch;
- an exploitable dependency issue that materially affects this project;
- compromise of CI, source code, or the dependency supply chain.

## What can be published in Issues

- a reproducible UI bug with no security impact;
- a catalog, cart, or administration bug that does not expose another user's data;
- a Docker/bootstrap or compatibility issue;
- a documentation problem;
- an improvement request.

If you are unsure whether an issue is security-sensitive, use a private channel first.

## How to report

- If the repository Security section provides a private vulnerability-reporting form, use it first.
- Do not publish exploit code, real secrets, OAuth credentials, access tokens, cookies, session identifiers, or local `.env*` contents in Issues, Pull Requests, or logs.
- If private reporting is unavailable, create a minimal public Issue without exploitation details and ask for a private communication channel.
- Do not disclose technical exploitation details publicly before a fix is available.

## What to include

When possible, provide:

- commit SHA or branch;
- affected application area;
- impact;
- minimal reproduction steps;
- a sanitized request, response, or log fragment when relevant;
- PHP/Symfony/PostgreSQL versions when they matter for reproduction.

Use synthetic data only. Do not attach real passwords, tokens, external IDs, cookies, session identifiers, or local `.env*` contents.

## What happens next

- The project is maintained by one author; no guaranteed SLA is provided.
- The report will be reviewed and, when necessary, followed by a fix and a regression check.
- No bug-bounty program is promised.
- Public disclosure should preferably wait until a fix is available.
