<?php

declare(strict_types=1);

namespace App\Account\Manager;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function remove(User $user): void
    {
        $this->em->persist($user);
        $user->setIsDeleted(true);
        $this->em->flush();
    }
}
