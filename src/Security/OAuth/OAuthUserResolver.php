<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserChecker\DeletedUserChecker;
use InvalidArgumentException;

final class OAuthUserResolver
{
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_YANDEX = 'yandex';
    public const PROVIDER_VKONTAKTE = 'vkontakte';
    public const PROVIDER_GITHUB_EN = 'github_en';
    public const PROVIDER_GITHUB_RUS = 'github_rus';

    private const PROVIDER_SOCIAL_ID_FIELDS = [
        self::PROVIDER_GOOGLE => ['googleId', 'setGoogleId'],
        self::PROVIDER_YANDEX => ['yandexId', 'setYandexId'],
        self::PROVIDER_VKONTAKTE => ['vkontakteId', 'setVkontakteId'],
        self::PROVIDER_GITHUB_EN => ['githubId', 'setGithubId'],
        self::PROVIDER_GITHUB_RUS => ['githubId', 'setGithubId'],
    ];

    public function __construct(
        private UserRepository $userRepository,
        private DeletedUserChecker $deletedUserChecker
    ) {
    }

    /**
     * @param callable(): User $newUserFactory
     */
    public function resolve(string $provider, string $externalId, string $email, callable $newUserFactory): OAuthUserResolution
    {
        if (!isset(self::PROVIDER_SOCIAL_ID_FIELDS[$provider])) {
            throw new InvalidArgumentException(sprintf('Unknown OAuth provider "%s".', $provider));
        }

        [$socialIdField, $socialIdSetter] = self::PROVIDER_SOCIAL_ID_FIELDS[$provider];

        $user = $this->userRepository->findOneBy([$socialIdField => $externalId]);

        if ($user instanceof User) {
            $this->deletedUserChecker->checkPreAuth($user);

            return new OAuthUserResolution($user, false, false);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user instanceof User) {
            $this->deletedUserChecker->checkPreAuth($user);
            $user->{$socialIdSetter}($externalId);

            return new OAuthUserResolution($user, false, true);
        }

        return new OAuthUserResolution($newUserFactory(), true, true);
    }
}
