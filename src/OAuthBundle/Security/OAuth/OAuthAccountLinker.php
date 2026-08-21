<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use App\Account\Repository\UserRepository;
use App\Entity\User;
use App\OAuthBundle\Security\OAuth\Exception\OAuthIdentityConflictException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class OAuthAccountLinker
{
    public function __construct(
        private readonly OAuthIdentityAccessor $identityAccessor,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function link(User $user, OAuthProvider $provider, mixed $externalId): void
    {
        $externalId = is_scalar($externalId) || $externalId instanceof \Stringable ? trim((string) $externalId) : '';
        if ('' === $externalId || null !== $this->identityAccessor->getExternalId($user, $provider)) {
            throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
        }

        $owner = $this->userRepository->findOneBy([
            $this->identityAccessor->identityField($provider) => $externalId,
        ]);
        if ($owner instanceof User) {
            throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
        }

        $this->identityAccessor->link($user, $provider, $externalId);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->identityAccessor->unlink($user, $provider);

            throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
        } catch (\Throwable $exception) {
            $this->identityAccessor->unlink($user, $provider);

            throw $exception;
        }
    }
}
