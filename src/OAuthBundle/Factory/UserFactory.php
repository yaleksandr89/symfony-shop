<?php

declare(strict_types=1);

namespace App\OAuthBundle\Factory;

use App\Entity\User;
use App\OAuthBundle\Provider\Facebook\FacebookUser;
use App\OAuthBundle\Provider\Linkedin\LinkedinUser;
use App\OAuthBundle\Provider\Vk\VkUser;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\GoogleUser;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

class UserFactory
{
    public static function createUserFromGoogle(GoogleUser $googleUser): User
    {
        $user = new User();
        $user->setEmail($googleUser->getEmail());
        $user->setFullName($googleUser->getName());
        $user->setGoogleId($googleUser->getId());

        return $user;
    }

    public static function createUserFromYandex(YandexResourceOwner $yandexUser, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($yandexUser->getRealName() ?? $yandexUser->getDisplayName() ?? $yandexUser->getLogin());
        $user->setYandexId($yandexUser->getId());

        return $user;
    }

    public static function createUserFromVk(VkUser $vkontakteUser): User
    {
        $user = new User();
        $user->setEmail($vkontakteUser->getEmail());
        $user->setFullName($vkontakteUser->getFullName());
        $user->setVkontakteId($vkontakteUser->getId());

        return $user;
    }

    public static function createUserFromGithub(GithubResourceOwner $githubUser): User
    {
        $user = new User();
        $user->setEmail($githubUser->getEmail());
        $user->setFullName($githubUser->getName());
        $user->setGithubId((string) $githubUser->getId());

        return $user;
    }

    public static function createUserFromFacebook(FacebookUser $facebookUser, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($facebookUser->getName());
        $user->setFacebookId($facebookUser->getId());

        return $user;
    }

    public static function createUserFromLinkedin(LinkedinUser $linkedinUser, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($linkedinUser->getName());
        $user->setLinkedinId($linkedinUser->getId());

        return $user;
    }
}
