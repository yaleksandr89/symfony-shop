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
     * @param int $productCount
     * @param int|null $categoryId
     * @return array
     */
    public function findActiveProduct(int $productCount, ?int $categoryId): array
    {
        $query = $this
            ->createQueryBuilder('p')
            ->andWhere('p.isDeleted = false')
            ->andWhere('p.isPublished = true');

        if ($categoryId) {
            $query = $query
                ->andWhere('p.category = :idCategory')
                ->setParameter('idCategory', $categoryId);
        }

        return $query
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($productCount)
            ->getQuery()
            ->getResult();
    }

    /*
    public function findOneBySomeField($value): ?Product
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
