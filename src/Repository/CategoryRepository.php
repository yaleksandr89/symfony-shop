<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Category|null find($id, $lockMode = null, $lockVersion = null)
 * @method Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method Category[]    findAll()
 * @method Category[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return array|null
     */
    public function findActiveCategory(): ?array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isDeleted = FALSE')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array|null
     */
    public function findActiveCategoryWithJoinProduct(): ?array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isDeleted = FALSE')
            ->join('c.products', 'p')
            ->andWhere('p.isDeleted = FALSE')
            ->andWhere('p.isPublished = TRUE')
            ->getQuery()
            ->getResult();
    }
}
