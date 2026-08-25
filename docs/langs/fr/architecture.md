# Architecture

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/architecture.md) | [English](../ru/architecture.md) | [Español](../ru/architecture.md) | [中文](../ru/architecture.md) | **Français** | [Deutsch](../ru/architecture.md) |


Symfony Shop est une seule application Symfony avec des pages rendues côté serveur, une partie administration et une API. Le code est regroupé par domaines applicatifs et les routes sont centralisées dans des fichiers YAML, afin de pouvoir suivre le chemin d’une URL jusqu’à un contrôleur ou une ressource API sans démarrer l’application.

## Vue d’ensemble

```text
Navigateur
  ↓
Nginx
  ↓
Symfony
  ├─ Controller → Twig → HTML
  └─ API Platform → JSON API
          ↓
Services applicatifs / handlers
          ↓
Doctrine ORM
          ↓
PostgreSQL
```

Vue 2 est monté sur certaines pages Twig lorsqu’une interaction est nécessaire : panier, indicateur du panier et éditeur de commande. L’architecture actuelle ne comporte ni SPA indépendante ni Vue Router.

## Domaines applicatifs

| Domaine | Contenu |
|---|---|
| [`src/Account`](../../../src/Account) | inscription, connexion locale, profil, vérification email, réinitialisation du mot de passe, messages et scénarios de courrier |
| [`src/Catalog`](../../../src/Catalog) | catégories, produits, images, lecture du catalogue et requêtes Doctrine/API associées |
| [`src/Commerce`](../../../src/Commerce) | panier, lignes du panier, checkout, commandes, contrôles d’accès et notifications |
| [`src/Money`](../../../src/Money) | objets-valeurs monétaires et calculs utilisés dans les scénarios commerciaux |

Les entités Doctrine restent dans [`src/Entity`](../../../src/Entity), tandis que les services applicatifs se trouvent dans le domaine qui possède le cas d’usage concerné.

## Bundles Symfony internes

Le projet contient trois bundles Symfony internes. Ils font partie de la même application et ne sont pas des packages Composer séparés.

| Bundle | Rôle |
|---|---|
| [`src/AdminBundle`](../../../src/AdminBundle) | contrôleurs, formulaires, templates et opérations API d’administration |
| [`src/OAuthBundle`](../../../src/OAuthBundle) | clients OAuth, authenticators, association/dissociation et mapping des fournisseurs |
| [`src/SeoBundle`](../../../src/SeoBundle) | `robots.txt` et sitemap |

Les liens pointent directement vers les répertoires des modules pour pouvoir inspecter leur structure sans navigation supplémentaire.

## Routage

Les routes de l’application sont définies dans [`config/routes.yaml`](../../../config/routes.yaml) et [`config/routes/app/`](../../../config/routes/app/).

Les domaines localisés `account`, `catalog`, `commerce`, `admin` et `oauth` utilisent le préfixe `/{_locale}` avec les locales `ru|en`. Les routes SEO restent sans préfixe de langue.

API Platform est enregistré séparément via [`config/routes/api_platform.yaml`](../../../config/routes/api_platform.yaml) avec le préfixe `/api`.

Chemin pratique pour suivre une requête :

```text
URL
→ config/routes*.yaml
→ contrôleur ou ressource API
→ service applicatif / handler API
→ repository Doctrine / Doctrine ORM
```

## Doctrine et données

Les entités Doctrine se trouvent dans [`src/Entity`](../../../src/Entity), et les migrations dans [`migrations`](../../../migrations).

Principales entités :

- `User` ;
- `Category`, `Product`, `ProductImage` ;
- `Cart`, `CartProduct` ;
- `Order`, `OrderProduct` ;
- `ResetPasswordRequest`.

Les repositories et services applicatifs ne sont pas regroupés dans un répertoire commun : ils vivent près du domaine qui les utilise.

Les données de démonstration reproductibles se trouvent dans [`tools/demo`](../../../tools/demo) et ne sont chargées que pour `dev` et `test`.

## API Platform

API Platform est utilisé pour l’API applicative, et non pour publier automatiquement toutes les entités Doctrine.

L’API couvre le catalogue, le panier et les commandes. L’accès et les modifications sont aussi limités par des contrôles de droits, des extensions de requêtes, des objets d’entrée et des handlers API Platform. Le checkout utilise un objet d’entrée et un handler dédiés, tandis que les opérations administratives sur les lignes de commande sont complétées par la configuration de `AdminBundle`.

Pour analyser le comportement de l’API, ne regardez pas seulement les attributs de l’entité : vérifiez aussi les handlers API Platform, les extensions de requêtes et les règles d’accès correspondantes.

## Twig, Vue et Webpack Encore

La plupart des pages sont rendues avec Twig. Les templates communs sont dans [`templates`](../../../templates), et ceux des bundles internes dans leurs modules respectifs.

Webpack Encore compile les ressources de [`assets`](../../../assets) vers `public/build`. Vue 2 est utilisé ponctuellement comme couche interactive au-dessus des pages rendues côté serveur.

L’architecture cliente actuelle est conservée jusqu’à la migration distincte vers Inertia.js et Vue 3.

## Configuration et injection de dépendances

[`config/services.yaml`](../../../config/services.yaml) active l’injection automatique de dépendances (`autowiring`) pour le code de l’application et contient la configuration explicite des services qui nécessitent des paramètres spéciaux ou des mappings de fournisseurs.

Les paramètres Doctrine, Security, Messenger, Mailer, Twig et API Platform se trouvent dans [`config/packages`](../../../config/packages).

## Tests

| Répertoire / groupe | Rôle |
|---|---|
| [`tests/Unit`](../../../tests/Unit) | règles et services isolés de l’application |
| [`tests/Integration`](../../../tests/Integration) | Doctrine et interaction entre plusieurs services |
| [`tests/Functional`](../../../tests/Functional) | HTTP, contrôleurs, API et règles d’accès |
| `functional-panther` | scénarios navigateur via Panther |
| [`tests/TestUtils`](../../../tests/TestUtils) | utilitaires communs et remplacements des clients OAuth externes |

La couverture PHP/PHPUnit est calculée sur `src` et `tools/demo` ; Panther n’est pas inclus dans le rapport. Les commandes sont regroupées dans le [guide de développement](development.md).

## Docker

Docker Compose démarre trois services permanents :

| Service | Rôle |
|---|---|
| `php` | PHP-FPM, Composer, Symfony Console et environnement Panther |
| `nginx` | entrée HTTP et fichiers statiques |
| `postgres` | PostgreSQL avec volume de données persistant |

`node` appartient au profil `tools` et sert aux commandes npm ponctuelles et aux builds frontend. Docker Compose ne dispose actuellement d’aucun worker Messenger permanent.

Le premier démarrage est décrit dans le [guide de démarrage](getting-started.md), et les couches `.env*` dans le [guide de configuration](configuration.md).
