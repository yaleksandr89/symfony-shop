<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use App\Entity\User;
use App\OAuthBundle\Security\OAuth\Exception\OAuthIdentityConflictException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use RuntimeException;
use Stringable;
use Throwable;

final readonly class OAuthAccountLinker
{
    public function __construct(
        private readonly OAuthIdentityAccessor $identityAccessor,
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    public function link(User $user, OAuthProvider $provider, mixed $externalId): User
    {
        $externalId = is_scalar($externalId) || $externalId instanceof Stringable ? trim((string) $externalId) : '';
        if ('' === $externalId) {
            throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
        }

        return $this->mutate($user, $provider, $externalId, false);
    }

    public function unlink(User $user, OAuthProvider $provider, string $expectedId): User
    {
        return $this->mutate($user, $provider, $expectedId, true);
    }

    public function recoverUser(User $user): User
    {
        $entityManager = $this->entityManager();
        if (!$entityManager->isOpen()) {
            $this->doctrine->resetManager();
            $entityManager = $this->entityManager();
        }
        if ($entityManager->contains($user)) {
            return $user;
        }

        $persistedUser = $entityManager->find(User::class, $user->getId());
        if (!$persistedUser instanceof User || $persistedUser->getId() !== $user->getId()) {
            throw new RuntimeException('OAuth account is unavailable.');
        }

        return $persistedUser;
    }

    private function mutate(User $user, OAuthProvider $provider, string $externalId, bool $unlink): User
    {
        $userId = $user->getId();
        if (null === $userId) {
            throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
        }
        $entityManager = $this->entityManager();
        // EN: Preserve the authenticated snapshot; refresh and mutation use a separately loaded managed user.
        // RU: Сохраняем снимок аутентификации; refresh и изменение используют отдельно загруженного managed user.
        $entityManager->detach($user);
        $managedUser = null;
        $previousId = null;
        $mutationStarted = false;
        try {
            $accepted = $entityManager->wrapInTransaction(function (EntityManagerInterface $manager) use ($user, $userId, $provider, $externalId, $unlink, &$managedUser, &$previousId, &$mutationStarted): bool {
                $managedUser = $manager->find(User::class, $userId, LockMode::PESSIMISTIC_WRITE);
                if (!$managedUser instanceof User) {
                    return false;
                }
                $manager->refresh($managedUser, LockMode::PESSIMISTIC_WRITE);
                if (!$user->isEqualTo($managedUser)) {
                    return false;
                }
                $previousId = $this->identityAccessor->getExternalId($managedUser, $provider);
                if ($unlink) {
                    if (null !== $previousId && $previousId !== $externalId) {
                        return false;
                    }
                    $mutationStarted = true;
                    $this->identityAccessor->unlink($managedUser, $provider);

                    return true;
                }
                if (null !== $previousId || null !== $manager->getRepository(User::class)->findOneBy([
                    $this->identityAccessor->identityField($provider) => $externalId,
                ])) {
                    return false;
                }
                $mutationStarted = true;
                $this->identityAccessor->link($managedUser, $provider, $externalId);

                return true;
            });
        } catch (Throwable $exception) {
            // EN: Restore the rollback snapshot only; HTTP recovery reloads a managed user before session persistence.
            // RU: Восстанавливаем только снимок до отката; HTTP recovery загрузит managed user перед сохранением сессии.
            if ($mutationStarted && $managedUser instanceof User) {
                if (null === $previousId) {
                    $this->identityAccessor->unlink($managedUser, $provider);
                } else {
                    $this->identityAccessor->link($managedUser, $provider, $previousId);
                }
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
            }

            throw $exception;
        }
        // EN: Expected conflicts leave the transaction normally, so Doctrine keeps the manager usable.
        // RU: Ожидаемые конфликты завершают транзакцию штатно, поэтому Doctrine сохраняет работоспособность manager.
        if (!$accepted || !$managedUser instanceof User) {
            throw new OAuthIdentityConflictException('OAuth identity cannot be linked.');
        }

        return $managedUser;
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass(User::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException('OAuth accounts require an ORM manager.');
        }

        return $manager;
    }
}
