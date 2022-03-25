<?php

declare(strict_types=1);

namespace App\Utils\Factory;

use App\Entity\User;
use League\OAuth2\Client\Provider\GoogleUser;

class UserFactory
{
    /**
     * @param GoogleUser $googleUser
     *
     * @return User
     */
    public static function createUserFromGoogleUser(GoogleUser $googleUser): User
    {
        $user = new User();
        $user->setEmail($googleUser->getEmail());
        $user->setFullName($googleUser->getName());
        $user->setGoogleId($googleUser->getId());
        $user->setIsVerified(true);

        return $user;
    }
}
