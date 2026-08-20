<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class OAuthCallbackModeResolver
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OAuthLinkIntentStore $intentStore,
    ) {
    }

    public function useOrdinaryAuthenticator(): bool
    {
        return !($this->tokenStorage->getToken()?->getUser() instanceof User)
            && !$this->intentStore->hasPending();
    }
}
