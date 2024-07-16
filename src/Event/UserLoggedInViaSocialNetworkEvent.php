<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserLoggedInViaSocialNetworkEvent extends Event
{
    public function __construct(
        private User $user,
        private string $plainPassword,
        private array $verifyEmail
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }

    public function getVerifyEmail(): array
    {
        return $this->verifyEmail;
    }
}
