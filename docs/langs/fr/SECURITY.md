# Sécurité

## Choisir la langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../.github/SECURITY.md) | [English](../en/SECURITY.md) | [Español](../es/SECURITY.md) | [中文](../zh/SECURITY.md) | **Français** | [Deutsch](../de/SECURITY.md) |

Merci de signaler les vulnérabilités potentielles de manière responsable. Symfony Shop est un projet éducatif public, mais les problèmes liés à l’authentification, OAuth, au panier, au checkout, à l’API, au traitement des entrées utilisateur et à la configuration sont considérés comme des problèmes normaux de sécurité applicative.

## Ce qui doit être signalé en privé

- contournement de l’authentification ou de l’autorisation ;
- possibilité de se connecter au compte d’un autre utilisateur ou d’associer une identité OAuth externe au mauvais utilisateur local ;
- contournement de la protection CSRF sur une opération modifiant l’état ;
- injection SQL/DQL ;
- XSS stocké ou réfléchi ;
- lecture ou modification du panier, d’une commande ou de données d’administration appartenant à un autre utilisateur ;
- contournement des restrictions de l’API ou divulgation de données via l’API ;
- exposition de `.env`, identifiants OAuth, tokens d’accès, cookies, identifiants de session, exceptions internes ou autres informations sensibles ;
- contournement du mécanisme serveur désactivant un fournisseur OAuth ;
- vulnérabilité exploitable d’une dépendance ayant un impact important sur le projet ;
- compromission de la CI, du code source ou de la chaîne d’approvisionnement des dépendances.

## Ce qui peut être publié dans Issues

- bug reproductible de l’interface sans impact sur la sécurité ;
- erreur du catalogue, du panier ou de l’administration sans accès aux données d’un autre utilisateur ;
- problème Docker/bootstrap ou de compatibilité ;
- erreur de documentation ;
- proposition d’amélioration.

En cas de doute sur le caractère sensible d’un problème, utilisez d’abord un canal privé.

## Comment signaler

- Si la section Security du dépôt propose un formulaire privé de signalement des vulnérabilités, utilisez-le en priorité.
- Ne publiez pas de code d’exploitation, secrets réels, identifiants OAuth, tokens d’accès, cookies, identifiants de session ou contenu local de `.env*` dans Issues, Pull Requests ou les logs.
- Si aucun formulaire privé n’est disponible, créez un Issue public minimal sans détails d’exploitation et demandez un canal privé.
- Ne divulguez pas publiquement les détails techniques d’exploitation avant la disponibilité d’un correctif.

## Informations à fournir

Lorsque c’est possible, indiquez :

- le SHA du commit ou la branche ;
- la zone de l’application concernée ;
- l’impact ;
- les étapes minimales de reproduction ;
- un extrait nettoyé de requête, réponse ou log si nécessaire ;
- les versions PHP/Symfony/PostgreSQL lorsqu’elles sont utiles à la reproduction.

Utilisez uniquement des données synthétiques. Ne joignez pas de vrais mots de passe, tokens, ID externes, cookies, identifiants de session ou contenu local de `.env*`.

## Suite du traitement

- Le projet est maintenu par un seul auteur ; aucun SLA n’est garanti.
- Le signalement sera vérifié et, si nécessaire, suivi d’un correctif et d’un contrôle de régression.
- Aucun programme de bug bounty n’est promis.
- La divulgation publique devrait de préférence attendre la disponibilité du correctif.
