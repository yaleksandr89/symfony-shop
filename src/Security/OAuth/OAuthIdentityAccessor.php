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
            default => throw new \LogicException('Unsupported OAuth identity provider.'),
        };
    }
}
