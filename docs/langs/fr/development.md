# Développement

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/development.md) | [English](../ru/development.md) | [Español](../ru/development.md) | [中文](../ru/development.md) | **Français** | [Deutsch](../ru/development.md) |


Le Makefile est l’interface principale du développement local. PHP, Composer et Symfony Console s’exécutent dans le conteneur PHP sous l’utilisateur `app` ; npm s’exécute dans un conteneur Node éphémère.

La liste actuelle des cibles est toujours disponible via `make help`.

## Configuration initiale

| Commande | Rôle |
|---|---|
| `make help` | Affiche l’aide intégrée du Makefile |
| `make init` | Crée `.env.docker` et les répertoires locaux accessibles en écriture |
| `make check-env` | Vérifie la présence de `.env.docker` |

## Docker Compose

| Commande | Rôle | Remarque |
|---|---|---|
| `make config` | Valide la configuration Compose finale | Ne démarre rien |
| `make build` | Construit l’image PHP | |
| `make up` | Démarre `php`, `nginx` et `postgres` | |
| `make ps` | Affiche l’état des conteneurs | |
| `make restart <service>` | Redémarre un service | `php`, `nginx`, `postgres` |
| `make log <service>` | Affiche le journal d’un service | `php`, `nginx`, `postgres` |
| `make log-all` | Affiche tous les journaux | |
| `make in <service>` | Ouvre un shell dans un service | `php`, `nginx`, `postgres`, `node` |
| `make down` | Arrête l’environnement | Le volume PostgreSQL est conservé |

Le shell du conteneur PHP s’ouvre en tant que `app`, afin que les commandes normales ne créent pas de fichiers appartenant à `root` dans la copie de travail.

## Symfony, Composer et npm

| Commande | Rôle | Remarque |
|---|---|---|
| `make console CMD=about` | Lance Symfony Console | Toute commande est transmise via `CMD` |
| `make composer CMD='validate --strict'` | Lance Composer | Dans le conteneur PHP |
| `make composer-install` | Exécute `composer install` | Utilise `composer.lock` |
| `make npm CMD='npm --version'` | Lance une commande npm arbitraire | Dans un conteneur Node éphémère |
| `make npm-install` | Exécute `npm ci` | Utilise `package-lock.json` |
| `make assets-build` | Compile les ressources frontend optimisées | Webpack Encore |
| `make watch` | Surveille les modifications des ressources frontend | Commande longue durée |

PHP, Composer, Node.js et Symfony Console ne sont pas utilisés directement sur l’hôte.

Pour traiter manuellement la file Messenger :

| Commande | Rôle |
|---|---|
| `make console CMD='messenger:consume async -vv'` | Démarre le worker de la file `async` |

Docker Compose ne possède actuellement aucun worker Messenger permanent. Voir le [guide de configuration](configuration.md) pour le courrier et la file.

## Vérifications qualité

| Commande | Rôle | Modifie les fichiers |
|---|---|---|
| `make check` | ESLint + vérification PHP-CS-Fixer + PHPStan | non |
| `make eslint-check` | Vérifie JS/Vue avec ESLint | non |
| `make php-cs-fixer-check` | Vérifie le formatage de `src/` et `tools/demo/` | non |
| `make phpstan-check` | Lance PHPStan sur `src` et `tools/demo` | non |
| `make eslint-fix` | Corrige les problèmes ESLint | oui |
| `make php-cs-fixer` | Corrige le formatage PHP | oui |

`make check` ne lance pas PHPUnit. Les tests ont leurs propres cibles.

## Tests

| Commande | Ce qu’elle vérifie | Remarque |
|---|---|---|
| `make test-groups` | Affiche les groupes PHPUnit | |
| `make test-list` | Affiche la liste des tests | |
| `make test-unit` | Logique applicative isolée | groupe `unit` |
| `make test-integration` | Doctrine et interaction entre services | groupe `integration` |
| `make test-functional` | HTTP, contrôleurs, API et règles d’accès | groupe `functional` |
| `make test-functional-panther` | Scénarios navigateur | groupe `functional-panther` |
| `make test-all-core CONFIRM=testdb` | Ressources frontend + unit + integration + functional | Recrée la base SQLite de test |
| `make test-all CONFIRM=testdb` | Suite complète, Panther inclus | Recrée la base SQLite de test |

`CONFIRM=testdb` est volontaire : les scénarios agrégés suppriment et recréent `var/db_for_test.db`.

Panther utilise Chrome for Testing et Chromedriver de l’image PHP. Selenium Server, GeckoDriver, Java et un navigateur local ne sont pas nécessaires pour les tests actuels.

## Couverture du code

| Commande | Résultat | Remarque |
|---|---|---|
| `make coverage CONFIRM=testdb` | Statistiques dans le terminal | `src` + `tools/demo`, sans Panther |
| `make coverage-html CONFIRM=testdb` | Terminal + HTML + Clover | `var/coverage/html`, `var/coverage/clover.xml` |

Les deux commandes utilisent la même portée PHP/PHPUnit et recréent la base de test au préalable. Panther n’est pas inclus dans le rapport.

## Base de données et données de démonstration

| Commande | Rôle | Risque |
|---|---|---|
| `make migrate` | Applique les migrations Doctrine | opération normale |
| `make demo-init` | Initialise catalogue, comptes et commandes de démonstration | remplace les commandes existantes |
| `make test-db-reset CONFIRM=testdb` | Recrée `var/db_for_test.db` | supprime la base SQLite de test |
| `make postgres-reinit CONFIRM=postgres18` | Recrée le volume PostgreSQL local | supprime les données PostgreSQL locales |
| `make cache-prod-clear` | Supprime le cache prod généré | uniquement `var/cache/prod` dans le conteneur PHP |

`make demo-init` est prévu pour un environnement `dev`/`test` reproductible. Ne l’exécutez pas si la base locale contient des commandes à conserver.

## CI

Le workflow [`CI`](../../../.github/workflows/basic.yml) s’exécute pour les push et Pull Requests vers `master`.

Il :

1. télécharge les objets Git LFS et vérifie l’archive Chrome ;
2. crée `.env.docker` ;
3. valide Compose, construit et démarre l’environnement Docker ;
4. installe les dépendances et compile les ressources frontend ;
5. lance ESLint ;
6. exécute les tests unitaires, d’intégration, fonctionnels et Panther ;
7. lance PHPStan ;
8. arrête les conteneurs.

La CI ne lance pas la vérification PHP-CS-Fixer et ne génère pas de rapport de couverture ; ces vérifications sont faites localement lorsque nécessaire.

## Journaux et diagnostic

| Commande | Ce qu’elle affiche |
|---|---|
| `make ps` | État des conteneurs |
| `make log php` | Journal PHP |
| `make log nginx` | Journal Nginx |
| `make log postgres` | Journal PostgreSQL |
| `make log-all` | Tous les journaux |
| `make console CMD=about` | État de l’application Symfony |

## Toutes les commandes Make

| Cible | Rôle |
|---|---|
| `help` | aide intégrée |
| `init` | créer `.env.docker` et les répertoires locaux |
| `check-env` | vérifier `.env.docker` |
| `config` | valider Docker Compose |
| `build` | construire l’image PHP |
| `up` | démarrer les services principaux |
| `down` | arrêter l’environnement |
| `restart <service>` | redémarrer un service |
| `ps` | état des conteneurs |
| `log <service>` | journal du service choisi |
| `log-all` | journaux de tous les services |
| `in <service>` | shell du service choisi |
| `cache-prod-clear` | supprimer le cache prod |
| `console CMD='...'` | Symfony Console |
| `composer CMD='...'` | Composer dans le conteneur PHP |
| `composer-install` | installer les dépendances Composer |
| `npm CMD='...'` | npm dans un conteneur Node éphémère |
| `npm-install` | installer les dépendances npm |
| `assets-build` | build frontend optimisé |
| `watch` | surveiller les ressources frontend |
| `migrate` | migrations Doctrine |
| `demo-init` | données de démonstration |
| `postgres-reinit CONFIRM=postgres18` | recréer entièrement le volume PostgreSQL local |
| `check` | ESLint + PHP-CS-Fixer check + PHPStan |
| `eslint-fix` | corriger ESLint |
| `eslint-check` | vérifier ESLint |
| `php-cs-fixer` | corriger le formatage PHP |
| `php-cs-fixer-check` | vérifier le formatage PHP |
| `phpstan-check` | analyse statique PHPStan |
| `test-all-core CONFIRM=testdb` | suite principale sans Panther |
| `coverage CONFIRM=testdb` | couverture dans le terminal |
| `coverage-html CONFIRM=testdb` | couverture + HTML/Clover |
| `test-all CONFIRM=testdb` | suite complète avec Panther |
| `test-groups` | groupes PHPUnit |
| `test-list` | liste des tests PHPUnit |
| `test-unit` | tests unitaires |
| `test-db-reset CONFIRM=testdb` | recréer la base SQLite de test |
| `test-integration` | tests d’intégration |
| `test-functional` | tests fonctionnels |
| `test-functional-panther` | tests navigateur Panther |

Pour le premier démarrage et les méthodes d’obtention de Chrome for Testing, voir le [guide de démarrage](getting-started.md). Les règles `.env*` et les secrets locaux sont décrits dans le [guide de configuration](configuration.md).
