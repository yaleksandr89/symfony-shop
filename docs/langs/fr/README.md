# Symfony Shop

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fsymfony--shop-blue.svg?style=flat-square)](https://github.com/yaleksandr89/symfony-shop)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000.svg?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18.4-4169E1.svg?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED.svg?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![CI](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml/badge.svg)](https://github.com/yaleksandr89/symfony-shop/actions/workflows/basic.yml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](../../../LICENSE.md)

<p align="center">
  <img
    src="../../img/symfony-shop-readme-cover.png"
    alt="Symfony Shop — boutique en ligne avec Symfony, Docker et PostgreSQL"
    width="100%"
  >
</p>

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../README.md) | [English](../en/README.md) | [Español](../es/README.md) | [中文](../zh/README.md) | **Sélectionné** | [Deutsch](../de/README.md) |

Symfony Shop est une boutique en ligne éducative construite avec Symfony. Le projet comprend un catalogue de produits, un panier et le passage de commande, un compte utilisateur, une partie administration, une API et la connexion via OAuth. La plupart des pages sont rendues avec Twig, tandis que Vue 2 est utilisé pour certains éléments interactifs de l’interface.

L’environnement local pris en charge repose sur Docker Compose. PHP, Composer, Node.js, PostgreSQL et Chrome for Testing s’exécutent dans les conteneurs ou sont installés dans l’image Docker, et les opérations principales sont regroupées dans un Makefile unique. L’exécution avec PHP, Composer et PostgreSQL installés directement sur l’hôte n’est pas un scénario pris en charge et n’est pas vérifiée par la CI.

## Fonctionnalités

- catalogue de catégories et de produits avec images, nouveautés et remises ;
- panier avec contrôle de disponibilité et passage de commande ;
- inscription, connexion, vérification de l’email et réinitialisation du mot de passe ;
- compte utilisateur ;
- OAuth via Google, Yandex, VKontakte, GitHub, Facebook et LinkedIn ;
- scénarios distincts pour la connexion OAuth, l’association et la dissociation d’un compte externe ;
- administration des utilisateurs, catégories, produits et commandes ;
- API basée sur API Platform ;
- tests unitaires, d’intégration, fonctionnels et navigateur ;
- CI GitHub Actions utilisant le même environnement Docker.

## Démarrage rapide

L’hôte doit disposer de Git, Make et Docker avec Compose. Git LFS est recommandé pour un clonage classique ; la grande archive du navigateur peut également être obtenue sans Git LFS.

> [!NOTE]
> Make est un outil en ligne de commande courant sur les systèmes de type Unix. Sous Linux et macOS, le projet peut être lancé directement depuis le terminal. Sous Windows, la configuration recommandée est WSL2 avec Docker Desktop.

| Commande | Rôle | Remarque |
|---|---|---|
| `git clone https://github.com/yaleksandr89/symfony-shop.git` | Clone le dépôt | |
| `cd symfony-shop` | Entre dans le répertoire du projet | |
| `git lfs install` | Active Git LFS | Uniquement pour le scénario Git LFS |
| `git lfs pull` | Télécharge Chrome for Testing | À exécuter avant `make build` |
| `make init` | Crée `.env.docker` et les répertoires locaux | Ne remplace pas un `.env.docker` existant |
| `make build` | Construit l’image PHP | L’image contient Chrome et Chromedriver pour Panther |
| `make up` | Démarre PHP-FPM, Nginx et PostgreSQL | |
| `make composer-install` | Installe les dépendances PHP depuis `composer.lock` | Composer n’est pas requis sur l’hôte |
| `make npm-install` | Installe les dépendances depuis `package-lock.json` | Node.js n’est pas requis sur l’hôte |
| `make assets-build` | Compile les ressources frontend | |
| `make migrate` | Applique les migrations Doctrine | |
| `make demo-init` | Crée les données de démonstration | Uniquement en local `dev`/`test` |

Après le démarrage, l’application est disponible par défaut sur [http://localhost:8080](http://localhost:8080).

> [!IMPORTANT]
> Le projet fixe Chrome for Testing `150.0.7871.46`. La méthode recommandée pour obtenir l’archive est `git lfs pull`. À partir de `v3.0.0`, le ZIP du projet peut être téléchargé depuis [Releases](https://github.com/yaleksandr89/symfony-shop/releases) avec Chrome for Testing déjà inclus, ce qui rend Git LFS inutile pour ce scénario. La version fixée peut aussi être téléchargée directement depuis la source officielle. Les liens exacts, le nom du fichier et le SHA-256 figurent dans le [guide de démarrage](getting-started.md).

> [!IMPORTANT]
> Les valeurs de `.env.docker` sont transmises au conteneur PHP comme variables d’environnement du processus. Si une même clé est définie à la fois dans `.env.docker` et `.env.local`, la valeur de `.env.docker` est prioritaire. Le schéma complet est décrit dans le [guide de configuration](configuration.md).

> [!WARNING]
> `make demo-init` recrée les commandes de démonstration. Ne l’exécutez pas sur une base locale contenant des données à conserver.

Le premier démarrage détaillé, les trois méthodes pour obtenir Chrome for Testing et la gestion des conteneurs sont décrits dans le [guide de démarrage](getting-started.md).

## Courrier et file de messages

Par défaut, `MAILER_DSN=null://null` : l’application n’envoie donc aucun courrier via un service SMTP externe. Les messages envoyés de manière synchrone pendant une requête HTTP peuvent être consultés dans le panneau Mailer de Symfony Profiler.

L’inscription et la réinitialisation du mot de passe utilisent le transport Messenger `async`. Le routage vers la file est déjà configuré, mais Docker Compose ne démarre pas encore de worker permanent. Ces messages ne sont donc traités qu’après l’exécution de :

```text
make console CMD='messenger:consume async -vv'
```

La configuration du transport, du courrier et des secrets locaux est décrite dans le [guide de configuration](configuration.md).

## OAuth

La connexion OAuth et l’association d’un compte externe à un utilisateur existant sont deux opérations distinctes. La correspondance de l’adresse email chez le fournisseur ne suffit pas pour associer automatiquement une identité externe à un compte local existant.

Pour associer un compte, l’utilisateur se connecte d’abord normalement, confirme son mot de passe actuel puis lance explicitement le flux OAuth depuis son compte. La dissociation est elle aussi protégée par le mot de passe actuel et un jeton CSRF.

Les fournisseurs pris en charge, variables d’environnement, routes et règles de sécurité sont décrits dans le [guide OAuth](oauth.md). Les règles générales de configuration locale et de secrets figurent dans le [guide de configuration](configuration.md).

## Structure du projet

```text
Navigateur
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
  ↓
Services applicatifs / Doctrine
  ↓
PostgreSQL
```

Le code principal est regroupé dans les domaines `Account`, `Catalog` et `Commerce`. L’administration, OAuth et SEO sont implémentés sous forme de bundles Symfony internes. Vue 2 est utilisé pour certains composants interactifs, et non comme SPA indépendante.

La carte des répertoires, le routage, API Platform, Doctrine et les limites du frontend sont décrits dans le [guide d’architecture](architecture.md).

## Vérifications

| Commande | Rôle | Remarque |
|---|---|---|
| `make check` | Lance ESLint, la vérification PHP-CS-Fixer et PHPStan | Les tests ne sont pas inclus |
| `make test-unit` | Lance les tests unitaires | |
| `make test-integration` | Lance les tests d’intégration | |
| `make test-functional` | Lance les tests fonctionnels | |
| `make test-functional-panther` | Lance les tests navigateur avec Panther | Chrome est déjà dans l’image PHP |
| `make test-all CONFIRM=testdb` | Lance l’ensemble des tests | Recrée la base de tests |
| `make coverage CONFIRM=testdb` | Affiche la couverture PHP/PHPUnit dans le terminal | Panther n’est pas inclus |
| `make coverage-html CONFIRM=testdb` | Génère les rapports HTML et Clover | `var/coverage/html`, `var/coverage/clover.xml` |

La liste complète des commandes Make, le fonctionnement de la base de tests et la composition de la CI figurent dans le [guide de développement](development.md).

## À venir

1. **Environnement local de courrier.** Ajouter un service de courrier avec interface web et un worker Messenger permanent afin que les messages du transport `async` soient traités automatiquement.
2. **Inertia.js et Vue 3.** Faire évoluer l’interaction serveur/client vers Inertia.js et Vue 3. Je souhaite aussi revoir la localisation pendant cette migration : selon l’ampleur des changements, il sera peut-être possible de supprimer le préfixe `/{_locale}` obligatoire dans les URL. Ce choix sera fait lors de la conception du nouveau frontend.
3. **Administration.** Après la migration du frontend, étendre fortement les possibilités de gestion de la boutique depuis l’interface d’administration.

## Retour et échanges

- bugs reproductibles — [GitHub Issues](https://github.com/yaleksandr89/symfony-shop/issues) ;
- questions et idées — [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions).

## Historique du projet

### 2026 — préparation de v3.0.0

- Docker Compose devient l’environnement principal de développement. Un Makefile unique, un bootstrap reproductible, PostgreSQL dans Docker, des données de démonstration, Xdebug et APCu sont ajoutés.
- La CI passe à GitHub Actions et utilise le même workflow Docker que le développement local.
- Le stack backend est progressivement mis à jour vers PHP 8.5, Symfony 8.1, API Platform 4.3, Doctrine ORM 3 / DBAL 4, PHPUnit 13 et PHPStan 2.
- La sécurité et les frontières métier du panier, du checkout, de l’API, de l’inscription, de la réinitialisation du mot de passe et d’OAuth sont largement retravaillées.
- OAuth est étendu à Facebook et LinkedIn ; connexion, inscription, association et dissociation deviennent des flux distincts protégés par des contrôles dédiés.
- Selenium, GeckoDriver, l’outillage Java et Deployer sont supprimés. Les tests navigateur passent à Panther et Chrome for Testing ; l’archive Chrome est stockée via Git LFS.
- L’architecture est réorganisée autour de `Account`, `Catalog` et `Commerce`, ainsi que `AdminBundle`, `OAuthBundle` et `SeoBundle` ; le routage et le callback OAuth commun sont centralisés.
- L’infrastructure de tests est reconstruite avec des quality gates Docker et des commandes de couverture.
- La documentation est entièrement réécrite avec des guides dédiés au démarrage, à la configuration, au développement, à OAuth et à l’architecture.
- La licence est uniformisée en MIT ; GitHub Issues/Discussions, les modèles de Pull Request, le guide de contribution et la politique de sécurité sont ajoutés.

### 2024 — v2.3.0

- Symfony est mis à jour vers 6.4.9.
- PHPUnit passe de 9 à 11 et DAMA Doctrine Test Bundle à la version 8 ; les tests existants sont refactorisés.
- La migration des annotations vers les attributs PHP et le nettoyage PHPStan se poursuivent.
- Selenium, ChromeDriver et GeckoDriver sont mis à jour.
- Des exemples Nginx et Supervisor, des instructions Deployer et des traductions du README sont ajoutés.

### 2023 — v2.1.1 / v2.2.0

- Symfony est mis à jour vers 6.3.1, les dépendances sont actualisées et les avertissements de dépréciation du code propriétaire sont supprimés.
- Une nouvelle phase de refactorisation et de nettoyage PHPStan est menée.
- La configuration Deployer est mise à jour.
- CircleCI est supprimé après l’arrêt du service pour les utilisateurs en Russie.

### 2022 — v1.2.0 / v2.0.0 / v2.1.0

- Les fonctionnalités principales de la boutique sont établies.
- L’authentification OAuth via Google, Yandex, VKontakte et GitHub est ajoutée.
- Symfony est progressivement mis à jour de 5.4 à 6.0.
- L’association et la dissociation de comptes OAuth externes sont ajoutées au compte utilisateur.
- Une protection empêche la réutilisation d’une même identité externe par plusieurs utilisateurs locaux.

### 2021 — début du projet

- La première version de Symfony Shop est créée sur Symfony 5.3 avec PostgreSQL.

---

<p align="center">
  Si ce projet vous a été utile, ajoutez-lui une étoile sur GitHub : cela aidera d’autres développeurs à le découvrir. 🤘
</p>
