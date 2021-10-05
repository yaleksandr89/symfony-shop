<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param int|null $productCount
     * @param int|null $categoryId
     * @return array
     */
    public function findActiveProduct(?int $categoryId, int $productCount = null): array
    {
        $query = $this
            ->createQueryBuilder('p')
            ->andWhere('p.isDeleted = false')
            ->andWhere('p.isPublished = true');

        if ($categoryId) {
            $query
                ->andWhere('p.category = :idCategory')
                ->setParameter('idCategory', $categoryId);
        }

        if ($productCount) {
            $query->setMaxResults($productCount);
        }

        return $query
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
