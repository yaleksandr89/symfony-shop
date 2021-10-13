<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\User;
use Doctrine\Persistence\ObjectRepository;

final class UserManager extends AbstractBaseManager
{
    /**
     * @return ObjectRepository
     */
    public function getRepository(): ObjectRepository
    {
        return $this->em->getRepository(User::class);
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