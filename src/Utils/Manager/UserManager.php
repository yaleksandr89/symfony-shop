<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\User;
use App\Exception\Security\EmptyUserPlainPasswordException;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManager extends AbstractBaseManager
{
    // >>> Autowiring
    /**
     * @var UserPasswordHasherInterface
     */
    private $userPasswordHasher;

    /**
     * @required
     *
     * @param UserPasswordHasherInterface $userPasswordHasher
     *
     * @return UserManager
     */
    public function setUserPasswordHasher(UserPasswordHasherInterface $userPasswordHasher): UserManager
    {
        $this->userPasswordHasher = $userPasswordHasher;

        return $this;
    }
    // Autowiring <<<

    /**
     * @return ObjectRepository
     */
    public function getRepository(): ObjectRepository
    {
        return $this->em->getRepository(User::class);
    }

    /**
     * @param User   $user
     * @param string $plainPassword
     */
    public function encodePassword(User $user, string $plainPassword): void
    {
        $preparedPassword = trim($plainPassword);

        if (!$preparedPassword) {
            throw new EmptyUserPlainPasswordException('Empty user\'s password');
        }

        $hashPassword = $this->userPasswordHasher->hashPassword($user, $preparedPassword);
        $user->setPassword($hashPassword);
    }

    /**
     * @param object $entity
     */
    public function remove(object $entity): void
    {
        /** @var User $user */
        $user = $entity;

        $this->em->persist($user);
        $user->setIsDeleted(true);
        $this->em->flush();
    }
}
