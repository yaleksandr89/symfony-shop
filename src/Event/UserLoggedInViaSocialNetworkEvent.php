<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserLoggedInViaSocialNetworkEvent extends Event
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    private $plainPassword;

    /**
     * @var array
     */
    private $verifyEmail;

    public function __construct(User $user, string $plainPassword, array $verifyEmail)
    {
        $this->user = $user;
        $this->plainPassword = $plainPassword;
        $this->verifyEmail = $verifyEmail;
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
