<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

abstract class AbstractBaseManager
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em,)
    {
        $this->em = $em;
    }

    /**
     * @return ObjectRepository
     */
    abstract public function getRepository(): ObjectRepository;

    /**
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object
    {
        return $this->getRepository()->find($id);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->em->flush();
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }
}