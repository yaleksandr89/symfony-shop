<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use App\Entity\User;

final class OAuthUserResolution
{
    public function __construct(
        private readonly User $user,
        private readonly bool $newUser,
    ) {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function isNewUser(): bool
    {
        return $this->newUser;
    }
}
