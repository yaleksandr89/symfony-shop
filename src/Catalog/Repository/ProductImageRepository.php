<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductImage[]    findAll()
 * @method ProductImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, array{filenameBig: string, filenameMiddle: string, filenameSmall: string}>
     */
    public function findFirstCoversByProductIds(array $productIds): array
    {
        if ([] === $productIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('image')
            ->select('IDENTITY(image.product) AS productId')
            ->addSelect('image.filenameBig AS filenameBig')
            ->addSelect('image.filenameMiddle AS filenameMiddle')
            ->addSelect('image.filenameSmall AS filenameSmall')
            ->andWhere('image.product IN (:productIds)')
            ->setParameter('productIds', array_values(array_unique($productIds)))
            ->orderBy('image.product', 'ASC')
            ->addOrderBy('image.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $covers = [];
        foreach ($rows as $row) {
            $productId = (int) $row['productId'];
            $covers[$productId] ??= [
                'filenameBig' => (string) $row['filenameBig'],
                'filenameMiddle' => (string) $row['filenameMiddle'],
                'filenameSmall' => (string) $row['filenameSmall'],
            ];
        }

        return $covers;
    }
}
