<?php

declare(strict_types=1);

namespace App\Utils\Authenticator;

use App\Entity\User;
use Symfony\Component\Security\Core\Security;

trait CheckingUserSocialNetworkBeforeAuthorization
{
    /** @var Security */
    private $security;

    /**
     * @required
     *
     * @param Security $security
     *
     * @return self
     */
    public function setSecurity(Security $security): self
    {
        $this->security = $security;

        return $this;
    }

    /**
     * @param string $socialNetworkUserEmail
     *
     * @return bool
     */
    protected function checkingUserSocialNetworkBeforeAuthorization(string $socialNetworkUserEmail): bool
    {
        /** @var User $activeUser */
        if ($activeUser = $this->security->getUser()) {
            $activeUserEmail = $activeUser->getEmail();

            if ($activeUserEmail !== $socialNetworkUserEmail) {
                return true;
            }
        }

        return false;
    }
}
