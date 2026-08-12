<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;

final class OAuthIdentityAccessor
{
    public function getExternalId(User $user, OAuthProvider $provider): ?string
    {
        return match ($provider) {
            OAuthProvider::Google => $user->getGoogleId(),
            OAuthProvider::Yandex => $user->getYandexId(),
            OAuthProvider::Vkontakte => $user->getVkontakteId(),
            OAuthProvider::GithubEn,
            OAuthProvider::GithubRus => $user->getGithubId(),
            OAuthProvider::Facebook => $user->getFacebookId(),
            OAuthProvider::Linkedin => $user->getLinkedinId(),
            default => throw new \LogicException('Unsupported OAuth identity provider.'),
        };
    }

    public function unlink(User $user, OAuthProvider $provider): void
    {
        match ($provider) {
            OAuthProvider::Google => $user->setGoogleId(null),
            OAuthProvider::Yandex => $user->setYandexId(null),
            OAuthProvider::Vkontakte => $user->setVkontakteId(null),
            OAuthProvider::GithubEn,
            OAuthProvider::GithubRus => $user->setGithubId(null),
            OAuthProvider::Facebook => $user->setFacebookId(null),
            OAuthProvider::Linkedin => $user->setLinkedinId(null),
            default => throw new \LogicException('Unsupported OAuth identity provider.'),
        };
    }

    public function link(User $user, OAuthProvider $provider, string $externalId): void
    {
        match ($provider) {
            OAuthProvider::Google => $user->setGoogleId($externalId),
            OAuthProvider::Yandex => $user->setYandexId($externalId),
            OAuthProvider::Vkontakte => $user->setVkontakteId($externalId),
            OAuthProvider::GithubEn,
            OAuthProvider::GithubRus => $user->setGithubId($externalId),
            OAuthProvider::Facebook => $user->setFacebookId($externalId),
            OAuthProvider::Linkedin => $user->setLinkedinId($externalId),
            default => throw new \LogicException('Unsupported OAuth identity provider.'),
        };
    }

    public function identityField(OAuthProvider $provider): string
    {
        return match ($provider) {
            OAuthProvider::Google => 'googleId',
            OAuthProvider::Yandex => 'yandexId',
            OAuthProvider::Vkontakte => 'vkontakteId',
            OAuthProvider::GithubEn,
            OAuthProvider::GithubRus => 'githubId',
            OAuthProvider::Facebook => 'facebookId',
            OAuthProvider::Linkedin => 'linkedinId',
            default => throw new \LogicException('Unsupported OAuth identity provider.'),
        };
    }
}
