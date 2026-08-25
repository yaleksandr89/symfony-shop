# Contribuer à Symfony Shop

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../.github/CONTRIBUTING.md) | [English](../en/CONTRIBUTING.md) | [Español](../es/CONTRIBUTING.md) | [中文](../zh/CONTRIBUTING.md) | **Français** | [Deutsch](../de/CONTRIBUTING.md) |

Merci de votre intérêt pour Symfony Shop. Il s’agit d’un projet e-commerce éducatif sur Symfony avec un environnement Docker, PostgreSQL, API Platform, OAuth et quelques composants interactifs en Vue.

## Avant de commencer

Consultez les Discussions, Issues et Pull Requests existants et gardez chaque changement centré sur une tâche claire. Les questions et idées sont à discuter d’abord dans [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions), les bugs reproductibles et améliorations concrètes dans Issues, et les problèmes de sécurité selon la [politique de sécurité](SECURITY.md), sans publier de détails d’exploitation.

## Limites du projet

- L’environnement local pris en charge utilise Docker Compose et le Makefile.
- PHP, Composer, PostgreSQL, Node.js et l’environnement navigateur ne s’exécutent pas directement sur l’hôte dans le workflow normal.
- Les changements ne doivent pas affaiblir discrètement les règles d’accès, les flux OAuth, l’intégrité du panier/des commandes ou d’autres contrats existants.
- N’ajoutez pas de refactorisation large ni de mise à jour de dépendances sans rapport avec la tâche.
- L’architecture frontend Vue 2 reste en place jusqu’à la migration distincte vers Inertia.js et Vue 3.

L’architecture est décrite dans [`architecture.md`](architecture.md), et les commandes de développement dans [`development.md`](development.md).

## Branches

Créez une branche dédiée depuis le `master` actuel. Le nom doit décrire brièvement le changement, par exemple :

```text
fix/cart-quantity
docs/oauth
refactor/catalog-query
```

Les changements arrivent dans `master` via une Pull Request.

## Commits

Le projet utilise Conventional Commits avec une description écrite en russe :

```text
fix: исправить проверку количества товара
docs: уточнить настройку OAuth
refactor: упростить выборку каталога
```

Un commit doit contenir un groupe de changements logiquement cohérent.

## Vérifications locales

Lisez le Makefile actuel avant d’exécuter des commandes. Vérifications principales :

| Commande | Rôle |
|---|---|
| `make check` | ESLint + vérification PHP-CS-Fixer + PHPStan |
| `make test-unit` | tests unitaires |
| `make test-integration` | tests d’intégration |
| `make test-functional` | tests fonctionnels |
| `make test-functional-panther` | tests navigateur Panther |
| `make test-all CONFIRM=testdb` | ensemble complet, Panther inclus |

Exécutez les vérifications liées au changement. Utilisez l’ensemble complet lorsque des frontières communes de l’application sont touchées ou avant la validation finale d’un changement important.

## Pull Request

Dans la description de la Pull Request, indiquez :

- ce qui a changé et pourquoi ;
- comment le changement a été vérifié ;
- si des étapes manuelles sont nécessaires ;
- si la configuration, les données, OAuth, les droits d’accès ou un autre contrat important sont touchés ;
- si la documentation a été mise à jour lorsque l’usage public du projet change.

## Liste de contrôle

- Aucun secret, identifiant OAuth réel, token d’accès, cookie, identifiant de session ou contenu de `.env*` local.
- Aucun changement sans rapport avec la tâche dans le diff.
- `git diff --check` passe.
- Les vérifications pertinentes ont été exécutées.
- Les nouveaux tests protègent un comportement concret et ne sont pas ajoutés pour faire du volume.
- La documentation est mise à jour lorsqu’un contrat public, la configuration ou le mode de démarrage change.
