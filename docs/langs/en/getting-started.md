# Getting started

## Choose language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/getting-started.md) | **English** | [Español](../es/getting-started.md) | [中文](../zh/getting-started.md) | [Français](../fr/getting-started.md) | [Deutsch](../de/getting-started.md) |


The supported local development workflow uses Docker Compose. PHP, Composer, Node.js, PostgreSQL, and the browser environment required by Panther do not need to be installed on the host.

Running the project with PHP, Composer, PostgreSQL, and Node.js installed directly in the operating system is not supported: the Makefile, CI, test commands, and browser environment are designed around Docker. Such a setup can technically be assembled manually, but it is not a verified project contract and is therefore not documented here.

## Requirements

For normal use you need:

- Git;
- Make;
- Docker with Compose support;
- Git LFS — recommended when cloning with Git; Chrome for Testing can also be obtained in other ways.

> [!NOTE]
> Make is a standard command-line tool for Unix-like systems. On Linux and macOS, the project can be run directly from the terminal. On Windows, the recommended setup is WSL2 together with Docker Desktop.

## First startup with Git LFS

| Command | Purpose | Note |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Clone the repository | |
| `cd symfony-shop` | Enter the project directory | |
| `git lfs install` | Enable Git LFS | Usually required once per user |
| `git lfs pull` | Download Chrome for Testing | Run before `make build` |
| `make init` | Create `.env.docker` and local directories | Uses `.env.docker.example` and the host user's UID/GID |
| `make build` | Build the PHP image | |
| `make up` | Start `php`, `nginx`, and `postgres` | |
| `make composer-install` | Install PHP dependencies | Uses `composer.lock` |
| `make npm-install` | Install frontend dependencies | Uses `package-lock.json` |
| `make assets-build` | Build frontend assets | |
| `make migrate` | Apply Doctrine migrations | |
| `make demo-init` | Initialize demo data | Local `dev`/`test` data only |

With the default configuration, the application is available at [http://localhost:8080](http://localhost:8080). The port can be changed through `APP_PORT` in `.env.docker`.

> [!WARNING]
> `make demo-init` recreates demo orders. Use this command only with a database whose data can be replaced.

## Git LFS and Chrome for Testing

Panther uses Chrome for Testing, which is installed into the PHP image during `make build`. The browser archive is stored through Git LFS, while Chromedriver is a normal Git file.

| Artifact | Path | Storage |
|---|---|---|
| Chrome for Testing | `bin/chrome-linux64-150.0.7871.46.zip` | Git LFS |
| Chromedriver | `bin/drivers/chromedriver` | regular Git |

The Dockerfile expects exactly Chrome for Testing `150.0.7871.46`. Do not replace it with the current stable Chrome release without updating and verifying the Docker/Panther configuration at the same time.

The pinned archive has been verified with:

| Check | Expected value |
|---|---|
| Size | `186933179` bytes |
| SHA-256 | `ad115a7498a17f53f6ed0914458326c6516addc756224db14c32184a9b1ab078` |

There are three supported ways to obtain the archive.

### Option 1 — Git LFS

This is the recommended option for a normal `git clone`:

```text
git lfs install
git lfs pull
```

Official client and installation instructions: [git-lfs.com](https://git-lfs.com/).

### Option 2 — Symfony Shop release archive

Starting with version `v3.0.0`, the project ZIP can be downloaded from the [Releases](https://github.com/yaleksandr89/symfony-shop/releases) page. Chrome for Testing is already included in that archive, so Git LFS does not need to be installed for this workflow.

Use the archive for the exact project version you need: older releases may contain a different Chrome version and different configuration.

### Option 3 — official Chrome for Testing

Version `150.0.7871.46` is published in the official Chrome for Testing catalog:

- [version `150.0.7871.46` metadata](https://googlechromelabs.github.io/chrome-for-testing/150.0.7871.46.json);
- [official Chrome for Testing archive for Linux x64](https://storage.googleapis.com/chrome-for-testing-public/150.0.7871.46/linux64/chrome-linux64.zip).

Save the downloaded file as:

```text
bin/chrome-linux64-150.0.7871.46.zip
```

After a manual download, always verify the file size, SHA-256, and ZIP integrity against the table above.

## Verifying the Chrome archive

| Command | What it checks |
|---|---|
| `git lfs ls-files` | The archive is registered in Git LFS when the LFS workflow is used |
| `wc -c < bin/chrome-linux64-150.0.7871.46.zip` | File size |
| `sha256sum bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 on Linux/WSL |
| `shasum -a 256 bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 on macOS |
| `unzip -tq bin/chrome-linux64-150.0.7871.46.zip` | ZIP integrity |

If the file is only around a hundred bytes and starts with `version https://git-lfs.github.com/spec/v1`, the working copy still contains a Git LFS pointer. Run `git lfs pull` or replace the pointer with the real archive obtained through either alternative method above.

After any manual replacement, the archive must produce the same expected SHA-256. If the checksum differs, do not build the image and do not commit that file.

## Local configuration

`make init` creates `.env.docker` from `.env.docker.example`, substitutes the current `HOST_UID` and `HOST_GID`, and creates `var/cache`, `var/log`, and `public/uploads`.

If `.env.docker` already exists, the command does not overwrite it. Keep local application secrets and OAuth credentials in `.env.local`, not in `.env.docker`.

> [!IMPORTANT]
> Values from `.env.docker` are passed into the PHP container as process environment variables and take precedence over same-named values from `.env.local`. This is especially important for Panther settings, database settings, and any keys accidentally duplicated in both files.

Environment layers and their precedence are described in detail in the [configuration guide](configuration.md).

## Docker management

| Command | Purpose | Note |
|---|---|---|
| `make ps` | Show project containers | |
| `make restart php` | Restart PHP | `nginx` and `postgres` are also supported |
| `make log php` | Follow the PHP log | `nginx` and `postgres` are also supported |
| `make log-all` | Show logs for all services | |
| `make in php` | Open Bash in the PHP container as user `app` | |
| `make down` | Stop the environment | The PostgreSQL volume is preserved |

The complete Make target list, including tests, checks, coverage, and destructive commands, is available in the [development guide](development.md).

## If the first startup fails

| Symptom | What to check |
|---|---|
| `make build` fails while extracting Chrome | Chrome archive size, SHA-256, and `unzip -tq` |
| The Chrome file contains `git-lfs.github.com/spec/v1` text | Whether `git lfs pull` was run; when installing from a release or manually, replace the pointer with the real Chrome ZIP |
| `.env.docker` is missing | Run `make init` |
| Containers do not start | `make config`, then `make ps` and `make log-all` |
| The application is not available on `8080` | Check `APP_PORT` in `.env.docker` and `make ps` |
| A `.env.local` change has no effect | Check whether the same key is defined in `.env.docker` |

Mail, Messenger, OAuth, test-environment, and other `.env*` rules are collected in the [configuration guide](configuration.md).
