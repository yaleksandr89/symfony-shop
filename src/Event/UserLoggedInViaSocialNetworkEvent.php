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

    /**
     * @param User   $user
     * @param string $plainPassword
     * @param array  $verifyEmail
     */
    public function __construct(User $user, string $plainPassword, array $verifyEmail)
    {
        $this->user = $user;
        $this->plainPassword = $plainPassword;
        $this->verifyEmail = $verifyEmail;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }

    public function getVerifyEmail(): array
    {
        return $this->verifyEmail;
    }
}
