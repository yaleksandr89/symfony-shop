<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private ProductImageRepository $productImageRepository,
    ) {
        parent::__construct($registry, Category::class);
    }

    /** @return list<array{slug: string}> */
    public function findSitemapRows(): array
    {
        /** @var list<array{slug: string}> $rows */
        $rows = $this->createQueryBuilder('category')
            ->select('DISTINCT category.slug AS slug')
            ->innerJoin('category.products', 'product')
            ->andWhere('category.isDeleted = false')
            ->andWhere('category.slug IS NOT NULL')
            ->andWhere("TRIM(category.slug) != ''")
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->orderBy('category.slug', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }

    public function forFormQueryBuilderFindActiveCategory(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isDeleted = FALSE');
    }

    /** @return list<array{id: int, title: string|null, slug: string}> */
    public function findActiveNavigationRows(): array
    {
        /** @var list<array{id: int, title: string|null, slug: string}> $rows */
        $rows = $this->createQueryBuilder('category')
            ->select('DISTINCT category.id AS id, category.title AS title, category.slug AS slug')
            ->innerJoin('category.products', 'product')
            ->andWhere('category.isDeleted = false')
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->orderBy('category.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }

    /**
     * @return list<array{categoryId: int, categoryTitle: string, categorySlug: string, productId: int, cover: array{filenameBig: string, filenameMiddle: string, filenameSmall: string}}>
     */
    public function findHomepageBannerCandidates(): array
    {
        $rows = $this->createQueryBuilder('category')
            ->select('category.id AS categoryId')
            ->addSelect('category.title AS categoryTitle')
            ->addSelect('category.slug AS categorySlug')
            ->addSelect('product.id AS productId')
            ->innerJoin('category.products', 'product')
            ->andWhere('category.isDeleted = false')
            ->andWhere('product.isDeleted = false')
            ->andWhere('product.isPublished = true')
            ->orderBy('category.id', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $covers = $this->productImageRepository->findFirstCoversByProductIds(array_values(array_map(
            static fn(array $row): int => (int) $row['productId'],
            $rows,
        )));

        $candidates = [];
        foreach ($rows as $row) {
            $productId = (int) $row['productId'];
            if (!isset($covers[$productId])) {
                continue;
            }
            $candidates[] = [
                'categoryId' => (int) $row['categoryId'],
                'categoryTitle' => (string) $row['categoryTitle'],
                'categorySlug' => (string) $row['categorySlug'],
                'productId' => $productId,
                'cover' => $covers[$productId],
            ];
        }

        return $candidates;
    }
}
