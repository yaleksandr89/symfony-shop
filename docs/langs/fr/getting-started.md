# Démarrage du projet

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/getting-started.md) | [English](../ru/getting-started.md) | [Español](../ru/getting-started.md) | [中文](../ru/getting-started.md) | **Français** | [Deutsch](../ru/getting-started.md) |


Le workflow local pris en charge utilise Docker Compose. Il n’est pas nécessaire d’installer sur l’hôte PHP, Composer, Node.js, PostgreSQL ni l’environnement navigateur utilisé par Panther.

Le projet ne prend pas en charge comme scénario officiel une installation avec PHP, Composer, PostgreSQL et Node.js directement dans le système d’exploitation : le Makefile, la CI, les commandes de test et l’environnement navigateur sont conçus autour de Docker. Une installation manuelle reste techniquement possible, mais elle ne fait pas partie du contrat vérifié du projet et n’est donc pas documentée ici.

## Prérequis

Pour travailler normalement, il faut :

- Git ;
- Make ;
- Docker avec le support de Compose ;
- Git LFS, recommandé pour un clonage Git classique ; l’archive Chrome for Testing peut aussi être obtenue autrement.

> [!NOTE]
> Make est un outil en ligne de commande courant sur les systèmes de type Unix. Sous Linux et macOS, le projet peut être lancé directement depuis le terminal. Sous Windows, la configuration recommandée est WSL2 avec Docker Desktop.

## Premier démarrage avec Git LFS

| Commande | Rôle | Remarque |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Clone le dépôt | |
| `cd symfony-shop` | Entre dans le répertoire du projet | |
| `git lfs install` | Active Git LFS | En général une seule fois par utilisateur |
| `git lfs pull` | Télécharge Chrome for Testing | À exécuter avant `make build` |
| `make init` | Crée `.env.docker` et les répertoires locaux | Utilise `.env.docker.example` et l’UID/GID de l’utilisateur hôte |
| `make build` | Construit l’image PHP | |
| `make up` | Démarre `php`, `nginx` et `postgres` | |
| `make composer-install` | Installe les dépendances PHP | Utilise `composer.lock` |
| `make npm-install` | Installe les dépendances frontend | Utilise `package-lock.json` |
| `make assets-build` | Compile les ressources frontend | |
| `make migrate` | Applique les migrations Doctrine | |
| `make demo-init` | Initialise les données de démonstration | Données locales `dev`/`test` uniquement |

Avec la configuration standard, l’application est disponible sur [http://localhost:8080](http://localhost:8080). Le port peut être modifié via `APP_PORT` dans `.env.docker`.

> [!WARNING]
> `make demo-init` recrée les commandes de démonstration. Utilisez cette commande uniquement avec une base dont les données peuvent être remplacées.

## Git LFS et Chrome for Testing

Panther utilise Chrome for Testing, installé dans l’image PHP pendant `make build`. L’archive du navigateur est stockée via Git LFS, tandis que Chromedriver est un fichier Git normal.

| Artefact | Chemin | Stockage |
|---|---|---|
| Chrome for Testing | `bin/chrome-linux64-150.0.7871.46.zip` | Git LFS |
| Chromedriver | `bin/drivers/chromedriver` | Git normal |

Le Dockerfile attend exactement Chrome for Testing `150.0.7871.46`. Ne le remplacez pas par la version stable courante de Chrome sans mettre à jour et vérifier en même temps la configuration Docker/Panther.

Pour l’archive fixée, les valeurs suivantes ont été vérifiées :

| Vérification | Valeur attendue |
|---|---|
| Taille | `186933179` octets |
| SHA-256 | `ad115a7498a17f53f6ed0914458326c6516addc756224db14c32184a9b1ab078` |

Trois méthodes permettent d’obtenir l’archive.

### Option 1 — Git LFS

C’est la méthode recommandée pour un `git clone` normal :

```text
git lfs install
git lfs pull
```

Client officiel et instructions d’installation : [git-lfs.com](https://git-lfs.com/).

### Option 2 — archive d’une version de Symfony Shop

À partir de `v3.0.0`, le ZIP du projet peut être téléchargé depuis la page [Releases](https://github.com/yaleksandr89/symfony-shop/releases). Chrome for Testing y est déjà inclus ; Git LFS n’est donc pas nécessaire pour ce scénario.

Utilisez l’archive correspondant exactement à la version du projet dont vous avez besoin : les versions plus anciennes peuvent contenir une autre version de Chrome et une configuration différente.

### Option 3 — Chrome for Testing officiel

La version `150.0.7871.46` est publiée dans le catalogue officiel Chrome for Testing :

- [métadonnées de la version `150.0.7871.46`](https://googlechromelabs.github.io/chrome-for-testing/150.0.7871.46.json) ;
- [archive officielle Chrome for Testing pour Linux x64](https://storage.googleapis.com/chrome-for-testing-public/150.0.7871.46/linux64/chrome-linux64.zip).

Enregistrez le fichier téléchargé sous :

```text
bin/chrome-linux64-150.0.7871.46.zip
```

Après un téléchargement manuel, vérifiez toujours la taille, le SHA-256 et l’intégrité ZIP avec les valeurs ci-dessus.

## Vérification de l’archive Chrome

| Commande | Ce qu’elle vérifie |
|---|---|
| `git lfs ls-files` | L’archive est enregistrée dans Git LFS si ce scénario est utilisé |
| `wc -c < bin/chrome-linux64-150.0.7871.46.zip` | Taille du fichier |
| `sha256sum bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 sous Linux/WSL |
| `shasum -a 256 bin/chrome-linux64-150.0.7871.46.zip` | SHA-256 sous macOS |
| `unzip -tq bin/chrome-linux64-150.0.7871.46.zip` | Intégrité ZIP |

Si le fichier ne fait qu’une centaine d’octets et commence par `version https://git-lfs.github.com/spec/v1`, la copie de travail contient encore un pointeur Git LFS. Exécutez `git lfs pull` ou remplacez le pointeur par l’archive réelle obtenue avec l’une des deux méthodes alternatives.

Après tout remplacement manuel, l’archive doit produire le même SHA-256. Si la somme diffère, ne lancez pas la construction et ne commitez pas ce fichier.

## Configuration locale

`make init` crée `.env.docker` à partir de `.env.docker.example`, remplace `HOST_UID` et `HOST_GID` par les valeurs actuelles et crée `var/cache`, `var/log` et `public/uploads`.

Si `.env.docker` existe déjà, il n’est pas écrasé. Conservez les secrets locaux de l’application et les identifiants OAuth dans `.env.local`, pas dans `.env.docker`.

> [!IMPORTANT]
> Les valeurs de `.env.docker` sont transmises au conteneur PHP comme variables d’environnement du processus et ont priorité sur les valeurs homonymes de `.env.local`. C’est particulièrement important pour Panther, la base de données et toute clé dupliquée par erreur dans les deux fichiers.

Les couches d’environnement et leur priorité sont détaillées dans le [guide de configuration](configuration.md).

## Gestion de Docker

| Commande | Rôle | Remarque |
|---|---|---|
| `make ps` | Affiche les conteneurs du projet | |
| `make restart php` | Redémarre PHP | `nginx` et `postgres` sont aussi disponibles |
| `make log php` | Suit le journal PHP | `nginx` et `postgres` sont aussi disponibles |
| `make log-all` | Affiche tous les journaux | |
| `make in php` | Ouvre Bash dans le conteneur PHP en tant qu’utilisateur `app` | |
| `make down` | Arrête l’environnement | Le volume PostgreSQL est conservé |

La liste complète des cibles Make, y compris tests, vérifications, couverture et commandes destructives, se trouve dans le [guide de développement](development.md).

## Si le premier démarrage échoue

| Symptôme | À vérifier |
|---|---|
| `make build` échoue lors de l’extraction de Chrome | taille, SHA-256 et `unzip -tq` de l’archive Chrome |
| Le fichier Chrome contient `git-lfs.github.com/spec/v1` | si `git lfs pull` a été exécuté ; avec une release ou un téléchargement manuel, remplacez le pointeur par le vrai ZIP |
| `.env.docker` est absent | exécuter `make init` |
| Les conteneurs ne démarrent pas | `make config`, puis `make ps` et `make log-all` |
| L’application n’est pas accessible sur `8080` | vérifier `APP_PORT` dans `.env.docker` et `make ps` |
| Un changement dans `.env.local` n’a pas d’effet | vérifier si la même clé existe dans `.env.docker` |

Les règles concernant le courrier, Messenger, OAuth, l’environnement de test et les autres `.env*` sont regroupées dans le [guide de configuration](configuration.md).
