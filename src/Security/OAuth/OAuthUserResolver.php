<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OAuth\Exception\OAuthLoginDeniedException;
use App\Security\UserChecker\DeletedUserChecker;

final class OAuthUserResolver
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DeletedUserChecker $deletedUserChecker,
        private readonly OAuthIdentityAccessor $identityAccessor,
    ) {
    }

    /**
     * @param callable(): User $newUserFactory
     */
    public function resolve(OAuthProvider $provider, mixed $externalId, ?string $email, callable $newUserFactory): OAuthUserResolution
    {
        if (!$provider->isImplemented()) {
            throw new OAuthLoginDeniedException();
        }

        $externalId = is_scalar($externalId) || $externalId instanceof \Stringable ? trim((string) $externalId) : '';
        if ('' === $externalId) {
            throw new OAuthLoginDeniedException();
        }

        $user = $this->userRepository->findOneBy([
            $this->identityAccessor->identityField($provider) => $externalId,
        ]);

        if ($user instanceof User) {
            $this->deletedUserChecker->checkPreAuth($user);

            return new OAuthUserResolution($user, false);
        }

        $email = trim($email ?? '');
        if ('' === $email) {
            throw new OAuthLoginDeniedException();
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user instanceof User) {
            throw new OAuthLoginDeniedException();
        }

        $user = $newUserFactory();
        $user->setEmail($email);
        $this->identityAccessor->link($user, $provider, $externalId);
        $user->setIsVerified(false);

        return new OAuthUserResolution($user, true);
    }
}
