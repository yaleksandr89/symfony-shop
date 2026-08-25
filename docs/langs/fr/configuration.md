# Configuration

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../configuration.md) | [English](../en/configuration.md) | [Español](../es/configuration.md) | [中文](../zh/configuration.md) | **Français** | [Deutsch](../de/configuration.md) |


Le projet sépare les paramètres Symfony communs, les paramètres Docker, les secrets locaux et les surcharges de test. Point important : les valeurs transmises au conteneur PHP par Docker Compose ont une priorité supérieure à celles chargées depuis les fichiers Symfony Dotenv.

## Fichiers d’environnement

| Fichier | Rôle | Git |
|---|---|---|
| `.env` | Paramètres Symfony sûrs et valeurs locales par défaut | suivi |
| `.env.docker` | Paramètres Docker Compose et PostgreSQL local | ignoré |
| `.env.local` | Secrets et paramètres propres au développeur | ignoré |
| `.env.test` | Paramètres des tests automatiques | suivi |

## Priorité des variables

De la priorité la plus haute à la plus basse :

1. variables d’environnement du processus, y compris les valeurs de `.env.docker` transmises par Docker Compose ;
2. `.env.<environnement>.local` ;
3. `.env.<environnement>` ;
4. `.env.local` ;
5. `.env`.

Le nom `.env.docker` ne lui donne pas une priorité spéciale en soi. Cette priorité vient du fait que Docker Compose transmet ces valeurs au conteneur PHP comme variables d’environnement du processus.

Exemple pratique :

```text
.env.docker
PANTHER_WEB_SERVER_PORT=9080

.env.local
PANTHER_WEB_SERVER_PORT=9999

→ dans le conteneur PHP, 9080 est utilisé
```

À l’inverse, les identifiants OAuth de `.env.local` sont utilisés si Docker n’a pas transmis de variables du même nom.

Après une modification de `.env.docker`, recréez les conteneurs avec `make down` puis `make up`. Les changements dans `.env` ou `.env.local` ne l’exigent généralement pas.

## `.env`

Ce fichier contient les paramètres communs de l’application : `APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `APP_TIMEZONE`, `DATABASE_URL`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`, l’adresse de l’application, CORS et les interrupteurs OAuth.

Les valeurs de `.env` sont des valeurs locales du projet et ne sont pas destinées à la production.

## `.env.docker`

`make init` crée ce fichier depuis `.env.docker.example` et y insère l’UID/GID de l’utilisateur hôte.

Paramètres principaux :

| Variable | Rôle | Valeur par défaut |
|---|---|---|
| `HOST_UID`, `HOST_GID` | Propriétaire des fichiers créés par les conteneurs | remplis par `make init` |
| `APP_PORT` | Port HTTP Nginx sur l’hôte | `8080` |
| `POSTGRES_DB` | Base PostgreSQL locale | `s_shop` |
| `POSTGRES_USER` | Utilisateur PostgreSQL local | `s_shop` |
| `POSTGRES_PASSWORD` | Mot de passe PostgreSQL local | valeur de démonstration |
| `PANTHER_WEB_SERVER_HOST` | Hôte du serveur intégré Panther | `php` |
| `PANTHER_WEB_SERVER_PORT` | Port du serveur intégré Panther | `9080` |

Compose utilise `.env.docker` comme `env_file` du conteneur PHP ; ces valeurs deviennent donc des variables d’environnement du processus.

## `.env.local`

Utilisez `.env.local` pour les identifiants OAuth, un vrai `MAILER_DSN`, un `ADMIN_EMAIL` local et les autres secrets propres à la machine.

N’ajoutez pas ce fichier à Git et ne publiez pas son contenu. Dans l’environnement `test`, Symfony ne charge pas `.env.local`.

## `.env.test`

L’environnement de test utilise une base SQLite séparée `var/db_for_test.db`, des paramètres Panther, des transports Mailer/Messenger neutres et des fournisseurs OAuth désactivés.

## Courrier et Messenger

Valeurs par défaut :

```dotenv
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default
```

`MAILER_DSN=null://null` signifie que l’environnement local n’envoie aucun message via un service SMTP externe. Les messages créés de manière synchrone pendant une requête HTTP peuvent être consultés dans le panneau Mailer de Symfony Profiler.

Pour un transport SMTP réel, définissez votre propre `MAILER_DSN` dans `.env.local`, par exemple :

```dotenv
MAILER_DSN=smtp://USER:PASSWORD@mail.example.test:587
```

Messenger route déjà l’inscription et la réinitialisation du mot de passe vers le transport `async`, mais Docker Compose ne démarre pas de worker permanent. Le message reste dans la file Doctrine jusqu’au lancement manuel du worker :

| Commande | Rôle |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Démarre le worker du transport `async` dans le conteneur PHP |

C’est particulièrement important pour tester l’inscription et la réinitialisation du mot de passe : sans worker, les messages asynchrones correspondants ne seront pas traités. Un service local de courrier avec interface web et un worker Messenger permanent sont prévus ultérieurement.

## PostgreSQL

Docker Compose utilise PostgreSQL 18.4. Le conteneur PHP se connecte à la base via le nom de service `postgres` ; `localhost` dans le conteneur PHP ne pointe pas vers PostgreSQL.

PostgreSQL n’est exposé à l’hôte que via `127.0.0.1:5433`.

`DATABASE_URL` est construit à partir de `POSTGRES_*` et utilisé par Doctrine. La recréation complète du volume PostgreSQL local se fait avec la commande destructive `make postgres-reinit CONFIRM=postgres18` ; voir le [guide de développement](development.md).

## OAuth

Tous les fournisseurs OAuth sont désactivés par défaut. Activer un fournisseur et fournir ses identifiants sont deux réglages distincts : il faut à la fois `*_ENABLED=1` et des Client ID / Client Secret valides.

| Fournisseur | Interrupteur |
|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` |
| Yandex | `OAUTH_YANDEX_ENABLED` |
| VKontakte | `OAUTH_VK_ENABLED` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` |
| Mail.ru | `OAUTH_MAILRU_ENABLED` : doit rester à `0` |

Exemple local pour Google :

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

Les autres noms d’identifiants, les routes et les règles de fonctionnement sont regroupés dans le [guide OAuth](oauth.md). N’ajoutez jamais de vraies clés, tokens d’accès, codes d’autorisation ou ID externes à la documentation ou à Git.

## Panther

L’image PHP contient Chrome for Testing et Chromedriver. Aucun navigateur sur l’hôte ni Java ne sont nécessaires pour les tests.

Docker utilise `PANTHER_WEB_SERVER_HOST=php` et `PANTHER_WEB_SERVER_PORT=9080`, tandis que `.env.test` ajoute les paramètres propres aux tests et le répertoire des captures d’erreur.

Les méthodes pour obtenir l’archive Chrome sont décrites dans le [guide de démarrage](getting-started.md), et les tests navigateur dans le [guide de développement](development.md).
