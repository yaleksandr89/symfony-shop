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
        dd(__METHOD__, $entity);
//        /** @var User $user */
//        $user = $entity;
//
//        /** @var Product[] $linkedProducts */
//        $linkedProducts = $category->getProducts()->getValues();
//
//        $this->em->persist($category);
//
//        $category->setIsDeleted(true);
//        foreach ($linkedProducts as $linkedProduct) {
//            $linkedProduct->setIsDeleted(true);
//        }
//
//        $this->em->flush();
    }
}