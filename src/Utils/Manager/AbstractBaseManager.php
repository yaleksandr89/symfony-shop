<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

abstract class AbstractBaseManager
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    abstract public function getRepository(): EntityRepository;

    public function find(int $id): ?object
    {
        return $this->getRepository()->find($id);
    }

    public function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function remove(object $entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }
}
