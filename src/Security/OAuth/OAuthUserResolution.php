<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;

final class OAuthUserResolution
{
    public function __construct(
        private User $user,
        private bool $newUser,
        private bool $requiresFlush,
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

    public function requiresFlush(): bool
    {
        return $this->requiresFlush;
    }
}
