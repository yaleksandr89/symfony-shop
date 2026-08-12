<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OrderProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderProduct[]    findAll()
 * @method OrderProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderProduct::class);
    }

    /**
     * @param list<int> $orderIds
     *
     * @return array<int, int>
     */
    public function countByOrderIds(array $orderIds): array
    {
        if ([] === $orderIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('orderProduct')
            ->select('IDENTITY(orderProduct.appOrder) AS orderId')
            ->addSelect('COUNT(orderProduct.id) AS productCount')
            ->andWhere('orderProduct.appOrder IN (:orderIds)')
            ->setParameter('orderIds', array_values(array_unique($orderIds)))
            ->groupBy('orderProduct.appOrder')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['orderId']] = (int) $row['productCount'];
        }

        return $counts;
    }
}
