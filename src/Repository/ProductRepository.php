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
    public function __construct(
        ManagerRegistry $registry,
        private ProductImageRepository $productImageRepository,
    ) {
        parent::__construct($registry, Product::class);
    }

    /** @return list<array{slug: string}> */
    public function findSitemapRows(): array
    {
        return $this->createQueryBuilder('product')
            ->select('product.slug AS slug')
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->andWhere('product.slug IS NOT NULL')
            ->andWhere("TRIM(product.slug) != ''")
            ->andWhere('product.category IS NOT NULL')
            ->orderBy('product.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return list<array{id: int, uuid: mixed, title: string, slug: string|null, price: string, quantity: int, isNew: bool, isOnSale: bool, categoryId: int|null, cover: array{filenameBig: string, filenameMiddle: string, filenameSmall: string}|null}>
     */
    public function findCardRowsByCategoryAndCount(?int $categoryId, ?int $productCount = null): array
    {
        $queryBuilder = $this->createQueryBuilder('product')
            ->select('product.id AS id')
            ->addSelect('product.uuid AS uuid')
            ->addSelect('product.title AS title')
            ->addSelect('product.slug AS slug')
            ->addSelect('product.price AS price')
            ->addSelect('product.quantity AS quantity')
            ->addSelect('product.isNew AS isNew')
            ->addSelect('product.isOnSale AS isOnSale')
            ->addSelect('IDENTITY(product.category) AS categoryId')
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->orderBy('product.id', 'DESC');

        if (null !== $categoryId) {
            $queryBuilder
                ->andWhere('product.category = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }
        if (null !== $productCount) {
            $queryBuilder->setMaxResults($productCount);
        }

        $rows = $queryBuilder->getQuery()->getArrayResult();
        $covers = $this->productImageRepository->findFirstCoversByProductIds(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows,
        ));

        foreach ($rows as &$row) {
            $productId = (int) $row['id'];
            $row['id'] = $productId;
            $row['quantity'] = (int) $row['quantity'];
            $row['isNew'] = (bool) $row['isNew'];
            $row['isOnSale'] = (bool) $row['isOnSale'];
            $row['categoryId'] = null === $row['categoryId'] ? null : (int) $row['categoryId'];
            $row['cover'] = $covers[$productId] ?? null;
        }
        unset($row);

        return $rows;
    }
}
