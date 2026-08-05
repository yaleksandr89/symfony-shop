<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;

final class OAuthLoginHandler
{
    public function __construct(
        private readonly OAuthUserResolver $userResolver,
        private readonly OAuthNewUserRegistrar $newUserRegistrar,
    ) {
    }

    /**
     * @param callable(): User $newUserFactory
     */
    public function handle(OAuthProvider $provider, mixed $externalId, ?string $email, callable $newUserFactory): User
    {
        $resolution = $this->userResolver->resolve($provider, $externalId, $email, $newUserFactory);

        if ($resolution->isNewUser()) {
            $this->newUserRegistrar->register($resolution->user(), $provider);
        }

        return $resolution->user();
    }
}
