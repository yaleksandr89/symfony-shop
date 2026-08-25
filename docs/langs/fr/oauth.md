# OAuth

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/oauth.md) | [English](../ru/oauth.md) | [Español](../ru/oauth.md) | [中文](../ru/oauth.md) | **Français** | [Deutsch](../ru/oauth.md) |


Symfony Shop utilise OAuth pour la connexion et l’inscription via un service externe, ainsi que pour l’association explicite d’un tel compte à un utilisateur local existant. Ces scénarios sont séparés : une adresse email identique ne constitue pas à elle seule une preuve de propriété du compte local.

Terminologie utilisée ici :

- **fournisseur** — service externe de connexion, par exemple Google ou GitHub ;
- **ID externe** — identifiant du compte utilisateur chez le fournisseur ;
- **callback** — retour de l’utilisateur vers l’application après autorisation chez le fournisseur ;
- **state** — token aléatoire reliant le début du scénario OAuth au callback.

## Fournisseurs pris en charge

| Fournisseur | Nom dans l’application | Champ `User` |
|---|---|---|
| Google | `google` | `google_id` |
| Yandex | `yandex` | `yandex_id` |
| VKontakte | `vkontakte` | `vkontakte_id` |
| GitHub EN | `github_en` | `github_id` |
| GitHub RU | `github_rus` | `github_id` |
| Facebook | `facebook` | `facebook_id` |
| LinkedIn | `linkedin` | `linkedin_id` |

GitHub EN et GitHub RU utilisent des clients OAuth distincts mais le même identifiant externe `github_id`. Un même compte GitHub ne peut pas être associé à deux utilisateurs locaux via des clients différents.

Mail.ru n’est volontairement pas pris en charge : aucun client OAuth ni aucune route n’existe pour lui, et `OAUTH_MAILRU_ENABLED` doit rester à `0`.

## Configuration d’un fournisseur

Tous les fournisseurs implémentés sont désactivés par défaut.

| Fournisseur | Interrupteur | Client ID | Client secret |
|---|---|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` | `OAUTH_GOOGLE_ID` | `OAUTH_GOOGLE_SECRET` |
| Yandex | `OAUTH_YANDEX_ENABLED` | `OAUTH_YANDEX_CLIENT_ID` | `OAUTH_YANDEX_CLIENT_SECRET` |
| VKontakte | `OAUTH_VK_ENABLED` | `OAUTH_VK_CLIENT_ID` | `OAUTH_VK_CLIENT_SECRET` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` | `OAUTH_GITHUB_EN_CLIENT_ID` | `OAUTH_GITHUB_EN_CLIENT_SECRET` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` | `OAUTH_GITHUB_RUS_CLIENT_ID` | `OAUTH_GITHUB_RUS_CLIENT_SECRET` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` | `OAUTH_FACEBOOK_CLIENT_ID` | `OAUTH_FACEBOOK_CLIENT_SECRET` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` | `OAUTH_LINKEDIN_CLIENT_ID` | `OAUTH_LINKEDIN_CLIENT_SECRET` |

Exemple pour `.env.local` :

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

L’interrupteur est appliqué côté serveur et ne contrôle pas seulement la visibilité du bouton. Avec `*_ENABLED=0`, les nouveaux scénarios de connexion, inscription et association sont bloqués avant tout appel au fournisseur.

Les vrais identifiants ne doivent pas être ajoutés à Git. La priorité entre `.env.local` et les variables Docker est décrite dans le [guide de configuration](configuration.md).

## Connexion et inscription normales

Les cas principaux se comparent plus facilement ensemble :

| Situation | Résultat | Ce qui ne se produit pas |
|---|---|---|
| L’ID externe est déjà associé | Connexion au même compte local | l’email local n’est pas remplacé par celui du fournisseur et l’association n’est pas réécrite |
| L’ID externe est nouveau, mais cet email existe déjà localement | La connexion est refusée avec un message neutre | aucune association automatique, connexion au compte trouvé, création d’utilisateur ou email d’inscription |
| L’ID externe et l’email sont nouveaux | Création d’un utilisateur local non vérifié puis connexion OAuth | le fournisseur ne valide pas automatiquement l’email local et aucun mot de passe aléatoire n’est envoyé |
| Le fournisseur ne renvoie pas d’email | La connexion est refusée avec un message neutre | aucun utilisateur n’est créé et aucune donnée n’est modifiée |

Si l’ID externe est déjà associé à un utilisateur local supprimé, la connexion est également refusée.

Pour un nouvel utilisateur, l’application conserve l’email et l’ID externe, garde `isVerified=false`, génère un mot de passe interne aléatoire et n’en stocke que le hash. Après l’enregistrement, le scénario normal de vérification de l’email démarre. L’utilisateur peut définir un mot de passe local connu via la réinitialisation du mot de passe.

L’email d’inscription est traité via Messenger `async`. Docker Compose n’a actuellement aucun worker permanent ; pour vérifier ce scénario localement, il faut lancer séparément `make console CMD='messenger:consume async -vv'`. Voir la [section courrier et Messenger](configuration.md).

Les erreurs d’échange du token OAuth ou de récupération du profil sont transformées en erreur applicative sûre, sans afficher la réponse du fournisseur à l’utilisateur.

## Association explicite à un compte existant

L’association est initiée par un utilisateur local déjà authentifié.

| Étape | Ce qui se passe |
|---|---|
| `GET` de la page d’association | Affichage d’un formulaire de confirmation ; aucune donnée ne change |
| `POST` du formulaire | Vérification du mot de passe actuel et du token CSRF |
| Redirection vers le fournisseur | Création dans la session d’une intention d’association à usage unique |
| Callback du fournisseur | Vérification de l’utilisateur, du fournisseur, du `state` OAuth et de la durée de vie de l’intention |
| Succès | Seul l’ID externe du fournisseur choisi est écrit |

L’intention reste dans la session au maximum 600 secondes et est liée à un utilisateur et un fournisseur précis. Le `state` OAuth original n’y est pas stocké ; seul son hash SHA-256 est conservé. L’intention est à usage unique, donc un callback rejoué est refusé.

L’association ne recherche pas l’utilisateur par email et ne modifie pas la session de connexion courante. Si l’ID externe appartient déjà à un autre utilisateur, aucune association n’est créée. La dernière protection contre les écritures concurrentes reste la contrainte unique en base de données.

## Dissociation

La dissociation se fait également depuis un compte authentifié.

| Étape | Ce qui se passe |
|---|---|
| `GET` de la page de dissociation | Affichage d’un formulaire ; l’ID externe reste inchangé |
| `POST` du formulaire | Vérification du mot de passe actuel et du token CSRF |
| Succès | Seul le champ OAuth sélectionné est vidé |

Le champ `User` est choisi côté serveur à partir d’un nom de fournisseur autorisé. Le client ne transmet ni nom de méthode setter ni nom de champ arbitraire.

Si un fournisseur est désactivé après l’association, l’utilisateur peut toujours supprimer le lien existant. L’interrupteur bloque les nouveaux scénarios OAuth mais pas une dissociation sûre.

## Routes

Les routes OAuth normales se trouvent sous `/{_locale}`, avec `ru` et `en` pris en charge.

| Fournisseur | Début du scénario OAuth | Callback |
|---|---|---|
| Google | `/{_locale}/connect/google` | `/{_locale}/connect/google/check` |
| Yandex | `/{_locale}/connect/yandex` | `/{_locale}/connect/yandex/check` |
| VKontakte | `/{_locale}/connect/vkontakte` | `/{_locale}/connect/vkontakte/check` |
| GitHub EN | `/{_locale}/connect/github-en` | `/{_locale}/connect/github-en/check` |
| GitHub RU | `/{_locale}/connect/github-ru` | `/{_locale}/connect/github-ru/check` |
| Facebook | `/{_locale}/connect/facebook` | `/{_locale}/connect/facebook/check` |
| LinkedIn | `/{_locale}/connect/linkedin` | `/{_locale}/connect/linkedin/check` |

Ces routes sont utilisées par le scénario GET du navigateur, mais la configuration YAML actuelle ne définit pas de restriction HTTP spécifique pour elles au niveau du Symfony Router.

Les opérations du compte utilisateur ont des méthodes explicites :

| Opération | Route | Méthodes |
|---|---|---|
| Association | `/{_locale}/profile/oauth/{provider}/link` | `GET`, `POST` |
| Dissociation | `/{_locale}/profile/oauth/{provider}/unlink` | `GET`, `POST` |

Pour `{provider}`, les valeurs autorisées sont `google`, `yandex`, `vkontakte`, `github_en`, `github_rus`, `facebook` et `linkedin`.

## Unicité de l’ID externe

Les champs `google_id`, `yandex_id`, `vkontakte_id`, `github_id`, `facebook_id` et `linkedin_id` sont protégés par des contraintes uniques dans Doctrine et la base de données. Un même compte externe ne peut pas appartenir simultanément à deux utilisateurs locaux.
